<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$pageTitle = 'Login';

// Se já estiver logado, redireciona
if (isLoggedIn()) {
    redirect(isAdmin() ? '/admin/dashboard.php' : '/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Preencha todos os campos';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['is_admin'] = $user['is_admin'];
            
            setFlashMessage('Bem-vindo de volta, ' . $user['name'] . '!', 'success');
            
            $redirect = $_GET['redirect'] ?? (isAdmin() ? '/admin/dashboard.php' : '/index.php');
            redirect($redirect);
        } else {
            $error = 'Email ou senha incorretos';
        }
    }
}

include 'includes/header.php';
?>

<div class="form-container">
    <h1 class="form-title">🏆 Login</h1>
    
    <?php if ($error): ?>
        <div class="flash-message flash-error" style="position: static; margin-bottom: 20px;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="" data-validate>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required 
                   value="<?php echo $_POST['email'] ?? ''; ?>">
        </div>
        
        <div class="form-group">
            <label for="password">Senha</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                Entrar
            </button>
        </div>
    </form>
    
    <div style="text-align: center; margin-top: 20px;">
        <p style="color: #666;">
            Não tem uma conta? 
            <a href="/register.php" style="color: var(--primary-green); font-weight: 600;">
                Cadastre-se aqui
            </a>
        </p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
