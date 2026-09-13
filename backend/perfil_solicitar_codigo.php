<?php
session_start();
header('Content-Type: application/json');

// Inclui os arquivos de configuração e os serviços necessários.
require_once 'config.php';
require_once 'email_service.php';
require_once 'whatsapp_service.php';

if (!isset($_SESSION['paciente_cpf'])) {
    echo json_encode(['success' => false, 'message' => 'Paciente não autenticado.']);
    exit;
}

$response = ['success' => false, 'message' => 'Erro ao solicitar código.'];
$pdo = null;

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);

    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);
    $metodo = $input['metodo'] ?? null;

    if (empty($metodo)) {
        throw new Exception('Método de envio do código é obrigatório.');
    }

    // Busca nome, email e telefone do paciente logado.
    $stmt = $pdo->prepare("SELECT nome, email, telefone FROM paciente WHERE cpf = :cpf");
    $stmt->bindParam(':cpf', $_SESSION['paciente_cpf']);
    $stmt->execute();
    $paciente_info = $stmt->fetch();

    if (!$paciente_info) {
        throw new Exception('Dados do paciente não encontrados.');
    }

    $contato_para_envio = '';
    if ($metodo === 'email') {
        $contato_para_envio = $paciente_info['email'];
    } elseif ($metodo === 'sms') {
        $contato_para_envio = $paciente_info['telefone'];
    }

    if (empty($contato_para_envio)) {
        throw new Exception('Contato (' . $metodo . ') não cadastrado para este perfil.');
    }

    $codigo_alteracao = (string)rand(100000, 999999);
    $_SESSION['codigo_alteracao_senha'] = $codigo_alteracao;
    $_SESSION['contato_alteracao_senha'] = $contato_para_envio; 
    $_SESSION['cpf_alteracao_senha'] = $_SESSION['paciente_cpf']; 
    $_SESSION['codigo_alteracao_senha_expira'] = time() + (10 * 60);

    $envioSucesso = false;
    if ($metodo === 'email') {
        $assunto = "Clinix - Código de Verificação para Alteração de Senha";
        $corpoHTML = "<p>Olá, <strong>{$paciente_info['nome']}</strong>. Seu código para alterar a senha é: <strong>{$codigo_alteracao}</strong></p>";
        $envioSucesso = enviarEmail($paciente_info['email'], $paciente_info['nome'], $assunto, $corpoHTML);

    } elseif ($metodo === 'sms') {
        // Lógica de envio via WhatsApp.
        $telefoneLimpo = preg_replace('/\D/', '', $paciente_info['telefone']);
        $telefoneParaAPI = '55' . $telefoneLimpo;
        $mensagemWhatsApp = "Clinix informa: Olá, ".explode(' ', $paciente_info['nome'])[0]."! Seu código de verificação para alterar a senha é: *{$codigo_alteracao}*";
        
        $envioSucesso = enviarWhatsApp($telefoneParaAPI, $mensagemWhatsApp);
    }

    if ($envioSucesso) {
        $response['success'] = true;
        $response['message'] = 'Um código de verificação foi enviado para ' . htmlspecialchars($contato_para_envio) . '. Por favor, verifique suas mensagens.';
    } else {
         $response['message'] = 'Não foi possível enviar o código de verificação. Tente novamente mais tarde.';
    }

} catch (Exception $e) {
    $response['message'] = "Erro: " . $e->getMessage();
    error_log("Erro em perfil_solicitar_codigo.php: " . $e->getMessage());
}

echo json_encode($response);
?>
