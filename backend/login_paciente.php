<?php
session_start();
header('Content-Type: application/json');

require_once 'config.php';

$response = ['success' => false, 'message' => 'Erro desconhecido.'];
$pdo = null;

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);
} catch (PDOException $e) {
    $response['message'] = 'Erro de conexão com o banco de dados.';
    error_log("Erro de conexão PDO: " . $e->getMessage());
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? null;
    $senha_original = $_POST['senha_original'] ?? null;

    if (empty($email) || empty($senha_original)) {
        $response['message'] = 'E-mail e senha são obrigatórios.';
        echo json_encode($response);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT cpf, nome, email, senha_hash FROM paciente WHERE email = :email");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $paciente = $stmt->fetch();

        if ($paciente) {
            // --- INÍCIO DA CORREÇÃO ---
            // Email encontrado, verifica a senha
            if (password_verify($senha_original, $paciente['senha_hash'])) {
                // Senha correta: Sucesso
                $_SESSION['paciente_cpf'] = $paciente['cpf'];
                $_SESSION['paciente_nome'] = $paciente['nome'];
                $_SESSION['paciente_email'] = $paciente['email'];
                $_SESSION['tipo_usuario'] = 'paciente';

                $response = [
                    'success' => true,
                    'message' => 'Login realizado com sucesso!',
                    'redirect' => 'Area_Paciente.html'
                ];
            } else {
                // Senha incorreta: Erro de credenciais
                $response = [
                    'success' => false,
                    'error_type' => 'incorrect_credentials',
                    'message' => 'E-mail ou senha inválidos.'
                ];
            }
        } else {
            // Email não encontrado: Erro de e-mail não encontrado
            $response = [
                'success' => false,
                'error_type' => 'email_not_found',
                'message' => 'E-mail não encontrado.'
            ];
        }
        // --- FIM DA CORREÇÃO ---
    } catch (PDOException $e) {
        $response['message'] = 'Erro no servidor ao tentar realizar o login.';
        error_log("Erro de consulta PDO: " . $e->getMessage());
    }
} else {
    $response['message'] = 'Método de requisição inválido.';
}

echo json_encode($response);
?>
