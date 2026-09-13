<?php
/**
 * Serviço de Envio de Mensagens WhatsApp via Twilio API
 *
 * Centraliza a lógica de envio de mensagens para o WhatsApp.
 * Para usar, inclua este arquivo e chame a função enviarWhatsApp().
 */

// Inclui o arquivo de configuração para buscar as credenciais da API.
require_once 'config.php';

/**
 * Envia uma mensagem de texto simples via Twilio API para WhatsApp.
 *
 * @param string $para O número de telefone do destinatário no formato internacional (ex: 5511999998888).
 * @param string $mensagem O conteúdo da mensagem de texto a ser enviada.
 * @return bool Retorna true se a mensagem foi enviada com sucesso (API retornou status 'queued' ou similar), false caso contrário.
 */
function enviarWhatsApp($para, $mensagem)
{
    // Verifica se as credenciais foram definidas no config.php
    if (!defined('...') || !defined('...') || TWILIO_ACCOUNT_SID === 'SEU_ACCOUNT_SID_AQUI') {
        error_log("WhatsApp Service Error: Credenciais do Twilio não configuradas no arquivo config.php.");
        return false;
    }

    $accountSid = ...;
    $authToken = ...;
    $fromWhatsAppNumber = ...;
    $toWhatsAppNumber = 'whatsapp:+' . $para; // Twilio exige o prefixo 'whatsapp:+'

    // Monta a URL da API
    $url = "...";

    // Prepara os dados para a API do Twilio
    $postData = http_build_query([
        'To' => $toWhatsAppNumber,
        'From' => $fromWhatsAppNumber,
        'Body' => $mensagem
    ]);

    // Usa cURL para fazer a requisição para a API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    // Autenticação Basic Auth do Twilio
    curl_setopt($ch, CURLOPT_USERPWD, "{$accountSid}:{$authToken}");

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        error_log("Twilio cURL Error: " . $err);
        return false;
    } else {
        // Para o Twilio, um status 201 (Created) significa sucesso.
        if ($httpCode == 201) {
            error_log("Twilio Success: Mensagem para {$para} enfileirada. Resposta: {$response}");
            return true;
        } else {
            error_log("Twilio API Error: A mensagem para {$para} falhou. Status: {$httpCode}. Resposta: {$response}");
            return false;
        }
    }
}
?>
