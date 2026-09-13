<?php
// Define que a resposta será em formato JSON
header('Content-Type: application/json');

// Inclui o arquivo de configuração para ter acesso às constantes
require_once 'config.php';

$configData = [];

// Verifica se a constante com o contato existe e a adiciona na resposta
if (defined('CLINICA_TELEFONE_CONTATO')) {
    $configData['contato'] = CLINICA_TELEFONE_CONTATO;
}

// Envia os dados em formato JSON para o JavaScript
echo json_encode($configData);
?>
