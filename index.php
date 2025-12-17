<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$pageTitle = 'Início';

// Buscar produtos em destaque
$stmt = $pdo->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE p.featured = 1 AND p.active = 1 
    LIMIT 8
");
$featuredProducts = $stmt->fetchAll();

// Buscar categorias
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();

include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <div class="hero-content">
            <h1 class="hero-title">
                VISTA A CAMISA<br>
                DO SEU TIME!
            </h1>
            <p class="hero-subtitle">
                Produtos oficiais dos maiores clubes do mundo
            </p>
            <div class="hero-features">
                <div class="hero-feature">
                    <span>✅</span>
                    <span>Produtos Originais</span>
                </div>
                <div class="hero-feature">
                    <span>🚚</span>
                    <span>Entrega Rápida</span>
                </div>
                <div class="hero-feature">
                    <span>🔒</span>
                    <span>Compra Segura</span>
                </div>
            </div>
            <div class="hero-buttons">
                <a href="https://likc.net/mantospremium/products.php" class="btn btn-primary">Ver Produtos</a>
                <a href="#featured" class="btn btn-secondary">Destaques</a>
            </div>
        </div>
    </div>
</section>

<!-- Categorias -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Categorias</h2>
            <p class="section-subtitle">Escolha sua categoria favorita</p>
        </div>
        
        <div class="products-grid">
            <?php foreach ($categories as $category): ?>
                <a href="https://likc.net/mantospremium/products.php?category=<?php echo $category['slug']; ?>" class="product-card" style="text-decoration: none;">
                    <div class="product-info" style="text-align: center; padding: 40px;">
                        <h3 style="font-size: 32px; margin-bottom: 10px;">
                            <?php 
                            $icons = ['camisas' => '👕', 'shorts' => '🩳', 'conjuntos-infantis' => '👶', 'agasalhos' => '🧥', 'acessorios' => '🧢'];
                            echo $icons[$category['slug']] ?? '⚽';
                            ?>
                        </h3>
                        <h4 class="product-name"><?php echo $category['name']; ?></h4>
                        <p class="product-team"><?php echo $category['description']; ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Produtos em Destaque -->
<section class="section" id="featured" style="background: #f9f9f9;">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Produtos em Destaque</h2>
            <p class="section-subtitle">Os melhores produtos selecionados para você</p>
        </div>
        
        <?php if (empty($featuredProducts)): ?>
            <div style="text-align: center; padding: 60px; background: white; border-radius: 15px;">
                <p style="font-size: 24px; color: #666;">⚽</p>
                <p style="font-size: 18px; color: #666;">Em breve novos produtos!</p>
                <p style="margin-top: 10px;">
                    <a href="https://likc.net/mantospremium/admin/products.php" class="btn btn-primary btn-small">
                        <?php echo isAdmin() ? 'Adicionar Produtos' : 'Voltar'; ?>
                    </a>
                </p>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($featuredProducts as $product): ?>
                    <div class="product-card">
                        <?php if ($product['stock'] < 5): ?>
                            <div class="product-badge">ÚLTIMAS UNIDADES</div>
                        <?php endif; ?>
                        
                        <img src="https://likc.net/mantospremium/<?php echo $product['image'] ?: 'assets/images/placeholder.jpg'; ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>" 
                             class="product-image">
                        
                        <div class="product-info">
                            <div class="product-team"><?php echo htmlspecialchars($product['team']); ?></div>
                            <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="product-price"><?php echo formatPrice($product['price']); ?></div>
                            
                            <div class="product-actions">
                                <a href="https://likc.net/mantospremium/product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary btn-small">
                                    Ver Detalhes
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div style="text-align: center; margin-top: 40px;">
                <a href="https://likc.net/mantospremium/products.php" class="btn btn-primary">Ver Todos os Produtos</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Banner Promocional -->
<section class="section" style="background: linear-gradient(135deg, #1a472a 0%, #2d7a4a 100%); color: white;">
    <div class="container" style="text-align: center;">
        <h2 style="font-family: 'Bebas Neue', sans-serif; font-size: 48px; margin-bottom: 20px;">
            🎉 FRETE GRÁTIS
        </h2>
        <p style="font-size: 24px; margin-bottom: 30px;">
            Em compras acima de R$ 200,00 ou em 3 ou mais produtos
        </p>
        <a href="https://likc.net/mantospremium/products.php" class="btn btn-primary">Aproveitar Agora</a>
    </div>
</section>

<!-- Benefícios -->
<section class="section">
    <div class="container">
        <div class="products-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
            <div style="text-align: center; padding: 30px;">
                <div style="font-size: 48px; margin-bottom: 15px;">🚚</div>
                <h3 style="font-size: 20px; margin-bottom: 10px;">Entrega Rápida</h3>
                <p style="color: #666;">Receba seus produtos em poucos dias</p>
            </div>
            
            <div style="text-align: center; padding: 30px;">
                <div style="font-size: 48px; margin-bottom: 15px;">✅</div>
                <h3 style="font-size: 20px; margin-bottom: 10px;">Produtos Originais</h3>
                <p style="color: #666;">100% oficiais e licenciados</p>
            </div>
            
            <div style="text-align: center; padding: 30px;">
                <div style="font-size: 48px; margin-bottom: 15px;">🔒</div>
                <h3 style="font-size: 20px; margin-bottom: 10px;">Compra Segura</h3>
                <p style="color: #666;">Seus dados protegidos</p>
            </div>
            
            <div style="text-align: center; padding: 30px;">
                <div style="font-size: 48px; margin-bottom: 15px;">💳</div>
                <h3 style="font-size: 20px; margin-bottom: 10px;">Parcelamento</h3>
                <p style="color: #666;">Parcele suas compras</p>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
