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

$paypayMerchantId = getSetting('paypay_merchant_id', '');
$paypayApiKey = getSetting('paypay_api_key', '');
$isConfigured = !empty($paypayMerchantId) && !empty($paypayApiKey);

$pageTitle = 'Pagamento PayPay - Mantos Premium';
?>

<style>
/* USANDO AS CORES DA SUA INDEX.PHP */
:root {
    --primary-green: #2d7a4a;
    --dark-green: #1e5332;
    --gold: #FFD700;
    --bg-light: #f9f9f9;
}

.checkout-wrapper {
    max-width: 1200px;
    margin: 40px auto;
    padding: 0 20px;
}

.checkout-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 30px;
}

/* Coluna da Esquerda (Pagamento) */
.payment-section {
    flex: 1;
    min-width: 320px;
    background: #fff;
    padding: 40px;
    border-radius: 15px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
    text-align: center;
    border-top: 5px solid var(--primary-green); /* Cor da Index */
}

/* Sidebar (Direita - Igual ao Checkout) */
.order-sidebar {
    width: 380px;
}

.summary-card {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
}

.summary-title {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 26px;
    letter-spacing: 1px;
    border-bottom: 2px solid #f1f1f1;
    padding-bottom: 15px;
    margin-bottom: 20px;
    color: var(--primary-green); /* Cor da Index */
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
    font-size: 24px;
    color: var(--primary-green); /* Cor da Index */
}

/* Elementos do PayPay */
.paypay-qr-container {
    background: #fff;
    padding: 20px;
    display: inline-block;
    border: 3px solid var(--gold); /* Destaque em Dourado da Index */
    border-radius: 15px;
    margin: 25px 0;
}

.btn-return {
    display: inline-block;
    margin-top: 30px;
    padding: 10px 20px;
    background: #f1f1f1;
    color: #666;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: 0.3s;
}

.btn-return:hover {
    background: #e5e5e5;
    color: #333;
}

.status-badge {
    background: #e8f5e9;
    color: var(--primary-green);
    padding: 10px 20px;
    border-radius: 30px;
    font-size: 14px;
    font-weight: bold;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

@media (max-width: 992px) {
    .order-sidebar { width: 100%; order: -1; }
}
</style>

<div class="checkout-wrapper">
    <div class="checkout-grid">
        
        <div class="payment-section">
            <h2 style="font-family: 'Bebas Neue', sans-serif; font-size: 36px; color: var(--primary-green); margin-bottom: 10px;">
                PAGAMENTO VIA PAYPAY
            </h2>
            <p style="color: #666;">Abra o seu aplicativo PayPay e escaneie o código abaixo.</p>

            <?php if ($isConfigured): ?>
                <div class="paypay-qr-container">
                    <div id="qr-code"></div>
                </div>

                <div class="status-badge">
                    <div class="spinner-border spinner-border-sm" role="status"></div>
                    Aguardando confirmação do pagamento...
                </div>
            <?php else: ?>
                <div style="padding: 20px; color: #721c24; background: #f8d7da; border-radius: 10px; margin: 20px 0;">
                    Configuração do PayPay não encontrada.
                </div>
            <?php endif; ?>

            <br>
            <a href="../checkout.php" class="btn-return">← Voltar e mudar forma de pagamento</a>
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
                <div class="summary-item" style="color: var(--primary-green); font-weight: bold;">
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

<?php if ($isConfigured): ?>
<script src="https://cdn.rawgit.com/davidshimjs/qrcodejs/gh-pages/qrcode.min.js"></script>
<script>
// Gerar QR Code com a cor dourada da sua marca
new QRCode(document.getElementById("qr-code"), {
    text: '<?php echo $order['order_number']; ?>',
    width: 250,
    height: 250,
    colorDark: "#2d7a4a", // Verde da marca
    colorLight: "#ffffff",
    correctLevel: QRCode.CorrectLevel.H
});

// Verificação de status
setInterval(function() {
    fetch('check-paypay-status.php?order_id=<?php echo $orderId; ?>')
        .then(res => res.json())
        .then(data => {
            if (data.paid) window.location.href = '../order-success.php?order=<?php echo $order['order_number']; ?>';
        });
}, 3000);
</script>
<?php endif; ?>