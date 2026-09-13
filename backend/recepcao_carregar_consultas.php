<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';

// --- CORREÇÃO: Inicializa a variável de resposta no início ---
$response = ['success' => false, 'message' => 'Nenhuma consulta encontrada para os filtros selecionados.', 'consultas' => []];
// --- FIM DA CORREÇÃO ---

if (!isset($_SESSION['recepcionista_id'])) {
    $response['message'] = 'Recepcionista não autenticado.';
    echo json_encode($response);
    exit;
}

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);
    
    $filtro_medico_id = $_GET['medico_id'] ?? null; 
    $filtro_data = $_GET['data'] ?? null;
    $filtro_horario = $_GET['horario'] ?? null;
    $filtro_nome_paciente = $_GET['nome_paciente'] ?? null;

    // --- CORREÇÃO: Alterado 'observacoes' para 'observacoes_paciente' para corresponder ao BD ---
    $sql = "SELECT
                c.id, c.data, c.hora, c.status,
                p.nome AS nome_paciente,
                m.nome AS nome_medico,
                e.nome AS nome_especialidade,
                c.observacoes_paciente,
                c.orientacoes_consulta
            FROM consultas c
            JOIN paciente p ON c.paciente_cpf = p.cpf
            JOIN medicos m ON c.medico_id = m.id
            JOIN especialidades e ON m.especialidade_id = e.id
            WHERE c.status = 'ativa'";
    // --- FIM DA CORREÇÃO ---

    $params = [];
    if (!empty($filtro_medico_id) && $filtro_medico_id !== 'TODOS') {
        $sql .= " AND c.medico_id = :medico_id ";
        $params[':medico_id'] = $filtro_medico_id;
    }
    if (!empty($filtro_data)) {
        $sql .= " AND c.data = :data_consulta ";
        $params[':data_consulta'] = $filtro_data;
    }
    if (!empty($filtro_horario)) {
        $sql .= " AND c.hora = :horario_consulta ";
        $params[':horario_consulta'] = $filtro_horario;
    }
    if (!empty($filtro_nome_paciente)) {
        $sql .= " AND p.nome LIKE :nome_paciente ";
        $params[':nome_paciente'] = '%' . $filtro_nome_paciente . '%';
    }


    $sql .= " ORDER BY c.data ASC, c.hora ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultados = $stmt->fetchAll();

    if ($resultados) {
        $consultasFormatadas = [];
        foreach ($resultados as $row) {
            $consultasFormatadas[] = [
                'id' => $row['id'],
                'nomePaciente' => $row['nome_paciente'],
                'especialidade' => $row['nome_especialidade'],
                'nomeMedico' => $row['nome_medico'],
                'dia' => (new DateTime($row['data']))->format('d/m/Y'),
                'hora' => (new DateTime($row['hora']))->format('H:i'),
                'observacoes' => $row['observacoes_paciente'] ?? '', // Corrigido aqui também
                'orientacoesConsulta' => $row['orientacoes_consulta'] ?? ''
            ];
        }
        $response = ['success' => true, 'consultas' => $consultasFormatadas];
    }

} catch (Exception $e) {
    $response['message'] = 'Erro: ' . $e->getMessage();
    error_log("Erro recepcao_carregar_consultas (PDO): " . $e->getMessage());
}

echo json_encode($response);
?>
