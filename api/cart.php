<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$pageTitle = 'Carrinho';

// Processar ações do carrinho
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['remove'])) {
        $key = $_POST['key'];
        removeFromCart($key);
        setFlashMessage('Produto removido do carrinho', 'success');
        redirect('/cart.php');
    }
    
    if (isset($_POST['update'])) {
        foreach ($_POST['quantity'] as $key => $qty) {
            updateCartQuantity($key, intval($qty));
        }
        setFlashMessage('Carrinho atualizado', 'success');
        redirect('/cart.php');
    }
    
    if (isset($_POST['apply_coupon'])) {
        $code = strtoupper(trim($_POST['coupon_code']));
        $coupon = applyCoupon($code);
        
        if ($coupon) {
            setFlashMessage('Cupom aplicado com sucesso!', 'success');
        } else {
            setFlashMessage('Cupom inválido ou expirado', 'error');
        }
        redirect('/cart.php');
    }
    
    if (isset($_POST['remove_coupon'])) {
        unset($_SESSION['coupon']);
        setFlashMessage('Cupom removido', 'info');
        redirect('/cart.php');
    }
}

// Buscar produtos do carrinho
$cartItems = [];
$subtotal = 0;

if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $key => $item) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$item['product_id']]);
        $product = $stmt->fetch();
        
        if ($product) {
            $cartItems[$key] = [
                'key' => $key,
                'product' => $product,
                'size' => $item['size'],
                'quantity' => $item['quantity'],
                'subtotal' => $product['price'] * $item['quantity']
            ];
            $subtotal += $product['price'] * $item['quantity'];
        }
    }
}

// Calcular valores
$discount = calculateDiscount($subtotal);
$itemCount = getCartItemCount();
$shipping = calculateShipping($subtotal - $discount, $itemCount);
$total = $subtotal - $discount + $shipping;

include 'includes/header.php';
?>

<section class="section">
    <div class="container">
        <h1 class="section-title">🛒 Meu Carrinho</h1>
        
        <?php if (empty($cartItems)): ?>
            <div style="text-align: center; padding: 80px 20px; background: white; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <p style="font-size: 64px; margin-bottom: 20px;">🛒</p>
                <h2 style="font-size: 28px; margin-bottom: 15px;">Seu carrinho está vazio</h2>
                <p style="color: #666; margin-bottom: 30px;">Adicione produtos e comece a comprar!</p>
                <a href="/products.php" class="btn btn-primary">Ver Produtos</a>
            </div>
        <?php else: ?>
            <div class="cart-container">
                <!-- Itens do Carrinho -->
                <div class="cart-items">
                    <h2 style="margin-bottom: 30px;">Produtos</h2>
                    
                    <form method="POST" action="">
                        <?php foreach ($cartItems as $item): ?>
                            <div class="cart-item">
                                <img src="/<?php echo $item['product']['image'] ?: 'assets/images/placeholder.jpg'; ?>" 
                                     alt="<?php echo htmlspecialchars($item['product']['name']); ?>"
                                     class="cart-item-image">
                                
                                <div class="cart-item-info">
                                    <h3><?php echo htmlspecialchars($item['product']['name']); ?></h3>
                                    <div class="cart-item-details">
                                        Time: <?php echo htmlspecialchars($item['product']['team']); ?><br>
                                        Tamanho: <?php echo htmlspecialchars($item['size']); ?><br>
                                        Preço unitário: <?php echo formatPrice($item['product']['price']); ?>
                                    </div>
                                </div>
                                
                                <div class="cart-item-actions">
                                    <div class="quantity-selector">
                                        <button type="button" class="qty-btn qty-minus" 
                                                onclick="updateQuantity('<?php echo $item['key']; ?>', <?php echo $item['quantity'] - 1; ?>)">−</button>
                                        <input type="number" name="quantity[<?php echo $item['key']; ?>]" 
                                               value="<?php echo $item['quantity']; ?>" 
                                               min="1" max="<?php echo $item['product']['stock']; ?>"
                                               class="quantity-input" 
                                               data-key="<?php echo $item['key']; ?>"
                                               readonly>
                                        <button type="button" class="qty-btn qty-plus"
                                                onclick="updateQuantity('<?php echo $item['key']; ?>', <?php echo $item['quantity'] + 1; ?>)">+</button>
                                    </div>
                                    
                                    <div style="font-size: 24px; font-weight: 900; color: var(--primary-green);">
                                        <?php echo formatPrice($item['subtotal']); ?>
                                    </div>
                                    
                                    <form method="POST" action="" style="margin: 0;">
                                        <input type="hidden" name="key" value="<?php echo $item['key']; ?>">
                                        <button type="submit" name="remove" 
                                                onclick="return confirm('Remover este produto?')"
                                                style="background: none; border: none; color: #e74c3c; cursor: pointer; font-size: 14px;">
                                            🗑️ Remover
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </form>
                </div>
                
                <!-- Resumo do Pedido -->
                <div class="cart-summary">
                    <h2 style="margin-bottom: 30px;">Resumo do Pedido</h2>
                    
                    <!-- Cupom de Desconto -->
                    <div style="margin-bottom: 25px; padding: 20px; background: #f9f9f9; border-radius: 10px;">
                        <?php if (isset($_SESSION['coupon'])): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong>Cupom Aplicado:</strong><br>
                                    <span style="color: var(--primary-green); font-weight: 600;">
                                        <?php echo $_SESSION['coupon']['code']; ?>
                                    </span>
                                </div>
                                <form method="POST" action="" style="margin: 0;">
                                    <button type="submit" name="remove_coupon" class="btn-icon" title="Remover cupom">
                                        ❌
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="" id="couponForm">
                                <label style="font-weight: 600; margin-bottom: 10px; display: block;">
                                    Cupom de Desconto
                                </label>
                                <div style="display: flex; gap: 10px;">
                                    <input type="text" name="coupon_code" id="couponCode" 
                                           placeholder="Digite o cupom"
                                           style="flex: 1; padding: 10px; border: 2px solid var(--border); border-radius: 5px;">
                                    <button type="submit" name="apply_coupon" class="btn btn-primary btn-small">
                                        Aplicar
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Valores -->
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <strong><?php echo formatPrice($subtotal); ?></strong>
                    </div>
                    
                    <?php if ($discount > 0): ?>
                        <div class="summary-row" style="color: var(--primary-green);">
                            <span>Desconto:</span>
                            <strong>-<?php echo formatPrice($discount); ?></strong>
                        </div>
                    <?php endif; ?>
                    
                    <div class="summary-row">
                        <span>Frete:</span>
                        <strong>
                            <?php if ($shipping == 0): ?>
                                <span style="color: var(--primary-green);">GRÁTIS 🎉</span>
                            <?php else: ?>
                                <?php echo formatPrice($shipping); ?>
                            <?php endif; ?>
                        </strong>
                    </div>
                    
                    <div class="summary-row summary-total">
                        <span>Total:</span>
                        <strong><?php echo formatPrice($total); ?></strong>
                    </div>
                    
                    <!-- Informações sobre Frete Grátis -->
                    <?php
                    $stmt = $pdo->query("SELECT * FROM shipping_config LIMIT 1");
                    $shippingConfig = $stmt->fetch();
                    
                    if ($shipping > 0 && $shippingConfig): ?>
                        <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 8px; font-size: 14px;">
                            <?php
                            $remaining = $shippingConfig['free_shipping_min_value'] - ($subtotal - $discount);
                            if ($remaining > 0): ?>
                                💡 Faltam apenas <strong><?php echo formatPrice($remaining); ?></strong> 
                                para ganhar frete grátis!
                            <?php elseif ($shippingConfig['free_shipping_min_quantity'] - $itemCount > 0): ?>
                                💡 Adicione mais 
                                <strong><?php echo $shippingConfig['free_shipping_min_quantity'] - $itemCount; ?></strong> 
                                produto(s) para ganhar frete grátis!
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Botão Finalizar Compra -->
                    <a href="/checkout.php" class="btn btn-primary" style="width: 100%; margin-top: 30px; text-align: center;">
                        Finalizar Compra
                    </a>
                    
                    <a href="/products.php" style="display: block; text-align: center; margin-top: 15px; color: var(--text-light); text-decoration: none;">
                        ← Continuar Comprando
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
function updateQuantity(key, quantity) {
    if (quantity < 1) return;
    
    fetch('/api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update', key: key, quantity: quantity })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>
