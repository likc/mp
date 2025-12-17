<?php
require_once '../config/config.php';


// Receber dados do callback do Skrill
$transactionId = $_POST['transaction_id'] ?? '';
$status = $_POST['status'] ?? '';
$amount = $_POST['amount'] ?? '';
$md5sig = $_POST['md5sig'] ?? '';

// Verificar assinatura MD5
$concatFields = $_POST['merchant_id'] . $transactionId . strtoupper(SKRILL_SECRET_WORD) . $_POST['mb_amount'] . $_POST['mb_currency'] . $status;
$expectedSig = strtoupper(md5($concatFields));

if ($md5sig === $expectedSig && $status == '2') {
    // Pagamento confirmado
    // Extrair ID do pedido do transaction_id (formato: MP{order_id}{timestamp})
    preg_match('/MP(\d+)/', $transactionId, $matches);
    if (isset($matches[1])) {
        $orderId = $matches[1];
        
        $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'paid', order_status = 'processing' WHERE id = ?");
        $stmt->execute([$orderId]);
    }
}

// Responder ao Skrill
http_response_code(200);
?>
