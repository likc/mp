<?php
// Funções auxiliares do sistema

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: /index.php');
        exit;
    }
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function formatPrice($price) {
    return 'R$ ' . number_format($price, 2, ',', '.');
}

function generateSlug($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'n-a' : $text;
}

function generateOrderNumber() {
    return 'MP' . date('Ymd') . rand(1000, 9999);
}

function uploadImage($file, $directory = 'uploads/products/') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $filename = $file['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed)) {
        return false;
    }
    
    $newFilename = uniqid() . '_' . time() . '.' . $ext;
    $destination = __DIR__ . '/../' . $directory . $newFilename;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return $directory . $newFilename;
    }
    
    return false;
}

function getCartTotal() {
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        return 0;
    }
    
    global $pdo;
    $total = 0;
    
    foreach ($_SESSION['cart'] as $item) {
        $stmt = $pdo->prepare("SELECT price FROM products WHERE id = ?");
        $stmt->execute([$item['product_id']]);
        $product = $stmt->fetch();
        
        if ($product) {
            $total += $product['price'] * $item['quantity'];
        }
    }
    
    return $total;
}

function getCartItemCount() {
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        return 0;
    }
    
    $count = 0;
    foreach ($_SESSION['cart'] as $item) {
        $count += $item['quantity'];
    }
    
    return $count;
}

function addToCart($product_id, $size, $quantity = 1) {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    $key = $product_id . '_' . $size;
    
    if (isset($_SESSION['cart'][$key])) {
        $_SESSION['cart'][$key]['quantity'] += $quantity;
    } else {
        $_SESSION['cart'][$key] = [
            'product_id' => $product_id,
            'size' => $size,
            'quantity' => $quantity
        ];
    }
    
    return true;
}

function removeFromCart($key) {
    if (isset($_SESSION['cart'][$key])) {
        unset($_SESSION['cart'][$key]);
        return true;
    }
    return false;
}

function updateCartQuantity($key, $quantity) {
    if (isset($_SESSION['cart'][$key])) {
        if ($quantity <= 0) {
            unset($_SESSION['cart'][$key]);
        } else {
            $_SESSION['cart'][$key]['quantity'] = $quantity;
        }
        return true;
    }
    return false;
}

function clearCart() {
    $_SESSION['cart'] = [];
}

function applyCoupon($code) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM coupons 
        WHERE code = ? 
        AND active = 1 
        AND valid_from <= NOW() 
        AND valid_until >= NOW()
        AND (max_uses IS NULL OR used_count < max_uses)
    ");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch();
    
    if (!$coupon) {
        return false;
    }
    
    $cartTotal = getCartTotal();
    
    if ($cartTotal < $coupon['min_order_value']) {
        return false;
    }
    
    $_SESSION['coupon'] = $coupon;
    return $coupon;
}

function calculateDiscount($subtotal) {
    if (!isset($_SESSION['coupon'])) {
        return 0;
    }
    
    $coupon = $_SESSION['coupon'];
    
    if ($coupon['discount_type'] == 'percentage') {
        return ($subtotal * $coupon['discount_value']) / 100;
    } else {
        return $coupon['discount_value'];
    }
}

function calculateShipping($subtotal, $quantity) {
    global $pdo;
    
    $stmt = $pdo->query("SELECT * FROM shipping_config LIMIT 1");
    $config = $stmt->fetch();
    
    if (!$config) {
        return 15.00; // Valor padrão
    }
    
    // Verifica frete grátis por valor
    if ($config['free_shipping_min_value'] > 0 && $subtotal >= $config['free_shipping_min_value']) {
        return 0;
    }
    
    // Verifica frete grátis por quantidade
    if ($config['free_shipping_min_quantity'] > 0 && $quantity >= $config['free_shipping_min_quantity']) {
        return 0;
    }
    
    return $config['default_shipping_cost'];
}

function sendEmail($to, $subject, $body, $type = 'general') {
    require_once __DIR__ . '/mailgun.php';
    return sendMailgunEmail($to, $subject, $body, $type);
}

function logError($message) {
    $logFile = __DIR__ . '/../logs/error.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function setFlashMessage($message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'];
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}
?>
