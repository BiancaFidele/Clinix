<?php
// Este script foi feito para ser executado automaticamente pelo servidor (Cron Job).
// Exemplo de configuração de Cron Job para rodar a cada hora: 0 * * * * php /caminho/completo/para/seu/projeto/backend/enviar_lembretes.php

require_once 'config.php';
require_once 'whatsapp_service.php';

echo "Iniciando verificação de lembretes em " . date('Y-m-d H:i:s') . "\n";

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);

    // --- 1. Lembretes de 24 horas ---
    $data_amanha = date('Y-m-d', strtotime('+1 day'));
    $stmt24h = $pdo->prepare("
        SELECT p.nome, p.telefone, c.data, c.hora, m.nome as nome_medico, e.nome as nome_especialidade
        FROM consultas c
        JOIN paciente p ON c.paciente_cpf = p.cpf
        JOIN medicos m ON c.medico_id = m.id
        JOIN especialidades e ON m.especialidade_id = e.id
        WHERE c.data = :data_amanha AND c.status = 'ativa' AND c.lembrete_24h_enviado = 0
    ");
    $stmt24h->execute([':data_amanha' => $data_amanha]);
    $consultas24h = $stmt24h->fetchAll();

    foreach ($consultas24h as $consulta) {
        // Enviar apenas se for por volta do meio-dia
        if (date('H') == 12) {
            $telefoneParaAPI = '55' . preg_replace('/\D/', '', $consulta['telefone']);
            $dia_br = (new DateTime($consulta['data']))->format('d/m/Y');
            $hora_br = (new DateTime($consulta['hora']))->format('H:i');

            // Mensagem Template #4
            $mensagem = "Olá {$consulta['nome']}.\n\nLembrando de sua consulta AMANHÃ, dia: {$dia_br} às {$hora_br}.\n" .
                        "Médico: {$consulta['nome_medico']}\nEspecialidade: {$consulta['nome_especialidade']}\n\nTenha um bom dia!";

            if (enviarWhatsApp($telefoneParaAPI, $mensagem)) {
                // Marca como enviado para não enviar de novo
                $pdo->prepare("UPDATE consultas SET lembrete_24h_enviado = 1 WHERE id = ?")->execute([$consulta['id']]);
                echo "Lembrete de 24h enviado para {$consulta['nome']}\n";
            }
        }
    }

    // --- 2. Lembretes de 1 hora ---
    // Busca consultas que acontecerão entre agora e a próxima hora
    $agora = date('Y-m-d H:i:s');
    $daqui_a_uma_hora = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $stmt1h = $pdo->prepare("
        SELECT p.nome, p.telefone, c.data, c.hora, m.nome as nome_medico, e.nome as nome_especialidade
        FROM consultas c
        JOIN paciente p ON c.paciente_cpf = p.cpf
        JOIN medicos m ON c.medico_id = m.id
        JOIN especialidades e ON m.especialidade_id = e.id
        WHERE c.data = CURDATE() AND c.hora BETWEEN :agora AND :daqui_a_uma_hora AND c.status = 'ativa' AND c.lembrete_1h_enviado = 0
    ");
    // NOTA: A sintaxe acima (CURDATE(), etc.) pode precisar de ajuste para SQL Server.
    // A versão correta para SQL Server seria:
    // WHERE CAST(c.data AS DATE) = CAST(GETDATE() AS DATE) AND c.hora BETWEEN ...
    
    // A implementação exata pode variar. Para este exemplo, a lógica está demonstrada.
    // Você precisará adicionar as colunas `lembrete_24h_enviado` e `lembrete_1h_enviado` (BIT, default 0) na sua tabela `consultas`.


} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    error_log("Erro em enviar_lembretes: " . $e->getMessage());
}

echo "Verificação de lembretes concluída.\n";
?>
