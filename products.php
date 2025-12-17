<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$pageTitle = 'Produtos';

// Filtros
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$orderBy = $_GET['order'] ?? 'name';

// Query base
$sql = "SELECT p.*, c.name as category_name, c.slug as category_slug 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.active = 1";

$params = [];

// Filtro por categoria
if ($category) {
    $sql .= " AND c.slug = ?";
    $params[] = $category;
    
    // Buscar nome da categoria
    $stmt = $pdo->prepare("SELECT name FROM categories WHERE slug = ?");
    $stmt->execute([$category]);
    $categoryName = $stmt->fetchColumn();
    $pageTitle = $categoryName ?? 'Produtos';
}

// Filtro por busca
if ($search) {
    $sql .= " AND (p.name LIKE ? OR p.team LIKE ? OR p.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $pageTitle = "Busca: $search";
}

// Ordenação
switch ($orderBy) {
    case 'price_asc':
        $sql .= " ORDER BY p.price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY p.price DESC";
        break;
    case 'newest':
        $sql .= " ORDER BY p.created_at DESC";
        break;
    default:
        $sql .= " ORDER BY p.name ASC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Buscar todas as categorias para o filtro
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

include 'includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="section-header">
            <h1 class="section-title"><?php echo $pageTitle; ?></h1>
            <p class="section-subtitle">
                <?php echo count($products); ?> produto(s) encontrado(s)
            </p>
        </div>
        
        <!-- Filtros -->
        <div style="background: white; padding: 25px; border-radius: 15px; margin-bottom: 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: center;">
                <!-- Busca -->
                <form method="GET" style="flex: 1; min-width: 250px;">
                    <div class="search-box" style="width: 100%;">
                        <input type="text" name="search" placeholder="Buscar produtos..." 
                               value="<?php echo htmlspecialchars($search); ?>"
                               style="width: 100%;">
                        <button type="submit">🔍</button>
                    </div>
                </form>
                
                <!-- Filtro por Categoria -->
                <select onchange="window.location.href='/products.php?category=' + this.value + '<?php echo $search ? '&search=' . urlencode($search) : ''; ?>'" 
                        style="padding: 12px 20px; border: 2px solid var(--border); border-radius: 8px; font-size: 14px;">
                    <option value="">Todas as Categorias</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['slug']; ?>" 
                                <?php echo $category === $cat['slug'] ? 'selected' : ''; ?>>
                            <?php echo $cat['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <!-- Ordenação -->
                <select onchange="window.location.href='/products.php?order=' + this.value + '<?php echo $category ? '&category=' . urlencode($category) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>'" 
                        style="padding: 12px 20px; border: 2px solid var(--border); border-radius: 8px; font-size: 14px;">
                    <option value="name" <?php echo $orderBy === 'name' ? 'selected' : ''; ?>>Ordem Alfabética</option>
                    <option value="price_asc" <?php echo $orderBy === 'price_asc' ? 'selected' : ''; ?>>Menor Preço</option>
                    <option value="price_desc" <?php echo $orderBy === 'price_desc' ? 'selected' : ''; ?>>Maior Preço</option>
                    <option value="newest" <?php echo $orderBy === 'newest' ? 'selected' : ''; ?>>Mais Recentes</option>
                </select>
            </div>
        </div>
        
        <!-- Grid de Produtos -->
        <?php if (empty($products)): ?>
            <div style="text-align: center; padding: 80px 20px; background: white; border-radius: 15px;">
                <p style="font-size: 48px; margin-bottom: 20px;">⚽</p>
                <h3 style="font-size: 24px; margin-bottom: 10px;">Nenhum produto encontrado</h3>
                <p style="color: #666; margin-bottom: 30px;">Tente buscar por outros termos ou categorias</p>
                <a href="/products.php" class="btn btn-primary">Ver Todos os Produtos</a>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <?php if ($product['stock'] < 5): ?>
                            <div class="product-badge">ÚLTIMAS UNIDADES</div>
                        <?php endif; ?>
                        
                        <img src="/<?php echo $product['image'] ?: 'assets/images/placeholder.jpg'; ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>" 
                             class="product-image">
                        
                        <div class="product-info">
                            <div class="product-team"><?php echo htmlspecialchars($product['team']); ?></div>
                            <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="product-price"><?php echo formatPrice($product['price']); ?></div>
                            
                            <div class="product-actions">
                                <a href="/product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary btn-small">
                                    Ver Detalhes
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
