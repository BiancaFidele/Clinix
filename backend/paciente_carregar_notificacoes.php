<?php
session_start();
header('Content-Type: application/json');

// Inclui o arquivo de configuração central
require_once 'config.php';

if (!isset($_SESSION['paciente_cpf'])) {
    echo json_encode(['success' => false, 'message' => 'Paciente não autenticado.', 'notificacoes' => []]);
    exit;
}
$paciente_cpf_sessao = $_SESSION['paciente_cpf'];

$response = ['success' => false, 'message' => 'Nenhuma notificação encontrada.', 'notificacoes' => []];
$pdo = null;

try {
    // Utiliza as constantes do arquivo config.php para a conexão
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);

    $sql = "SELECT 
                msg.id AS id_mensagem,
                msg.tipo AS tipo_notificacao,
                msg.conteudo AS conteudo_notificacao,
                msg.data_envio,
                c.data AS data_consulta,
                c.hora AS hora_consulta,
                m.nome AS nome_medico,
                e.nome AS nome_especialidade
            FROM mensagens_enviadas msg
            LEFT JOIN consultas c ON msg.consulta_id = c.id
            LEFT JOIN medicos m ON c.medico_id = m.id
            LEFT JOIN especialidades e ON m.especialidade_id = e.id
            WHERE msg.paciente_cpf = :paciente_cpf
            ORDER BY msg.data_envio DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':paciente_cpf', $paciente_cpf_sessao, PDO::PARAM_STR);
    $stmt->execute();
    $resultados = $stmt->fetchAll();

    $notificacoesFormatadas = [];
    if ($resultados) {
        foreach ($resultados as $row) {
            $dataFormatadaConsulta = 'N/A';
            if ($row['data_consulta']) {
                try {
                    $dataObj = new DateTime($row['data_consulta']);
                    $dataFormatadaConsulta = $dataObj->format('d/m/Y');
                } catch (Exception $e) {}
            }

            $horaFormatadaConsulta = 'N/A';
            if ($row['hora_consulta']) {
                try {
                    $horaObj = new DateTime($row['hora_consulta']);
                    $horaFormatadaConsulta = $horaObj->format('H:i');
                } catch (Exception $e) {}
            }
            
            $texto_razao = '';
            if ($row['tipo_notificacao'] === 'cancelamento') {
                $texto_razao = "Consulta cancelada devido: ";
            } else if ($row['tipo_notificacao'] === 'remarcacao') {
                $texto_razao = "Consulta remarcada devido: ";
            } else if ($row['tipo_notificacao'] === 'agendamento') {
                $texto_razao = "Nova consulta agendada: ";
            } else if ($row['tipo_notificacao'] === 'lembrete') {
                $texto_razao = "Lembrete de consulta: ";
            }

            $motivoEspecifico = $row['conteudo_notificacao'];
            if (strpos($row['conteudo_notificacao'], "Motivo: ") !== false) {
                $motivoEspecifico = substr($row['conteudo_notificacao'], strpos($row['conteudo_notificacao'], "Motivo: ") + strlen("Motivo: "));
            } else if (strpos(strtolower($row['conteudo_notificacao']), "cancelada pela") !== false || strpos(strtolower($row['conteudo_notificacao']), "remarcada pela") !== false) {
                if (strpos($row['conteudo_notificacao'], ". ") !== false) {
                     $motivoEspecifico = substr($row['conteudo_notificacao'], strpos($row['conteudo_notificacao'], ". ") + 2);
                } else {
                    if($row['tipo_notificacao'] === 'cancelamento') $motivoEspecifico = "Solicitação da clínica.";
                    if($row['tipo_notificacao'] === 'remarcacao') $motivoEspecifico = "Ajuste de agenda da clínica.";
                }
            } else {
                $motivoEspecifico = $row['conteudo_notificacao'];
                if (strpos($motivoEspecifico, $texto_razao) === 0) {
                    $motivoEspecifico = trim(substr($motivoEspecifico, strlen($texto_razao)));
                }
            }


            $notificacoesFormatadas[] = [
                'id_mensagem' => $row['id_mensagem'],
                'tipo' => $row['tipo_notificacao'],
                'texto_principal_razao' => $texto_razao,
                'motivo_especifico' => $motivoEspecifico,
                'data_envio' => $row['data_envio'] ? (new DateTime($row['data_envio']))->format('d/m/Y H:i') : 'N/A',
                'especialidade_consulta' => $row['nome_especialidade'] ?? 'Não aplicável',
                'medico_consulta' => $row['nome_medico'] ?? 'Não aplicável',
                'data_hora_consulta' => ($row['data_consulta'] && $row['hora_consulta']) ? $dataFormatadaConsulta . ' - ' . $horaFormatadaConsulta : 'Não aplicável',
            ];
        }
        $response = ['success' => true, 'message' => 'Notificações carregadas.', 'notificacoes' => $notificacoesFormatadas];
    }

} catch (PDOException $e) {
    $response['message'] = 'Erro de banco de dados: ' . $e->getMessage();
    error_log("Erro paciente_carregar_notificacoes (PDO): " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Erro geral: ' . $e->getMessage();
    error_log("Erro paciente_carregar_notificacoes (Geral): " . $e->getMessage());
}

echo json_encode($response);
?>
