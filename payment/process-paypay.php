<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    redirect('../login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['confirm_payment'])) {
    redirect('../index.php');
}

try {
    $orderId = intval($_POST['order_id'] ?? 0);
    
    if ($orderId <= 0) {
        throw new Exception('ID do pedido inválido');
    }
    
    // Buscar pedido
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$orderId, $_SESSION['user_id']]);
    $order = $stmt->fetch();
    
    if (!$order) {
        throw new Exception('Pedido não encontrado');
    }
    
    // Verificar se já foi pago
    if ($order['payment_status'] === 'paid') {
        redirect('../order-success.php?order=' . $order['order_number']);
    }
    
    // Atualizar pedido para "aguardando confirmação"
    $stmt = $pdo->prepare("
        UPDATE orders SET 
            payment_status = 'pending_confirmation',
            status = 'pending',
            payment_confirmed_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([$orderId]);
    
    // Em produção real, aqui você faria:
    // 1. Verificar na API do PayPay se o pagamento foi recebido
    // 2. Validar o valor
    // 3. Confirmar a transação
    
    // Por enquanto, marcamos como "aguardando confirmação"
    // O admin pode confirmar manualmente
    
    setFlashMessage('Pagamento registrado! Aguardando confirmação do PayPay. Você receberá um email quando for confirmado.', 'success');
    redirect('../order-success.php?order=' . $order['order_number']);
    
} catch (Exception $e) {
    error_log('Erro no processo PayPay: ' . $e->getMessage());
    setFlashMessage('Erro ao processar pagamento: ' . $e->getMessage(), 'error');
    redirect('../cart.php');
}
