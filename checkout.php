<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
}

// Verificar se tem itens no carrinho
if (empty($_SESSION['cart'])) {
    redirect('cart.php');
}

$userId = $_SESSION['user_id'];
$pageTitle = 'Finalizar Pedido';

// Buscar dados do usuário
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Buscar prefeituras para dropdown
$prefectures = $pdo->query("SELECT * FROM prefectures ORDER BY display_order")->fetchAll();

// Calcular totais do carrinho
$subtotal = 0;
$cartItems = [];

foreach ($_SESSION['cart'] as $key => $item) {
    $itemTotal = ($item['price'] + $item['customization_price']) * $item['quantity'];
    $subtotal += $itemTotal;
    $cartItems[] = array_merge($item, [
        'cart_key' => $key,
        'item_total' => $itemTotal
    ]);
}

// Buscar configuração de frete
$stmt = $pdo->query("SELECT * FROM shipping_config LIMIT 1");
$shippingConfig = $stmt->fetch();

// Calcular frete
$shippingCost = 0;
$freeShippingMinValue = $shippingConfig['free_shipping_min_value'] ?? 10000;
$freeShippingMinItems = $shippingConfig['free_shipping_min_items'] ?? 3;
$defaultShippingCost = $shippingConfig['default_shipping_cost'] ?? 800;

$totalItems = array_sum(array_column($cartItems, 'quantity'));

// Frete grátis se atingir valor mínimo OU quantidade mínima
if ($subtotal >= $freeShippingMinValue || $totalItems >= $freeShippingMinItems) {
    $shippingCost = 0;
    $freeShipping = true;
} else {
    $shippingCost = $defaultShippingCost;
    $freeShipping = false;
}

// Verificar se tem cupom aplicado
$discount = 0;
$appliedCoupon = $_SESSION['applied_coupon'] ?? null;

if ($appliedCoupon) {
    $discount = $appliedCoupon['discount_amount'] ?? 0;
}

$total = $subtotal + $shippingCost - $discount;

// Processar pedido
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_order'])) {
    try {
        // Validar dados
        $fullName = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $postalCode = sanitize($_POST['postal_code']);
        $prefecture = sanitize($_POST['prefecture']);
        $city = sanitize($_POST['city']);
        $addressLine1 = sanitize($_POST['address_line1']);
        $addressLine2 = sanitize($_POST['address_line2'] ?? '');
        $paymentMethod = sanitize($_POST['payment_method']);
        
        if (empty($fullName) || empty($email) || empty($phone) || empty($postalCode) || 
            empty($prefecture) || empty($city) || empty($addressLine1) || empty($paymentMethod)) {
            throw new Exception('Por favor, preencha todos os campos obrigatórios');
        }
        
        // Iniciar transação
        $pdo->beginTransaction();
        
        // Criar pedido
        $orderNumber = 'MP' . date('Ymd') . rand(1000, 9999);
        
        $stmt = $pdo->prepare("
            INSERT INTO orders 
            (order_number, user_id, subtotal, shipping_cost, discount, total, 
             shipping_name, shipping_email, shipping_phone, 
             shipping_postal_code, shipping_prefecture, shipping_city, 
             shipping_address_line1, shipping_address_line2,
             payment_method, status, coupon_code, coupon_discount)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)
        ");
        
        $stmt->execute([
            $orderNumber,
            $userId,
            $subtotal,
            $shippingCost,
            $discount,
            $total,
            $fullName,
            $email,
            $phone,
            $postalCode,
            $prefecture,
            $city,
            $addressLine1,
            $addressLine2,
            $paymentMethod,
            $appliedCoupon['code'] ?? null,
            $discount
        ]);
        
        $orderId = $pdo->lastInsertId();
        
        // Adicionar itens do pedido
        $stmt = $pdo->prepare("
            INSERT INTO order_items 
            (order_id, product_id, variant_id, product_name, size_code, quantity, 
             unit_price, customization_name, customization_number, customization_price, total_price)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($cartItems as $item) {
            $itemTotal = ($item['price'] + $item['customization_price']) * $item['quantity'];
            
            $stmt->execute([
                $orderId,
                $item['product_id'],
                $item['variant_id'],
                $item['name'],
                $item['size_code'],
                $item['quantity'],
                $item['price'],
                $item['customization_name'] ?? null,
                $item['customization_number'] ?? null,
                $item['customization_price'],
                $itemTotal
            ]);
        }
        
        // Atualizar uso do cupom se tiver
        if ($appliedCoupon) {
            $pdo->prepare("UPDATE coupons SET times_used = times_used + 1 WHERE id = ?")
                ->execute([$appliedCoupon['coupon_id']]);
        }
        
        // Commit
        $pdo->commit();
        
        // Limpar carrinho e cupom
        unset($_SESSION['cart']);
        unset($_SESSION['applied_coupon']);
        
        // Redirecionar conforme método de pagamento
        if ($paymentMethod === 'paypal') {
            redirect('payment/paypal.php?order=' . $orderId);
        } elseif ($paymentMethod === 'paypay') {
            redirect('payment/paypay.php?order=' . $orderId);
        } elseif ($paymentMethod === 'bank_transfer') {
            // Transferência bancária - mostrar dados e permitir upload
            redirect('payment/bank-transfer.php?order=' . $orderId);
        }
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

include 'includes/header.php';
?>

<style>
:root {
    --primary: #2d7a4a;
    --gold: #FFD700;
    --border: #ddd;
}

.checkout-container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 0 20px;
}

.checkout-grid {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 30px;
    align-items: start;
}

.checkout-main {
    background: white;
    padding: 35px;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.section-title {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-section {
    margin-bottom: 35px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 12px;
    border: 2px solid var(--border);
    border-radius: 8px;
    font-size: 15px;
    transition: border-color 0.3s;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--primary);
}

.required {
    color: red;
}

/* Cupom */
.coupon-box {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
}

.coupon-input-group {
    display: flex;
    gap: 10px;
}

.coupon-input-group input {
    flex: 1;
    padding: 12px;
    border: 2px solid var(--border);
    border-radius: 8px;
    text-transform: uppercase;
}

.coupon-input-group button {
    padding: 12px 24px;
    background: var(--gold);
    color: black;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
}

.coupon-input-group button:hover {
    background: #FFC700;
}

.coupon-applied {
    margin-top: 15px;
    padding: 12px;
    background: #d4edda;
    border: 1px solid #c3e6cb;
    border-radius: 8px;
    display: none;
}

.coupon-applied.show {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.coupon-error {
    margin-top: 10px;
    color: #dc3545;
    font-size: 14px;
    display: none;
}

.coupon-error.show {
    display: block;
}

/* Métodos de pagamento */
.payment-methods {
    display: grid;
    gap: 15px;
}

.payment-option {
    border: 2px solid var(--border);
    padding: 20px;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s;
    position: relative;
}

.payment-option:hover {
    border-color: var(--primary);
    background: #f9f9f9;
}

.payment-option input[type="radio"] {
    position: absolute;
    opacity: 0;
}

.payment-option input[type="radio"]:checked + .payment-content {
    border-left: 4px solid var(--primary);
    padding-left: 16px;
}

.payment-option.selected {
    border-color: var(--primary);
    background: #f0f8f4;
}

.payment-content {
    display: flex;
    align-items: center;
    gap: 15px;
}

.payment-icon {
    font-size: 32px;
}

.payment-info h4 {
    margin-bottom: 5px;
    font-size: 18px;
}

.payment-info p {
    color: #666;
    font-size: 14px;
}

/* Sidebar */
.checkout-sidebar {
    position: sticky;
    top: 100px;
}

.order-summary {
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.summary-title {
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--border);
}

.summary-items {
    max-height: 300px;
    overflow-y: auto;
    margin-bottom: 20px;
}

.summary-item {
    display: flex;
    gap: 15px;
    padding: 15px 0;
    border-bottom: 1px solid #f5f5f5;
}

.item-image {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 8px;
    background: #f5f5f5;
}

.item-details {
    flex: 1;
}

.item-name {
    font-weight: 600;
    margin-bottom: 5px;
}

.item-meta {
    font-size: 13px;
    color: #666;
}

.item-price {
    font-weight: bold;
    color: var(--primary);
}

.summary-totals {
    margin-top: 20px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    font-size: 15px;
}

.summary-row.subtotal {
    color: #666;
}

.summary-row.shipping {
    color: #666;
}

.summary-row.shipping.free {
    color: var(--primary);
    font-weight: bold;
}

.summary-row.discount {
    color: #28a745;
    font-weight: bold;
}

.summary-row.total {
    font-size: 22px;
    font-weight: bold;
    color: var(--primary);
    padding-top: 15px;
    border-top: 2px solid var(--border);
    margin-top: 10px;
}

.place-order-btn {
    width: 100%;
    padding: 18px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 18px;
    font-weight: bold;
    cursor: pointer;
    margin-top: 20px;
    transition: all 0.3s;
}

.place-order-btn:hover {
    background: #1a472a;
    transform: translateY(-2px);
}

.place-order-btn:disabled {
    background: #ccc;
    cursor: not-allowed;
    transform: none;
}

.free-shipping-badge {
    background: var(--primary);
    color: white;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: bold;
    display: inline-block;
    margin-bottom: 15px;
}

.error-message {
    background: #f8d7da;
    color: #721c24;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

@media (max-width: 968px) {
    .checkout-grid {
        grid-template-columns: 1fr;
    }
    
    .checkout-sidebar {
        position: relative;
        top: 0;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="checkout-container">
    <?php if (isset($error)): ?>
    <div class="error-message">
        ❌ <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>
    
    <div class="checkout-grid">
        <!-- Formulário Principal -->
        <div class="checkout-main">
            <form method="POST" id="checkoutForm">
                <!-- Informações de Contato -->
                <div class="form-section">
                    <h2 class="section-title">
                        📧 Informações de Contato
                    </h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nome Completo <span class="required">*</span></label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email <span class="required">*</span></label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Telefone <span class="required">*</span></label>
                        <input type="tel" name="phone" placeholder="090-1234-5678" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <!-- Endereço de Entrega -->
                <div class="form-section">
                    <h2 class="section-title">
                        📍 Endereço de Entrega
                    </h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>CEP (〒) <span class="required">*</span></label>
                            <input type="text" name="postal_code" placeholder="150-0001" pattern="[0-9]{3}-?[0-9]{4}" required>
                        </div>
                        <div class="form-group">
                            <label>Prefeitura <span class="required">*</span></label>
                            <select name="prefecture" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($prefectures as $pref): ?>
                                <option value="<?php echo htmlspecialchars($pref['name_ja']); ?>">
                                    <?php echo htmlspecialchars($pref['name_ja']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Cidade/Município <span class="required">*</span></label>
                        <input type="text" name="city" placeholder="渋谷区" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Endereço (Linha 1) <span class="required">*</span></label>
                        <input type="text" name="address_line1" placeholder="神宮前1-2-3 青山マンション 101号室" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Endereço (Linha 2) - Opcional</label>
                        <input type="text" name="address_line2" placeholder="Apartamento, Sala, etc.">
                    </div>
                </div>
                
                <!-- Cupom de Desconto -->
                <div class="form-section">
                    <h2 class="section-title">
                        🎟️ Cupom de Desconto
                    </h2>
                    
                    <div class="coupon-box">
                        <div class="coupon-input-group">
                            <input type="text" id="couponCode" placeholder="Digite seu cupom" maxlength="20">
                            <button type="button" onclick="applyCoupon()">Aplicar</button>
                        </div>
                        
                        <div class="coupon-applied" id="couponApplied">
                            <span>
                                ✅ Cupom <strong id="appliedCouponCode"></strong> aplicado!
                                Desconto: <strong id="appliedCouponDiscount"></strong>
                            </span>
                            <button type="button" onclick="removeCoupon()" style="background: #dc3545; color: white; padding: 6px 12px; border: none; border-radius: 5px; cursor: pointer;">
                                ✕ Remover
                            </button>
                        </div>
                        
                        <div class="coupon-error" id="couponError"></div>
                    </div>
                </div>
                
                <!-- Método de Pagamento -->
                <div class="form-section">
                    <h2 class="section-title">
                        💳 Método de Pagamento
                    </h2>
                    
                    <div class="payment-methods">
                        <!-- PayPal -->
                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="paypal" required onchange="selectPayment(this)">
                            <div class="payment-content">
                                <div class="payment-icon">💳</div>
                                <div class="payment-info">
                                    <h4>PayPal</h4>
                                    <p>Pague com segurança via PayPal</p>
                                </div>
                            </div>
                        </label>
                        
                        <!-- PayPay -->
                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="paypay" required onchange="selectPayment(this)">
                            <div class="payment-content">
                                <div class="payment-icon">📱</div>
                                <div class="payment-info">
                                    <h4>PayPay</h4>
                                    <p>Pagamento via app PayPay</p>
                                </div>
                            </div>
                        </label>
                        
                        <!-- Transferência Bancária -->
                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="bank_transfer" required onchange="selectPayment(this)">
                            <div class="payment-content">
                                <div class="payment-icon">🏦</div>
                                <div class="payment-info">
                                    <h4>Transferência Bancária</h4>
                                    <p>Dados bancários serão enviados após o pedido</p>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
                
                <input type="hidden" name="place_order" value="1">
            </form>
        </div>
        
        <!-- Resumo do Pedido -->
        <div class="checkout-sidebar">
            <div class="order-summary">
                <h3 class="summary-title">📦 Resumo do Pedido</h3>
                
                <?php if ($freeShipping): ?>
                <div class="free-shipping-badge">
                    🎉 Frete Grátis!
                </div>
                <?php endif; ?>
                
                <div class="summary-items">
                    <?php foreach ($cartItems as $item): ?>
                    <div class="summary-item">
                        <?php if (!empty($item['image'])): ?>
                        <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="item-image">
                        <?php else: ?>
                        <div class="item-image" style="display: flex; align-items: center; justify-content: center; font-size: 24px;">📷</div>
                        <?php endif; ?>
                        
                        <div class="item-details">
                            <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                            <div class="item-meta">
                                Tamanho: <?php echo htmlspecialchars($item['size_code']); ?> | 
                                Qtd: <?php echo $item['quantity']; ?>
                                <?php if (!empty($item['customization_name']) || !empty($item['customization_number'])): ?>
                                <br>✏️ <?php echo htmlspecialchars($item['customization_name']); ?> 
                                #<?php echo htmlspecialchars($item['customization_number']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="item-price">
                            <?php echo formatPrice($item['item_total']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="summary-totals">
                    <div class="summary-row subtotal">
                        <span>Subtotal (<?php echo $totalItems; ?> itens)</span>
                        <span id="displaySubtotal"><?php echo formatPrice($subtotal); ?></span>
                    </div>
                    
                    <div class="summary-row shipping <?php echo $freeShipping ? 'free' : ''; ?>">
                        <span>Frete</span>
                        <span id="displayShipping">
                            <?php echo $freeShipping ? 'GRÁTIS' : formatPrice($shippingCost); ?>
                        </span>
                    </div>
                    
                    <?php if ($discount > 0): ?>
                    <div class="summary-row discount" id="discountRow">
                        <span>Desconto</span>
                        <span id="displayDiscount">-<?php echo formatPrice($discount); ?></span>
                    </div>
                    <?php else: ?>
                    <div class="summary-row discount" id="discountRow" style="display: none;">
                        <span>Desconto</span>
                        <span id="displayDiscount">-¥0</span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="summary-row total">
                        <span>Total</span>
                        <span id="displayTotal"><?php echo formatPrice($total); ?></span>
                    </div>
                </div>
                
                <button type="submit" form="checkoutForm" class="place-order-btn" id="placeOrderBtn">
                    🔒 Finalizar Pedido
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Valores para cálculos
const subtotal = <?php echo $subtotal; ?>;
const shippingCost = <?php echo $shippingCost; ?>;
let currentDiscount = <?php echo $discount; ?>;

// Selecionar método de pagamento
function selectPayment(radio) {
    document.querySelectorAll('.payment-option').forEach(opt => {
        opt.classList.remove('selected');
    });
    radio.closest('.payment-option').classList.add('selected');
}

// Aplicar cupom
async function applyCoupon() {
    const code = document.getElementById('couponCode').value.trim().toUpperCase();
    
    if (!code) {
        showCouponError('Digite um código de cupom');
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'validate');
        formData.append('code', code);
        formData.append('subtotal', subtotal);
        
        const response = await fetch('api/coupons.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Aplicar cupom
            currentDiscount = data.data.discount_amount;
            
            document.getElementById('appliedCouponCode').textContent = data.data.code;
            document.getElementById('appliedCouponDiscount').textContent = data.data.formatted_discount;
            document.getElementById('couponApplied').classList.add('show');
            document.getElementById('couponError').classList.remove('show');
            document.getElementById('couponCode').value = '';
            
            // Atualizar totais
            updateTotals();
            
            // Salvar na sessão
            const saveData = new FormData();
            saveData.append('action', 'apply');
            saveData.append('coupon_data', JSON.stringify(data.data));
            await fetch('api/coupons.php', {
                method: 'POST',
                body: saveData
            });
            
        } else {
            showCouponError(data.message);
        }
    } catch (error) {
        showCouponError('Erro ao validar cupom');
        console.error(error);
    }
}

// Remover cupom
async function removeCoupon() {
    currentDiscount = 0;
    
    document.getElementById('couponApplied').classList.remove('show');
    document.getElementById('couponError').classList.remove('show');
    
    updateTotals();
    
    // Remover da sessão
    const formData = new FormData();
    formData.append('action', 'remove');
    await fetch('api/coupons.php', {
        method: 'POST',
        body: formData
    });
}

// Mostrar erro do cupom
function showCouponError(message) {
    const errorDiv = document.getElementById('couponError');
    errorDiv.textContent = message;
    errorDiv.classList.add('show');
}

// Atualizar totais
function updateTotals() {
    const total = subtotal + shippingCost - currentDiscount;
    
    document.getElementById('displayDiscount').textContent = '-' + formatPrice(currentDiscount);
    document.getElementById('displayTotal').textContent = formatPrice(total);
    
    if (currentDiscount > 0) {
        document.getElementById('discountRow').style.display = 'flex';
    } else {
        document.getElementById('discountRow').style.display = 'none';
    }
}

// Formatar preço
function formatPrice(value) {
    return '¥' + Math.round(value).toLocaleString('ja-JP');
}

// Validar formulário antes de enviar
document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    const selectedPayment = document.querySelector('input[name="payment_method"]:checked');
    
    if (!selectedPayment) {
        e.preventDefault();
        alert('Por favor, selecione um método de pagamento');
        return false;
    }
    
    document.getElementById('placeOrderBtn').disabled = true;
    document.getElementById('placeOrderBtn').textContent = 'Processando...';
});

// Auto-aplicar cupom da sessão ao carregar
<?php if ($appliedCoupon): ?>
document.getElementById('appliedCouponCode').textContent = '<?php echo $appliedCoupon['code']; ?>';
document.getElementById('appliedCouponDiscount').textContent = '<?php echo formatPrice($appliedCoupon['discount_amount']); ?>';
document.getElementById('couponApplied').classList.add('show');
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>