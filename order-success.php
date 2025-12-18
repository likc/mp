<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
}

$orderNumber = $_GET['order'] ?? '';

if (empty($orderNumber)) {
    redirect('index.php');
}

// Buscar pedido
$stmt = $pdo->prepare("
    SELECT o.*, u.name as customer_name, u.email as customer_email
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.order_number = ? AND o.user_id = ?
");
$stmt->execute([$orderNumber, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    redirect('index.php');
}

// Buscar itens do pedido
$stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt->execute([$order['id']]);
$items = $stmt->fetchAll();

$pageTitle = 'Pedido Confirmado';
include 'includes/header.php';
?>

<style>
.success-container {
    max-width: 800px;
    margin: 60px auto;
    padding: 0 20px;
}

.success-card {
    background: white;
    padding: 50px;
    border-radius: 20px;
    box-shadow: 0 5px 30px rgba(0,0,0,0.1);
    text-align: center;
}

.success-icon {
    font-size: 100px;
    margin-bottom: 30px;
    animation: bounce 1s ease;
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
    40% {transform: translateY(-20px);}
    60% {transform: translateY(-10px);}
}

.success-title {
    font-size: 36px;
    color: #2d7a4a;
    margin-bottom: 15px;
}

.success-message {
    font-size: 18px;
    color: #666;
    margin-bottom: 30px;
}

.order-number {
    background: #f0f8f4;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 40px;
}

.order-number strong {
    font-size: 24px;
    color: #2d7a4a;
}

.order-details {
    text-align: left;
    margin: 40px 0;
}

.detail-section {
    margin-bottom: 30px;
}

.detail-title {
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 15px;
    border-bottom: 2px solid #f5f5f5;
    padding-bottom: 10px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid #f9f9f9;
}

.detail-label {
    color: #666;
}

.detail-value {
    font-weight: 600;
}

.items-list {
    margin: 20px 0;
}

.order-item {
    display: flex;
    justify-content: space-between;
    padding: 15px;
    background: #f9f9f9;
    margin-bottom: 10px;
    border-radius: 8px;
}

.item-info {
    flex: 1;
}

.item-name {
    font-weight: bold;
    margin-bottom: 5px;
}

.item-meta {
    font-size: 14px;
    color: #666;
}

.item-price {
    font-weight: bold;
    color: #2d7a4a;
}

.payment-info {
    background: #fff9e6;
    padding: 25px;
    border-radius: 10px;
    border: 2px solid #FFD700;
    margin: 30px 0;
}

.payment-info h3 {
    color: #333;
    margin-bottom: 15px;
}

.bank-details {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-top: 15px;
}

.bank-detail {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #f5f5f5;
}

.bank-detail:last-child {
    border-bottom: none;
}

.totals-section {
    background: #f9f9f9;
    padding: 25px;
    border-radius: 10px;
    margin: 30px 0;
}

.total-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    font-size: 16px;
}

.total-row.final {
    font-size: 24px;
    font-weight: bold;
    color: #2d7a4a;
    padding-top: 15px;
    border-top: 2px solid #ddd;
    margin-top: 10px;
}

.action-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-top: 40px;
}

.btn {
    padding: 16px 32px;
    border-radius: 10px;
    font-size: 16px;
    font-weight: bold;
    text-decoration: none;
    text-align: center;
    transition: all 0.3s;
}

.btn-primary {
    background: #2d7a4a;
    color: white;
}

.btn-primary:hover {
    background: #1a472a;
    transform: translateY(-2px);
}

.btn-secondary {
    background: white;
    color: #2d7a4a;
    border: 2px solid #2d7a4a;
}

.btn-secondary:hover {
    background: #f0f8f4;
}

@media (max-width: 768px) {
    .success-card {
        padding: 30px 20px;
    }
    
    .action-buttons {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="success-container">
    <div class="success-card">
        <div class="success-icon">🎉</div>
        
        <h1 class="success-title">Pedido Confirmado!</h1>
        <p class="success-message">
            Obrigado pela sua compra! Seu pedido foi recebido com sucesso.
        </p>
        
        <div class="order-number">
            Número do Pedido: <strong>#<?php echo htmlspecialchars($order['order_number']); ?></strong>
        </div>
        
        <!-- Detalhes do Pedido -->
        <div class="order-details">
            <!-- Informações do Cliente -->
            <div class="detail-section">
                <div class="detail-title">📧 Informações de Contato</div>
                <div class="detail-row">
                    <span class="detail-label">Nome:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($order['shipping_name']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($order['shipping_email']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Telefone:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($order['shipping_phone']); ?></span>
                </div>
            </div>
            
            <!-- Endereço de Entrega -->
            <div class="detail-section">
                <div class="detail-title">📍 Endereço de Entrega</div>
                <div class="detail-row">
                    <span class="detail-label">CEP:</span>
                    <span class="detail-value">〒<?php echo htmlspecialchars($order['shipping_postal_code']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Endereço:</span>
                    <span class="detail-value">
                        <?php echo htmlspecialchars($order['shipping_prefecture']); ?> 
                        <?php echo htmlspecialchars($order['shipping_city']); ?><br>
                        <?php echo htmlspecialchars($order['shipping_address_line1']); ?>
                        <?php if (!empty($order['shipping_address_line2'])): ?>
                        <br><?php echo htmlspecialchars($order['shipping_address_line2']); ?>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            
            <!-- Itens do Pedido -->
            <div class="detail-section">
                <div class="detail-title">📦 Itens do Pedido</div>
                <div class="items-list">
                    <?php foreach ($items as $item): ?>
                    <div class="order-item">
                        <div class="item-info">
                            <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                            <div class="item-meta">
                                Tamanho: <?php echo htmlspecialchars($item['size_code']); ?> | 
                                Quantidade: <?php echo $item['quantity']; ?>
                                <?php if (!empty($item['customization_name']) || !empty($item['customization_number'])): ?>
                                <br>✏️ Personalização: <?php echo htmlspecialchars($item['customization_name']); ?> 
                                #<?php echo htmlspecialchars($item['customization_number']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="item-price">
                            <?php echo formatPrice($item['total_price']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Totais -->
            <div class="totals-section">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span><?php echo formatPrice($order['subtotal']); ?></span>
                </div>
                <div class="total-row">
                    <span>Frete:</span>
                    <span><?php echo $order['shipping_cost'] > 0 ? formatPrice($order['shipping_cost']) : 'GRÁTIS'; ?></span>
                </div>
                <?php if ($order['discount'] > 0): ?>
                <div class="total-row" style="color: #28a745;">
                    <span>Desconto (<?php echo htmlspecialchars($order['coupon_code']); ?>):</span>
                    <span>-<?php echo formatPrice($order['discount']); ?></span>
                </div>
                <?php endif; ?>
                <div class="total-row final">
                    <span>Total:</span>
                    <span><?php echo formatPrice($order['total']); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Informações de Pagamento -->
        <?php if ($order['payment_method'] === 'bank_transfer'): ?>
        <div class="payment-info">
            <h3>🏦 Informações para Transferência Bancária</h3>
            <p style="margin-bottom: 15px;">Por favor, realize a transferência para a conta abaixo:</p>
            
            <div class="bank-details">
                <div class="bank-detail">
                    <span><strong>Banco:</strong></span>
                    <span>MUFG Bank</span>
                </div>
                <div class="bank-detail">
                    <span><strong>Agência:</strong></span>
                    <span>001</span>
                </div>
                <div class="bank-detail">
                    <span><strong>Conta:</strong></span>
                    <span>1234567</span>
                </div>
                <div class="bank-detail">
                    <span><strong>Titular:</strong></span>
                    <span>Mantos Premium KK</span>
                </div>
                <div class="bank-detail">
                    <span><strong>Valor:</strong></span>
                    <span style="font-size: 20px; font-weight: bold; color: #2d7a4a;">
                        <?php echo formatPrice($order['total']); ?>
                    </span>
                </div>
            </div>
            
            <p style="margin-top: 15px; font-size: 14px; color: #666;">
                ⚠️ <strong>Importante:</strong> Use o número do pedido como referência na transferência
            </p>
        </div>
        <?php elseif ($order['payment_method'] === 'paypal'): ?>
        <div class="payment-info">
            <h3>💳 Pagamento via PayPal</h3>
            <p>Seu pagamento via PayPal está sendo processado. Você receberá uma confirmação por email em breve.</p>
        </div>
        <?php elseif ($order['payment_method'] === 'paypay'): ?>
        <div class="payment-info">
            <h3>📱 Pagamento via PayPay</h3>
            <p>Seu pagamento via PayPay está sendo processado. Você receberá uma confirmação por email em breve.</p>
        </div>
        <?php endif; ?>
        
        <!-- Próximos Passos -->
        <div class="detail-section">
            <div class="detail-title">📋 Próximos Passos</div>
            <ol style="text-align: left; line-height: 2; color: #666;">
                <li>Você receberá um email de confirmação em breve</li>
                <li>Acompanhe o status do seu pedido na área "Meus Pedidos"</li>
                <?php if ($order['payment_method'] === 'bank_transfer'): ?>
                <li>Após confirmarmos o pagamento, seu pedido será enviado</li>
                <?php else: ?>
                <li>Seu pedido será processado assim que o pagamento for confirmado</li>
                <?php endif; ?>
                <li>Prazo de entrega: 5-10 dias úteis</li>
            </ol>
        </div>
        
        <!-- Botões de Ação -->
        <div class="action-buttons">
            <a href="account/orders.php" class="btn btn-primary">
                📦 Ver Meus Pedidos
            </a>
            <a href="index.php" class="btn btn-secondary">
                🏠 Voltar para Home
            </a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
