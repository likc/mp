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

$bankName = getSetting('bank_name', 'Correio / ゆうちょ銀行');
$bankBranch = getSetting('bank_branch', '12040');
$bankAccount = getSetting('bank_account', '22895581');
$bankAccountType = getSetting('bank_account_type', '普通(Futsuu)');
$bankHolder = getSetting('bank_holder', 'ウメダ　フェレイラ　レラウジ　ケンジ');

$uploadError = '';
$uploadSuccess = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_receipt'])) {
    try {
        if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] === UPLOAD_ERR_NO_FILE) {
            throw new Exception('Por favor, selecione um arquivo');
        }
        
        $file = $_FILES['receipt'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Erro ao fazer upload do arquivo');
        }
        
        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            throw new Exception('Arquivo muito grande. Tamanho máximo: 5MB');
        }
        
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, $allowedExtensions)) {
            throw new Exception('Formato não permitido. Use: JPG, PNG ou PDF');
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        
        if (!in_array($mimeType, $allowedMimes)) {
            throw new Exception('Tipo de arquivo inválido');
        }
        
        $hash = hash('sha256', $order['order_number'] . time() . $file['name']);
        $newFilename = 'receipt_' . $order['order_number'] . '_' . $hash . '.' . $extension;
        
        $uploadDir = __DIR__ . '/../private/receipts/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
            file_put_contents($uploadDir . '.htaccess', "Deny from all");
        }
        
        $uploadPath = $uploadDir . $newFilename;
        
        $realUploadDir = realpath($uploadDir);
        $realUploadPath = $realUploadDir . '/' . basename($newFilename);
        
        if (strpos($realUploadPath, $realUploadDir) !== 0) {
            throw new Exception('Caminho de arquivo inválido');
        }
        
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception('Erro ao salvar arquivo');
        }
        
        $stmt = $pdo->prepare("
            UPDATE orders SET 
                payment_status = 'pending_confirmation',
                payment_receipt = ?,
                receipt_uploaded_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        
        $stmt->execute([$newFilename, $orderId, $_SESSION['user_id']]);
        
        $uploadSuccess = true;
        
    } catch (Exception $e) {
        $uploadError = $e->getMessage();
        error_log('Erro no upload de comprovante: ' . $e->getMessage());
    }
}

$pageTitle = 'Transferência Bancária - Mantos Premium';
?>

<style>
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
    padding: 35px;
    border-radius: 12px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
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
    position: sticky;
    top: 20px;
}

.summary-title {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 24px;
    border-bottom: 2px solid #f1f1f1;
    padding-bottom: 15px;
    margin-bottom: 20px;
    color: #2d7a4a;
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
    color: #2d7a4a;
}

/* Estilos Bancários */
.bank-details-box {
    background: #fafafa;
    padding: 25px;
    border-radius: 10px;
    border: 1px solid #eee;
    margin: 25px 0;
}

.bank-detail-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}

.bank-detail-row:last-child { border-bottom: none; }

.bank-label { font-weight: bold; color: #666; font-size: 14px; }
.bank-value { font-family: 'Courier New', monospace; font-weight: bold; color: #333; }

.instructions-list {
    text-align: left;
    background: #e8f5e9;
    padding: 20px 20px 20px 40px;
    border-radius: 10px;
    margin-bottom: 30px;
    font-size: 14px;
    color: #2e7d32;
    line-height: 1.6;
}

/* Upload UI */
.upload-box {
    border: 2px dashed #ddd;
    padding: 30px;
    border-radius: 10px;
    background: #fff;
    transition: all 0.3s;
}

.file-input-label {
    display: inline-block;
    padding: 12px 25px;
    background: #f0f0f0;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    margin-bottom: 10px;
}

.upload-btn {
    width: 100%;
    padding: 15px;
    background: #2d7a4a;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    margin-top: 15px;
}

.upload-btn:disabled { background: #ccc; cursor: not-allowed; }

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-weight: 500;
}
.alert-error { background: #fdecea; color: #d32f2f; border: 1px solid #f5c6cb; }
.alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c3e6cb; }

.security-tag {
    display: inline-block;
    background: #e8f5e9;
    color: #2e7d32;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    margin-top: 20px;
    text-align: center;
}
</style>

<div class="checkout-wrapper">
    <div class="checkout-grid">
        
        <div class="payment-section">
            <h2 style="font-family: 'Bebas Neue', sans-serif; font-size: 32px; color: #333; text-align: center;">PAGAMENTO VIA TRANSFERÊNCIA</h2>
            
            <?php if ($uploadError): ?>
                <div class="alert alert-error">❌ <?php echo htmlspecialchars($uploadError); ?></div>
            <?php endif; ?>

            <?php if ($uploadSuccess): ?>
                <div style="text-align: center; padding: 40px 0;">
                    <div style="font-size: 60px; margin-bottom: 20px;">✅</div>
                    <h3 style="color: #2d7a4a;">Comprovante Enviado!</h3>
                    <p>Estamos processando sua confirmação (prazo de até 24h).</p>
                    <a href="../order-success.php?order=<?php echo $order['order_number']; ?>" class="upload-btn" style="display: block; text-decoration: none;">Ver Pedido</a>
                </div>
            <?php else: ?>
                <p style="color: #666; text-align: center; margin-bottom: 25px;">Realize a transferência bancária utilizando os dados abaixo.</p>

                <div class="bank-details-box">
                    <div class="bank-detail-row"><span class="bank-label">Banco</span><span class="bank-value"><?php echo htmlspecialchars($bankName); ?></span></div>
                    <div class="bank-detail-row"><span class="bank-label">Agência</span><span class="bank-value"><?php echo htmlspecialchars($bankBranch); ?></span></div>
                    <div class="bank-detail-row"><span class="bank-label">Conta</span><span class="bank-value"><?php echo htmlspecialchars($bankAccount); ?></span></div>
                    <div class="bank-detail-row"><span class="bank-label">Titular</span><span class="bank-value"><?php echo htmlspecialchars($bankHolder); ?></span></div>
                </div>

                <div class="instructions-list">
                    <strong>Próximos passos:</strong>
                    <ol style="margin-top: 10px;">
                        <li>Efetue o depósito no valor total do pedido.</li>
                        <li>Identifique a transferência com o número <strong>#<?php echo $order['order_number']; ?></strong>.</li>
                        <li>Anexe o comprovante (Foto ou PDF) no campo abaixo.</li>
                    </ol>
                </div>

                <div class="upload-box" style="text-align: center;">
                    <h4 style="margin-bottom: 15px; color: #333;">ANEXAR COMPROVANTE</h4>
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <input type="file" name="receipt" id="receiptFile" accept=".jpg,.jpeg,.png,.pdf" required style="display:none;" onchange="updateFileName(this)">
                        <label for="receiptFile" class="file-input-label">📁 Selecionar Arquivo</label>
                        <div id="fileName" style="font-size: 13px; color: #888; margin: 10px 0;">Nenhum arquivo selecionado</div>
                        
                        <button type="submit" name="upload_receipt" class="upload-btn" id="uploadBtn" disabled>📤 ENVIAR AGORA</button>
                    </form>
                </div>

                <div style="text-align: center;">
                    <div class="security-tag">🔒 AMBIENTE 100% SEGURO</div>
                </div>
            <?php endif; ?>

            <p style="margin-top: 30px; text-align: center;">
                <a href="../cart.php" style="text-decoration: none; color: #888; font-size: 14px;">← Voltar para o carrinho</a>
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

<script>
function updateFileName(input) {
    const fileName = document.getElementById('fileName');
    const uploadBtn = document.getElementById('uploadBtn');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.size > 5 * 1024 * 1024) {
            alert('Arquivo muito grande! Máximo 5MB');
            input.value = '';
            return;
        }
        fileName.textContent = '✓ ' + file.name;
        fileName.style.color = '#2d7a4a';
        uploadBtn.disabled = false;
    }
}

document.getElementById('uploadForm').addEventListener('submit', function() {
    const btn = document.getElementById('uploadBtn');
    btn.disabled = true;
    btn.textContent = 'Enviando...';
});
</script>