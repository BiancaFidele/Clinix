<?php
session_start();
header('Content-Type: application/json');

// Inclui o arquivo de configuração central
require_once 'config.php';

$response = ['success' => false, 'message' => 'Erro ao redefinir senha.'];

if (!isset($_SESSION['codigo_verificado']) || !$_SESSION['codigo_verificado'] || !isset($_SESSION['cpf_recuperacao'])) {
    $response['message'] = 'Processo de recuperação inválido ou não concluído. Por favor, comece novamente.';
    echo json_encode($response);
    exit;
}

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

$nova_senha_original = $input['nova_senha_original'] ?? null; 

if (empty($nova_senha_original)) {
    $response['message'] = 'Nova senha é obrigatória.';
    echo json_encode($response);
    exit;
}

$pdo = null;

try {
    // Utiliza as constantes do arquivo config.php para a conexão
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);

    $nova_senha_hash_bcrypt = password_hash($nova_senha_original, PASSWORD_BCRYPT);
    $cpf_para_atualizar = $_SESSION['cpf_recuperacao'];

    $sql = "UPDATE paciente SET senha_hash = :nova_senha_hash WHERE cpf = :cpf";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':nova_senha_hash', $nova_senha_hash_bcrypt);
    $stmt->bindParam(':cpf', $cpf_para_atualizar);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Senha redefinida com sucesso!';

        // Limpar variáveis de sessão da recuperação
        unset($_SESSION['codigo_recuperacao']);
        unset($_SESSION['contato_recuperacao']);
        unset($_SESSION['cpf_recuperacao']);
        unset($_SESSION['codigo_recuperacao_expira']);
        unset($_SESSION['codigo_verificado']);
    } else {
        $response['message'] = 'Erro ao atualizar a senha no banco de dados.';
    }

} catch (PDOException $e) {
    $response['message'] = 'Erro de banco de dados ao redefinir senha.';
    error_log("Erro ao redefinir senha (PDO): " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Erro inesperado ao redefinir senha.';
    error_log("Erro ao redefinir senha (Geral): " . $e->getMessage());
}

echo json_encode($response);
?>
