<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/mailgun.php';

$pageTitle = 'Cadastro';

// Se já estiver logado, redireciona
if (isLoggedIn()) {
    redirect('/index.php');
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validações
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Preencha todos os campos obrigatórios';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email inválido';
    } elseif (strlen($password) < 6) {
        $error = 'A senha deve ter no mínimo 6 caracteres';
    } elseif ($password !== $confirm_password) {
        $error = 'As senhas não coincidem';
    } else {
        // Verifica se o email já existe
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = 'Este email já está cadastrado';
        } else {
            // Cria o usuário
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, phone, password) 
                VALUES (?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$name, $email, $phone, $hashedPassword])) {
                $userId = $pdo->lastInsertId();
                
                // Envia email de boas-vindas
                sendWelcomeEmail($email, $name);
                
                // Faz login automático
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['is_admin'] = 0;
                
                setFlashMessage('Cadastro realizado com sucesso! Bem-vindo à Mantos Premium!', 'success');
                redirect('/index.php');
            } else {
                $error = 'Erro ao criar conta. Tente novamente.';
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="form-container">
    <h1 class="form-title">🏆 Criar Conta</h1>
    
    <?php if ($error): ?>
        <div class="flash-message flash-error" style="position: static; margin-bottom: 20px;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="" data-validate>
        <div class="form-group">
            <label for="name">Nome Completo *</label>
            <input type="text" id="name" name="name" required 
                   value="<?php echo $_POST['name'] ?? ''; ?>">
        </div>
        
        <div class="form-group">
            <label for="email">Email *</label>
            <input type="email" id="email" name="email" required 
                   value="<?php echo $_POST['email'] ?? ''; ?>">
        </div>
        
        <div class="form-group">
            <label for="phone">Telefone</label>
            <input type="tel" id="phone" name="phone" 
                   placeholder="(11) 98765-4321"
                   value="<?php echo $_POST['phone'] ?? ''; ?>">
        </div>
        
        <div class="form-group">
            <label for="password">Senha * (mínimo 6 caracteres)</label>
            <input type="password" id="password" name="password" required minlength="6">
        </div>
        
        <div class="form-group">
            <label for="confirm_password">Confirmar Senha *</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                Criar Conta
            </button>
        </div>
    </form>
    
    <div style="text-align: center; margin-top: 20px;">
        <p style="color: #666;">
            Já tem uma conta? 
            <a href="login.php" style="color: var(--primary-green); font-weight: 600;">
                Faça login aqui
            </a>
        </p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
