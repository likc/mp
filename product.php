<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$productId = intval($_GET['id'] ?? 0);

if ($productId <= 0) {
    redirect('products.php');
}

// Buscar produto
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, sg.name as size_group_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN size_groups sg ON p.size_group_id = sg.id
    WHERE p.id = ? AND p.active = 1
");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    redirect('products.php');
}

// Buscar imagens
$stmt = $pdo->prepare("
    SELECT * FROM product_images 
    WHERE product_id = ? 
    ORDER BY is_primary DESC, display_order
");
$stmt->execute([$productId]);
$images = $stmt->fetchAll();

// Se não tem imagens, usar a imagem principal do produto
if (empty($images) && !empty($product['image'])) {
    $images = [[
        'image_path' => $product['image'],
        'is_primary' => 1
    ]];
}

// Buscar variações de tamanho
$stmt = $pdo->prepare("
    SELECT pv.*, s.code, s.name as size_name
    FROM product_variants pv
    JOIN sizes s ON pv.size_id = s.id
    WHERE pv.product_id = ?
    ORDER BY s.display_order
");
$stmt->execute([$productId]);
$variants = $stmt->fetchAll();

$pageTitle = $product['name'];
include 'includes/header.php';
?>

<style>
.product-detail-container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 0 20px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
}

/* Galeria de Imagens */
.product-gallery {
    position: sticky;
    top: 100px;
}

.main-image {
    width: 100%;
    height: 600px;
    border-radius: 20px;
    overflow: hidden;
    margin-bottom: 20px;
    background: #f5f5f5;
    position: relative;
}

.main-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    cursor: zoom-in;
}

.image-thumbnails {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 10px;
}

.thumbnail {
    height: 100px;
    border-radius: 10px;
    overflow: hidden;
    cursor: pointer;
    border: 3px solid transparent;
    transition: all 0.3s;
}

.thumbnail.active {
    border-color: var(--primary-green);
}

.thumbnail:hover {
    border-color: var(--gold);
}

.thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Info do Produto */
.product-info {
    padding-top: 20px;
}

.product-title {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 42px;
    letter-spacing: 2px;
    margin-bottom: 10px;
    color: var(--text);
}

.product-meta {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    color: var(--text-light);
}

.product-price {
    font-size: 36px;
    font-weight: bold;
    color: var(--primary-green);
    margin: 20px 0;
}

.product-description {
    line-height: 1.8;
    color: var(--text);
    margin-bottom: 30px;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 10px;
}

/* Seleção de Tamanho */
.size-selector {
    margin: 30px 0;
}

.size-selector label {
    display: block;
    font-weight: bold;
    margin-bottom: 15px;
    font-size: 18px;
}

.size-options {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.size-option {
    min-width: 70px;
    padding: 15px 20px;
    border: 2px solid var(--border);
    border-radius: 10px;
    cursor: pointer;
    text-align: center;
    transition: all 0.3s;
    background: white;
    font-weight: bold;
}

.size-option:hover {
    border-color: var(--primary-green);
    background: #f0f8f4;
}

.size-option.selected {
    border-color: var(--primary-green);
    background: var(--primary-green);
    color: white;
}

.size-option.out-of-stock {
    opacity: 0.4;
    cursor: not-allowed;
    text-decoration: line-through;
}

/* Personalização */
.customization-section {
    background: #fff9e6;
    border: 2px solid var(--gold);
    border-radius: 15px;
    padding: 25px;
    margin: 30px 0;
}

.customization-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}

.customization-header h3 {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
}

.customization-toggle {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 30px;
}

.customization-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 30px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 22px;
    width: 22px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .toggle-slider {
    background-color: var(--primary-green);
}

input:checked + .toggle-slider:before {
    transform: translateX(30px);
}

.customization-fields {
    display: none;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.customization-fields.active {
    display: grid;
}

.custom-input-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.custom-input-group input {
    width: 100%;
    padding: 12px;
    border: 2px solid var(--border);
    border-radius: 8px;
    font-size: 16px;
}

.customization-price {
    margin-top: 15px;
    padding: 10px;
    background: white;
    border-radius: 8px;
    text-align: center;
    font-weight: bold;
    color: var(--primary-green);
}

/* Quantidade e Botão */
.purchase-controls {
    display: flex;
    gap: 15px;
    margin-top: 30px;
}

.quantity-selector {
    display: flex;
    align-items: center;
    border: 2px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
}

.quantity-selector button {
    width: 50px;
    height: 60px;
    border: none;
    background: #f5f5f5;
    font-size: 24px;
    cursor: pointer;
    transition: all 0.3s;
}

.quantity-selector button:hover {
    background: var(--primary-green);
    color: white;
}

.quantity-selector input {
    width: 80px;
    height: 60px;
    border: none;
    text-align: center;
    font-size: 20px;
    font-weight: bold;
}

.add-to-cart-btn {
    flex: 1;
    padding: 0 40px;
    background: var(--primary-green);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 18px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
    height: 60px;
}

.add-to-cart-btn:hover {
    background: var(--dark-green);
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(45, 122, 74, 0.3);
}

.add-to-cart-btn:disabled {
    background: #ccc;
    cursor: not-allowed;
    transform: none;
}

/* Responsivo */
@media (max-width: 768px) {
    .product-detail-container {
        grid-template-columns: 1fr;
        gap: 30px;
    }
    
    .product-gallery {
        position: relative;
        top: 0;
    }
    
    .main-image {
        height: 400px;
    }
    
    .customization-fields {
        grid-template-columns: 1fr;
    }
    
    .purchase-controls {
        flex-direction: column;
    }
}

/* Notificação */
.notification {
    position: fixed;
    top: 100px;
    right: 20px;
    background: var(--primary-green);
    color: white;
    padding: 20px 30px;
    border-radius: 10px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
    z-index: 10000;
    animation: slideIn 0.3s;
    display: none;
}

.notification.show {
    display: block;
}

.notification.error {
    background: #ff4444;
}

@keyframes slideIn {
    from {
        transform: translateX(400px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}
</style>

<div class="product-detail-container">
    <!-- Galeria de Imagens -->
    <div class="product-gallery">
        <div class="main-image" id="mainImage">
            <?php if (!empty($images)): ?>
                <img src="<?php echo htmlspecialchars($images[0]['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" id="mainImg">
            <?php else: ?>
                <div style="display: flex; align-items: center; justify-content: center; height: 100%; font-size: 48px;">
                    📷
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (count($images) > 1): ?>
        <div class="image-thumbnails">
            <?php foreach ($images as $index => $img): ?>
            <div class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" onclick="changeImage('<?php echo htmlspecialchars($img['image_path']); ?>', this)">
                <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="Foto <?php echo $index + 1; ?>">
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Informações do Produto -->
    <div class="product-info">
        <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
        
        <div class="product-meta">
            <?php if (!empty($product['team'])): ?>
                <span>⚽ <?php echo htmlspecialchars($product['team']); ?></span>
            <?php endif; ?>
            <?php if (!empty($product['season'])): ?>
                <span>📅 <?php echo htmlspecialchars($product['season']); ?></span>
            <?php endif; ?>
            <?php if (!empty($product['category_name'])): ?>
                <span>📂 <?php echo htmlspecialchars($product['category_name']); ?></span>
            <?php endif; ?>
        </div>
        
        <div class="product-price" id="displayPrice">
            <?php echo formatPrice($product['price']); ?>
        </div>
        
        <?php if (!empty($product['description'])): ?>
        <div class="product-description">
            <?php echo nl2br(htmlspecialchars($product['description'])); ?>
        </div>
        <?php endif; ?>
        
        <!-- Seleção de Tamanho -->
        <?php if (!empty($variants)): ?>
        <div class="size-selector">
            <label>📏 Selecione o Tamanho:</label>
            <div class="size-options">
                <?php foreach ($variants as $variant): ?>
                <div class="size-option <?php echo $variant['stock'] == 0 ? 'out-of-stock' : ''; ?>" 
                     data-variant-id="<?php echo $variant['id']; ?>"
                     data-stock="<?php echo $variant['stock']; ?>"
                     onclick="selectSize(this)">
                    <?php echo htmlspecialchars($variant['code']); ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Personalização -->
        <?php if ($product['allow_customization']): ?>
        <div class="customization-section">
            <div class="customization-header">
                <h3>
                    ✏️ Personalizar Camisa
                    <small style="font-size: 14px; font-weight: normal; color: #666;">
                        (+<?php echo formatPrice($product['customization_price']); ?>)
                    </small>
                </h3>
                <label class="customization-toggle">
                    <input type="checkbox" id="customizationToggle" onchange="toggleCustomization()">
                    <span class="toggle-slider"></span>
                </label>
            </div>
            
            <div class="customization-fields" id="customizationFields">
                <div class="custom-input-group">
                    <label>Nome (opcional)</label>
                    <input type="text" id="customName" placeholder="Ex: MESSI" maxlength="20" oninput="updatePrice()">
                </div>
                <div class="custom-input-group">
                    <label>Número (opcional)</label>
                    <input type="number" id="customNumber" placeholder="Ex: 10" min="0" max="99" oninput="updatePrice()">
                </div>
            </div>
            
            <div class="customization-price" id="customizationPrice" style="display: none;">
                Taxa de Personalização: <?php echo formatPrice($product['customization_price']); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Quantidade e Adicionar ao Carrinho -->
        <div class="purchase-controls">
            <div class="quantity-selector">
                <button onclick="changeQuantity(-1)">−</button>
                <input type="number" id="quantity" value="1" min="1" readonly>
                <button onclick="changeQuantity(1)">+</button>
            </div>
            
            <button class="add-to-cart-btn" id="addToCartBtn" onclick="addToCart()" disabled>
                🛒 Adicionar ao Carrinho
            </button>
        </div>
    </div>
</div>

<div class="notification" id="notification">
    ✅ Produto adicionado ao carrinho!
</div>

<script>
// Debug
console.log('Product page loaded');

const product = {
    id: <?php echo $product['id']; ?>,
    price: <?php echo $product['price']; ?>,
    customizationPrice: <?php echo $product['customization_price']; ?>,
    allowCustomization: <?php echo $product['allow_customization'] ? 'true' : 'false'; ?>
};

let selectedVariant = null;

// Mudar imagem principal
function changeImage(imagePath, thumbnail) {
    document.getElementById('mainImg').src = imagePath;
    
    // Atualizar thumbnails ativas
    document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
    thumbnail.classList.add('active');
}

// Selecionar tamanho
function selectSize(element) {
    if (element.classList.contains('out-of-stock')) {
        alert('Este tamanho está esgotado');
        return;
    }
    
    // Remover seleção anterior
    document.querySelectorAll('.size-option').forEach(opt => opt.classList.remove('selected'));
    
    // Selecionar novo
    element.classList.add('selected');
    selectedVariant = element.dataset.variantId;
    
    console.log('Tamanho selecionado:', selectedVariant);
    
    // Habilitar botão
    document.getElementById('addToCartBtn').disabled = false;
}

// Toggle personalização
function toggleCustomization() {
    const isChecked = document.getElementById('customizationToggle').checked;
    const fields = document.getElementById('customizationFields');
    const priceDisplay = document.getElementById('customizationPrice');
    
    if (isChecked) {
        fields.classList.add('active');
        priceDisplay.style.display = 'block';
    } else {
        fields.classList.remove('active');
        priceDisplay.style.display = 'none';
        document.getElementById('customName').value = '';
        document.getElementById('customNumber').value = '';
    }
    
    updatePrice();
}

// Atualizar preço com customização
function updatePrice() {
    const isCustomizing = document.getElementById('customizationToggle').checked;
    let totalPrice = product.price;
    
    if (isCustomizing) {
        totalPrice += product.customizationPrice;
    }
    
    document.getElementById('displayPrice').textContent = formatPrice(totalPrice);
}

// Mudar quantidade
function changeQuantity(delta) {
    const input = document.getElementById('quantity');
    let value = parseInt(input.value) + delta;
    if (value < 1) value = 1;
    input.value = value;
}

// Adicionar ao carrinho
async function addToCart() {
    console.log('addToCart chamado');
    
    if (!selectedVariant) {
        alert('Por favor, selecione um tamanho');
        return;
    }
    
    const quantity = parseInt(document.getElementById('quantity').value);
    const isCustomizing = document.getElementById('customizationToggle').checked;
    const customName = isCustomizing ? document.getElementById('customName').value : '';
    const customNumber = isCustomizing ? document.getElementById('customNumber').value : '';
    
    console.log('Dados:', {
        product_id: product.id,
        variant_id: selectedVariant,
        quantity: quantity,
        customName: customName,
        customNumber: customNumber
    });
    
    const formData = new FormData();
    formData.append('action', 'add');  // IMPORTANTE!
    formData.append('product_id', product.id);
    formData.append('variant_id', selectedVariant);
    formData.append('quantity', quantity);
    formData.append('customization_name', customName);
    formData.append('customization_number', customNumber);
    
    // Debug: ver o que está sendo enviado
    console.log('FormData entries:');
    for (let pair of formData.entries()) {
        console.log(pair[0] + ': ' + pair[1]);
    }
    
    try {
        console.log('Enviando para: api/cart.php');
        
        const response = await fetch('api/cart.php', {
            method: 'POST',
            body: formData
        });
        
        console.log('Response status:', response.status);
        
        const text = await response.text();
        console.log('Response text:', text);
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Erro ao parsear JSON:', e);
            console.error('Texto recebido:', text);
            throw new Error('Resposta inválida do servidor');
        }
        
        console.log('Response data:', data);
        
        if (data.success) {
            // Mostrar notificação
            const notification = document.getElementById('notification');
            notification.classList.remove('error');
            notification.textContent = '✅ ' + data.message;
            notification.classList.add('show');
            
            // Atualizar contador do carrinho (se existir)
            const cartCount = document.getElementById('cart-count');
            if (cartCount) {
                cartCount.textContent = data.cart_count;
            }
            
            // Esconder notificação após 3s
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
            
            // Resetar formulário
            document.getElementById('quantity').value = 1;
            if (isCustomizing) {
                document.getElementById('customizationToggle').checked = false;
                toggleCustomization();
            }
        } else {
            // Mostrar erro
            const notification = document.getElementById('notification');
            notification.classList.add('error');
            notification.textContent = '❌ ' + data.message;
            notification.classList.add('show');
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 5000);
            
            console.error('Erro do servidor:', data);
        }
    } catch (error) {
        console.error('Erro completo:', error);
        alert('Erro ao adicionar ao carrinho: ' + error.message);
    }
}

// Formatar preço
function formatPrice(value) {
    return '¥' + Math.round(value).toLocaleString('ja-JP');
}

console.log('Script carregado, product:', product);
</script>

<?php include 'includes/footer.php'; ?>