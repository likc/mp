<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$pageTitle = 'Mantos Premium - Camisas de Futebol';

// Buscar produtos em destaque
$featuredProducts = $pdo->query("
    SELECT * FROM products 
    WHERE active = 1 AND featured = 1 
    ORDER BY created_at DESC 
    LIMIT 8
")->fetchAll();

// Buscar novidades
$newProducts = $pdo->query("
    SELECT * FROM products 
    WHERE active = 1 
    ORDER BY created_at DESC 
    LIMIT 8
")->fetchAll();

// Buscar categorias
$categories = $pdo->query("SELECT * FROM categories ORDER BY name LIMIT 6")->fetchAll();

include 'includes/header.php';
?>

<style>
/* Hero Section */
.hero-section {
    background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
    padding: 80px 20px;
    text-align: center;
    color: white;
    position: relative;
    overflow: hidden;
}

.hero-section::before {
    content: '⚽';
    position: absolute;
    font-size: 300px;
    opacity: 0.1;
    top: -50px;
    right: -50px;
    animation: rotate 20s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.hero-content {
    max-width: 800px;
    margin: 0 auto;
    position: relative;
    z-index: 1;
}

.hero-title {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 64px;
    letter-spacing: 4px;
    margin-bottom: 20px;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
}

.hero-subtitle {
    font-size: 24px;
    margin-bottom: 40px;
    opacity: 0.95;
}

.hero-search {
    max-width: 600px;
    margin: 0 auto;
    position: relative;
}

.hero-search form {
    display: flex;
    gap: 10px;
}

.hero-search input {
    flex: 1;
    padding: 18px 25px;
    border: none;
    border-radius: 50px;
    font-size: 16px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
}

.hero-search button {
    padding: 18px 40px;
    background: var(--gold);
    color: black;
    border: none;
    border-radius: 50px;
    font-weight: bold;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
}

.hero-search button:hover {
    background: #FFE55C;
    transform: translateY(-2px);
    box-shadow: 0 7px 25px rgba(0,0,0,0.3);
}

/* Container Principal */
.main-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px;
}

/* Section Headers */
.section-header {
    text-align: center;
    margin: 60px 0 40px;
}

.section-title {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 48px;
    letter-spacing: 3px;
    color: var(--text);
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
}

.section-subtitle {
    color: var(--text-light);
    font-size: 18px;
}

/* Produtos Grid */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 30px;
    margin-bottom: 60px;
}

.product-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: all 0.3s;
    cursor: pointer;
    position: relative;
}

.product-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.product-image {
    position: relative;
    height: 300px;
    overflow: hidden;
    background: #f5f5f5;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}

.product-card:hover .product-image img {
    transform: scale(1.08);
}

.product-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: var(--gold);
    color: black;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    z-index: 2;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
}

.product-badge.featured {
    background: var(--gold);
}

.product-badge.new {
    background: #FF6B6B;
    color: white;
}

.product-info {
    padding: 20px;
}

.product-team {
    color: var(--text-light);
    font-size: 14px;
    margin-bottom: 8px;
}

.product-name {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 12px;
    color: var(--text);
    min-height: 50px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.product-price {
    font-size: 28px;
    font-weight: bold;
    color: var(--primary-green);
    margin-bottom: 15px;
}

.view-btn {
    width: 100%;
    padding: 12px;
    background: var(--primary-green);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
}

.view-btn:hover {
    background: var(--dark-green);
}

/* Categorias */
.categories-section {
    background: #f9f9f9;
    padding: 60px 20px;
    margin: 60px 0;
}

.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
    max-width: 1400px;
    margin: 0 auto;
}

.category-card {
    background: white;
    padding: 30px;
    border-radius: 15px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}

.category-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    background: var(--primary-green);
    color: white;
}

.category-icon {
    font-size: 48px;
    margin-bottom: 15px;
}

.category-name {
    font-size: 18px;
    font-weight: bold;
}

/* Ver Todos */
.view-all-btn {
    display: block;
    width: fit-content;
    margin: 40px auto;
    padding: 15px 50px;
    background: var(--primary-green);
    color: white;
    text-decoration: none;
    border-radius: 50px;
    font-weight: bold;
    font-size: 18px;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(45, 122, 74, 0.3);
}

.view-all-btn:hover {
    background: var(--dark-green);
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(45, 122, 74, 0.4);
}

/* Features */
.features-section {
    background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
    padding: 60px 20px;
    margin: 60px 0;
    color: white;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 40px;
    max-width: 1200px;
    margin: 0 auto;
}

.feature-item {
    text-align: center;
}

.feature-icon {
    font-size: 64px;
    margin-bottom: 20px;
}

.feature-title {
    font-size: 22px;
    font-weight: bold;
    margin-bottom: 10px;
}

.feature-text {
    opacity: 0.9;
    line-height: 1.6;
}

/* Responsivo */
@media (max-width: 768px) {
    .hero-title {
        font-size: 42px;
    }
    
    .hero-subtitle {
        font-size: 18px;
    }
    
    .hero-search form {
        flex-direction: column;
    }
    
    .hero-search button {
        width: 100%;
    }
    
    .section-title {
        font-size: 36px;
    }
    
    .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 20px;
    }
}
</style>

<!-- Hero Section -->
<div class="hero-section">
    <div class="hero-content">
        <h1 class="hero-title">⚽ MANTOS PREMIUM</h1>
        <p class="hero-subtitle">As Melhores Camisas de Futebol do Mundo</p>
        
        <div class="hero-search">
            <form method="GET" action="products.php">
                <input type="text" 
                       name="search" 
                       placeholder="Busque por time, jogador ou temporada..." 
                       required>
                <button type="submit">🔍 Buscar</button>
            </form>
        </div>
    </div>
</div>

<div class="main-container">
    <!-- Produtos em Destaque -->
    <?php if (!empty($featuredProducts)): ?>
    <div class="section-header">
        <h2 class="section-title">
            ⭐ Produtos em Destaque
        </h2>
        <p class="section-subtitle">Os mantos mais procurados da temporada</p>
    </div>
    
    <div class="products-grid">
        <?php foreach ($featuredProducts as $product): ?>
        <div class="product-card" onclick="window.location.href='product.php?id=<?php echo $product['id']; ?>'">
            <div class="product-image">
                <?php if (!empty($product['image'])): ?>
                    <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                         loading="lazy">
                <?php else: ?>
                    <div style="display: flex; align-items: center; justify-content: center; height: 100%; font-size: 64px;">
                        📷
                    </div>
                <?php endif; ?>
                
                <div class="product-badge featured">⭐ Destaque</div>
            </div>
            
            <div class="product-info">
                <?php if (!empty($product['team'])): ?>
                    <div class="product-team">⚽ <?php echo htmlspecialchars($product['team']); ?></div>
                <?php endif; ?>
                
                <div class="product-name">
                    <?php echo htmlspecialchars($product['name']); ?>
                </div>
                
                <div class="product-price">
                    <?php echo formatPrice($product['price']); ?>
                </div>
                
                <button class="view-btn" onclick="event.stopPropagation(); window.location.href='product.php?id=<?php echo $product['id']; ?>'">
                    Ver Detalhes
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Categorias -->
<?php if (!empty($categories)): ?>
<div class="categories-section">
    <div class="section-header">
        <h2 class="section-title">📂 Categorias</h2>
        <p class="section-subtitle">Navegue por nossas coleções</p>
    </div>
    
    <div class="categories-grid">
        <?php foreach ($categories as $category): ?>
        <div class="category-card" onclick="window.location.href='products.php?category=<?php echo $category['id']; ?>'">
            <div class="category-icon">⚽</div>
            <div class="category-name"><?php echo htmlspecialchars($category['name']); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="main-container">
    <!-- Novidades -->
    <?php if (!empty($newProducts)): ?>
    <div class="section-header">
        <h2 class="section-title">
            🆕 Novidades
        </h2>
        <p class="section-subtitle">Recém-chegados à nossa loja</p>
    </div>
    
    <div class="products-grid">
        <?php foreach ($newProducts as $product): ?>
        <div class="product-card" onclick="window.location.href='product.php?id=<?php echo $product['id']; ?>'">
            <div class="product-image">
                <?php if (!empty($product['image'])): ?>
                    <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                         loading="lazy">
                <?php else: ?>
                    <div style="display: flex; align-items: center; justify-content: center; height: 100%; font-size: 64px;">
                        📷
                    </div>
                <?php endif; ?>
                
                <div class="product-badge new">🆕 Novo</div>
            </div>
            
            <div class="product-info">
                <?php if (!empty($product['team'])): ?>
                    <div class="product-team">⚽ <?php echo htmlspecialchars($product['team']); ?></div>
                <?php endif; ?>
                
                <div class="product-name">
                    <?php echo htmlspecialchars($product['name']); ?>
                </div>
                
                <div class="product-price">
                    <?php echo formatPrice($product['price']); ?>
                </div>
                
                <button class="view-btn" onclick="event.stopPropagation(); window.location.href='product.php?id=<?php echo $product['id']; ?>'">
                    Ver Detalhes
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- Botão Ver Todos -->
    <a href="products.php" class="view-all-btn">
        Ver Todos os Produtos →
    </a>
</div>

<!-- Features -->
<div class="features-section">
    <div class="features-grid">
        <div class="feature-item">
            <div class="feature-icon">🚚</div>
            <div class="feature-title">Frete Grátis</div>
            <div class="feature-text">Em compras acima de ¥10,000 ou 3+ produtos</div>
        </div>
        
        <div class="feature-item">
            <div class="feature-icon">✏️</div>
            <div class="feature-title">Personalização</div>
            <div class="feature-text">Adicione nome e número à sua camisa</div>
        </div>
        
        <div class="feature-item">
            <div class="feature-icon">💳</div>
            <div class="feature-title">Pagamento Seguro</div>
            <div class="feature-text">Cartão, PayPal e Transferência Bancária</div>
        </div>
        
        <div class="feature-item">
            <div class="feature-icon">⚽</div>
            <div class="feature-title">Produtos Autênticos</div>
            <div class="feature-text">Camisas originais dos principais times</div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>