<?php
if (!isset($pageTitle)) $pageTitle = 'Mantos Premium';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Mantos Premium</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;600;700;900&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Flash Messages -->
    <?php
    $flash = getFlashMessage();
    if ($flash):
    ?>
    <div class="flash-message flash-<?php echo $flash['type']; ?>" id="flashMessage">
        <?php echo $flash['message']; ?>
    </div>
    <script>
        setTimeout(() => {
            const flash = document.getElementById('flashMessage');
            if (flash) {
                flash.style.opacity = '0';
                setTimeout(() => flash.remove(), 300);
            }
        }, 4000);
    </script>
    <?php endif; ?>

    <!-- Header -->
    <header class="main-header">
        <div class="header-top">
            <div class="container">
                <div class="header-top-content">
                    <div class="header-promo">
                        ⚽ FRETE GRÁTIS acima de R$ 200 ou em 3+ produtos
                    </div>
                    <div class="header-links">
                        <?php if (isLoggedIn()): ?>
                            <a href="admin/dashboard.php">
                                <?php echo isAdmin() ? '⚙️ Admin' : '👤 Minha Conta'; ?>
                            </a>
                            <a href="logout.php">Sair</a>
                        <?php else: ?>
                            <a href="login.php">Entrar</a>
                            <a href="register.php">Cadastrar</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="header-main">
            <div class="container">
                <div class="header-content">
                    <a href="index.php" class="logo">
                        <span class="logo-icon">🏆</span>
                        <span class="logo-text">
                            <span class="logo-mantos">MANTOS</span>
                            <span class="logo-premium">PREMIUM</span>
                        </span>
                    </a>
                    
                    <nav class="main-nav">
                        <a href="index.php" class="nav-link">Início</a>
                        <a href="products.php" class="nav-link">Produtos</a>
                        <a href="products.php?category=camisas" class="nav-link">Camisas</a>
                        <a href="products.php?category=shorts" class="nav-link">Shorts</a>
                        <a href="products.php?category=conjuntos-infantis" class="nav-link">Infantil</a>
                    </nav>
                    
                    <div class="header-actions">
                        <div class="search-box">
                            <input type="text" placeholder="Buscar produtos..." id="searchInput">
                            <button type="button">🔍</button>
                        </div>
                        
                        <a href="cart.php" class="cart-button">
                            🛒
                            <span class="cart-count"><?php echo getCartItemCount(); ?></span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <main class="main-content">
