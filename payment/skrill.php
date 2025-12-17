<?php
require_once '../config/config.php';

require_once '../includes/functions.php';

requireLogin();

$pageTitle = 'Pagamento Skrill';
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

// Buscar dados do usuário
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Gerar ID único para a transação
$transactionId = 'MP' . $order['id'] . time();

include '../includes/header.php';
?>

<section class="section">
    <div class="container" style="max-width: 800px;">
        <div style="background: white; padding: 50px; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
            <div style="text-align: center; margin-bottom: 40px;">
                <div style="font-size: 60px; margin-bottom: 20px;">💜</div>
                <h1 style="font-size: 36px; margin-bottom: 10px;">Pagamento via Skrill</h1>
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
            
            <!-- Instruções Skrill -->
            <div style="background: #F5E6FF; padding: 25px; border-radius: 15px; margin-bottom: 30px;">
                <h3 style="color: #862B9F; margin-bottom: 15px;">📌 Como pagar com Skrill</h3>
                <ol style="color: #666; line-height: 2;">
                    <li>Clique no botão "Pagar com Skrill" abaixo</li>
                    <li>Você será redirecionado para o site seguro do Skrill</li>
                    <li>Faça login na sua conta Skrill</li>
                    <li>Confirme o pagamento</li>
                    <li>Você será redirecionado de volta automaticamente</li>
                </ol>
            </div>
            
            <!-- Formulário Skrill -->
            <form action="<?php echo SKRILL_PAY_URL; ?>" method="POST" id="skrillForm">
                <!-- Informações do Comerciante -->
                <input type="hidden" name="pay_to_email" value="<?php echo SKRILL_EMAIL; ?>">
                <input type="hidden" name="merchant_id" value="<?php echo SKRILL_MERCHANT_ID; ?>">
                
                <!-- Informações da Transação -->
                <input type="hidden" name="transaction_id" value="<?php echo $transactionId; ?>">
                <input type="hidden" name="amount" value="<?php echo number_format($order['total'], 2, '.', ''); ?>">
                <input type="hidden" name="currency" value="BRL">
                
                <!-- Informações do Pedido -->
                <input type="hidden" name="detail1_description" value="Pedido">
                <input type="hidden" name="detail1_text" value="<?php echo $orderNumber; ?>">
                
                <!-- Informações do Cliente -->
                <input type="hidden" name="pay_from_email" value="<?php echo $user['email']; ?>">
                <input type="hidden" name="firstname" value="<?php echo htmlspecialchars($user['name']); ?>">
                
                <!-- URLs de Retorno -->
                <input type="hidden" name="return_url" value="<?php echo SITE_URL; ?>/payment/skrill-success.php?order=<?php echo $orderNumber; ?>">
                <input type="hidden" name="cancel_url" value="<?php echo SITE_URL; ?>/payment/skrill-cancel.php?order=<?php echo $orderNumber; ?>">
                <input type="hidden" name="status_url" value="<?php echo SITE_URL; ?>/payment/skrill-callback.php">
                
                <!-- Idioma -->
                <input type="hidden" name="language" value="PT">
                
                <!-- Logo -->
                <input type="hidden" name="logo_url" value="<?php echo SITE_URL; ?>assets/images/logo.png">
                
                <div style="text-align: center;">
                    <button type="submit" class="btn btn-primary" style="font-size: 18px; padding: 15px 50px;">
                        💜 Pagar com Skrill
                    </button>
                </div>
            </form>
            
            <!-- Informações de Segurança -->
            <div style="text-align: center; color: #666; font-size: 14px; margin-top: 30px;">
                <p>🔒 Pagamento 100% seguro processado pelo Skrill</p>
                <p style="margin-top: 10px;">Aceita cartões e transferências internacionais</p>
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

<?php include '../includes/footer.php'; ?>
