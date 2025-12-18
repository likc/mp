<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/settings.php';

header('Content-Type: application/json');

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

try {
    // Receber dados JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Dados inválidos');
    }
    
    $orderId = intval($data['order_id'] ?? 0);
    $paypalOrderId = $data['paypal_order_id'] ?? '';
    $payer = $data['payer'] ?? [];
    
    if ($orderId <= 0 || empty($paypalOrderId)) {
        throw new Exception('Informações do pedido inválidas');
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
        throw new Exception('Este pedido já foi pago');
    }
    
    // Verificar pagamento no PayPal
    $paypalMode = getSetting('paypal_mode', 'sandbox');
    $paypalClientId = getSetting('paypal_client_id');
    $paypalSecret = getSetting('paypal_secret');
    
    $apiUrl = $paypalMode === 'live' 
        ? 'https://api-m.paypal.com' 
        : 'https://api-m.sandbox.paypal.com';
    
    // Obter token de acesso
    $ch = curl_init($apiUrl . '/v1/oauth2/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_USERPWD, $paypalClientId . ':' . $paypalSecret);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    
    $tokenResponse = curl_exec($ch);
    $tokenData = json_decode($tokenResponse, true);
    curl_close($ch);
    
    if (!isset($tokenData['access_token'])) {
        throw new Exception('Erro ao autenticar com PayPal');
    }
    
    $accessToken = $tokenData['access_token'];
    
    // Verificar detalhes do pedido
    $ch = curl_init($apiUrl . '/v2/checkout/orders/' . $paypalOrderId);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    
    $orderResponse = curl_exec($ch);
    $orderData = json_decode($orderResponse, true);
    curl_close($ch);
    
    if (!isset($orderData['status']) || $orderData['status'] !== 'COMPLETED') {
        throw new Exception('Pagamento não foi completado no PayPal');
    }
    
    // Atualizar pedido no banco
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("
        UPDATE orders SET 
            payment_status = 'paid',
            status = 'processing',
            payment_id = ?,
            payment_details = ?,
            paid_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([
        $paypalOrderId,
        json_encode($orderData),
        $orderId
    ]);
    
    $pdo->commit();
    
    // TODO: Enviar email de confirmação
    
    echo json_encode([
        'success' => true,
        'message' => 'Pagamento confirmado com sucesso!'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('Erro no processo PayPal: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
