<?php
session_start();
header('Content-Type: application/json');

// Inclui o arquivo de configuração central
require_once 'config.php';

$response = ['success' => false, 'message' => 'Erro ao alterar senha.'];

if (!isset($_SESSION['codigo_alteracao_verificado']) || !$_SESSION['codigo_alteracao_verificado'] || 
    !isset($_SESSION['paciente_cpf'])) {
    $response['message'] = 'Processo de alteração de senha inválido ou não concluído. Por favor, comece novamente.';
    echo json_encode($response);
    exit;
}

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

$senha_atual_original = $input['senha_atual_original'] ?? null;
$nova_senha_original = $input['nova_senha_original'] ?? null; 

if (empty($senha_atual_original) || empty($nova_senha_original)) {
    $response['message'] = 'Senha atual e nova senha são obrigatórias.';
    echo json_encode($response);
    exit;
}

if (strlen($nova_senha_original) < 6) {
    $response['message'] = 'A nova senha deve ter pelo menos 6 caracteres.';
    echo json_encode($response);
    exit;
}

$pdo = null;

try {
    // Utiliza as constantes do arquivo config.php para a conexão
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);
    $paciente_cpf = $_SESSION['paciente_cpf'];

    // 1. Buscar hash da senha atual do paciente
    $stmtFetch = $pdo->prepare("SELECT senha_hash FROM paciente WHERE cpf = :cpf");
    $stmtFetch->bindParam(':cpf', $paciente_cpf);
    $stmtFetch->execute();
    $paciente = $stmtFetch->fetch();

    if (!$paciente) {
        $response['message'] = 'Paciente não encontrado.';
        echo json_encode($response);
        exit;
    }

    // 2. Verificar se a senha atual fornecida corresponde à hash armazenada
    if (password_verify($senha_atual_original, $paciente['senha_hash'])) {
        // 3. Se a senha atual estiver correta, hashear a nova senha e atualizar
        $nova_senha_hash_bcrypt = password_hash($nova_senha_original, PASSWORD_BCRYPT);

        $sql_update = "UPDATE paciente SET senha_hash = :nova_senha_hash WHERE cpf = :cpf";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->bindParam(':nova_senha_hash', $nova_senha_hash_bcrypt);
        $stmt_update->bindParam(':cpf', $paciente_cpf);
        
        if ($stmt_update->execute()) {
            $response['success'] = true;
            $response['message'] = 'Senha alterada com sucesso!';

            // Limpar variáveis de sessão da alteração de senha
            unset($_SESSION['codigo_alteracao_senha']);
            unset($_SESSION['contato_alteracao_senha']);
            unset($_SESSION['cpf_alteracao_senha']);
            unset($_SESSION['codigo_alteracao_senha_expira']);
            unset($_SESSION['codigo_alteracao_verificado']);
        } else {
            $response['message'] = 'Erro ao atualizar a senha no banco de dados.';
        }
    } else {
        $response['message'] = 'Senha atual incorreta.';
    }

} catch (PDOException $e) {
    $response['message'] = 'Erro de banco de dados ao alterar senha.';
    error_log("Erro ao alterar senha (PDO): " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Erro inesperado ao alterar senha.';
    error_log("Erro ao alterar senha (Geral): " . $e->getMessage());
}

echo json_encode($response);
?>
