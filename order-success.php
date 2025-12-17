<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

requireLogin();

$pageTitle = 'Pedido Realizado';
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

include 'includes/header.php';
?>

<section class="section">
    <div class="container" style="max-width: 800px; text-align: center;">
        <div style="background: white; padding: 60px 40px; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
            <!-- Ícone de Sucesso -->
            <div style="font-size: 80px; margin-bottom: 30px;">
                ✅
            </div>
            
            <h1 style="font-family: 'Bebas Neue', sans-serif; font-size: 48px; color: var(--primary-green); margin-bottom: 20px;">
                PEDIDO REALIZADO!
            </h1>
            
            <p style="font-size: 20px; color: #666; margin-bottom: 40px;">
                Obrigado pela sua compra! Seu pedido foi confirmado com sucesso.
            </p>
            
            <!-- Número do Pedido -->
            <div style="background: #f9f9f9; padding: 30px; border-radius: 15px; margin-bottom: 40px;">
                <p style="font-size: 14px; color: #666; margin-bottom: 10px;">Número do Pedido:</p>
                <p style="font-size: 36px; font-weight: 900; color: var(--primary-green); letter-spacing: 2px;">
                    #<?php echo htmlspecialchars($orderNumber); ?>
                </p>
            </div>
            
            <!-- Informações -->
            <div style="background: linear-gradient(135deg, #f0f9f4 0%, #e8f5e9 100%); padding: 25px; border-radius: 15px; margin-bottom: 40px; text-align: left;">
                <h3 style="font-size: 18px; margin-bottom: 15px; color: var(--primary-green);">
                    📧 Confirmação Enviada
                </h3>
                <p style="color: #666; line-height: 1.8;">
                    Enviamos um email de confirmação para <strong><?php echo $_SESSION['user_email']; ?></strong> 
                    com todos os detalhes do seu pedido.<br><br>
                    Você receberá outro email assim que o pedido for enviado, com o código de rastreamento.
                </p>
            </div>
            
            <!-- Resumo do Pedido -->
            <div style="background: white; border: 2px solid var(--border); padding: 25px; border-radius: 15px; margin-bottom: 40px; text-align: left;">
                <h3 style="font-size: 18px; margin-bottom: 20px;">Resumo do Pedido</h3>
                
                <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border);">
                    <span>Subtotal:</span>
                    <strong><?php echo formatPrice($order['subtotal']); ?></strong>
                </div>
                
                <?php if ($order['discount'] > 0): ?>
                    <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border); color: var(--primary-green);">
                        <span>Desconto:</span>
                        <strong>-<?php echo formatPrice($order['discount']); ?></strong>
                    </div>
                <?php endif; ?>
                
                <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border);">
                    <span>Frete:</span>
                    <strong>
                        <?php if ($order['shipping_cost'] == 0): ?>
                            <span style="color: var(--primary-green);">GRÁTIS 🎉</span>
                        <?php else: ?>
                            <?php echo formatPrice($order['shipping_cost']); ?>
                        <?php endif; ?>
                    </strong>
                </div>
                
                <div style="display: flex; justify-content: space-between; padding: 15px 0; font-size: 20px;">
                    <span><strong>Total:</strong></span>
                    <strong style="color: var(--primary-green);"><?php echo formatPrice($order['total']); ?></strong>
                </div>
            </div>
            
            <!-- Botões -->
            <div style="display: flex; gap: 15px; justify-content: center;">
                <a href="/admin/orders.php" class="btn btn-primary">
                    Ver Meus Pedidos
                </a>
                <a href="/products.php" class="btn btn-secondary">
                    Continuar Comprando
                </a>
            </div>
        </div>
        
        <!-- Informações Adicionais -->
        <div style="margin-top: 40px; padding: 30px; background: #f9f9f9; border-radius: 15px;">
            <h3 style="font-size: 20px; margin-bottom: 20px;">📦 Próximos Passos</h3>
            <div style="text-align: left; max-width: 600px; margin: 0 auto;">
                <p style="color: #666; line-height: 1.8;">
                    1️⃣ <strong>Pagamento:</strong> Complete o pagamento conforme a forma escolhida<br><br>
                    2️⃣ <strong>Processamento:</strong> Assim que confirmarmos o pagamento, começaremos a preparar seu pedido<br><br>
                    3️⃣ <strong>Envio:</strong> Você receberá o código de rastreamento por email<br><br>
                    4️⃣ <strong>Entrega:</strong> Acompanhe sua entrega e aguarde seu pedido chegar!
                </p>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
