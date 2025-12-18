<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/settings.php';

if (!isset($_SESSION['user_id'])) {
    redirect('../login.php');
}

$orderId = intval($_GET['order'] ?? 0);
if ($orderId <= 0) {
    redirect('../index.php');
}

$stmt = $pdo->prepare("SELECT o.* FROM orders o WHERE o.id = ? AND o.user_id = ?");
$stmt->execute([$orderId, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    redirect('../index.php');
}

if ($order['payment_status'] === 'paid') {
    redirect('../order-success.php?order=' . $order['order_number']);
}

$paypalClientId = getSetting('paypal_client_id');
$pageTitle = 'Pagamento PayPal - Mantos Premium';
?>

<style>
/* Reset de container para alinhar com o checkout.php */
.checkout-wrapper {
    max-width: 1200px;
    margin: 40px auto;
    padding: 0 20px;
    font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
}

.checkout-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 30px;
}

/* Coluna Principal (Esquerda) */
.payment-section {
    flex: 1;
    min-width: 320px;
    background: #fff;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
    text-align: center;
}

/* Sidebar (Direita) */
.order-sidebar {
    width: 380px;
}

@media (max-width: 992px) {
    .order-sidebar { width: 100%; order: -1; }
}

.summary-card {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
}

.summary-title {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 24px;
    border-bottom: 2px solid #f1f1f1;
    padding-bottom: 15px;
    margin-bottom: 20px;
    color: var(--primary-green);
}

.summary-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
    font-size: 15px;
}

.summary-total {
    display: flex;
    justify-content: space-between;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 2px solid #f1f1f1;
    font-weight: bold;
    font-size: 20px;
    color: var(--primary-green);
}

.paypal-box {
    margin: 30px 0;
    padding: 20px;
    border: 1px solid #eee;
    border-radius: 10px;
    background: #fafafa;
}

.security-tag {
    display: inline-block;
    background: #e8f5e9;
    color: #2e7d32;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    margin-top: 20px;
}
</style>

<div class="checkout-wrapper">
    <div class="checkout-grid">
        
        <div class="payment-section">
            <h2 style="font-family: 'Bebas Neue', sans-serif; font-size: 32px; color: #333;">FINALIZAR PAGAMENTO</h2>
            <p style="color: #666;">Clique no botão abaixo para pagar via PayPal ou Cartão de Crédito.</p>
            
            <div class="paypal-box">
                <div id="paypal-button-container"></div>
            </div>

            <div class="security-tag">🔒 AMBIENTE 100% SEGURO</div>
            
            <p style="margin-top: 30px;">
                <a href="../cart.php" style="text-decoration: none; color: #888; font-size: 14px;">
                    ← Voltar para o carrinho
                </a>
            </p>
        </div>

        <div class="order-sidebar">
            <div class="summary-card">
                <h3 class="summary-title">Resumo do Pedido</h3>
                
                <div class="summary-item">
                    <span>Nº do Pedido:</span>
                    <strong>#<?php echo $order['order_number']; ?></strong>
                </div>

                <div class="summary-item">
                    <span>Subtotal:</span>
                    <span><?php echo formatPrice($order['subtotal']); ?></span>
                </div>

                <div class="summary-item">
                    <span>Frete:</span>
                    <span><?php echo $order['shipping_cost'] > 0 ? formatPrice($order['shipping_cost']) : 'Grátis'; ?></span>
                </div>

                <?php if ($order['discount'] > 0): ?>
                <div class="summary-item" style="color: #d32f2f;">
                    <span>Desconto:</span>
                    <span>-<?php echo formatPrice($order['discount']); ?></span>
                </div>
                <?php endif; ?>

                <div class="summary-total">
                    <span>Total:</span>
                    <span><?php echo formatPrice($order['total']); ?></span>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://www.paypal.com/sdk/js?client-id=<?php echo $paypalClientId; ?>&currency=JPY&locale=ja_JP"></script>

<script>
paypal.Buttons({
    style: { layout: 'vertical', color: 'blue', shape: 'rect', label: 'paypal' },
    createOrder: function(data, actions) {
        return actions.order.create({
            purchase_units: [{
                reference_id: '<?php echo $order['order_number']; ?>',
                amount: {
                    currency_code: 'JPY',
                    value: '<?php echo number_format($order['total'], 0, '.', ''); ?>'
                }
            }]
        });
    },
    onApprove: function(data, actions) {
        return actions.order.capture().then(function(details) {
            document.querySelector('.payment-section').innerHTML = `
                <div style="padding: 50px 0;">
                    <div style="width: 50px; height: 50px; border: 5px solid #f3f3f3; border-top: 5px solid #2d7a4a; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
                    <h3>Validando Pagamento...</h3>
                    <p>Aguarde um instante.</p>
                </div>
                <style>@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>
            `;
            
            fetch('process-paypal.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    order_id: <?php echo $orderId; ?>,
                    paypal_order_id: data.orderID,
                    payer: details.payer,
                    payment_details: details
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = '../order-success.php?order=<?php echo $order['order_number']; ?>';
                } else {
                    alert('Erro: ' + data.message);
                    location.reload();
                }
            });
        });
    }
}).render('#paypal-button-container');
</script>