<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['paid' => false]);
    exit;
}

$orderId = intval($_GET['order_id'] ?? 0);

if ($orderId <= 0) {
    echo json_encode(['paid' => false]);
    exit;
}

try {
    // Buscar pedido
    $stmt = $pdo->prepare("
        SELECT payment_status 
        FROM orders 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$orderId, $_SESSION['user_id']]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo json_encode(['paid' => false]);
        exit;
    }
    
    // Verificar se foi pago
    $paid = in_array($order['payment_status'], ['paid', 'confirmed']);
    
    echo json_encode(['paid' => $paid]);
    
} catch (Exception $e) {
    error_log('Erro ao verificar status PayPay: ' . $e->getMessage());
    echo json_encode(['paid' => false]);
}
