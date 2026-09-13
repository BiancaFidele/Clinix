<?php
session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Erro ao verificar código.'];

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

$contato_enviado = $input['contato'] ?? null;
$codigo_enviado = $input['codigo'] ?? null;

if (empty($contato_enviado) || empty($codigo_enviado)) {
    $response['message'] = 'Contato e código são obrigatórios.';
    echo json_encode($response);
    exit;
}

if (isset($_SESSION['codigo_recuperacao']) && 
    isset($_SESSION['contato_recuperacao']) &&
    isset($_SESSION['codigo_recuperacao_expira'])) {

    if ($_SESSION['contato_recuperacao'] == $contato_enviado && $_SESSION['codigo_recuperacao'] == $codigo_enviado) {
        if (time() < $_SESSION['codigo_recuperacao_expira']) {
            $response['success'] = true;
            $response['message'] = 'Código verificado com sucesso!';
            $_SESSION['codigo_verificado'] = true; // Flag para o próximo passo
        } else {
            $response['message'] = 'Código de recuperação expirado.';
            unset($_SESSION['codigo_recuperacao']);
            unset($_SESSION['contato_recuperacao']);
            unset($_SESSION['cpf_recuperacao']);
            unset($_SESSION['codigo_recuperacao_expira']);
        }
    } else {
        $response['message'] = 'Código de recuperação inválido.';
    }
} else {
    $response['message'] = 'Nenhuma solicitação de recuperação de senha ativa ou sessão expirada.';
}

echo json_encode($response);
?>
