<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireAdmin();

header('Content-Type: application/json');

$productId = intval($_GET['id'] ?? 0);

if ($productId <= 0) {
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

try {
    // Buscar produto
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['error' => 'Produto não encontrado']);
        exit;
    }
    
    // Buscar variações
    $stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ?");
    $stmt->execute([$productId]);
    $product['variants'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar imagens
    $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, display_order");
    $stmt->execute([$productId]);
    $product['images'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($product);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro ao buscar produto']);
    logError('Erro em get-product.php: ' . $e->getMessage());
}
