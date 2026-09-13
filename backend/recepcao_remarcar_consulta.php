<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

require_once 'config.php';

$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado.'];
$pdo = null;
$paciente_cpf_notificacao = null;

try {
    if (!isset($_SESSION['recepcionista_id'])) {
        throw new Exception('Recepcionista não autenticado.');
    }
    
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);

    $id_consulta = $input['id_consulta'] ?? null;
    $novo_dia_yyyymmdd = $input['novo_dia'] ?? null;
    $nova_hora_hhmm = $input['nova_hora'] ?? null;
    $motivo = trim($input['motivo_remarcacao'] ?? '');

    if (empty($id_consulta) || empty($novo_dia_yyyymmdd) || empty($nova_hora_hhmm) || empty($motivo)) {
        throw new Exception('ID, novo dia, nova hora e motivo são obrigatórios.');
    }
    
    $dataHoraAgendamento = new DateTime($novo_dia_yyyymmdd . ' ' . $nova_hora_hhmm, new DateTimeZone('America/Sao_Paulo'));
    $agora = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));

    if ($dataHoraAgendamento < $agora) {
        throw new Exception('Não é possível remarcar consultas para datas e horários no passado.');
    }

    $diaDaSemana = (int)$dataHoraAgendamento->format('N');
    if ($diaDaSemana >= 6) {
        throw new Exception('Não é possível agendar consultas aos Sábados e Domingos.');
    }

    $horaAgendamento = (int)$dataHoraAgendamento->format('H');
    if ($horaAgendamento < 8 || $horaAgendamento >= 16) {
        throw new Exception('O horário de funcionamento da clínica é das 08:00 às 16:00.');
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


    $sql_update = "UPDATE consultas SET data = :novo_dia, hora = :nova_hora, motivo_remarcacao = :motivo WHERE id = :id";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([
        ':novo_dia' => $novo_dia_yyyymmdd,
        ':nova_hora' => $nova_hora_hhmm . ':00',
        ':motivo' => $motivo,
        ':id' => $id_consulta
    ]);

    if ($stmt_update->rowCount() > 0) {
        $pdo->commit();
        $response = ['success' => true, 'message' => 'Consulta remarcada com sucesso!'];
    } else {
        $pdo->rollBack();
        throw new Exception('Não foi possível remarcar a consulta (talvez a data e hora sejam as mesmas).');
    }

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    $response['message'] = $e->getMessage();
    error_log("Erro em recepcao_remarcar_consulta.php: " . $e->getMessage());
    echo json_encode($response);
    exit;
}

// --- INÍCIO DA CORREÇÃO ---
// ETAPA 2: ENVIO E REGISTRO DE NOTIFICAÇÃO
if ($response['success']) {
    try {
        require_once 'whatsapp_service.php';

        // Busca os dados do paciente + consulta para montar mensagem
        $stmt_dados = $pdo->prepare("
            SELECT 
                p.nome,
                p.telefone,
                p.whatsapp_autorizado,
                c.data,
                c.hora,
                m.nome AS nome_medico,
                e.nome AS nome_especialidade
            FROM consultas c
            JOIN paciente p ON c.paciente_cpf = p.cpf
            JOIN medicos m ON c.medico_id = m.id
            JOIN especialidades e ON m.especialidade_id = e.id
            WHERE c.id = :id
        ");
        $stmt_dados->execute([':id' => $id_consulta]);
        $dados_msg = $stmt_dados->fetch();

        // Se conseguiu buscar os dados da consulta, sempre grava a notificação no BD
        if ($dados_msg) {
            // Conteúdo da notificação exibida na tela "Notificações do Paciente"
            // Mantemos o padrão usado em paciente_carregar_notificacoes.php:
            // "Consulta remarcada devido: <motivo>"
            $conteudoNotificacao = "Consulta remarcada devido: " . $motivo;

            $stmt_hist = $pdo->prepare("
                INSERT INTO mensagens_enviadas 
                    (paciente_cpf, consulta_id, tipo, conteudo, data_envio)
                VALUES 
                    (:cpf, :cid, 'remarcacao', :cont, GETDATE())
            ");
            $stmt_hist->execute([
                ':cpf'  => $paciente_cpf_notificacao,  // pego na ETAPA 1
                ':cid'  => $id_consulta,
                ':cont' => $conteudoNotificacao
            ]);

            // Agora tentamos enviar WhatsApp SOMENTE se o paciente autorizou
            if (!empty($dados_msg['telefone']) && !empty($dados_msg['whatsapp_autorizado'])) {
                $data_nova_br = (new DateTime($novo_dia_yyyymmdd))->format('d/m/Y');

                $mensagemWhats = "Olá {$dados_msg['nome']}.\n\n" .
                                 "Sua consulta foi REMARCADA pela clínica para o dia: {$data_nova_br} às {$nova_hora_hhmm}.\n" .
                                 "Motivo: {$motivo}\n\n" .
                                 "Para mais informações: " . CLINICA_TELEFONE_CONTATO;

                $telefoneParaAPI = '55' . preg_replace('/\D/', '', $dados_msg['telefone']);

                // Mesmo que falhe, a notificação do site já está garantida
                $okEnvio = enviarWhatsApp($telefoneParaAPI, $mensagemWhats);
                if (!$okEnvio) {
                    error_log("Falha ao enviar WhatsApp na remarcação (recepção) para CPF {$paciente_cpf_notificacao}");
                }
            }
        }
    } catch (Throwable $t) {
        error_log("AVISO: Erro na etapa de notificação de remarcação (recepção): " . $t->getMessage());
    }
}
// --- FIM DA CORREÇÃO ---
