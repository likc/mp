<?php
require_once '../config/config.php';

require_once '../includes/functions.php';

requireLogin();

$pageTitle = 'Pagamento PIX';
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

// Gerar código PIX (simplificado - em produção use uma API real)
$pixCode = "00020126580014BR.GOV.BCB.PIX0136" . PIX_KEY . "520400005303986540" . number_format($order['total'], 2, '', '') . "5802BR5925" . substr(PIX_HOLDER, 0, 25) . "6009SAO PAULO62070503***6304";

// Confirmar pagamento PIX
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_pix'])) {
    $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'pending', order_status = 'pending' WHERE id = ?");
    $stmt->execute([$order['id']]);
    
    setFlashMessage('Aguardando confirmação do pagamento PIX!', 'success');
    redirect('/order-success.php?order=' . $orderNumber);
}

include '../includes/header.php';
?>

<section class="section">
    <div class="container" style="max-width: 700px;">
        <div style="background: white; padding: 50px; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
            <div style="text-align: center; margin-bottom: 40px;">
                <div style="font-size: 60px; margin-bottom: 20px;">📱</div>
                <h1 style="font-size: 36px; margin-bottom: 10px;">Pagamento via PIX</h1>
                <p style="color: #666;">Pedido #<?php echo htmlspecialchars($orderNumber); ?></p>
            </div>
            
            <!-- Valor a Pagar -->
            <div style="background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%); padding: 30px; border-radius: 15px; margin-bottom: 30px; text-align: center;">
                <div style="font-size: 16px; color: #666; margin-bottom: 10px;">Valor a Pagar:</div>
                <div style="font-size: 42px; font-weight: 900; color: var(--primary-green);">
                    <?php echo formatPrice($order['total']); ?>
                </div>
            </div>
            
            <!-- QR Code PIX (simulado) -->
            <div style="background: #f9f9f9; padding: 30px; border-radius: 15px; margin-bottom: 30px; text-align: center;">
                <h3 style="margin-bottom: 20px;">Escaneie o QR Code</h3>
                <div style="background: white; padding: 20px; border-radius: 10px; display: inline-block;">
                    <!-- Em produção, use uma biblioteca para gerar QR Code real -->
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=<?php echo urlencode($pixCode); ?>" 
                         alt="QR Code PIX" 
                         style="width: 250px; height: 250px;">
                </div>
                <p style="color: #666; margin-top: 15px; font-size: 14px;">
                    Abra o app do seu banco e escaneie o código
                </p>
            </div>
            
            <!-- Chave PIX -->
            <div style="background: #FFF3CD; padding: 25px; border-radius: 15px; margin-bottom: 30px;">
                <h3 style="color: #D97706; margin-bottom: 15px; text-align: center;">
                    Ou use a Chave PIX
                </h3>
                <div style="background: white; padding: 20px; border-radius: 10px; text-align: center; margin-bottom: 15px;">
                    <div style="font-size: 14px; color: #666; margin-bottom: 5px;">
                        Tipo: <?php echo strtoupper(PIX_KEY_TYPE); ?>
                    </div>
                    <div style="font-size: 24px; font-weight: 700; color: var(--primary-green); word-break: break-all;">
                        <?php echo PIX_KEY; ?>
                    </div>
                </div>
                <button onclick="copyPixKey()" class="btn btn-secondary" style="width: 100%;">
                    📋 Copiar Chave PIX
                </button>
            </div>
            
            <!-- PIX Copia e Cola -->
            <div style="background: #E3F2FD; padding: 25px; border-radius: 15px; margin-bottom: 30px;">
                <h3 style="color: #1976D2; margin-bottom: 15px; text-align: center;">
                    PIX Copia e Cola
                </h3>
                <textarea readonly onclick="this.select()" 
                          style="width: 100%; padding: 15px; border: 2px solid #1976D2; border-radius: 8px; font-family: monospace; font-size: 12px; height: 100px; resize: none;"><?php echo $pixCode; ?></textarea>
                <button onclick="copyPixCode()" class="btn btn-primary" style="width: 100%; margin-top: 15px;">
                    📋 Copiar Código PIX
                </button>
            </div>
            
            <!-- Instruções -->
            <div style="background: #f9f9f9; padding: 25px; border-radius: 15px; margin-bottom: 30px;">
                <h3 style="margin-bottom: 15px;">📌 Como pagar com PIX</h3>
                <ol style="color: #666; line-height: 2; padding-left: 20px;">
                    <li>Abra o app do seu banco</li>
                    <li>Escolha PIX e depois "Ler QR Code" ou "PIX Copia e Cola"</li>
                    <li>Escaneie o QR Code ou cole o código</li>
                    <li>Confirme o pagamento</li>
                    <li>O pagamento é processado em segundos!</li>
                </ol>
            </div>
            
            <!-- Prazo -->
            <div style="background: #FFEBEE; padding: 20px; border-radius: 10px; margin-bottom: 30px; text-align: center;">
                <p style="color: #C62828; font-weight: 600;">
                    ⏰ Este PIX expira em 30 minutos
                </p>
            </div>
            
            <!-- Botão Confirmar -->
            <form method="POST" action="">
                <button type="submit" name="confirm_pix" class="btn btn-primary" style="width: 100%; font-size: 18px;">
                    ✓ Já Paguei via PIX
                </button>
            </form>
            
            <!-- Link para voltar -->
            <div style="text-align: center; margin-top: 30px;">
                <a href="/checkout.php" style="color: var(--text-light); text-decoration: none;">
                    ← Escolher outra forma de pagamento
                </a>
            </div>
        </div>
    </div>
</section>

<script>
function copyPixKey() {
    const pixKey = '<?php echo PIX_KEY; ?>';
    navigator.clipboard.writeText(pixKey).then(function() {
        alert('✅ Chave PIX copiada!\nCole no app do seu banco.');
    });
}

function copyPixCode() {
    const pixCode = '<?php echo $pixCode; ?>';
    navigator.clipboard.writeText(pixCode).then(function() {
        alert('✅ Código PIX copiado!\nCole no app do seu banco (PIX Copia e Cola).');
    });
}
</script>

<?php include '../includes/footer.php'; ?>
