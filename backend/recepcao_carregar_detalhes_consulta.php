<?php
session_start();
header('Content-Type: application/json');

// Inclui o arquivo de configuração central
require_once 'config.php';

if (!isset($_SESSION['recepcionista_id'])) {
    echo json_encode(['success' => false, 'message' => 'Recepcionista não autenticado.']);
    exit;
}

$response = ['success' => false, 'message' => 'Erro ao carregar detalhes da consulta.'];
$pdo = null;

$consultaId = $_GET['consultaId'] ?? null;

if (empty($consultaId)) {
    $response['message'] = 'ID da consulta não fornecido.';
    echo json_encode($response);
    exit;
}

try {
    // Utiliza as constantes do arquivo config.php para a conexão
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);

    $sql = "SELECT
                c.id AS id_consulta,
                c.data,
                c.hora,
                c.status,
                c.motivo_remarcacao, 
                c.orientacoes_consulta,
                p.nome AS nome_paciente,
                p.cpf AS cpf_paciente,
                m.nome AS nome_medico,
                e.nome AS nome_especialidade
            FROM consultas c
            JOIN paciente p ON c.paciente_cpf = p.cpf
            JOIN medicos m ON c.medico_id = m.id
            JOIN especialidades e ON m.especialidade_id = e.id
            WHERE c.id = :consulta_id";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':consulta_id', $consultaId, PDO::PARAM_INT);
    $stmt->execute();
    $consulta = $stmt->fetch();

    if ($consulta) {
        if (!empty($consulta['data'])) {
            $dataObj = DateTime::createFromFormat('Y-m-d H:i:s.u', $consulta['data']);
             if (!$dataObj) {
                $dataObj = DateTime::createFromFormat('Y-m-d', $consulta['data']);
            }
            $consulta['data'] = $dataObj ? $dataObj->format('Y-m-d') : $consulta['data'];
        }
        
        $response = ['success' => true, 'consulta' => $consulta];
    } else {
        $response['message'] = 'Consulta não encontrada.';
    }

} catch (PDOException $e) {
    $response['message'] = 'Erro de banco de dados: ' . $e->getMessage();
    error_log("Erro recepcao_carregar_detalhes_consulta (PDO): " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Erro geral: ' . $e->getMessage();
    error_log("Erro recepcao_carregar_detalhes_consulta (Geral): " . $e->getMessage());
}

echo json_encode($response);
?>
