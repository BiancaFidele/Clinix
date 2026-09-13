<?php
session_start();
header('Content-Type: application/json');

require_once 'config.php';

// Verifica se o paciente está autenticado na sessão
if (!isset($_SESSION['paciente_cpf'])) {
    echo json_encode(['success' => false, 'message' => 'Paciente não autenticado. Faça login novamente.']);
    exit;
}

$paciente_cpf = $_SESSION['paciente_cpf'];
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);
$senha_digitada = $input['senha'] ?? null;

// Valida se a senha foi enviada
if (empty($senha_digitada)) {
    echo json_encode(['success' => false, 'message' => 'A senha é obrigatória para excluir a conta.']);
    exit;
}

$pdo = null;
try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);

    // 1. Busca o hash da senha armazenada no banco de dados
    $stmt = $pdo->prepare("SELECT senha_hash FROM paciente WHERE cpf = :cpf");
    $stmt->execute([':cpf' => $paciente_cpf]);
    $paciente = $stmt->fetch();

    if (!$paciente) {
        throw new Exception('Usuário não encontrado na base de dados.');
    }

    // 2. Verifica se a senha digitada corresponde à senha armazenada
    if (!password_verify($senha_digitada, $paciente['senha_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Senha incorreta. A exclusão foi cancelada.']);
        exit;
    }

    // 3. Senha correta. Inicia a transação para garantir a integridade dos dados.
    $pdo->beginTransaction();

    // Ordem de exclusão para respeitar as chaves estrangeiras (foreign keys)
    // Busca todos os IDs de consulta do paciente para limpar o histórico
    $stmtConsultas = $pdo->prepare("SELECT id FROM consultas WHERE paciente_cpf = :cpf");
    $stmtConsultas->execute([':cpf' => $paciente_cpf]);
    $consulta_ids = $stmtConsultas->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($consulta_ids)) {
        // Cria placeholders para a cláusula IN (ex: ?,?,?)
        $placeholders = implode(',', array_fill(0, count($consulta_ids), '?'));
        
        // Deleta do histórico de consultas
        $stmtDelHist = $pdo->prepare("DELETE FROM historico_consultas WHERE consultas_id IN ($placeholders)");
        $stmtDelHist->execute($consulta_ids);
    }

    // Deleta das mensagens enviadas
    $stmtDelMsg = $pdo->prepare("DELETE FROM mensagens_enviadas WHERE paciente_cpf = :cpf");
    $stmtDelMsg->execute([':cpf' => $paciente_cpf]);
    
    // Deleta de pré-reservas (se houver)
    $stmtDelPre = $pdo->prepare("DELETE FROM pre_reservas WHERE paciente_cpf = :cpf");
    $stmtDelPre->execute([':cpf' => $paciente_cpf]);
    
    // Deleta das consultas
    $stmtDelConsultas = $pdo->prepare("DELETE FROM consultas WHERE paciente_cpf = :cpf");
    $stmtDelConsultas->execute([':cpf' => $paciente_cpf]);
    
    // Finalmente, deleta o registro do paciente
    $stmtDelPaciente = $pdo->prepare("DELETE FROM paciente WHERE cpf = :cpf");
    $stmtDelPaciente->execute([':cpf' => $paciente_cpf]);
    
    // Confirma a transação
    $pdo->commit();
    
    // Limpa a sessão para deslogar o usuário
    session_destroy();

    echo json_encode(['success' => true, 'message' => 'Sua conta foi excluída com sucesso.']);

} catch (Exception $e) {
    // Se ocorrer qualquer erro, desfaz a transação
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro ao deletar conta: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro no servidor ao tentar excluir a conta.']);
}
?>
