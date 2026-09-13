<?php
session_start();
header('Content-Type: application/json');

// Inclui o arquivo de configuração central
require_once 'config.php';

if (!isset($_SESSION['paciente_cpf'])) {
    echo json_encode(['success' => false, 'message' => 'Paciente não autenticado.']);
    exit;
}
$paciente_cpf = $_SESSION['paciente_cpf'];

$response = ['success' => false, 'message' => 'Erro ao carregar dados do perfil.'];

try {
    // Utiliza as constantes do arquivo config.php para a conexão
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);

    // Selecionar todos os campos relevantes da tabela paciente
    $sql = "SELECT nome, data_nascimento, sexo, cpf, telefone, email, cep, endereco, numero, complemento, bairro, cidade, estado, whatsapp_autorizado 
            FROM paciente 
            WHERE cpf = :cpf";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':cpf', $paciente_cpf, PDO::PARAM_STR);
    $stmt->execute();
    $perfil = $stmt->fetch();

    if ($perfil) {
        // Formatar data_nascimento para DD/MM/YYYY se necessário para exibição
        if (!empty($perfil['data_nascimento'])) {
            $dataObj = DateTime::createFromFormat('Y-m-d', $perfil['data_nascimento']);
            if ($dataObj) {
                $perfil['data_nascimento_formatada'] = $dataObj->format('d/m/Y');
            }
        }

        $response = ['success' => true, 'perfil' => $perfil];
    } else {
        $response['message'] = 'Perfil não encontrado.';
    }

} catch (PDOException $e) {
    $response['message'] = 'Erro de banco de dados: ' . $e->getMessage();
    error_log("Erro ao carregar perfil (PDO): " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Erro geral: ' . $e->getMessage();
    error_log("Erro ao carregar perfil (Geral): " . $e->getMessage());
}

echo json_encode($response);
?>
