<?php
session_start();

require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');

// Verificar se é admin
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

try {
    $orderId = intval($_GET['order_id'] ?? 0);
    
    if ($orderId <= 0) {
        throw new Exception('ID do pedido inválido');
    }
    
    // Buscar itens do pedido
    $stmt = $pdo->prepare("
        SELECT 
            oi.*,
            p.name as product_name_full,
            p.image as product_image
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.id
    ");
    
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}