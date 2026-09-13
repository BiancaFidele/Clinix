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

    if (empty($id_consulta)) {
        throw new Exception('ID da consulta é obrigatório.');
    }

    // **ETAPA 1: SALVAR CANCELAMENTO NO BANCO**
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);
    $pdo->beginTransaction();
    
    $stmtFetch = $pdo->prepare("SELECT id FROM consultas WHERE id = :id AND paciente_cpf = :cpf AND status = 'ativa'");
    $stmtFetch->execute([':id' => $id_consulta, ':cpf' => $paciente_cpf_sessao]);
    $consulta_valida = $stmtFetch->fetch();

    if (!$consulta_valida) {
        throw new Exception('Consulta não encontrada ou você não tem permissão para cancelar.');
    }

    $sql_update = "UPDATE consultas SET status = 'cancelada' WHERE id = :id";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([':id' => $id_consulta]);

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
    error_log("Erro em cancelar_consulta.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// **ETAPA 2: TENTATIVA DE ENVIO DE NOTIFICAÇÃO**
try {
    require_once 'whatsapp_service.php';
    
    $stmt_dados = $pdo->prepare("SELECT p.nome, p.telefone, c.data, c.hora, m.nome AS nome_medico, e.nome AS nome_especialidade FROM consultas c JOIN paciente p ON c.paciente_cpf = p.cpf JOIN medicos m ON c.medico_id = m.id JOIN especialidades e ON m.especialidade_id = e.id WHERE c.id = :id");
    $stmt_dados->execute([':id' => $id_consulta]);
    $dados_msg = $stmt_dados->fetch();

    if ($dados_msg && !empty($dados_msg['telefone'])) {
        $dia_consulta = (new DateTime($dados_msg['data']))->format('d/m/Y');
        $hora_consulta = (new DateTime($dados_msg['hora']))->format('H:i');
        
        $mensagem = "Olá {$dados_msg['nome']}.\n\nSua consulta do dia: {$dia_consulta} às {$hora_consulta}\nMédico: {$dados_msg['nome_medico']}\nEspecialidade: {$dados_msg['nome_especialidade']}\n\nFoi DESMARCADA com sucesso.";
        $telefoneParaAPI = '55' . preg_replace('/\D/', '', $dados_msg['telefone']);
        enviarWhatsApp($telefoneParaAPI, $mensagem);
    }
} catch (Throwable $t) {
    error_log("Erro na etapa de notificação de cancelamento: " . $t->getMessage());
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
