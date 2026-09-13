<?php
session_start();
header('Content-Type: application/json');

require_once 'config.php';

if (!isset($_SESSION['paciente_cpf'])) {
    echo json_encode(['success' => false, 'message' => 'Paciente não autenticado.']);
    exit;
}
$paciente_cpf_sessao = $_SESSION['paciente_cpf'];

$response = ['success' => false, 'message' => 'Erro ao atualizar o perfil.'];
$pdo = null;

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, DB_OPTIONS);

    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);

    // Coleta e limpa os dados
    $nomeCompleto = trim($input['nomeCompleto'] ?? null);
    $dataNascimentoInput = $input['dataNascimento'] ?? null;
    $sexo = $input['sexo'] ?? null;
    $telefoneCelular = preg_replace('/\D/', '', $input['telefoneCelular'] ?? ''); // Limpa o telefone
    $email = trim($input['email'] ?? null);
    $cep = preg_replace('/\D/', '', $input['cep'] ?? ''); // Limpa o CEP
    $rua = trim($input['rua'] ?? null);
    $numero = trim($input['numero'] ?? null);
    $complemento = trim($input['complemento'] ?? '');
    $bairro = trim($input['bairro'] ?? null);
    $cidade = trim($input['cidade'] ?? null);
    $estado = trim($input['estado'] ?? null);
    $termoWhatsapp = !empty($input['termoWhatsapp']); // Converte para booleano

    if (empty($nomeCompleto) || empty($dataNascimentoInput) || empty($telefoneCelular) || empty($email)) {
        throw new Exception('Nome, Data de Nascimento, Telefone e E-mail são obrigatórios.');
    }
    
    // Validação de e-mail e telefone
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('O formato do e-mail é inválido.');
    }
    if (strlen($telefoneCelular) < 10 || strlen($telefoneCelular) > 11) {
        throw new Exception('O número de telefone deve ter 10 ou 11 dígitos.');
    }

    // Se o e-mail foi alterado, verifica se o novo e-mail já existe para outro paciente.
    if (strtolower($email) !== strtolower($_SESSION['paciente_email'])) {
        $stmtCheckEmail = $pdo->prepare("SELECT cpf FROM paciente WHERE email = :email AND cpf != :cpf_sessao");
        $stmtCheckEmail->execute([':email' => $email, ':cpf_sessao' => $paciente_cpf_sessao]);
        if ($stmtCheckEmail->fetch()) {
            throw new Exception('Este e-mail já está em uso por outro paciente.');
        }
    }

    $sql = "UPDATE paciente SET 
                nome = :nome, 
                data_nascimento = :data_nascimento, 
                sexo = :sexo, 
                telefone = :telefone, 
                email = :email, 
                cep = :cep, 
                endereco = :endereco, 
                numero = :numero, 
                complemento = :complemento, 
                bairro = :bairro, 
                cidade = :cidade, 
                estado = :estado,
                whatsapp_autorizado = :whatsapp_autorizado
            WHERE cpf = :cpf_sessao";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':nome', $nomeCompleto);
    $stmt->bindParam(':data_nascimento', $dataNascimentoInput);
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
    $stmt->bindParam(':whatsapp_autorizado', $termoWhatsapp, PDO::PARAM_BOOL);
    $stmt->bindParam(':cpf_sessao', $paciente_cpf_sessao);

    if ($stmt->execute()) {
        
        // --- INÍCIO DA CORREÇÃO ---
        // Atualiza os dados na sessão PHP imediatamente após salvar no banco.
        // Isso garante que qualquer script subsequente use as informações novas.
        $_SESSION['paciente_nome'] = $nomeCompleto;
        $_SESSION['paciente_email'] = $email;
        // Se houver uma variável de sessão para o telefone, ela seria atualizada aqui também.
        // Como não há, a busca no banco nos outros scripts pegará o valor atualizado.
        // --- FIM DA CORREÇÃO ---

        if ($stmt->rowCount() > 0) {
            $response = ['success' => true, 'message' => 'Perfil atualizado com sucesso!'];
        } else {
            $response = ['success' => true, 'message' => 'Nenhuma alteração detectada. Seus dados já estavam atualizados.'];
        }
    } else {
        $response['message'] = 'Erro ao atualizar o perfil no banco de dados.';
    }

} catch (Exception $e) {
    $response['message'] = 'Erro: ' . $e->getMessage();
    error_log("Erro ao atualizar perfil: " . $e->getMessage());
}

echo json_encode($response);
?>
