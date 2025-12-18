<?php
session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    if (empty($action)) {
        throw new Exception('Nenhuma ação especificada');
    }
    
    switch ($action) {
        case 'validate':
            // Validar cupom
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $subtotal = floatval($_POST['subtotal'] ?? 0);
            
            if (empty($code)) {
                throw new Exception('Código do cupom não informado');
            }
            
            if ($subtotal <= 0) {
                throw new Exception('Subtotal inválido');
            }
            
            // Buscar cupom no banco
            $stmt = $pdo->prepare("
                SELECT * FROM coupons 
                WHERE code = ? AND active = 1
            ");
            $stmt->execute([$code]);
            $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$coupon) {
                throw new Exception('Cupom inválido ou não existe');
            }
            
            // Verificar se está expirado
            if (!empty($coupon['expires_at'])) {
                $expiresAt = strtotime($coupon['expires_at']);
                if ($expiresAt < time()) {
                    throw new Exception('Este cupom expirou em ' . date('d/m/Y', $expiresAt));
                }
            }
            
            // Verificar valor mínimo
            if ($coupon['min_order_value'] > 0 && $subtotal < $coupon['min_order_value']) {
                $minFormatted = formatPrice($coupon['min_order_value']);
                throw new Exception("Este cupom requer valor mínimo de {$minFormatted}");
            }
            
            // Verificar uso máximo
            if ($coupon['max_uses'] > 0 && $coupon['times_used'] >= $coupon['max_uses']) {
                throw new Exception('Este cupom atingiu o limite de uso');
            }
            
            // Calcular desconto
            $discount = 0;
            if ($coupon['discount_type'] === 'percentage') {
                $discount = ($subtotal * $coupon['discount_value']) / 100;
                
                // Verificar desconto máximo
                if ($coupon['max_discount_amount'] > 0 && $discount > $coupon['max_discount_amount']) {
                    $discount = $coupon['max_discount_amount'];
                }
            } else {
                // Desconto fixo
                $discount = $coupon['discount_value'];
                
                // Desconto não pode ser maior que o subtotal
                if ($discount > $subtotal) {
                    $discount = $subtotal;
                }
            }
            
            $newTotal = $subtotal - $discount;
            
            $response['success'] = true;
            $response['message'] = 'Cupom aplicado com sucesso!';
            $response['data'] = [
                'coupon_id' => $coupon['id'],
                'code' => $coupon['code'],
                'discount_type' => $coupon['discount_type'],
                'discount_value' => $coupon['discount_value'],
                'discount_amount' => $discount,
                'subtotal' => $subtotal,
                'new_total' => $newTotal,
                'formatted_discount' => formatPrice($discount),
                'formatted_new_total' => formatPrice($newTotal)
            ];
            break;
            
        case 'apply':
            // Aplicar cupom na sessão
            $couponData = json_decode($_POST['coupon_data'] ?? '{}', true);
            
            if (empty($couponData)) {
                throw new Exception('Dados do cupom inválidos');
            }
            
            $_SESSION['applied_coupon'] = $couponData;
            
            $response['success'] = true;
            $response['message'] = 'Cupom aplicado!';
            $response['data'] = $couponData;
            break;
            
        case 'remove':
            // Remover cupom da sessão
            unset($_SESSION['applied_coupon']);
            
            $response['success'] = true;
            $response['message'] = 'Cupom removido';
            break;
            
        case 'get':
            // Obter cupom aplicado
            if (isset($_SESSION['applied_coupon'])) {
                $response['success'] = true;
                $response['data'] = $_SESSION['applied_coupon'];
            } else {
                $response['success'] = true;
                $response['data'] = null;
                $response['message'] = 'Nenhum cupom aplicado';
            }
            break;
            
        default:
            throw new Exception('Ação inválida');
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log('Erro na API de cupons: ' . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);