<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();

$pageTitle = 'Pagamento Boleto';
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

// Gerar linha digitável do boleto (simulado)
$linhaDigitavel = "34191.79001 01043.510047 91020.150008 1 " . date('ymd') . str_pad($order['total'] * 100, 10, '0', STR_PAD_LEFT);
$codigoBarras = str_replace(['.', ' '], '', $linhaDigitavel);

include '../includes/header.php';
?>

<section class="section">
    <div class="container" style="max-width: 800px;">
        <div style="background: white; padding: 50px; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
            <div style="text-align: center; margin-bottom: 40px;">
                <div style="font-size: 60px; margin-bottom: 20px;">🧾</div>
                <h1 style="font-size: 36px; margin-bottom: 10px;">Pagamento via Boleto</h1>
                <p style="color: #666;">Pedido #<?php echo htmlspecialchars($orderNumber); ?></p>
            </div>
            
            <!-- Valor do Boleto -->
            <div style="background: linear-gradient(135deg, #FFF9E6 0%, #FFE9B3 100%); padding: 30px; border-radius: 15px; margin-bottom: 30px; text-align: center;">
                <div style="font-size: 16px; color: #666; margin-bottom: 10px;">Valor do Boleto:</div>
                <div style="font-size: 42px; font-weight: 900; color: #D97706;">
                    <?php echo formatPrice($order['total']); ?>
                </div>
                <div style="margin-top: 15px; font-size: 14px; color: #666;">
                    Vencimento: <strong><?php echo date('d/m/Y', strtotime('+3 days')); ?></strong>
                </div>
            </div>
            
            <!-- Código de Barras -->
            <div style="background: #f9f9f9; padding: 30px; border-radius: 15px; margin-bottom: 30px;">
                <h3 style="margin-bottom: 20px; text-align: center;">Código de Barras</h3>
                <div style="text-align: center; margin-bottom: 20px;">
                    <!-- Simulação de código de barras -->
                    <div style="display: flex; justify-content: center; height: 80px; align-items: flex-end; gap: 2px;">
                        <?php for($i = 0; $i < 50; $i++): ?>
                            <div style="width: 3px; height: <?php echo rand(30, 80); ?>px; background: #000;"></div>
                        <?php endfor; ?>
                    </div>
                </div>
                <div style="text-align: center; font-family: monospace; font-size: 16px; color: #666;">
                    <?php echo $codigoBarras; ?>
                </div>
            </div>
            
            <!-- Linha Digitável -->
            <div style="background: #E8F5E9; padding: 25px; border-radius: 15px; margin-bottom: 30px;">
                <h3 style="color: var(--primary-green); margin-bottom: 15px; text-align: center;">
                    Linha Digitável
                </h3>
                <div style="background: white; padding: 20px; border-radius: 10px; text-align: center; margin-bottom: 15px;">
                    <div style="font-size: 20px; font-weight: 700; color: var(--text-dark); font-family: monospace; word-spacing: 10px;">
                        <?php echo $linhaDigitavel; ?>
                    </div>
                </div>
                <button onclick="copyBoleto()" class="btn btn-primary" style="width: 100%;">
                    📋 Copiar Linha Digitável
                </button>
            </div>
            
            <!-- Instruções -->
            <div style="background: #FFF3CD; padding: 25px; border-radius: 15px; margin-bottom: 30px;">
                <h3 style="color: #D97706; margin-bottom: 15px;">📌 Como pagar o boleto</h3>
                <ol style="color: #666; line-height: 2; padding-left: 20px;">
                    <li><strong>Imprima o boleto</strong> clicando no botão abaixo, ou</li>
                    <li><strong>Copie a linha digitável</strong> e pague pelo internet banking</li>
                    <li><strong>Pague em qualquer banco</strong>, lotérica ou app de pagamento</li>
                    <li>O pagamento é confirmado em <strong>até 3 dias úteis</strong></li>
                    <li>Após a confirmação, seu pedido será processado</li>
                </ol>
            </div>
            
            <!-- Prazo -->
            <div style="background: #FFEBEE; padding: 20px; border-radius: 10px; margin-bottom: 30px; text-align: center;">
                <p style="color: #C62828; font-weight: 600;">
                    ⏰ Vencimento: <?php echo date('d/m/Y', strtotime('+3 days')); ?>
                </p>
                <p style="color: #666; font-size: 14px; margin-top: 5px;">
                    Após o vencimento, o boleto não poderá mais ser pago
                </p>
            </div>
            
            <!-- Botões -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 30px;">
                <button onclick="printBoleto()" class="btn btn-primary">
                    🖨️ Imprimir Boleto
                </button>
                <button onclick="downloadBoleto()" class="btn btn-secondary">
                    📥 Baixar PDF
                </button>
            </div>
            
            <!-- Informações -->
            <div style="text-align: center; color: #666; font-size: 14px;">
                <p>💡 Você também receberá este boleto por email</p>
                <p style="margin-top: 10px;">
                    Dúvidas? <strong><?php echo ADMIN_EMAIL; ?></strong>
                </p>
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

<script>
function copyBoleto() {
    const linha = '<?php echo $linhaDigitavel; ?>';
    navigator.clipboard.writeText(linha).then(function() {
        alert('✅ Linha digitável copiada!\nCole no internet banking do seu banco.');
    });
}

function printBoleto() {
    window.print();
}

function downloadBoleto() {
    alert('📥 Em produção, aqui seria gerado um PDF do boleto.\n\nPor enquanto, use a opção de Imprimir e salve como PDF.');
}
</script>

<?php include '../includes/footer.php'; ?>
