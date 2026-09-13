<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

require_once 'config.php';

$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado.'];
$pdo = null;
$paciente_cpf_notificacao = null; // Variável para guardar o CPF para a notificação

try {
    if (!isset($_SESSION['recepcionista_id'])) {
        throw new Exception('Recepcionista não autenticado.');
    }

    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);
    $id_consulta = $input['id_consulta'] ?? null;
    $motivo = trim($input['motivo_cancelamento'] ?? 'Motivo não informado pela recepção.');

    if (empty($id_consulta)) {
        throw new Exception('ID da consulta é obrigatório.');
    }

    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);
    $pdo->beginTransaction();

    // --- CORREÇÃO: Pega o CPF do paciente para usar depois na notificação ---
    $stmtFetch = $pdo->prepare("SELECT paciente_cpf FROM consultas WHERE id = :id AND status = 'ativa'");
    $stmtFetch->execute([':id' => $id_consulta]);
    $consulta_info = $stmtFetch->fetch();

    if (!$consulta_info) {
        throw new Exception('Consulta não encontrada ou não está ativa.');
    }
    $paciente_cpf_notificacao = $consulta_info['paciente_cpf'];
    // --- FIM DA CORREÇÃO ---

    $sql_update = "UPDATE consultas SET status = 'cancelada', motivo_cancelamento = :motivo WHERE id = :id";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([':motivo' => $motivo, ':id' => $id_consulta]);

    if ($stmt_update->rowCount() > 0) {
        $pdo->commit();
        $response = ['success' => true, 'message' => 'Consulta cancelada com sucesso!'];
    } else {
        $pdo->rollBack();
        throw new Exception('Não foi possível cancelar a consulta.');
    }

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    $response['message'] = "Erro Crítico: " . $e->getMessage();
    error_log("Erro em recepcao_cancelar_consulta.php: " . $e->getMessage());
    echo json_encode($response);
    exit;
}

// --- INÍCIO DA CORREÇÃO ---
// ETAPA 2: ENVIO E REGISTRO DE NOTIFICAÇÃO
if ($response['success']) {
    try {
        require_once 'whatsapp_service.php';
        
        $stmt_dados = $pdo->prepare("SELECT p.nome, p.telefone, p.whatsapp_autorizado, c.data, c.hora, m.nome AS nome_medico, e.nome AS nome_especialidade FROM consultas c JOIN paciente p ON c.paciente_cpf = p.cpf JOIN medicos m ON c.medico_id = m.id JOIN especialidades e ON m.especialidade_id = e.id WHERE c.id = :id");
        $stmt_dados->execute([':id' => $id_consulta]);
        $dados_msg = $stmt_dados->fetch();

        if ($dados_msg && !empty($dados_msg['telefone']) && $dados_msg['whatsapp_autorizado']) {
            $dia_br = (new DateTime($dados_msg['data']))->format('d/m/Y');
            $hora_br = (new DateTime($dados_msg['hora']))->format('H:i');

            $mensagem = "Olá {$dados_msg['nome']}.\n\n" .
                        "Sua consulta do dia: {$dia_br} às {$hora_br}\n" .
                        "Médico: {$dados_msg['nome_medico']}\n" .
                        "Especialidade: {$dados_msg['nome_especialidade']}\n\n" .
                        "Foi DESMARCADA devido: {$motivo}\n\n" .
                        "Para mais informações: " . CLINICA_TELEFONE_CONTATO;
            
            $telefoneParaAPI = '55' . preg_replace('/\D/', '', $dados_msg['telefone']);
            if (enviarWhatsApp($telefoneParaAPI, $mensagem)) {
                // Se o envio for bem-sucedido, insere no histórico de mensagens
                $stmt_hist = $pdo->prepare("INSERT INTO mensagens_enviadas (paciente_cpf, consulta_id, tipo, conteudo, data_envio) VALUES (:cpf, :cid, 'cancelamento', :cont, GETDATE())");
                $stmt_hist->execute([
                    ':cpf' => $paciente_cpf_notificacao,
                    ':cid' => $id_consulta,
                    ':cont' => "Consulta cancelada devido: " . $motivo
                ]);
            }
        }
    } catch (Throwable $t) {
        error_log("AVISO: Erro na etapa de notificação de cancelamento (recepção): " . $t->getMessage());
    }
}
// --- FIM DA CORREÇÃO ---

echo json_encode($response);
exit;
?>
