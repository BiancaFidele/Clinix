<?php
/**
 * Arquivo de Configuração Central do Clinix
 */

// --- Configurações do Banco de Dados ---
define('DB_HOST', '...');
define('DB_NAME', '...');
define('DB_USER', '...');
define('DB_PASS', '...');

define('DB_DSN', "sqlsrv:Server=" . DB_HOST . ";Database=" . DB_NAME);
define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::SQLSRV_ATTR_ENCODING    => PDO::SQLSRV_ENCODING_UTF8,
]);


// --- Configurações da API de WhatsApp (Twilio) ---
// --- CORREÇÃO ---
// O log de erros indicou falha de autenticação e limite de conta gratuita.
// 1. Verifique se o 'ACCOUNT_SID' e o 'AUTH_TOKEN' estão 100% corretos, conforme seu painel da Twilio.
// 2. Lembre-se que a conta de testes tem um limite diário de mensagens. Se parar de funcionar, verifique o log de erros novamente.
define('TWILIO_ACCOUNT_SID', '...');
define('TWILIO_AUTH_TOKEN', '...');
define('TWILIO_WHATSAPP_FROM', 'whatsapp:+14155238886');
// --- FIM DA CORREÇÃO ---


// Constante para os contatos da clínica, usada no rodapé e nas mensagens.
define('CLINICA_TELEFONE_CONTATO', '(16) 99644-3518 | 1111-1111');

?>
