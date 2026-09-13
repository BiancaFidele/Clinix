<?php
// Habilita o log de erros, mas não exibe erros para o usuário, evitando quebrar o JSON.
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

// Inclui os arquivos de configuração e os serviços necessários.
require_once 'config.php';
require_once 'email_service.php';
require_once 'whatsapp_service.php';

$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado.'];
$pdo = null;

try {
    // Conecta ao banco de dados usando as credenciais do config.php
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);

    // Pega os dados enviados pelo JavaScript
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);
    if ($input === null) {
        throw new Exception('Dados da requisição em formato JSON inválido.');
    }

    $metodo = $input['metodo'] ?? null;
    $contato = $input['contato'] ?? null;

    if (empty($metodo) || empty($contato)) {
        throw new Exception('Método e contato são obrigatórios.');
    }

    // Busca o paciente no banco de dados com base no método escolhido
    $paciente = null;
    if ($metodo === 'email') {
        $stmt = $pdo->prepare("SELECT cpf, nome, email, telefone FROM paciente WHERE email = :contato");
        $stmt->bindParam(':contato', $contato);
        $stmt->execute();
        $paciente = $stmt->fetch();
    } elseif ($metodo === 'sms') { // "sms" é o valor do radio button, mas a ação é enviar WhatsApp
        $telefoneNumeros = preg_replace('/\D/', '', $contato);
        $stmt = $pdo->prepare("SELECT cpf, nome, email, telefone FROM paciente WHERE REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), ' ', ''), '-', '') LIKE :contato");
        $stmt->bindValue(':contato', '%' . $telefoneNumeros . '%');
        $stmt->execute();
        $paciente = $stmt->fetch();
    }

    // Se o paciente foi encontrado, prossegue com o envio
    if ($paciente) {
        // Gera um código seguro de 6 dígitos
        $codigo_recuperacao = (string)rand(100000, 999999);
        
        // Salva o código, o CPF e a data de expiração na sessão do usuário
        $_SESSION['codigo_recuperacao'] = $codigo_recuperacao;
        $_SESSION['contato_recuperacao'] = $contato;
        $_SESSION['cpf_recuperacao'] = $paciente['cpf'];
        $_SESSION['codigo_recuperacao_expira'] = time() + (10 * 60); // Válido por 10 minutos

        $envioSucesso = false;
        
        // Tenta enviar a notificação com base no método
        if ($metodo === 'email') {
            $assunto = "Clinix - Recuperação de Senha";
            $corpoHTML = "<p>Olá, <strong>".htmlspecialchars($paciente['nome'])."</strong>!</p><p>Seu código para recuperação de senha no sistema Clinix é: <strong>{$codigo_recuperacao}</strong></p><p>Este código é válido por 10 minutos.</p>";
            $envioSucesso = enviarEmail($paciente['email'], $paciente['nome'], $assunto, $corpoHTML);

        } elseif ($metodo === 'sms') {
            $telefoneLimpo = preg_replace('/\D/', '', $paciente['telefone']);
            $telefoneParaAPI = '55' . $telefoneLimpo;
            $primeiroNome = explode(' ', $paciente['nome'])[0];
            $mensagemWhatsApp = "Clinix informa: Olá, {$primeiroNome}! Seu código de recuperação de senha é: *{$codigo_recuperacao}*";
            $envioSucesso = enviarWhatsApp($telefoneParaAPI, $mensagemWhatsApp);
        }

        if ($envioSucesso) {
            $response['success'] = true;
            $response['message'] = 'Um código de verificação foi enviado para ' . htmlspecialchars($contato) . '. Por favor, verifique suas mensagens.';
        } else {
             $response['message'] = 'Não foi possível enviar o código de verificação. Tente novamente mais tarde.';
        }

    } else {
        // Por segurança, não informa se o usuário existe ou não.
        // A resposta de sucesso evita que um atacante saiba quais contatos estão cadastrados.
        $response['success'] = true; 
        $response['message'] = 'Se o contato informado estiver correto e associado a uma conta, um código de recuperação será enviado.';
    }

} catch (Exception $e) {
    // Captura qualquer erro e o formata na resposta JSON
    $response['message'] = 'Erro: ' . $e->getMessage();
    error_log("Erro em solicitar_codigo_recuperacao.php: " . $e->getMessage());
}

// Envia a resposta final para o JavaScript
echo json_encode($response);
exit;
?>
