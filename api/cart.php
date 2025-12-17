<?php
// Iniciar sessão SEMPRE primeiro
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Função para obter dados de múltiplas fontes
function getRequestData() {
    $data = [];
    
    // 1. Tentar POST normal
    if (!empty($_POST)) {
        $data = array_merge($data, $_POST);
    }
    
    // 2. Tentar GET
    if (!empty($_GET)) {
        $data = array_merge($data, $_GET);
    }
    
    // 3. Tentar JSON
    $json = file_get_contents('php://input');
    if (!empty($json)) {
        $jsonData = json_decode($json, true);
        if (is_array($jsonData)) {
            $data = array_merge($data, $jsonData);
        }
    }
    
    return $data;
}

// Obter dados da requisição
$requestData = getRequestData();

// Debug completo
error_log('=== CART API DEBUG ===');
error_log('POST: ' . print_r($_POST, true));
error_log('GET: ' . print_r($_GET, true));
error_log('JSON input: ' . file_get_contents('php://input'));
error_log('Request Data: ' . print_r($requestData, true));

// Inicializar carrinho se não existir
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Pegar action
$action = $requestData['action'] ?? '';

$response = [
    'success' => false,
    'message' => '',
    'debug' => [
        'action' => $action,
        'post_keys' => array_keys($_POST),
        'get_keys' => array_keys($_GET),
        'request_keys' => array_keys($requestData),
        'session_id' => session_id()
    ]
];

try {
    if (empty($action)) {
        throw new Exception('Nenhuma ação especificada. POST keys: ' . implode(', ', array_keys($_POST)) . ' GET keys: ' . implode(', ', array_keys($_GET)));
    }
    
    switch ($action) {
        case 'add':
            $productId = intval($requestData['product_id'] ?? 0);
            $variantId = intval($requestData['variant_id'] ?? 0);
            $quantity = intval($requestData['quantity'] ?? 1);
            $customizationName = isset($requestData['customization_name']) ? sanitize($requestData['customization_name']) : '';
            $customizationNumber = isset($requestData['customization_number']) ? sanitize($requestData['customization_number']) : '';
            
            if ($productId <= 0 || $variantId <= 0) {
                throw new Exception('Produto ou tamanho inválido (product_id: ' . $productId . ', variant_id: ' . $variantId . ')');
            }
            
            if ($quantity < 1) {
                throw new Exception('Quantidade inválida');
            }
            
            // Buscar produto e variação
            $stmt = $pdo->prepare("
                SELECT 
                    p.id,
                    p.name,
                    p.price,
                    p.image,
                    p.team,
                    p.allow_customization,
                    p.customization_price,
                    s.code as size_code,
                    s.name as size_name,
                    pv.id as variant_id,
                    pv.stock
                FROM products p
                JOIN product_variants pv ON pv.product_id = p.id
                JOIN sizes s ON s.id = pv.size_id
                WHERE p.id = ? AND pv.id = ? AND p.active = 1
            ");
            $stmt->execute([$productId, $variantId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                throw new Exception('Produto não encontrado ou inativo');
            }
            
            // Verificar se tem customização
            $hasCustomization = !empty($customizationName) || !empty($customizationNumber);
            $customPrice = 0;
            
            if ($hasCustomization && $product['allow_customization']) {
                $customPrice = floatval($product['customization_price']);
            }
            
            // Criar chave única para o item no carrinho
            $cartKey = $productId . '_' . $variantId;
            if ($hasCustomization) {
                $cartKey .= '_' . md5($customizationName . $customizationNumber);
            }
            
            // Adicionar ou atualizar item
            if (isset($_SESSION['cart'][$cartKey])) {
                // Item já existe, aumentar quantidade
                $_SESSION['cart'][$cartKey]['quantity'] += $quantity;
            } else {
                // Novo item
                $_SESSION['cart'][$cartKey] = [
                    'product_id' => $productId,
                    'variant_id' => $variantId,
                    'name' => $product['name'],
                    'price' => floatval($product['price']),
                    'image' => $product['image'],
                    'size_code' => $product['size_code'],
                    'size_name' => $product['size_name'],
                    'quantity' => $quantity,
                    'team' => $product['team'],
                    'customization_name' => $customizationName,
                    'customization_number' => $customizationNumber,
                    'customization_price' => $customPrice
                ];
            }
            
            // Calcular total de itens
            $cartCount = 0;
            foreach ($_SESSION['cart'] as $item) {
                $cartCount += $item['quantity'];
            }
            
            $response['success'] = true;
            $response['message'] = 'Produto adicionado ao carrinho!';
            $response['cart_count'] = $cartCount;
            $response['cart_key'] = $cartKey;
            unset($response['debug']); // Remove debug em caso de sucesso
            break;
            
        case 'update':
            $cartKey = $requestData['cart_key'] ?? '';
            $quantity = intval($requestData['quantity'] ?? 0);
            
            if (empty($cartKey)) {
                throw new Exception('Item inválido');
            }
            
            if (!isset($_SESSION['cart'][$cartKey])) {
                throw new Exception('Item não encontrado no carrinho');
            }
            
            if ($quantity <= 0) {
                // Remover item
                unset($_SESSION['cart'][$cartKey]);
                $response['message'] = 'Item removido do carrinho';
            } else {
                // Atualizar quantidade
                $_SESSION['cart'][$cartKey]['quantity'] = $quantity;
                $response['message'] = 'Quantidade atualizada!';
            }
            
            // Calcular total de itens
            $cartCount = 0;
            foreach ($_SESSION['cart'] as $item) {
                $cartCount += $item['quantity'];
            }
            
            $response['success'] = true;
            $response['cart_count'] = $cartCount;
            unset($response['debug']);
            break;
            
        case 'remove':
            $cartKey = $requestData['cart_key'] ?? '';
            
            if (empty($cartKey)) {
                throw new Exception('Item inválido');
            }
            
            if (isset($_SESSION['cart'][$cartKey])) {
                unset($_SESSION['cart'][$cartKey]);
                
                // Calcular total de itens
                $cartCount = 0;
                foreach ($_SESSION['cart'] as $item) {
                    $cartCount += $item['quantity'];
                }
                
                $response['success'] = true;
                $response['message'] = 'Item removido do carrinho';
                $response['cart_count'] = $cartCount;
                unset($response['debug']);
            } else {
                throw new Exception('Item não encontrado');
            }
            break;
            
        case 'get':
            $cartItems = [];
            $subtotal = 0;
            
            foreach ($_SESSION['cart'] as $key => $item) {
                $itemTotal = ($item['price'] + $item['customization_price']) * $item['quantity'];
                $subtotal += $itemTotal;
                
                $cartItems[] = array_merge($item, [
                    'cart_key' => $key,
                    'item_total' => $itemTotal,
                    'formatted_price' => formatPrice($item['price']),
                    'formatted_customization_price' => formatPrice($item['customization_price']),
                    'formatted_item_total' => formatPrice($itemTotal)
                ]);
            }
            
            $response['success'] = true;
            $response['items'] = $cartItems;
            $response['subtotal'] = $subtotal;
            $response['formatted_subtotal'] = formatPrice($subtotal);
            $response['cart_count'] = array_sum(array_column($_SESSION['cart'], 'quantity'));
            unset($response['debug']);
            break;
            
        case 'clear':
            $_SESSION['cart'] = [];
            $response['success'] = true;
            $response['message'] = 'Carrinho limpo!';
            $response['cart_count'] = 0;
            unset($response['debug']);
            break;
            
        case 'count':
            $cartCount = 0;
            foreach ($_SESSION['cart'] as $item) {
                $cartCount += $item['quantity'];
            }
            $response['success'] = true;
            $response['cart_count'] = $cartCount;
            unset($response['debug']);
            break;
            
        default:
            throw new Exception('Ação inválida: ' . $action);
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    $response['error_trace'] = $e->getTraceAsString();
    error_log('Erro no carrinho: ' . $e->getMessage());
}

// Log da resposta para debug
error_log('Response: ' . json_encode($response));

echo json_encode($response, JSON_UNESCAPED_UNICODE);