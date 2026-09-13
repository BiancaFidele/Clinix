<?php
session_start();
header('Content-Type: application/json');

require_once 'config.php';

$response = [
    'success' => false,
    'message' => 'Erro ao contar notificações.',
    'count'   => 0
];

try {
    // Verifica se o paciente está logado
    if (!isset($_SESSION['paciente_cpf'])) {
        $response['message'] = 'Paciente não autenticado.';
        echo json_encode($response);
        exit;
    }

    $paciente_cpf = $_SESSION['paciente_cpf'];

    // Conexão com o banco usando o config.php
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);

    // Conta quantas notificações existem para esse paciente
    $sql = "SELECT COUNT(*) AS total 
            FROM mensagens_enviadas 
            WHERE paciente_cpf = :cpf";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':cpf', $paciente_cpf, PDO::PARAM_STR);
    $stmt->execute();

    $row = $stmt->fetch();
    $total = $row ? (int)$row['total'] : 0;

    $response['success'] = true;
    $response['message'] = 'Contagem realizada com sucesso.';
    $response['count']   = $total;

} catch (PDOException $e) {
    error_log("Erro em contar_notificacoes.php (PDO): " . $e->getMessage());
    $response['message'] = 'Erro de banco de dados ao contar notificações.';
} catch (Exception $e) {
    error_log("Erro em contar_notificacoes.php (Geral): " . $e->getMessage());
    $response['message'] = 'Erro geral ao contar notificações.';
}

echo json_encode($response);
