<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/mailgun.php';

requireLogin();

$pageTitle = 'Finalizar Compra';

// Verificar se o carrinho não está vazio
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    redirect('/cart.php');
}

// Calcular valores
$subtotal = getCartTotal();
$discount = calculateDiscount($subtotal);
$itemCount = getCartItemCount();
$shipping = calculateShipping($subtotal - $discount, $itemCount);
$total = $subtotal - $discount + $shipping;

// Buscar endereços do usuário
$stmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC");
$stmt->execute([$_SESSION['user_id']]);
$addresses = $stmt->fetchAll();

$error = '';

// Processar o pedido
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_order'])) {
    $addressId = intval($_POST['address_id'] ?? 0);
    $paymentMethod = sanitize($_POST['payment_method'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    
    // Validações
    if ($addressId == 0) {
        $error = 'Selecione um endereço de entrega';
    } elseif (empty($paymentMethod)) {
        $error = 'Selecione uma forma de pagamento';
    } else {
        try {
            // Buscar endereço
            $stmt = $pdo->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ?");
            $stmt->execute([$addressId, $_SESSION['user_id']]);
            $address = $stmt->fetch();
            
            if (!$address) {
                $error = 'Endereço inválido';
            } else {
                // Iniciar transação
                $pdo->beginTransaction();
                
                // Criar pedido
                $orderNumber = generateOrderNumber();
                $shippingAddress = "{$address['street']}, {$address['number']}\n";
                if ($address['complement']) $shippingAddress .= "{$address['complement']}\n";
                $shippingAddress .= "{$address['neighborhood']} - {$address['city']}/{$address['state']}\n";
                $shippingAddress .= "CEP: {$address['zipcode']}";
                
                $couponCode = isset($_SESSION['coupon']) ? $_SESSION['coupon']['code'] : null;
                
                $stmt = $pdo->prepare("
                    INSERT INTO orders (
                        user_id, order_number, subtotal, shipping_cost, discount, total,
                        coupon_code, payment_method, shipping_address, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $_SESSION['user_id'],
                    $orderNumber,
                    $subtotal,
                    $shipping,
                    $discount,
                    $total,
                    $couponCode,
                    $paymentMethod,
                    $shippingAddress,
                    $notes
                ]);
                
                $orderId = $pdo->lastInsertId();
                
                // Adicionar itens do pedido
                foreach ($_SESSION['cart'] as $item) {
                    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                    $stmt->execute([$item['product_id']]);
                    $product = $stmt->fetch();
                    
                    if ($product) {
                        $stmt = $pdo->prepare("
                            INSERT INTO order_items (
                                order_id, product_id, product_name, size, quantity, price
                            ) VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        
                        $stmt->execute([
                            $orderId,
                            $product['id'],
                            $product['name'],
                            $item['size'],
                            $item['quantity'],
                            $product['price']
                        ]);
                        
                        // Atualizar estoque
                        $stmt = $pdo->prepare("
                            UPDATE products 
                            SET stock = stock - ? 
                            WHERE id = ?
                        ");
                        $stmt->execute([$item['quantity'], $product['id']]);
                    }
                }
                
                // Atualizar uso do cupom
                if ($couponCode) {
                    $stmt = $pdo->prepare("
                        UPDATE coupons 
                        SET used_count = used_count + 1 
                        WHERE code = ?
                    ");
                    $stmt->execute([$couponCode]);
                }
                
                // Commit da transação
                $pdo->commit();
                
                // Enviar email de confirmação
                sendOrderConfirmationEmail($orderId);
                
                // Limpar carrinho e cupom
                clearCart();
                unset($_SESSION['coupon']);
                
                // Redirecionar para página de pagamento apropriada
                switch ($paymentMethod) {
                    case 'paypal':
                        redirect('/payment/paypal.php?order=' . $orderNumber);
                        break;
                    case 'skrill':
                        redirect('/payment/skrill.php?order=' . $orderNumber);
                        break;
                    case 'credit_card':
                        redirect('/payment/credit-card.php?order=' . $orderNumber);
                        break;
                    case 'pix':
                        redirect('/payment/pix.php?order=' . $orderNumber);
                        break;
                    case 'boleto':
                        redirect('/payment/boleto.php?order=' . $orderNumber);
                        break;
                    case 'bank_transfer':
                        redirect('/payment/bank-transfer.php?order=' . $orderNumber);
                        break;
                    default:
                        setFlashMessage('Pedido realizado com sucesso! Número do pedido: ' . $orderNumber, 'success');
                        redirect('/order-success.php?order=' . $orderNumber);
                }
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Erro ao processar pedido. Tente novamente.';
            logError('Erro no checkout: ' . $e->getMessage());
        }
    }
}

include 'includes/header.php';
?>

<section class="section">
    <div class="container">
        <h1 class="section-title">🛒 Finalizar Compra</h1>
        
        <?php if ($error): ?>
            <div class="flash-message flash-error" style="position: static; margin-bottom: 20px;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" data-validate>
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
                <!-- Formulário de Checkout -->
                <div>
                    <!-- Endereço de Entrega -->
                    <div style="background: white; padding: 30px; border-radius: 15px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                        <h2 style="margin-bottom: 25px;">📍 Endereço de Entrega</h2>
                        
                        <?php if (empty($addresses)): ?>
                            <p style="color: #666; margin-bottom: 20px;">
                                Você ainda não tem endereços cadastrados.
                            </p>
                            <a href="account/addresses.php" class="btn btn-primary btn-small">
                                Adicionar Endereço
                            </a>
                        <?php else: ?>
                            <?php foreach ($addresses as $addr): ?>
                                <label style="display: block; padding: 20px; border: 2px solid var(--border); border-radius: 10px; margin-bottom: 15px; cursor: pointer; transition: all 0.3s;">
                                    <input type="radio" name="address_id" value="<?php echo $addr['id']; ?>" 
                                           <?php echo $addr['is_default'] ? 'checked' : ''; ?> required>
                                    <div style="margin-left: 30px;">
                                        <strong><?php echo htmlspecialchars($addr['street']); ?>, <?php echo htmlspecialchars($addr['number']); ?></strong>
                                        <?php if ($addr['complement']): ?>
                                            <br><?php echo htmlspecialchars($addr['complement']); ?>
                                        <?php endif; ?>
                                        <br><?php echo htmlspecialchars($addr['neighborhood']); ?>
                                        <br><?php echo htmlspecialchars($addr['city']); ?>/<?php echo $addr['state']; ?>
                                        <br>CEP: <?php echo htmlspecialchars($addr['zipcode']); ?>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                            
                            <a href="account/addresses.php" style="color: var(--primary-green); font-size: 14px;">
                                + Adicionar novo endereço
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Forma de Pagamento -->
                    <div style="background: white; padding: 30px; border-radius: 15px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                        <h2 style="margin-bottom: 25px;">💳 Forma de Pagamento</h2>
                        
                        <!-- PayPal -->
                        <label style="display: block; padding: 20px; border: 2px solid var(--border); border-radius: 10px; margin-bottom: 15px; cursor: pointer; transition: all 0.3s;">
                            <input type="radio" name="payment_method" value="paypal" required>
                            <span style="margin-left: 10px; font-weight: 600;">💙 PayPal</span>
                            <p style="margin-left: 30px; margin-top: 5px; color: #666; font-size: 14px;">
                                Pagamento seguro com PayPal - Aceita cartões e saldo
                            </p>
                        </label>
                        
                        <!-- Skrill -->
                        <label style="display: block; padding: 20px; border: 2px solid var(--border); border-radius: 10px; margin-bottom: 15px; cursor: pointer; transition: all 0.3s;">
                            <input type="radio" name="payment_method" value="skrill" required>
                            <span style="margin-left: 10px; font-weight: 600;">💜 Skrill</span>
                            <p style="margin-left: 30px; margin-top: 5px; color: #666; font-size: 14px;">
                                Pagamento internacional com Skrill
                            </p>
                        </label>
                        
                        <!-- Cartão de Crédito -->
                        <label style="display: block; padding: 20px; border: 2px solid var(--border); border-radius: 10px; margin-bottom: 15px; cursor: pointer; transition: all 0.3s;">
                            <input type="radio" name="payment_method" value="credit_card" required>
                            <span style="margin-left: 10px; font-weight: 600;">💳 Cartão de Crédito</span>
                            <p style="margin-left: 30px; margin-top: 5px; color: #666; font-size: 14px;">
                                Pagamento direto com cartão de crédito
                            </p>
                        </label>
                        
                        <!-- PIX -->
                        <label style="display: block; padding: 20px; border: 2px solid var(--border); border-radius: 10px; margin-bottom: 15px; cursor: pointer; transition: all 0.3s;">
                            <input type="radio" name="payment_method" value="pix" required>
                            <span style="margin-left: 10px; font-weight: 600;">📱 PIX</span>
                            <p style="margin-left: 30px; margin-top: 5px; color: #666; font-size: 14px;">
                                Aprovação imediata - QR Code ou Pix Copia e Cola
                            </p>
                        </label>
                        
                        <!-- Boleto -->
                        <label style="display: block; padding: 20px; border: 2px solid var(--border); border-radius: 10px; margin-bottom: 15px; cursor: pointer; transition: all 0.3s;">
                            <input type="radio" name="payment_method" value="boleto" required>
                            <span style="margin-left: 10px; font-weight: 600;">🧾 Boleto Bancário</span>
                            <p style="margin-left: 30px; margin-top: 5px; color: #666; font-size: 14px;">
                                Pagamento em até 3 dias úteis
                            </p>
                        </label>
                        
                        <!-- Depósito Bancário -->
                        <label style="display: block; padding: 20px; border: 2px solid var(--border); border-radius: 10px; cursor: pointer; transition: all 0.3s;">
                            <input type="radio" name="payment_method" value="bank_transfer" required>
                            <span style="margin-left: 10px; font-weight: 600;">🏦 Depósito/Transferência Bancária</span>
                            <p style="margin-left: 30px; margin-top: 5px; color: #666; font-size: 14px;">
                                Transfira diretamente para nossa conta bancária
                            </p>
                        </label>
                    </div>
                    
                    <!-- Observações -->
                    <div style="background: white; padding: 30px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                        <h2 style="margin-bottom: 25px;">📝 Observações (Opcional)</h2>
                        <textarea name="notes" rows="4" placeholder="Alguma observação sobre o pedido?"
                                  style="width: 100%; padding: 15px; border: 2px solid var(--border); border-radius: 8px; resize: vertical;"></textarea>
                    </div>
                </div>
                
                <!-- Resumo do Pedido -->
                <div>
                    <div class="cart-summary" style="position: sticky; top: 100px;">
                        <h2 style="margin-bottom: 30px;">Resumo do Pedido</h2>
                        
                        <?php
                        $cartItems = [];
                        foreach ($_SESSION['cart'] as $item) {
                            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                            $stmt->execute([$item['product_id']]);
                            $product = $stmt->fetch();
                            if ($product) {
                                $cartItems[] = [
                                    'product' => $product,
                                    'quantity' => $item['quantity'],
                                    'size' => $item['size']
                                ];
                            }
                        }
                        ?>
                        
                        <div style="max-height: 300px; overflow-y: auto; margin-bottom: 20px;">
                            <?php foreach ($cartItems as $item): ?>
                                <div style="padding: 15px 0; border-bottom: 1px solid var(--border);">
                                    <strong><?php echo htmlspecialchars($item['product']['name']); ?></strong>
                                    <div style="font-size: 14px; color: #666;">
                                        Tamanho: <?php echo $item['size']; ?> | Qtd: <?php echo $item['quantity']; ?>
                                    </div>
                                    <div style="font-weight: 600; color: var(--primary-green);">
                                        <?php echo formatPrice($item['product']['price'] * $item['quantity']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
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
                        
                        <button type="submit" name="place_order" class="btn btn-primary" style="width: 100%; margin-top: 30px;">
                            Finalizar Pedido
                        </button>
                        
                        <a href="cart.php" style="display: block; text-align: center; margin-top: 15px; color: var(--text-light); text-decoration: none;">
                            ← Voltar ao Carrinho
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>

<style>
input[type="radio"]:checked + span,
input[type="radio"]:checked + div {
    color: var(--primary-green);
}
label:has(input[type="radio"]:checked) {
    border-color: var(--primary-green);
    background: #f0f9f4;
}
</style>

<?php include 'includes/footer.php'; ?>
