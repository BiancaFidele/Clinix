<?php
/**
 * SCRIPT PARA BAIXA MANUAL PELA RECEPÇÃO
 * * Este script é chamado por um botão na interface para concluir uma consulta específica.
 */

session_start();
header('Content-Type: application/json');

require_once 'config.php';

$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado.'];
$pdo = null;

try {
    if (!isset($_SESSION['recepcionista_id'])) {
        throw new Exception('Recepcionista não autenticado.');
    }

    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);
    $id_consulta = $input['id_consulta'] ?? null;

    if (empty($id_consulta)) {
        throw new Exception('ID da consulta é obrigatório.');
    }

    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);
    $pdo->beginTransaction();

    // Busca a consulta para garantir que ela existe e está ativa
    $stmtFetch = $pdo->prepare("SELECT paciente_cpf, medico_id, data, hora FROM consultas WHERE id = :id AND status = 'ativa'");
    $stmtFetch->execute([':id' => $id_consulta]);
    $consulta = $stmtFetch->fetch();

    if (!$consulta) {
        throw new Exception('Consulta não encontrada ou já foi concluída/cancelada.');
    }

    // Atualiza o status da consulta para 'concluida'
    $update_sql = "UPDATE consultas SET status = 'concluida' WHERE id = :id";
    $update_stmt = $pdo->prepare($update_sql);
    
    if ($update_stmt->execute([':id' => $id_consulta])) {
        // Insere o registro no histórico
        $hist_sql = "INSERT INTO historico_consultas 
                        (consultas_id, paciente_cpf, medico_id, data_original, hora_original, motivo, tipo, origem) 
                     VALUES 
                        (:cid, :cpf, :mid, :dorig, :horig, :mot, 'concluida', 'recepcionista')";
        $hist_stmt = $pdo->prepare($hist_sql);
        $hist_stmt->execute([
            ':cid' => $id_consulta,
            ':cpf' => $consulta['paciente_cpf'],
            ':mid' => $consulta['medico_id'],
            ':dorig' => $consulta['data'],
            ':horig' => $consulta['hora'],
            ':mot' => 'Consulta concluída manualmente pela recepção.'
        ]);

        $pdo->commit();
        $response = ['success' => true, 'message' => 'Consulta marcada como concluída com sucesso!'];
    } else {
        $pdo->rollBack();
        throw new Exception('Não foi possível atualizar o status da consulta.');
    }

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = "Erro: " . $e->getMessage();
    error_log("Erro em recepcao_concluir_consulta.php: " . $e->getMessage());
}

echo json_encode($response);
?>
