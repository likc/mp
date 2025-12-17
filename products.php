<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$pageTitle = 'Produtos';

// Filtros
$search = $_GET['search'] ?? '';
$category = intval($_GET['category'] ?? 0);
$team = $_GET['team'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Query base
$sql = "SELECT p.*, c.name as category_name 
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.active = 1";

$params = [];

// Filtro de busca
if (!empty($search)) {
    $sql .= " AND (p.name LIKE ? OR p.team LIKE ? OR p.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Filtro de categoria
if ($category > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category;
}

// Filtro de time
if (!empty($team)) {
    $sql .= " AND p.team LIKE ?";
    $params[] = "%$team%";
}

// Ordenação
switch ($sort) {
    case 'price_asc':
        $sql .= " ORDER BY p.price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY p.price DESC";
        break;
    case 'name':
        $sql .= " ORDER BY p.name ASC";
        break;
    default:
        $sql .= " ORDER BY p.created_at DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Buscar categorias
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Buscar times únicos
$teams = $pdo->query("SELECT DISTINCT team FROM products WHERE team IS NOT NULL AND team != '' ORDER BY team")->fetchAll();

include 'includes/header.php';
?>

<style>
.products-container {
    max-width: 1400px;
    margin: 40px auto;
    padding: 0 20px;
}

.products-header {
    text-align: center;
    margin-bottom: 40px;
}

.products-header h1 {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 48px;
    letter-spacing: 3px;
    margin-bottom: 10px;
}

/* Filtros */
.filters-bar {
    background: white;
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.filters-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr;
    gap: 15px;
    align-items: end;
}

.filter-group label {
    display: block;
    font-weight: bold;
    margin-bottom: 8px;
    font-size: 14px;
}

.filter-group input,
.filter-group select {
    width: 100%;
    padding: 12px;
    border: 2px solid var(--border);
    border-radius: 8px;
    font-size: 16px;
}

.search-btn {
    padding: 12px 30px;
    background: var(--primary-green);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
}

.search-btn:hover {
    background: var(--dark-green);
}

.clear-filters {
    padding: 12px 20px;
    background: #f5f5f5;
    color: var(--text);
    border: none;
    border-radius: 8px;
    cursor: pointer;
    margin-left: 10px;
}

.clear-filters:hover {
    background: #e0e0e0;
}

/* Grid de Produtos */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 30px;
    margin-bottom: 40px;
}

.product-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: all 0.3s;
    cursor: pointer;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
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
    transform: scale(1.05);
}

.product-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: var(--gold);
    color: black;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
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
    margin-bottom: 10px;
    color: var(--text);
    min-height: 50px;
}

.product-price {
    font-size: 24px;
    font-weight: bold;
    color: var(--primary-green);
    margin-bottom: 15px;
}

.view-product-btn {
    display: block;
    width: 100%;
    padding: 12px;
    background: var(--primary-green);
    color: white;
    text-align: center;
    text-decoration: none;
    border-radius: 8px;
    font-weight: bold;
    transition: all 0.3s;
}

.view-product-btn:hover {
    background: var(--dark-green);
}

/* Resultado da Busca */
.search-results-info {
    text-align: center;
    padding: 20px;
    background: #f5f5f5;
    border-radius: 10px;
    margin-bottom: 30px;
}

.no-results {
    text-align: center;
    padding: 80px 20px;
}

.no-results-icon {
    font-size: 80px;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 20px;
    }
}
</style>

<div class="products-container">
    <div class="products-header">
        <h1>⚽ Nossos Produtos</h1>
        <p>Encontre a camisa perfeita do seu time</p>
    </div>
    
    <!-- Filtros -->
    <div class="filters-bar">
        <form method="GET" action="products.php" id="filterForm">
            <div class="filters-grid">
                <div class="filter-group">
                    <label>🔍 Buscar</label>
                    <input type="text" 
                           name="search" 
                           placeholder="Nome do produto, time..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="filter-group">
                    <label>📂 Categoria</label>
                    <select name="category">
                        <option value="">Todas</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>⚽ Time</label>
                    <select name="team">
                        <option value="">Todos</option>
                        <?php foreach ($teams as $t): ?>
                            <option value="<?php echo htmlspecialchars($t['team']); ?>" <?php echo $team == $t['team'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['team']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>📊 Ordenar</label>
                    <select name="sort">
                        <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Mais Recentes</option>
                        <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>Menor Preço</option>
                        <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>Maior Preço</option>
                        <option value="name" <?php echo $sort == 'name' ? 'selected' : ''; ?>>Nome A-Z</option>
                    </select>
                </div>
            </div>
            
            <div style="margin-top: 15px; text-align: center;">
                <button type="submit" class="search-btn">🔍 Buscar</button>
                <button type="button" class="clear-filters" onclick="clearFilters()">✖️ Limpar Filtros</button>
            </div>
        </form>
    </div>
    
    <!-- Info de Resultados -->
    <?php if (!empty($search) || $category > 0 || !empty($team)): ?>
    <div class="search-results-info">
        <strong><?php echo count($products); ?> produto(s) encontrado(s)</strong>
        <?php if (!empty($search)): ?>
            para "<?php echo htmlspecialchars($search); ?>"
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Grid de Produtos -->
    <?php if (empty($products)): ?>
        <div class="no-results">
            <div class="no-results-icon">😢</div>
            <h2>Nenhum produto encontrado</h2>
            <p>Tente ajustar os filtros ou buscar por outro termo</p>
        </div>
    <?php else: ?>
        <div class="products-grid">
            <?php foreach ($products as $product): ?>
            <div class="product-card" onclick="window.location.href='product.php?id=<?php echo $product['id']; ?>'">
                <div class="product-image">
                    <?php if (!empty($product['image'])): ?>
                        <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                    <?php else: ?>
                        <div style="display: flex; align-items: center; justify-content: center; height: 100%; font-size: 48px;">
                            📷
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($product['featured']): ?>
                        <div class="product-badge">⭐ Destaque</div>
                    <?php endif; ?>
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
                    
                    <a href="product.php?id=<?php echo $product['id']; ?>" class="view-product-btn" onclick="event.stopPropagation()">
                        Ver Detalhes
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function clearFilters() {
    window.location.href = 'products.php';
}
</script>

<?php include 'includes/footer.php'; ?>