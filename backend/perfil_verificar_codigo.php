<?php
session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Erro ao verificar código.'];

// Verificar se o paciente está logado e se há uma solicitação de código ativa
if (!isset($_SESSION['paciente_cpf']) || !isset($_SESSION['codigo_alteracao_senha'])) {
    $response['message'] = 'Nenhuma solicitação de alteração de senha ativa ou sessão inválida.';
    echo json_encode($response);
    exit;
}

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);
$codigo_enviado = $input['codigo'] ?? null;

if (empty($codigo_enviado)) {
    $response['message'] = 'Código de verificação é obrigatório.';
    echo json_encode($response);
    exit;
}

if (isset($_SESSION['codigo_alteracao_senha_expira']) && time() >= $_SESSION['codigo_alteracao_senha_expira']) {
    $response['message'] = 'Código de recuperação expirado.';
    // Limpar variáveis de sessão relacionadas à alteração
    unset($_SESSION['codigo_alteracao_senha']);
    unset($_SESSION['contato_alteracao_senha']);
    unset($_SESSION['cpf_alteracao_senha']); // Embora já tenhamos da sessão principal
    unset($_SESSION['codigo_alteracao_senha_expira']);
} elseif ($_SESSION['codigo_alteracao_senha'] === (string)$codigo_enviado) { // Compara como string
    $response['success'] = true;
    $response['message'] = 'Código verificado com sucesso!';
    $_SESSION['codigo_alteracao_verificado'] = true; // Flag para o próximo passo
} else {
    $response['message'] = 'Código de recuperação inválido.';
}

echo json_encode($response);
?>
