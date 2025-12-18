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
<style>
/* Estilo para a busca no Cabeçalho (Header) */
.header-actions .search-box form {
    display: flex;
    align-items: center;
    background: #f5f5f5; /* Fundo cinza claro para destacar no branco */
    border-radius: 50px; /* Estilo arredondado igual ao seu botão da Hero */
    padding: 5px 15px;
    border: 1px solid #ddd;
}

.header-actions .search-box input {
    background: transparent;
    border: none;
    padding: 8px;
    outline: none;
    font-size: 14px;
    width: 200px; /* Largura da barra no topo */
}

.header-actions .search-box button {
    background: transparent;
    border: none;
    cursor: pointer;
    font-size: 16px;
    padding: 0;
    display: flex;
    align-items: center;
}

/* Garante que o carrinho e a busca não fiquem grudados */
.header-actions {
    display: flex;
    align-items: center;
    gap: 15px;
}
</style>

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
                        ⚽ FRETE GRÁTIS acima de 3+ produtos
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
        <form action="products.php" method="GET">
            <input type="text" name="search" placeholder="Buscar produtos..." id="searchInput" required>
            <button type="submit">🔍</button>
        </form>
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
