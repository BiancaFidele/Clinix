<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

require_once 'config.php';

$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado.'];
$pdo = null;

try {
    if (!isset($_SESSION['paciente_cpf'])) {
        throw new Exception('Paciente não autenticado.');
    }
    $paciente_cpf_sessao = $_SESSION['paciente_cpf'];

    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);

    $id_consulta = $input['id_consulta'] ?? null;
    $novo_dia_yyyymmdd = $input['novo_dia'] ?? null;
    $nova_hora_hhmm = $input['nova_hora'] ?? null;

    if (empty($id_consulta) || empty($novo_dia_yyyymmdd) || empty($nova_hora_hhmm)) {
        throw new Exception('Dados insuficientes para remarcar a consulta.');
    }
    
    // Validação de Dia e Horário de Funcionamento
    $dataHoraAgendamento = new DateTime($novo_dia_yyyymmdd . ' ' . $nova_hora_hhmm, new DateTimeZone('America/Sao_Paulo'));
    $agora = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));

    if ($dataHoraAgendamento < $agora) {
        throw new Exception('Não é possível remarcar consultas para datas e horários no passado.');
    }

    $diaDaSemana = (int)$dataHoraAgendamento->format('N');
    if ($diaDaSemana >= 6) { // 6 = Sábado, 7 = Domingo
        throw new Exception('Não é possível agendar consultas aos Sábados e Domingos.');
    }

    $horaAgendamento = (int)$dataHoraAgendamento->format('H');
    if ($horaAgendamento < 8 || $horaAgendamento >= 16) { // Horário de 8h às 16h
        throw new Exception('O horário de funcionamento da clínica é das 08:00 às 16:00.');
    }

    $novo_dia_obj = new DateTime($novo_dia_yyyymmdd);
    $novo_dia_formatado_br = $novo_dia_obj->format('d/m/Y');
    $nova_hora_para_banco = $nova_hora_hhmm . ':00';

    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);
    $pdo->beginTransaction();

    $stmtFetch = $pdo->prepare("SELECT data, hora, medico_id FROM consultas WHERE id = :id AND paciente_cpf = :cpf AND status = 'ativa'");
    $stmtFetch->execute([':id' => $id_consulta, ':cpf' => $paciente_cpf_sessao]);
    $consulta_atual = $stmtFetch->fetch();

    if (!$consulta_atual) {
        throw new Exception('Consulta não encontrada ou você não tem permissão para alterá-la.');
    }

    $motivo_db = "Remarcada pelo paciente para " . $novo_dia_formatado_br . " às " . $nova_hora_hhmm;
    $sql_update = "UPDATE consultas SET data = :novo_dia, hora = :nova_hora, motivo_remarcacao = :motivo WHERE id = :id";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([
        ':novo_dia' => $novo_dia_yyyymmdd,
        ':nova_hora' => $nova_hora_para_banco,
        ':motivo' => $motivo_db,
        ':id' => $id_consulta
    ]);

    if ($stmt_update->rowCount() > 0) {
        // Futuramente, adicionar lógica de histórico aqui se necessário
        $pdo->commit();
        $response = ['success' => true, 'message' => 'Consulta remarcada com sucesso!'];
    } else {
        $pdo->rollBack();
        throw new Exception('Nenhuma alteração foi realizada. A data e hora são as mesmas.');
    }

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    $response['message'] = $e->getMessage();
    error_log("Erro em remarcar_consulta.php: " . $e->getMessage());
    echo json_encode($response);
    exit;
}

// --- INÍCIO DA CORREÇÃO: Bloco de notificação adicionado ---
if ($response['success']) {
    try {
        require_once 'whatsapp_service.php';

        $stmt_dados = $pdo->prepare("
            SELECT p.nome, p.telefone, p.whatsapp_autorizado, m.nome AS nome_medico, e.nome AS nome_especialidade
            FROM consultas c
            JOIN paciente p ON c.paciente_cpf = p.cpf
            JOIN medicos m ON c.medico_id = m.id
            JOIN especialidades e ON m.especialidade_id = e.id
            WHERE c.id = :id_consulta
        ");
        $stmt_dados->execute([':id_consulta' => $id_consulta]);
        $dados_msg = $stmt_dados->fetch();

        if ($dados_msg && !empty($dados_msg['telefone']) && $dados_msg['whatsapp_autorizado']) {
            $primeiroNome = explode(' ', $dados_msg['nome'])[0];
            
            $mensagem = "Olá {$primeiroNome}.\n\n" .
                        "Sua consulta foi REMARCADA com sucesso para o dia {$novo_dia_formatado_br} às {$nova_hora_hhmm}.\n\n" .
                        "Médico: {$dados_msg['nome_medico']}\n" .
                        "Especialidade: {$dados_msg['nome_especialidade']}\n\n" .
                        "Para mais informações: " . CLINICA_TELEFONE_CONTATO;

            $telefoneParaAPI = '55' . preg_replace('/\D/', '', $dados_msg['telefone']);
            enviarWhatsApp($telefoneParaAPI, $mensagem);
        }
    } catch (Throwable $t) {
        error_log("AVISO: Erro na etapa de notificação (remarcar_consulta.php): " . $t->getMessage());
    }
}
// --- FIM DA CORREÇÃO ---

echo json_encode($response);
exit;
?>
