<?php
session_start();
header('Content-Type: application/json');

// Inclui o arquivo de configuração central
require_once 'config.php';

$response = ['success' => false, 'message' => 'Erro desconhecido no login da recepção.'];
$pdo = null;

try {
    // Utiliza as constantes do arquivo config.php para a conexão
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = $_POST['email'] ?? null;
        $senha_original = $_POST['senha_original'] ?? null;

        if (empty($email) || empty($senha_original)) {
            $response['message'] = 'E-mail e senha são obrigatórios.';
            echo json_encode($response);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id, nome, email, senha_hash FROM recepcionistas WHERE email = :email");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $recepcionista = $stmt->fetch();

        if ($recepcionista) {
            // E-mail encontrado, agora verifica a senha
            if (password_verify($senha_original, $recepcionista['senha_hash'])) {
                // Login bem-sucedido
                $_SESSION['recepcionista_id'] = $recepcionista['id'];
                $_SESSION['recepcionista_nome'] = $recepcionista['nome'];
                $_SESSION['tipo_usuario'] = 'recepcionista';

                $response = [
                    'success' => true,
                    'message' => 'Login da recepção realizado com sucesso!',
                    'nomeUsuario' => $recepcionista['nome'],
                    'redirect' => 'Area_Recepcao_Consultas.html' 
                ];
            } else {
                // --- INÍCIO DA CORREÇÃO ---
                // Senha incorreta
                $response = [
                    'success' => false,
                    'error_type' => 'incorrect_credentials',
                    'message' => 'E-mail ou senha inválidos.'
                ];
                // --- FIM DA CORREÇÃO ---
            }
        } else {
            // --- INÍCIO DA CORREÇÃO ---
            // E-mail não encontrado
            $response = [
                'success' => false,
                'error_type' => 'email_not_found',
                'message' => 'E-mail não cadastrado, contate o administrador do sistema.'
            ];
            // --- FIM DA CORREÇÃO ---
        }
    } else {
        $response['message'] = 'Método de requisição inválido.';
    }

} catch (PDOException $e) {
    $response['message'] = 'Erro de banco de dados no login da recepção.';
    error_log("Erro login recepção (PDO): " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Erro inesperado no login da recepção.';
    error_log("Erro login recepção (Geral): " . $e->getMessage());
}

echo json_encode($response);
?>
