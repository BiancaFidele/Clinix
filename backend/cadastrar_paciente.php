<?php
// Suprimir erros na saída para não quebrar o JSON, eles ainda serão logados se configurado.
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

// Inclui o arquivo de configuração central
require_once 'config.php';

// Função de validação de CPF (mantida da correção anterior)
function validaCPF($cpf) {
    $cpf = preg_replace( '/[^0-9]/is', '', $cpf );
    if (strlen($cpf) != 11) { return false; }
    if (preg_match('/(\d)\1{10}/', $cpf)) { return false; }
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) { $d += $cpf[$c] * (($t + 1) - $c); }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) { return false; }
    }
    return true;
}

$response = ['success' => false, 'message' => 'Erro desconhecido ao processar o cadastro.'];
$pdo = null;

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);
} catch (PDOException $e) {
    $response['message'] = 'Erro de conexão com o banco de dados.';
    error_log("Erro de conexão PDO (Cadastro): " . $e->getMessage());
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coleta de dados e limpeza
    $nomeCompleto = $_POST['nomeCompleto'] ?? null;
    $dataNascimento = $_POST['dataNascimento'] ?? null;
    $sexo = $_POST['sexo'] ?? null;
    $cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
    $telefoneCelular = preg_replace('/\D/', '', $_POST['telefoneCelular'] ?? '');
    $email = $_POST['email'] ?? null;
    $senha_original = $_POST['senha_original'] ?? null;
    
    // --- CORREÇÃO: Limpeza de dados de endereço ---
    $cep = preg_replace('/\D/', '', $_POST['cep'] ?? '');
    $rua = $_POST['rua'] ?? null;
    $numero = $_POST['numero'] ?? null;
    $complemento = $_POST['complemento'] ?? ''; // Complemento é opcional
    $bairro = $_POST['bairro'] ?? null;
    $cidade = $_POST['cidade'] ?? null;
    $estado = $_POST['estado'] ?? null;
    // --- FIM DA CORREÇÃO ---

    // --- CORREÇÃO: Validação de campos vazios, incluindo endereço ---
    if (empty($nomeCompleto) || empty($dataNascimento) || empty($sexo) || empty($cpf) || empty($telefoneCelular) || empty($email) || empty($senha_original) || empty($rua) || empty($numero) || empty($bairro) || empty($cidade) || empty($estado)) {
        $response['message'] = 'Todos os campos obrigatórios (*) devem ser preenchidos, incluindo o endereço completo.';
        echo json_encode($response);
        exit;
    }
    // --- FIM DA CORREÇÃO ---

    if (!validaCPF($cpf)) {
        $response['message'] = 'O CPF informado não é válido.';
        echo json_encode($response);
        exit;
    }

    try {
        // Verificação de duplicidade (mantida da correção anterior)
        $stmtCheck = $pdo->prepare("SELECT cpf, email, telefone FROM paciente WHERE cpf = :cpf OR email = :email OR telefone = :telefone");
        $stmtCheck->bindParam(':cpf', $cpf, PDO::PARAM_STR);
        $stmtCheck->bindParam(':email', $email, PDO::PARAM_STR);
        $stmtCheck->bindParam(':telefone', $telefoneCelular, PDO::PARAM_STR);
        $stmtCheck->execute();
        $existingUser = $stmtCheck->fetch();

        if ($existingUser) {
            if ($existingUser['cpf'] === $cpf) {
                $response['message'] = 'Este CPF já está cadastrado no sistema.';
            } elseif ($existingUser['email'] === $email) {
                $response['message'] = 'Este endereço de e-mail já está em uso.';
            } elseif ($existingUser['telefone'] === $telefoneCelular) {
                $response['message'] = 'Este número de telefone já está cadastrado.';
            }
            echo json_encode($response);
            exit;
        }

        // Se passou na verificação, prossegue com o cadastro
        $senha_hash = password_hash($senha_original, PASSWORD_BCRYPT);
        
        $sql = "INSERT INTO paciente (cpf, nome, data_nascimento, sexo, telefone, email, cep, endereco, numero, complemento, bairro, cidade, estado, termos_aceitos, senha_hash, whatsapp_autorizado)
                VALUES (:cpf, :nome, :data_nascimento, :sexo, :telefone, :email, :cep, :endereco, :numero, :complemento, :bairro, :cidade, :estado, 1, :senha_hash, :whatsapp_autorizado)";
        
        $stmt = $pdo->prepare($sql);

        $stmt->bindParam(':cpf', $cpf);
        $stmt->bindParam(':nome', $nomeCompleto);
        $stmt->bindParam(':data_nascimento', $dataNascimento);
        $stmt->bindParam(':sexo', $sexo); 
        $stmt->bindParam(':telefone', $telefoneCelular);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':cep', $cep);
        $stmt->bindParam(':endereco', $rua);
        $stmt->bindParam(':numero', $numero);
        $stmt->bindParam(':complemento', $complemento);
        $stmt->bindParam(':bairro', $bairro);
        $stmt->bindParam(':cidade', $cidade);
        $stmt->bindParam(':estado', $estado);
        $stmt->bindParam(':senha_hash', $senha_hash);
        $termoWhatsapp = isset($_POST['termoWhatsapp']) && $_POST['termoWhatsapp'] === 'aceito';
        $stmt->bindParam(':whatsapp_autorizado', $termoWhatsapp, PDO::PARAM_BOOL);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Cadastro realizado com sucesso!';
        } else {
            $response['message'] = 'Erro ao salvar os dados no banco de dados.';
        }

    } catch (PDOException $e) {
        $response['message'] = 'Erro ao processar o cadastro no servidor.';
        error_log("Erro de cadastro PDO: " . $e->getMessage() . ". SQL: " . $sql);
    }
} else {
    $response['message'] = 'Método de requisição inválido.';
}

echo json_encode($response);
?>
