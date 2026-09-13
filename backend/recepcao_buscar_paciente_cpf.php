<?php
session_start();
header('Content-Type: application/json');

// Inclui o arquivo de configuração central
require_once 'config.php';

if (!isset($_SESSION['recepcionista_id'])) {
    echo json_encode(['success' => false, 'message' => 'Recepcionista não autenticado.']);
    exit;
}

$response = ['success' => false, 'message' => 'Erro ao buscar paciente.'];
$pdo = null;

try {
    // Utiliza as constantes do arquivo config.php para a conexão
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);
    $cpf = $_GET['cpf'] ?? null;

    if (empty($cpf)) {
        $response['message'] = 'CPF é obrigatório para a busca.';
        echo json_encode($response);
        exit;
    }

    $cpfNumeros = preg_replace('/\D/', '', $cpf);

    if (strlen($cpfNumeros) !== 11) {
        $response['message'] = 'CPF inválido. Deve conter 11 dígitos.';
        echo json_encode($response);
        exit;
    }

    $stmt = $pdo->prepare("SELECT nome FROM paciente WHERE cpf = :cpf");
    $stmt->bindParam(':cpf', $cpfNumeros, PDO::PARAM_STR);
    $stmt->execute();
    $paciente = $stmt->fetch();

    if ($paciente) {
        $response = ['success' => true, 'nome' => $paciente['nome']];
    } else {
        $response['message'] = 'Paciente não encontrado com este CPF.';
    }

} catch (PDOException $e) {
    $response['message'] = 'Erro de banco de dados.';
    error_log("Erro recepcao_buscar_paciente_cpf (PDO): " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Erro inesperado.';
    error_log("Erro recepcao_buscar_paciente_cpf (Geral): " . $e->getMessage());
}

echo json_encode($response);
?>
