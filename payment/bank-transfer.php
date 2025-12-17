<?php
require_once '../config/config.php';

require_once '../includes/functions.php';

requireLogin();

$pageTitle = 'Depósito Bancário';
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

// Confirmar depósito
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_transfer'])) {
    // Atualizar status do pedido para aguardando confirmação
    $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'pending', order_status = 'pending' WHERE id = ?");
    $stmt->execute([$order['id']]);
    
    setFlashMessage('Recebemos sua notificação! Confirmaremos o pagamento em até 24 horas.', 'success');
    redirect('/order-success.php?order=' . $orderNumber);
}

include '../includes/header.php';
?>

<section class="section">
    <div class="container" style="max-width: 900px;">
        <div style="background: white; padding: 50px; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
            <div style="text-align: center; margin-bottom: 40px;">
                <div style="font-size: 60px; margin-bottom: 20px;">🏦</div>
                <h1 style="font-size: 36px; margin-bottom: 10px;">Depósito/Transferência Bancária</h1>
                <p style="color: #666;">Pedido #<?php echo htmlspecialchars($orderNumber); ?></p>
            </div>
            
            <!-- Resumo do Pedido -->
            <div style="background: #f9f9f9; padding: 25px; border-radius: 15px; margin-bottom: 30px;">
                <h3 style="margin-bottom: 20px;">Resumo do Pedido</h3>
                <div style="display: flex; justify-content: space-between; padding-top: 15px; border-top: 2px solid var(--border); font-size: 24px;">
                    <span><strong>Valor Total a Depositar:</strong></span>
                    <strong style="color: var(--primary-green);"><?php echo formatPrice($order['total']); ?></strong>
                </div>
            </div>
            
            <!-- Dados Bancários -->
            <div style="background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%); padding: 30px; border-radius: 15px; margin-bottom: 30px; border: 3px solid var(--primary-green);">
                <h3 style="color: var(--primary-green); margin-bottom: 25px; font-size: 24px; text-align: center;">
                    💳 Dados para Depósito/Transferência
                </h3>
                
                <div style="background: white; padding: 25px; border-radius: 10px; margin-bottom: 20px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <label style="font-weight: 600; color: #666; font-size: 14px; display: block; margin-bottom: 5px;">
                                Banco:
                            </label>
                            <div style="font-size: 20px; font-weight: 700; color: var(--text-dark);">
                                <?php echo BANK_NAME; ?>
                            </div>
                        </div>
                        
                        <div>
                            <label style="font-weight: 600; color: #666; font-size: 14px; display: block; margin-bottom: 5px;">
                                Tipo de Conta:
                            </label>
                            <div style="font-size: 20px; font-weight: 700; color: var(--text-dark);">
                                <?php echo BANK_ACCOUNT_TYPE; ?>
                            </div>
                        </div>
                        
                        <div>
                            <label style="font-weight: 600; color: #666; font-size: 14px; display: block; margin-bottom: 5px;">
                                Agência:
                            </label>
                            <div style="font-size: 20px; font-weight: 700; color: var(--text-dark);">
                                <?php echo BANK_BRANCH; ?>
                            </div>
                        </div>
                        
                        <div>
                            <label style="font-weight: 600; color: #666; font-size: 14px; display: block; margin-bottom: 5px;">
                                Conta:
                            </label>
                            <div style="font-size: 20px; font-weight: 700; color: var(--text-dark);">
                                <?php echo BANK_ACCOUNT; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid var(--border);">
                        <label style="font-weight: 600; color: #666; font-size: 14px; display: block; margin-bottom: 5px;">
                            Favorecido:
                        </label>
                        <div style="font-size: 18px; font-weight: 700; color: var(--text-dark);">
                            <?php echo BANK_HOLDER; ?>
                        </div>
                        <div style="font-size: 14px; color: #666; margin-top: 5px;">
                            CNPJ: <?php echo BANK_CPF_CNPJ; ?>
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px; padding: 15px; background: #FFF3CD; border-radius: 8px; border-left: 4px solid #FFD700;">
                        <div style="font-weight: 700; color: #D97706; margin-bottom: 5px;">
                            💰 Valor Exato:
                        </div>
                        <div style="font-size: 28px; font-weight: 900; color: var(--primary-green);">
                            <?php echo formatPrice($order['total']); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Botão de Copiar -->
                <button onclick="copyBankData()" class="btn btn-secondary" style="width: 100%;">
                    📋 Copiar Dados Bancários
                </button>
            </div>
            
            <!-- Instruções -->
            <div style="background: #FFF3CD; padding: 25px; border-radius: 15px; margin-bottom: 30px; border-left: 4px solid #FFD700;">
                <h3 style="color: #D97706; margin-bottom: 15px;">📌 Instruções Importantes</h3>
                <ol style="color: #666; line-height: 2; padding-left: 20px;">
                    <li><strong>Realize o depósito ou transferência</strong> usando os dados bancários acima</li>
                    <li><strong>Use o valor EXATO</strong> informado (<?php echo formatPrice($order['total']); ?>) para facilitar a identificação</li>
                    <li><strong>Após realizar o depósito</strong>, clique no botão abaixo para nos notificar</li>
                    <li><strong>Confirmaremos o pagamento</strong> em até 24 horas úteis</li>
                    <li><strong>Guarde o comprovante</strong> para eventuais conferências</li>
                </ol>
            </div>
            
            <!-- Prazo de Validade -->
            <div style="background: #FFEBEE; padding: 20px; border-radius: 10px; margin-bottom: 30px; text-align: center;">
                <p style="color: #C62828; font-weight: 600; font-size: 16px;">
                    ⏰ Realize o depósito em até 3 dias úteis
                </p>
                <p style="color: #666; font-size: 14px; margin-top: 5px;">
                    Após este prazo, o pedido será cancelado automaticamente
                </p>
            </div>
            
            <!-- Formulário de Confirmação -->
            <form method="POST" action="">
                <div style="background: #E8F5E9; padding: 25px; border-radius: 15px; margin-bottom: 20px;">
                    <h3 style="color: var(--primary-green); margin-bottom: 15px;">
                        ✅ Já realizou o depósito?
                    </h3>
                    <p style="color: #666; margin-bottom: 20px;">
                        Clique no botão abaixo para nos notificar que você já fez o depósito. 
                        Verificaremos e liberaremos seu pedido em até 24 horas.
                    </p>
                    <button type="submit" name="confirm_transfer" class="btn btn-primary" style="width: 100%; font-size: 18px;">
                        ✓ Confirmar que Realizei o Depósito
                    </button>
                </div>
            </form>
            
            <!-- Informações de Contato -->
            <div style="text-align: center; color: #666; font-size: 14px; margin-top: 30px;">
                <p>💬 Dúvidas sobre o depósito?</p>
                <p style="margin-top: 10px;">
                    Entre em contato: <strong><?php echo ADMIN_EMAIL; ?></strong>
                </p>
            </div>
            
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
function copyBankData() {
    const bankData = `
DADOS PARA DEPÓSITO - MANTOS PREMIUM
Pedido: #<?php echo $orderNumber; ?>

Banco: <?php echo BANK_NAME; ?>
Agência: <?php echo BANK_BRANCH; ?>
Conta: <?php echo BANK_ACCOUNT; ?>
Tipo: <?php echo BANK_ACCOUNT_TYPE; ?>

Favorecido: <?php echo BANK_HOLDER; ?>
CNPJ: <?php echo BANK_CPF_CNPJ; ?>

VALOR EXATO: <?php echo formatPrice($order['total']); ?>
    `.trim();
    
    navigator.clipboard.writeText(bankData).then(function() {
        alert('✅ Dados bancários copiados!\nCole no aplicativo do seu banco.');
    }, function(err) {
        alert('Erro ao copiar. Por favor, anote os dados manualmente.');
    });
}
</script>

<?php include '../includes/footer.php'; ?>
