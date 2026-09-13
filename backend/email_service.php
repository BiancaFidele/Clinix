<?php
/**
 * Serviço de Envio de E-mail com PHPMailer
 *
 * Centraliza toda a lógica de configuração e envio de e-mails do sistema.
 * Para usar, inclua este arquivo e chame a função enviarEmail().
 */

// Importa as classes do PHPMailer para o namespace global
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Carrega os arquivos do PHPMailer. 
// IMPORTANTE: O caminho para a pasta 'PHPMailer' deve estar correto.
// Você precisa ter baixado o PHPMailer e colocado a pasta no seu projeto.
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

/**
 * Envia um e-mail configurado.
 *
 * @param string $destinatarioEmail O e-mail do destinatário.
 * @param string $destinatarioNome O nome do destinatário.
 * @param string $assunto O assunto do e-mail.
 * @param string $corpoHTML O conteúdo do e-mail em formato HTML.
 * @param string $corpoTexto O conteúdo alternativo em texto puro para clientes de e-mail que não suportam HTML.
 * @return bool Retorna true se o e-mail foi enviado com sucesso, false caso contrário.
 */
function enviarEmail($destinatarioEmail, $destinatarioNome, $assunto, $corpoHTML, $corpoTexto = '')
{
    $mail = new PHPMailer(true); // Habilita exceções

    try {
        // --- Configurações do Servidor SMTP do GMAIL ---
        
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'clinixtq@gmail.com'; // SEU E-MAIL DO GMAIL
        $mail->Password   = 'ssryhfnhmrjmxopl';    // IMPORTANTE: COLOQUE SUA SENHA DE APP AQUI
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        
        // Descomente a linha abaixo para depuração detalhada do SMTP, caso algo dê errado
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;

        // --- Remetente e Destinatário ---
        // O e-mail do remetente DEVE ser o mesmo do Username para o Gmail
        $mail->setFrom('clinixtq@gmail.com', 'Clinix Atendimento');
        $mail->addAddress($destinatarioEmail, $destinatarioNome);

        // --- Conteúdo do E-mail ---
        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body    = $corpoHTML;
        $mail->AltBody = !empty($corpoTexto) ? $corpoTexto : strip_tags($corpoHTML);

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Loga o erro detalhado no log do servidor para você poder ver o que aconteceu
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>
