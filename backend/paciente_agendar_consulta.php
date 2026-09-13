<?php
// Habilita o log de erros, mas não exibe erros para o usuário.
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

require_once 'config.php';

$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado no início do script.'];
$pdo = null;
$id_nova_consulta = null; // Variável para guardar o ID da nova consulta

try {
    if (!isset($_SESSION['paciente_cpf'])) {
        throw new Exception('Paciente não autenticado. Faça login para continuar.');
    }
    $paciente_cpf = $_SESSION['paciente_cpf'];

    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);
    if ($input === null) {
        throw new Exception('Dados da requisição em formato JSON inválido.');
    }

    $medico_id = $input['medico_id'] ?? null;
    $dia_formatado_br = $input['dia'] ?? null;
    $hora = $input['hora'] ?? null;
    $observacoes = $input['observacoes'] ?? '';

    if (empty($medico_id) || empty($dia_formatado_br) || empty($hora)) {
        throw new Exception('Médico, dia e hora são campos obrigatórios.');
    }
    
    $dataObj = DateTime::createFromFormat('d/m/Y', $dia_formatado_br);
    if (!$dataObj) {
        throw new Exception('Formato de data inválido. Utilize DD/MM/YYYY.');
    }
    $dataParaBanco = $dataObj->format('Y-m-d');
    $horaParaBanco = $hora . ':00';
    
    // Validação de Dia e Horário de Funcionamento
    $dataHoraAgendamento = new DateTime($dataParaBanco . ' ' . $hora, new DateTimeZone('America/Sao_Paulo'));
    $agora = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));

    if ($dataHoraAgendamento < $agora) {
        throw new Exception('Não é possível agendar consultas para datas e horários no passado.');
    }

    $diaDaSemana = (int)$dataHoraAgendamento->format('N');
    if ($diaDaSemana >= 6) { // 6 = Sábado, 7 = Domingo
        throw new Exception('Não é possível agendar consultas aos Sábados e Domingos.');
    }

    $horaAgendamento = (int)$dataHoraAgendamento->format('H');
    if ($horaAgendamento < 8 || $horaAgendamento >= 16) { // Horário de 8h às 16h
        throw new Exception('O horário de funcionamento da clínica é das 08:00 às 16:00.');
    }

    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);
    $pdo->beginTransaction();
    
    $sql = "INSERT INTO consultas (paciente_cpf, medico_id, data, hora, criada_por, status, observacoes_paciente)
            VALUES (:paciente_cpf, :medico_id, :data, :hora, 'paciente', 'ativa', :observacoes)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':paciente_cpf' => $paciente_cpf,
        ':medico_id' => $medico_id,
        ':data' => $dataParaBanco,
        ':hora' => $horaParaBanco,
        ':observacoes' => $observacoes
    ]);

    if ($stmt->rowCount() > 0) {
        $id_nova_consulta = $pdo->lastInsertId(); // Pega o ID da consulta que acabou de ser inserida
        $pdo->commit();
        $response = ['success' => true, 'message' => 'Consulta agendada com sucesso!'];
    } else {
        $pdo->rollBack();
        throw new Exception('Não foi possível salvar a consulta no banco de dados.');
    }

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = $e->getMessage();
    error_log("Erro em paciente_agendar_consulta: " . $e->getMessage());
    echo json_encode($response);
    exit;
}

// --- INÍCIO DA CORREÇÃO: Bloco de notificação adicionado ---
if ($id_nova_consulta) {
    try {
        require_once 'whatsapp_service.php';
        
        // Busca os dados completos para a mensagem
        $stmt_dados = $pdo->prepare("
            SELECT p.nome, p.telefone, p.whatsapp_autorizado, m.nome AS nome_medico, e.nome AS nome_especialidade
            FROM paciente p
            JOIN consultas c ON p.cpf = c.paciente_cpf
            JOIN medicos m ON c.medico_id = m.id
            JOIN especialidades e ON m.especialidade_id = e.id
            WHERE c.id = :id_consulta
        ");
        $stmt_dados->execute([':id_consulta' => $id_nova_consulta]);
        $dados_msg = $stmt_dados->fetch();

        if ($dados_msg && !empty($dados_msg['telefone']) && $dados_msg['whatsapp_autorizado']) {
            $primeiroNome = explode(' ', $dados_msg['nome'])[0];
            $mensagem = "Olá {$primeiroNome}.\n\n" .
                        "Sua consulta foi AGENDADA com sucesso!\n\n" .
                        "Dia: {$dia_formatado_br} às {$hora}\n" .
                        "Médico: {$dados_msg['nome_medico']}\n" .
                        "Especialidade: {$dados_msg['nome_especialidade']}\n\n" .
                        "Para mais informações, entre em contato: " . CLINICA_TELEFONE_CONTATO;

            $telefoneParaAPI = '55' . preg_replace('/\D/', '', $dados_msg['telefone']);
            enviarWhatsApp($telefoneParaAPI, $mensagem);
        }
    } catch(Throwable $t) {
        // Loga o erro mas não impede a resposta de sucesso para o usuário
        error_log("AVISO: Erro na etapa de notificação (paciente_agendar_consulta): " . $t->getMessage());
    }
}
// --- FIM DA CORREÇÃO ---

echo json_encode($response);
exit;
?>
