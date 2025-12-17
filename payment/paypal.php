<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();

$pageTitle = 'Pagamento PayPal';
$orderNumber = $_GET['order'] ?? '';

if (empty($orderNumber)) {
    redirect('/index.php');
}

// Buscar pedido
$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? AND user_id = ?");
$stmt->execute([$orderNumber, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    redirect('/index.php');
}

// Processar retorno do PayPal
if (isset($_GET['paymentId']) && isset($_GET['PayerID'])) {
    // Aqui você deve validar o pagamento com a API do PayPal
    // Por enquanto, apenas marcamos como pago
    
    $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'paid', order_status = 'processing' WHERE id = ?");
    $stmt->execute([$order['id']]);
    
    setFlashMessage('Pagamento realizado com sucesso via PayPal!', 'success');
    redirect('/order-success.php?order=' . $orderNumber);
}

include '../includes/header.php';
?>

<section class="section">
    <div class="container" style="max-width: 800px;">
        <div style="background: white; padding: 50px; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
            <div style="text-align: center; margin-bottom: 40px;">
                <div style="font-size: 60px; margin-bottom: 20px;">💙</div>
                <h1 style="font-size: 36px; margin-bottom: 10px;">Pagamento via PayPal</h1>
                <p style="color: #666;">Pedido #<?php echo htmlspecialchars($orderNumber); ?></p>
            </div>
            
            <!-- Resumo do Pedido -->
            <div style="background: #f9f9f9; padding: 25px; border-radius: 15px; margin-bottom: 30px;">
                <h3 style="margin-bottom: 20px;">Resumo do Pedido</h3>
                <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                    <span>Subtotal:</span>
                    <strong><?php echo formatPrice($order['subtotal']); ?></strong>
                </div>
                <?php if ($order['discount'] > 0): ?>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 15px; color: var(--primary-green);">
                        <span>Desconto:</span>
                        <strong>-<?php echo formatPrice($order['discount']); ?></strong>
                    </div>
                <?php endif; ?>
                <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                    <span>Frete:</span>
                    <strong><?php echo $order['shipping_cost'] > 0 ? formatPrice($order['shipping_cost']) : 'GRÁTIS'; ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; padding-top: 15px; border-top: 2px solid var(--border); font-size: 20px;">
                    <span><strong>Total:</strong></span>
                    <strong style="color: var(--primary-green);"><?php echo formatPrice($order['total']); ?></strong>
                </div>
            </div>
            
            <!-- Instruções PayPal -->
            <div style="background: #E8F4F8; padding: 25px; border-radius: 15px; margin-bottom: 30px;">
                <h3 style="color: #0070BA; margin-bottom: 15px;">📌 Como pagar com PayPal</h3>
                <ol style="color: #666; line-height: 2;">
                    <li>Clique no botão "Pagar com PayPal" abaixo</li>
                    <li>Você será redirecionado para o site seguro do PayPal</li>
                    <li>Faça login na sua conta PayPal ou pague como visitante</li>
                    <li>Confirme o pagamento</li>
                    <li>Você será redirecionado de volta para confirmação</li>
                </ol>
            </div>
            
            <!-- Botão PayPal -->
            <div id="paypal-button-container" style="margin-bottom: 30px;"></div>
            
            <!-- Informações de Segurança -->
            <div style="text-align: center; color: #666; font-size: 14px;">
                <p>🔒 Pagamento 100% seguro processado pelo PayPal</p>
                <p style="margin-top: 10px;">Suas informações financeiras estão protegidas</p>
            </div>
            
            <!-- Link para voltar -->
            <div style="text-align: center; margin-top: 30px;">
                <a href="checkout.php" style="color: var(--text-light); text-decoration: none;">
                    ← Escolher outra forma de pagamento
                </a>
            </div>
        </div>
    </div>
</section>

<!-- SDK do PayPal -->
<script src="https://www.paypal.com/sdk/js?client-id=<?php echo PAYPAL_CLIENT_ID; ?>&currency=BRL"></script>

<script>
paypal.Buttons({
    createOrder: function(data, actions) {
        return actions.order.create({
            purchase_units: [{
                description: 'Pedido #<?php echo $orderNumber; ?> - Mantos Premium',
                amount: {
                    currency_code: 'BRL',
                    value: '<?php echo number_format($order['total'], 2, '.', ''); ?>'
                }
            }]
        });
    },
    onApprove: function(data, actions) {
        return actions.order.capture().then(function(details) {
            // Pagamento aprovado - redirecionar
            window.location.href = '/payment/paypal.php?order=<?php echo $orderNumber; ?>&paymentId=' + data.orderID + '&PayerID=' + data.payerID;
        });
    },
    onError: function(err) {
        alert('Erro ao processar pagamento. Por favor, tente novamente.');
        console.error(err);
    }
}).render('#paypal-button-container');
</script>

<style>
#paypal-button-container {
    min-height: 150px;
}
</style>

<?php include '../includes/footer.php'; ?>
