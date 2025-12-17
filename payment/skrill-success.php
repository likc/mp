<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();

$orderNumber = $_GET['order'] ?? '';

if ($orderNumber) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ?");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();
    
    if ($order) {
        // Atualizar status do pedido
        $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'paid', order_status = 'processing' WHERE id = ?");
        $stmt->execute([$order['id']]);
        
        setFlashMessage('Pagamento realizado com sucesso via Skrill!', 'success');
    }
}

redirect('/order-success.php?order=' . $orderNumber);
?>
