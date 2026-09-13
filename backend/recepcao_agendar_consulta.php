<?php
// Habilita o log de erros, mas não exibe erros para o usuário.
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

require_once 'config.php';

$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado no agendamento.'];
$pdo = null;
$id_nova_consulta = null; 

try {
    if (!isset($_SESSION['recepcionista_id'])) {
        throw new Exception('Recepcionista não autenticado.');
    }
    
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);
    if ($input === null) {
        throw new Exception('Dados da requisição em formato JSON inválido.');
    }

    $paciente_cpf = preg_replace('/\D/', '', $input['cpf'] ?? '');
    $medico_id = $input['medico_id'] ?? null;
    $data_yyyymmdd = $input['data'] ?? null;
    $hora_hhmm = $input['hora'] ?? null;
    $orientacoes = $input['orientacoes'] ?? '';

    if (empty($paciente_cpf) || empty($medico_id) || empty($data_yyyymmdd) || empty($hora_hhmm)) {
        throw new Exception('Dados insuficientes para agendar a consulta.');
    }

    $dataHoraAgendamento = new DateTime($data_yyyymmdd . ' ' . $hora_hhmm, new DateTimeZone('America/Sao_Paulo'));
    $agora = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));

    if ($dataHoraAgendamento < $agora) {
        throw new Exception('Não é possível agendar consultas para datas e horários no passado.');
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

    $sql = "INSERT INTO consultas (paciente_cpf, medico_id, data, hora, criada_por, status, orientacoes_consulta) 
            VALUES (:cpf, :mid, :data, :hora, 'recepcionista', 'ativa', :orient)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':cpf' => $paciente_cpf,
        ':mid' => $medico_id,
        ':data' => $data_yyyymmdd,
        ':hora' => $hora_hhmm . ':00',
        ':orient' => $orientacoes
    ]);

    if ($stmt->rowCount() > 0) {
        $id_nova_consulta = $pdo->lastInsertId();
        $pdo->commit();
        $response = ['success' => true, 'message' => 'Consulta agendada com sucesso pela recepção!'];
    } else {
        $pdo->rollBack();
        throw new Exception('Erro ao salvar a consulta no banco de dados.');
    }

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    $response['message'] = $e->getMessage();
    error_log("Erro em recepcao_agendar_consulta: " . $e->getMessage());
    echo json_encode($response);
    exit;
}

// --- INÍCIO DA CORREÇÃO ---
// ETAPA 2: TENTATIVA DE ENVIO E REGISTRO DE NOTIFICAÇÃO
if ($response['success'] && $id_nova_consulta) {
    try {
        require_once 'whatsapp_service.php';
        
        $stmt_dados = $pdo->prepare("SELECT p.nome, p.telefone, p.whatsapp_autorizado, m.nome AS nome_medico, e.nome AS nome_especialidade FROM consultas c JOIN paciente p ON c.paciente_cpf = p.cpf JOIN medicos m ON c.medico_id = m.id JOIN especialidades e ON m.especialidade_id = e.id WHERE c.id = :id");
        $stmt_dados->execute([':id' => $id_nova_consulta]);
        $dados_msg = $stmt_dados->fetch();

        if ($dados_msg && !empty($dados_msg['telefone']) && $dados_msg['whatsapp_autorizado']) {
            $data_br = (new DateTime($data_yyyymmdd))->format('d/m/Y');
            
            $mensagem = "Olá {$dados_msg['nome']}.\n\n" .
                        "Sua consulta foi AGENDADA pela clínica para o dia: {$data_br} às {$hora_hhmm}\n" .
                        "Médico: {$dados_msg['nome_medico']}\n" .
                        "Especialidade: {$dados_msg['nome_especialidade']}\n\n";

            if(!empty($orientacoes)){
                $mensagem .= "Orientações: {$orientacoes}\n\n";
            }
            
            $mensagem .= "Para mais informações: " . CLINICA_TELEFONE_CONTATO;

            $telefoneParaAPI = '55' . preg_replace('/\D/', '', $dados_msg['telefone']);
            if (enviarWhatsApp($telefoneParaAPI, $mensagem)) {
                // Se o envio for bem-sucedido, insere no histórico de mensagens
                $stmt_hist = $pdo->prepare("INSERT INTO mensagens_enviadas (paciente_cpf, consulta_id, tipo, conteudo, data_envio) VALUES (:cpf, :cid, 'agendamento', :cont, GETDATE())");
                $stmt_hist->execute([
                    ':cpf' => $paciente_cpf,
                    ':cid' => $id_nova_consulta,
                    ':cont' => $mensagem
                ]);
            }
        }
    } catch (Throwable $t) {
        error_log("AVISO: Erro na etapa de notificação de agendamento (recepção): " . $t->getMessage());
    }
}
// --- FIM DA CORREÇÃO ---

echo json_encode($response);
exit;
?>
