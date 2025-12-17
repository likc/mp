<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$pageTitle = 'Carrinho de Compras';
include 'includes/header.php';

// Buscar itens do carrinho via API
$cartData = ['items' => [], 'subtotal' => 0];

if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $key => $item) {
        $itemTotal = ($item['price'] + $item['customization_price']) * $item['quantity'];
        $cartData['subtotal'] += $itemTotal;
        $cartData['items'][] = array_merge($item, [
            'cart_key' => $key,
            'item_total' => $itemTotal
        ]);
    }
}
?>

<style>
.cart-container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 0 20px;
}

.cart-header {
    text-align: center;
    margin-bottom: 40px;
}

.cart-header h1 {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 48px;
    letter-spacing: 3px;
}

.cart-content {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 30px;
}

/* Lista de Itens */
.cart-items {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.cart-item {
    display: grid;
    grid-template-columns: 120px 1fr auto;
    gap: 20px;
    padding: 20px;
    border-bottom: 1px solid var(--border);
    align-items: center;
}

.cart-item:last-child {
    border-bottom: none;
}

.item-image {
    width: 120px;
    height: 120px;
    border-radius: 10px;
    overflow: hidden;
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.item-details h3 {
    margin: 0 0 10px 0;
    font-size: 18px;
}

.item-meta {
    color: var(--text-light);
    font-size: 14px;
    margin: 5px 0;
}

.item-customization {
    background: #fff9e6;
    padding: 10px;
    border-radius: 8px;
    margin-top: 10px;
    border-left: 3px solid var(--gold);
}

.item-customization small {
    font-weight: bold;
}

.item-controls {
    text-align: right;
}

.item-price {
    font-size: 24px;
    font-weight: bold;
    color: var(--primary-green);
    margin-bottom: 15px;
}

.quantity-control {
    display: inline-flex;
    align-items: center;
    border: 2px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 15px;
}

.quantity-control button {
    width: 40px;
    height: 40px;
    border: none;
    background: #f5f5f5;
    font-size: 18px;
    cursor: pointer;
    transition: all 0.3s;
}

.quantity-control button:hover {
    background: var(--primary-green);
    color: white;
}

.quantity-control input {
    width: 60px;
    height: 40px;
    border: none;
    text-align: center;
    font-weight: bold;
}

.remove-btn {
    background: #ff4444;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
}

.remove-btn:hover {
    background: #cc0000;
}

/* Resumo */
.cart-summary {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    position: sticky;
    top: 120px;
}

.cart-summary h2 {
    margin-top: 0;
    border-bottom: 2px solid var(--border);
    padding-bottom: 15px;
}

.summary-line {
    display: flex;
    justify-content: space-between;
    padding: 15px 0;
    font-size: 16px;
}

.summary-line.total {
    border-top: 2px solid var(--border);
    margin-top: 15px;
    padding-top: 20px;
    font-size: 24px;
    font-weight: bold;
    color: var(--primary-green);
}

.checkout-btn {
    width: 100%;
    padding: 18px;
    background: var(--primary-green);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 18px;
    font-weight: bold;
    cursor: pointer;
    margin-top: 20px;
    transition: all 0.3s;
}

.checkout-btn:hover {
    background: var(--dark-green);
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(45, 122, 74, 0.3);
}

.continue-shopping {
    display: block;
    text-align: center;
    margin-top: 15px;
    color: var(--text-light);
    text-decoration: none;
}

.continue-shopping:hover {
    color: var(--primary-green);
}

/* Carrinho Vazio */
.empty-cart {
    text-align: center;
    padding: 80px 20px;
}

.empty-cart-icon {
    font-size: 120px;
    margin-bottom: 20px;
}

.empty-cart h2 {
    font-size: 32px;
    margin-bottom: 20px;
}

.empty-cart a {
    display: inline-block;
    padding: 15px 40px;
    background: var(--primary-green);
    color: white;
    text-decoration: none;
    border-radius: 10px;
    font-weight: bold;
    margin-top: 20px;
}

@media (max-width: 768px) {
    .cart-content {
        grid-template-columns: 1fr;
    }
    
    .cart-item {
        grid-template-columns: 80px 1fr;
        gap: 15px;
    }
    
    .item-controls {
        grid-column: 1 / -1;
        text-align: left;
    }
}
</style>

<div class="cart-container">
    <div class="cart-header">
        <h1>🛒 Carrinho de Compras</h1>
        <p>Revise seus itens antes de finalizar a compra</p>
    </div>
    
    <?php if (empty($cartData['items'])): ?>
        <div class="empty-cart">
            <div class="empty-cart-icon">🛒</div>
            <h2>Seu carrinho está vazio</h2>
            <p>Adicione produtos incríveis da nossa coleção!</p>
            <a href="products.php">Continuar Comprando</a>
        </div>
    <?php else: ?>
        <div class="cart-content">
            <!-- Lista de Itens -->
            <div class="cart-items">
                <?php foreach ($cartData['items'] as $item): ?>
                <div class="cart-item" data-cart-key="<?php echo $item['cart_key']; ?>">
                    <div class="item-image">
                        <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                    </div>
                    
                    <div class="item-details">
                        <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                        
                        <div class="item-meta">
                            ⚽ <?php echo htmlspecialchars($item['team']); ?>
                        </div>
                        
                        <div class="item-meta">
                            📏 Tamanho: <strong><?php echo htmlspecialchars($item['size_code']); ?></strong>
                        </div>
                        
                        <div class="item-meta">
                            💰 Preço unitário: <?php echo formatPrice($item['price']); ?>
                        </div>
                        
                        <?php if (!empty($item['customization_name']) || !empty($item['customization_number'])): ?>
                        <div class="item-customization">
                            <small>✏️ PERSONALIZAÇÃO</small><br>
                            <?php if (!empty($item['customization_name'])): ?>
                                Nome: <strong><?php echo htmlspecialchars($item['customization_name']); ?></strong><br>
                            <?php endif; ?>
                            <?php if (!empty($item['customization_number'])): ?>
                                Número: <strong><?php echo htmlspecialchars($item['customization_number']); ?></strong><br>
                            <?php endif; ?>
                            Taxa: <?php echo formatPrice($item['customization_price']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="item-controls">
                        <div class="item-price">
                            <?php echo formatPrice($item['item_total']); ?>
                        </div>
                        
                        <div class="quantity-control">
                            <button onclick="updateQuantity('<?php echo $item['cart_key']; ?>', -1)">−</button>
                            <input type="number" value="<?php echo $item['quantity']; ?>" readonly>
                            <button onclick="updateQuantity('<?php echo $item['cart_key']; ?>', 1)">+</button>
                        </div>
                        
                        <button class="remove-btn" onclick="removeItem('<?php echo $item['cart_key']; ?>')">
                            🗑️ Remover
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Resumo -->
            <div class="cart-summary">
                <h2>Resumo do Pedido</h2>
                
                <div class="summary-line">
                    <span>Subtotal</span>
                    <span id="subtotalAmount"><?php echo formatPrice($cartData['subtotal']); ?></span>
                </div>
                
                <div class="summary-line">
                    <span>Frete</span>
                    <span>Calculado no checkout</span>
                </div>
                
                <div class="summary-line total">
                    <span>Total</span>
                    <span id="totalAmount"><?php echo formatPrice($cartData['subtotal']); ?></span>
                </div>
                
                <button class="checkout-btn" onclick="window.location.href='checkout.php'">
                    Finalizar Compra
                </button>
                
                <a href="products.php" class="continue-shopping">
                    ← Continuar Comprando
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Atualizar quantidade
async function updateQuantity(cartKey, delta) {
    const cartItem = document.querySelector(`[data-cart-key="${cartKey}"]`);
    const input = cartItem.querySelector('input[type="number"]');
    let newQuantity = parseInt(input.value) + delta;
    
    if (newQuantity < 1) {
        if (confirm('Deseja remover este item do carrinho?')) {
            removeItem(cartKey);
        }
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'update');
        formData.append('cart_key', cartKey);
        formData.append('quantity', newQuantity);
        
        const response = await fetch('api/cart.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            location.reload();
        } else {
            alert(data.message);
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao atualizar carrinho. Tente novamente.');
    }
}

// Remover item
async function removeItem(cartKey) {
    if (!confirm('Deseja mesmo remover este item?')) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'remove');
        formData.append('cart_key', cartKey);
        
        const response = await fetch('api/cart.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            location.reload();
        } else {
            alert(data.message);
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao remover item. Tente novamente.');
    }
}
</script>

<?php include 'includes/footer.php'; ?>