<?php
/**
 * SCRIPT DE EXECUÇÃO AUTOMÁTICA (CRON JOB)
 * * Este script verifica todas as consultas ativas e altera o status para 'concluida'
 * se já tiverem passado 15 minutos do horário agendado.
 * * Configuração de Cron Job sugerida (para executar a cada 5 minutos):
 * */5 * * * * php /caminho/completo/para/seu/projeto/backend/concluir_consultas_automatico.php
*/

require_once 'config.php';

echo "Iniciando verificação de consultas para conclusão em " . date('Y-m-d H:i:s') . "\n";

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);

    // Pega a data e hora atual no fuso horário de São Paulo
    $agora = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));

    // Seleciona todas as consultas que ainda estão ativas
    $sql = "SELECT id, data, hora, paciente_cpf, medico_id FROM consultas WHERE status = 'ativa'";
    $stmt = $pdo->query($sql);
    $consultas_ativas = $stmt->fetchAll();

    $consultas_concluidas = 0;

    foreach ($consultas_ativas as $consulta) {
        // Combina data e hora da consulta para criar um objeto DateTime
        $data_hora_consulta_str = $consulta['data'] . ' ' . $consulta['hora'];
        $data_hora_consulta = new DateTime($data_hora_consulta_str, new DateTimeZone('America/Sao_Paulo'));

        // Adiciona a tolerância de 15 minutos
        $data_hora_consulta->add(new DateInterval('PT15M'));

        // Compara se o tempo atual já ultrapassou o horário da consulta + 15 minutos
        if ($agora > $data_hora_consulta) {
            
            // Inicia uma transação para garantir a consistência dos dados
            $pdo->beginTransaction();

            // Atualiza o status da consulta para 'concluida'
            $update_sql = "UPDATE consultas SET status = 'concluida' WHERE id = :id";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([':id' => $consulta['id']]);

            // Insere um registro no histórico
            // NOTA: A origem está como 'recepcionista' para se adequar ao CHECK do banco.
            // O ideal seria alterar a tabela para aceitar 'sistema'.
            $hist_sql = "INSERT INTO historico_consultas 
                            (consultas_id, paciente_cpf, medico_id, data_original, hora_original, motivo, tipo, origem) 
                         VALUES 
                            (:cid, :cpf, :mid, :dorig, :horig, :mot, 'concluida', 'recepcionista')";
            $hist_stmt = $pdo->prepare($hist_sql);
            $hist_stmt->execute([
                ':cid' => $consulta['id'],
                ':cpf' => $consulta['paciente_cpf'],
                ':mid' => $consulta['medico_id'],
                ':dorig' => $consulta['data'],
                ':horig' => $consulta['hora'],
                ':mot' => 'Consulta concluída automaticamente pelo sistema.'
            ]);
            
            $pdo->commit();
            
            echo "Consulta ID {$consulta['id']} concluída.\n";
            $consultas_concluidas++;
        }
    }

    echo "Verificação finalizada. Total de {$consultas_concluidas} consultas atualizadas.\n";

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERRO: " . $e->getMessage() . "\n";
    error_log("Erro em concluir_consultas_automatico.php: " . $e->getMessage());
}
?>
