<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';

if (!isset($_SESSION['paciente_cpf'])) {
    echo json_encode(['success' => false, 'message' => 'Paciente não autenticado.', 'agendamentos' => []]);
    exit;
}
$paciente_cpf = $_SESSION['paciente_cpf'];

$response = ['success' => false, 'message' => 'Nenhum agendamento encontrado.', 'agendamentos' => []];

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);

    // --- CORREÇÃO: Alterado 'observacoes' para 'observacoes_paciente' para corresponder ao BD ---
    $sql = "SELECT
                c.id,
                c.data,
                c.hora,
                c.status,
                c.observacoes_paciente,
                c.orientacoes_consulta,
                m.nome AS nome_medico,
                e.nome AS nome_especialidade
            FROM consultas c
            JOIN medicos m ON c.medico_id = m.id
            JOIN especialidades e ON m.especialidade_id = e.id
            WHERE c.paciente_cpf = :paciente_cpf AND c.status = 'ativa'
            ORDER BY c.data ASC, c.hora ASC";
    // --- FIM DA CORREÇÃO ---

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':paciente_cpf', $paciente_cpf);
    $stmt->execute();
    $resultados = $stmt->fetchAll();

    if ($resultados) {
        $agendamentos = [];
        foreach ($resultados as $row) {
            $dataObj = new DateTime($row['data']);
            $horaObj = new DateTime($row['hora']);

            $agendamentos[] = [
                'id' => $row['id'], 
                'especialidade' => $row['nome_especialidade'], 
                'nomeMedico' => $row['nome_medico'], 
                'dia' => $dataObj->format('d/m/Y'),
                'hora' => $horaObj->format('H:i'),
                'status' => $row['status'],
                'observacoes' => $row['observacoes_paciente'] ?? '', // Corrigido aqui também
                'orientacoesConsulta' => $row['orientacoes_consulta'] ?? ''
            ];
        }
        $response = ['success' => true, 'message' => 'Agendamentos carregados.', 'agendamentos' => $agendamentos];
    }

} catch (Exception $e) { 
    $response['message'] = 'Erro ao buscar agendamentos: ' . $e->getMessage();
    error_log("Erro ao listar agendamentos: " . $e->getMessage());
}

echo json_encode($response);
?>
