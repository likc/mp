<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$productId = $_GET['id'] ?? 0;

// Buscar produto
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, c.slug as category_slug 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE p.id = ? AND p.active = 1
");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    redirect('/products.php');
}

$pageTitle = $product['name'];

// Buscar variações (tamanhos)
$stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY size");
$stmt->execute([$productId]);
$variants = $stmt->fetchAll();

// Adicionar ao carrinho
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    $size = $_POST['size'] ?? '';
    $quantity = intval($_POST['quantity'] ?? 1);
    
    if (empty($size)) {
        setFlashMessage('Por favor, selecione um tamanho', 'error');
    } else {
        addToCart($productId, $size, $quantity);
        setFlashMessage('Produto adicionado ao carrinho!', 'success');
        redirect('/cart.php');
    }
}

include 'includes/header.php';
?>

<section class="section">
    <div class="container">
        <!-- Breadcrumb -->
        <div style="margin-bottom: 30px; color: #666;">
            <a href="/index.php" style="color: var(--primary-green);">Início</a> / 
            <a href="/products.php" style="color: var(--primary-green);">Produtos</a> / 
            <a href="/products.php?category=<?php echo $product['category_slug']; ?>" style="color: var(--primary-green);">
                <?php echo $product['category_name']; ?>
            </a> / 
            <span><?php echo $product['name']; ?></span>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 50px;">
            <!-- Imagem do Produto -->
            <div>
                <img src="/<?php echo $product['image'] ?: 'assets/images/placeholder.jpg'; ?>" 
                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                     style="width: 100%; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
            </div>
            
            <!-- Informações do Produto -->
            <div>
                <div style="background: white; padding: 40px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <div style="color: var(--primary-green); font-weight: 600; font-size: 14px; letter-spacing: 1px; margin-bottom: 10px;">
                        <?php echo htmlspecialchars($product['team']); ?>
                        <?php if ($product['season']): ?>
                            • <?php echo htmlspecialchars($product['season']); ?>
                        <?php endif; ?>
                    </div>
                    
                    <h1 style="font-size: 36px; margin-bottom: 20px; color: var(--text-dark);">
                        <?php echo htmlspecialchars($product['name']); ?>
                    </h1>
                    
                    <div style="font-size: 42px; font-weight: 900; color: var(--primary-green); margin-bottom: 30px;">
                        <?php echo formatPrice($product['price']); ?>
                    </div>
                    
                    <?php if ($product['description']): ?>
                        <div style="margin-bottom: 30px; padding: 20px; background: #f9f9f9; border-radius: 10px;">
                            <h3 style="font-size: 18px; margin-bottom: 10px;">Descrição</h3>
                            <p style="color: #666; line-height: 1.8;">
                                <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Estoque -->
                    <div style="margin-bottom: 20px;">
                        <?php if ($product['stock'] > 0): ?>
                            <span style="color: #2ecc71; font-weight: 600;">
                                ✓ Em estoque (<?php echo $product['stock']; ?> unidade<?php echo $product['stock'] > 1 ? 's' : ''; ?>)
                            </span>
                        <?php else: ?>
                            <span style="color: #e74c3c; font-weight: 600;">
                                ✗ Produto esgotado
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($product['stock'] > 0): ?>
                        <form method="POST" action="">
                            <!-- Seleção de Tamanho -->
                            <?php if (!empty($variants)): ?>
                                <div class="form-group">
                                    <label style="font-weight: 600; margin-bottom: 10px; display: block;">
                                        Tamanho *
                                    </label>
                                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                        <?php foreach ($variants as $variant): ?>
                                            <label style="cursor: pointer;">
                                                <input type="radio" name="size" value="<?php echo $variant['size']; ?>" 
                                                       required style="display: none;" class="size-radio">
                                                <div class="size-option" style="padding: 12px 20px; border: 2px solid var(--border); border-radius: 8px; font-weight: 600; transition: all 0.3s;">
                                                    <?php echo $variant['size']; ?>
                                                    <?php if ($variant['stock'] <= 0): ?>
                                                        <span style="font-size: 10px; color: #e74c3c;">Esgotado</span>
                                                    <?php endif; ?>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="size" value="ÚNICO">
                            <?php endif; ?>
                            
                            <!-- Quantidade -->
                            <div class="form-group">
                                <label style="font-weight: 600; margin-bottom: 10px; display: block;">
                                    Quantidade
                                </label>
                                <div class="quantity-selector">
                                    <button type="button" class="qty-btn qty-minus">−</button>
                                    <input type="number" name="quantity" value="1" min="1" 
                                           max="<?php echo $product['stock']; ?>" 
                                           class="quantity-input" style="width: 80px;">
                                    <button type="button" class="qty-btn qty-plus">+</button>
                                </div>
                            </div>
                            
                            <!-- Botões -->
                            <div style="display: flex; gap: 15px; margin-top: 30px;">
                                <button type="submit" name="add_to_cart" class="btn btn-primary" style="flex: 1;">
                                    🛒 Adicionar ao Carrinho
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.size-radio:checked + .size-option {
    background: var(--primary-green);
    color: white;
    border-color: var(--primary-green);
}
.size-option:hover {
    border-color: var(--primary-green);
}
</style>

<script>
// Atualizar quantidade
document.addEventListener('DOMContentLoaded', function() {
    const minusBtn = document.querySelector('.qty-minus');
    const plusBtn = document.querySelector('.qty-plus');
    const qtyInput = document.querySelector('.quantity-input');
    
    if (minusBtn && plusBtn && qtyInput) {
        minusBtn.addEventListener('click', function() {
            let value = parseInt(qtyInput.value);
            if (value > 1) {
                qtyInput.value = value - 1;
            }
        });
        
        plusBtn.addEventListener('click', function() {
            let value = parseInt(qtyInput.value);
            let max = parseInt(qtyInput.max);
            if (value < max) {
                qtyInput.value = value + 1;
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
