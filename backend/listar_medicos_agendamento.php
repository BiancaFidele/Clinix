<?php
header('Content-Type: application/json');

// Inclui o arquivo de configuração central
require_once 'config.php';

$response = ['success' => false, 'message' => 'Nenhum médico encontrado.', 'medicos' => []];

try {
    // Utiliza as constantes do arquivo config.php para a conexão
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);

    $sql = "SELECT
                m.id AS id_medico,
                m.nome AS nome_medico,
                e.nome AS nome_especialidade
            FROM medicos m
            JOIN especialidades e ON m.especialidade_id = e.id
            WHERE m.ativo = 1
            ORDER BY m.nome ASC";

    $stmt = $pdo->query($sql);
    $medicos = $stmt->fetchAll();

    if ($medicos) {
        $medicosFormatados = [];
        foreach ($medicos as $medico) {
            $medicosFormatados[] = [
                'id' => $medico['id_medico'],
                'nome_display' => $medico['nome_medico'] . ' - ' . $medico['nome_especialidade']
            ];
        }
        $response = ['success' => true, 'medicos' => $medicosFormatados];
    }

} catch (PDOException $e) {
    $response['message'] = 'Erro ao buscar médicos: ' . $e->getMessage();
    error_log("Erro ao listar médicos (agendamento): " . $e->getMessage());
}

echo json_encode($response);
?>
