<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireAdmin();

$pageTitle = 'Gerenciar Produtos';

// Salvar produto
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_product'])) {
    $productId = intval($_POST['product_id'] ?? 0);
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $price = floatval($_POST['price']);
    $team = sanitize($_POST['team']);
    $season = sanitize($_POST['season']);
    $categoryId = intval($_POST['category_id']);
    $sizeGroupId = intval($_POST['size_group_id']);
    $featured = isset($_POST['featured']) ? 1 : 0;
    $allowCustomization = isset($_POST['allow_customization']) ? 1 : 0;
    $customizationPrice = floatval($_POST['customization_price'] ?? 0);
    
    try {
        $pdo->beginTransaction();
        
        // Calcular estoque total das variações
        $totalStock = 0;
        if (isset($_POST['variants'])) {
            foreach ($_POST['variants'] as $stock) {
                $totalStock += intval($stock);
            }
        }
        
        if ($productId > 0) {
            // Atualizar produto
            $stmt = $pdo->prepare("
                UPDATE products 
                SET name = ?, description = ?, price = ?, team = ?, season = ?,
                    category_id = ?, size_group_id = ?, stock = ?, featured = ?, 
                    allow_customization = ?, customization_price = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $price, $team, $season, 
                          $categoryId, $sizeGroupId, $totalStock, $featured, 
                          $allowCustomization, $customizationPrice, $productId]);
        } else {
            // Criar novo produto
            $stmt = $pdo->prepare("
                INSERT INTO products (name, description, price, team, season, 
                                    category_id, size_group_id, stock, featured, 
                                    allow_customization, customization_price, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$name, $description, $price, $team, $season, 
                          $categoryId, $sizeGroupId, $totalStock, $featured, 
                          $allowCustomization, $customizationPrice]);
            $productId = $pdo->lastInsertId();
        }
        
        // Salvar variações
        if (isset($_POST['variants'])) {
            foreach ($_POST['variants'] as $sizeId => $stock) {
                $stock = intval($stock);
                $sku = 'SKU-' . $productId . '-' . $sizeId;
                
                $stmt = $pdo->prepare("
                    INSERT INTO product_variants (product_id, size_id, stock, sku)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE stock = ?, sku = ?
                ");
                $stmt->execute([$productId, $sizeId, $stock, $sku, $stock, $sku]);
            }
        }
        
        // Upload de múltiplas imagens
        if (isset($_FILES['images'])) {
            $uploadDir = '../uploads/products/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                    $extension = strtolower(pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION));
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    
                    if (in_array($extension, $allowedExtensions)) {
                        $filename = 'product_' . $productId . '_' . time() . '_' . $key . '.' . $extension;
                        $filepath = $uploadDir . $filename;
                        
                        if (move_uploaded_file($tmpName, $filepath)) {
                            // Salvar no banco
                            $relativePath = 'uploads/products/' . $filename;
                            $isPrimary = ($key == 0) ? 1 : 0;
                            
                            $stmt = $pdo->prepare("
                                INSERT INTO product_images (product_id, image_path, is_primary, display_order)
                                VALUES (?, ?, ?, ?)
                            ");
                            $stmt->execute([$productId, $relativePath, $isPrimary, $key]);
                            
                            // Se for primeira imagem, atualizar campo image do produto
                            if ($key == 0) {
                                $stmt = $pdo->prepare("UPDATE products SET image = ? WHERE id = ?");
                                $stmt->execute([$relativePath, $productId]);
                            }
                        }
                    }
                }
            }
        }
        
        $pdo->commit();
        setFlashMessage('Produto salvo com sucesso!', 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlashMessage('Erro Real: ' . $e->getMessage(), 'error');
        logError('Erro ao salvar produto: ' . $e->getMessage());
    }
    
    redirect('products.php');
}

// Deletar produto
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_product'])) {
    $productId = intval($_POST['product_id']);
    
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        setFlashMessage('Produto deletado com sucesso!', 'success');
    } catch (Exception $e) {
        setFlashMessage('Erro ao deletar produto.', 'error');
    }
    
    redirect('products.php');
}

// Deletar imagem
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_image'])) {
    $imageId = intval($_POST['image_id']);
    
    try {
        // Buscar caminho da imagem
        $stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE id = ?");
        $stmt->execute([$imageId]);
        $image = $stmt->fetch();
        
        if ($image) {
            // Deletar arquivo físico
            $filepath = '../' . $image['image_path'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            
            // Deletar do banco
            $stmt = $pdo->prepare("DELETE FROM product_images WHERE id = ?");
            $stmt->execute([$imageId]);
            
            setFlashMessage('Imagem deletada!', 'success');
        }
    } catch (Exception $e) {
        setFlashMessage('Erro ao deletar imagem.', 'error');
    }
    
    redirect('products.php');
}

// Toggle featured
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_featured'])) {
    $productId = intval($_POST['product_id']);
    $newValue = intval($_POST['featured_value']);
    
    $stmt = $pdo->prepare("UPDATE products SET featured = ? WHERE id = ?");
    $stmt->execute([$newValue, $productId]);
    
    setFlashMessage('Destaque atualizado!', 'success');
    redirect('products.php');
}

// Buscar produtos
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

$sql = "SELECT p.*, c.name as category_name, sg.name as size_group_name,
        (SELECT COUNT(*) FROM product_images WHERE product_id = p.id) as image_count
        FROM products p
        JOIN categories c ON p.category_id = c.id
        LEFT JOIN size_groups sg ON p.size_group_id = sg.id
        WHERE 1=1";

$params = [];

if ($search) {
    $sql .= " AND (p.name LIKE ? OR p.team LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($category) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category;
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Buscar categorias
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Buscar grupos de tamanho
$sizeGroups = $pdo->query("SELECT * FROM size_groups WHERE active = 1 ORDER BY display_order")->fetchAll();

// Estatísticas
$stats = [];
$stats['total_products'] = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$stats['out_of_stock'] = $pdo->query("SELECT COUNT(*) FROM products WHERE stock = 0")->fetchColumn();
$stats['low_stock'] = $pdo->query("SELECT COUNT(*) FROM products WHERE stock > 0 AND stock < 10")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Mantos Premium</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            overflow-y: auto;
            padding: 20px 0;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            padding: 40px;
            border-radius: 15px;
            max-width: 900px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            margin: auto;
        }
        .product-thumbnail {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        .variants-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .variant-box {
            border: 2px solid var(--border);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }
        .variant-box label {
            font-weight: bold;
            display: block;
            margin-bottom: 10px;
            color: var(--primary-green);
        }
        .image-preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .image-preview-item {
            position: relative;
            border: 2px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
        }
        .image-preview-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
        }
        .image-preview-item .remove-img {
            position: absolute;
            top: 5px;
            right: 5px;
            background: red;
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
        }
    </style>
</head>
<body>
    <?php
    $flash = getFlashMessage();
    if ($flash):
    ?>
    <div class="flash-message flash-<?php echo $flash['type']; ?>" style="position: fixed; top: 20px; right: 20px; z-index: 10000;">
        <?php echo $flash['message']; ?>
    </div>
    <?php endif; ?>

    <div class="admin-container">
        <aside class="admin-sidebar">
            <div style="padding: 0 30px; margin-bottom: 30px;">
                <h2 style="font-family: 'Bebas Neue', sans-serif; font-size: 28px; letter-spacing: 2px;">
                    🏆 MANTOS<br>PREMIUM
                </h2>
            </div>
            <ul class="admin-menu">
                <li><a href="dashboard.php">📊 Dashboard</a></li>
                <li><a href="products.php" class="active">📦 Produtos</a></li>
                <li><a href="orders.php">🛒 Pedidos</a></li>
                <li><a href="customers.php">👥 Clientes</a></li>
                <li><a href="coupons.php">🎫 Cupons</a></li>
                <li><a href="sizes.php">📏 Tamanhos</a></li>
                <li><a href="shipping.php">🚚 Frete</a></li>
                <li><a href="settings.php">⚙️ Configurações</a></li>
                <li><a href="../index.php" style="margin-top: 30px;">🏠 Ir para o Site</a></li>
                <li><a href="../logout.php">🚪 Sair</a></li>
            </ul>
        </aside>
        
        <main class="admin-content">
            <div class="admin-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1>📦 Gerenciar Produtos</h1>
                        <p>Adicione, edite e gerencie produtos da loja</p>
                    </div>
                    <button onclick="openModal()" class="btn btn-primary">
                        ➕ Novo Produto
                    </button>
                </div>
            </div>
            
            <!-- Estatísticas -->
            <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 30px;">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_products']; ?></div>
                    <div class="stat-label">Total de Produtos</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['low_stock']; ?></div>
                    <div class="stat-label">Estoque Baixo (&lt;10)</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['out_of_stock']; ?></div>
                    <div class="stat-label">Sem Estoque</div>
                </div>
            </div>
            
            <!-- Filtros -->
            <div style="background: white; padding: 20px; border-radius: 15px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <form method="GET" style="flex: 1; min-width: 250px;">
                        <input type="text" name="search" placeholder="Buscar produtos..." 
                               value="<?php echo htmlspecialchars($search); ?>"
                               style="width: 100%; padding: 10px; border: 2px solid var(--border); border-radius: 5px;">
                    </form>
                    
                    <select onchange="window.location.href='products.php?category=' + this.value" 
                            style="padding: 10px 20px; border: 2px solid var(--border); border-radius: 5px;">
                        <option value="">Todas as Categorias</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Tabela de Produtos -->
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Imagem</th>
                            <th>Nome</th>
                            <th>Time</th>
                            <th>Grupo Tamanho</th>
                            <th>Preço</th>
                            <th>Estoque</th>
                            <th>Imagens</th>
                            <th>Destaque</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 40px;">
                                    Nenhum produto cadastrado
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <?php if ($product['image']): ?>
                                            <img src="../<?php echo $product['image']; ?>" class="product-thumbnail">
                                        <?php else: ?>
                                            <div style="width: 60px; height: 60px; background: #f0f0f0; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                                📷
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($product['team']); ?></td>
                                    <td><?php echo htmlspecialchars($product['size_group_name'] ?: 'N/A'); ?></td>
                                    <td><?php echo formatPrice($product['price']); ?></td>
                                    <td>
                                        <?php if ($product['stock'] == 0): ?>
                                            <span class="badge badge-danger">Esgotado</span>
                                        <?php elseif ($product['stock'] < 10): ?>
                                            <span class="badge badge-warning"><?php echo $product['stock']; ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-success"><?php echo $product['stock']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $product['image_count']; ?> fotos</td>
                                    <td>
                                        <button onclick="toggleFeatured(<?php echo $product['id']; ?>, <?php echo $product['featured'] ? 0 : 1; ?>)" 
                                                class="btn-icon">
                                            <?php echo $product['featured'] ? '⭐' : '☆'; ?>
                                        </button>
                                    </td>
                                    <td>
                                        <button onclick="editProduct(<?php echo $product['id']; ?>)" class="btn-icon" title="Editar">✏️</button>
                                        <button onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')" class="btn-icon" title="Deletar">🗑️</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Modal de Produto -->
    <div id="productModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h2 id="modalTitle">Novo Produto</h2>
                <button onclick="closeModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">×</button>
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data" id="productForm">
                <input type="hidden" name="product_id" id="product_id">
                
                <div class="form-group">
                    <label>Nome do Produto *</label>
                    <input type="text" name="name" id="name" required>
                </div>
                
                <div class="form-group">
                    <label>Descrição</label>
                    <textarea name="description" id="description" rows="4"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Preço * (¥)</label>
                        <input type="number" name="price" id="price" required min="0" step="1" placeholder="10000">
                    </div>
                    
                    <div class="form-group">
                        <label>Categoria *</label>
                        <select name="category_id" id="category_id" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Time</label>
                        <input type="text" name="team" id="team" placeholder="Ex: Real Madrid">
                    </div>
                    
                    <div class="form-group">
                        <label>Temporada</label>
                        <input type="text" name="season" id="season" placeholder="Ex: 2024/25">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Grupo de Tamanhos * 
                        <a href="sizes.php" target="_blank" style="font-size: 12px;">(Gerenciar Tamanhos)</a>
                    </label>
                    <select name="size_group_id" id="size_group_id" required onchange="loadSizes(this.value)">
                        <option value="">Selecione...</option>
                        <?php foreach ($sizeGroups as $group): ?>
                            <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="sizesContainer" style="display: none;">
                    <label style="display: block; margin-bottom: 10px; font-weight: bold;">
                        📏 Estoque por Tamanho
                    </label>
                    <div class="variants-grid" id="variantsGrid">
                        <!-- Tamanhos serão carregados aqui dinamicamente -->
                    </div>
                </div>
                
                <hr style="margin: 30px 0;">
                
                <div class="form-group">
                    <label>🖼️ Imagens do Produto (Múltiplas)</label>
                    <input type="file" name="images[]" id="images" accept="image/*" multiple onchange="previewImages(this)">
                    <small>Selecione múltiplas imagens. A primeira será a principal.</small>
                    <div id="imagePreviewGrid" class="image-preview-grid"></div>
                </div>
                
                <div id="existingImagesContainer"></div>
                
                <hr style="margin: 30px 0;">
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" name="allow_customization" id="allow_customization" checked style="margin-right: 10px;">
                        <span>✏️ Permitir Customização (Nome + Número)</span>
                    </label>
                </div>
                
                <div class="form-group">
                    <label>Taxa de Customização (¥)</label>
                    <input type="number" name="customization_price" id="customization_price" min="0" step="1" value="1500" placeholder="1500">
                    <small>Valor extra cobrado para personalizar a camisa</small>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" name="featured" id="featured" style="margin-right: 10px;">
                        <span>⭐ Produto em Destaque</span>
                    </label>
                </div>
                
                <div style="display: flex; gap: 15px; margin-top: 30px;">
                    <button type="button" onclick="closeModal()" class="btn btn-secondary" style="flex: 1;">
                        Cancelar
                    </button>
                    <button type="submit" name="save_product" class="btn btn-primary" style="flex: 1;">
                        💾 Salvar Produto
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    const sizesByGroup = <?php echo json_encode(
        array_reduce($pdo->query("SELECT s.*, sg.id as group_id FROM sizes s JOIN size_groups sg ON s.group_id = sg.id WHERE s.active = 1 ORDER BY s.display_order")->fetchAll(), function($carry, $size) {
            if (!isset($carry[$size['group_id']])) {
                $carry[$size['group_id']] = [];
            }
            $carry[$size['group_id']][] = $size;
            return $carry;
        }, [])
    ); ?>;
    
    function openModal() {
        document.getElementById('modalTitle').textContent = 'Novo Produto';
        document.getElementById('productForm').reset();
        document.getElementById('product_id').value = '';
        document.getElementById('sizesContainer').style.display = 'none';
        document.getElementById('imagePreviewGrid').innerHTML = '';
        document.getElementById('existingImagesContainer').innerHTML = '';
        document.getElementById('productModal').classList.add('active');
    }
    
    function closeModal() {
        document.getElementById('productModal').classList.remove('active');
    }
    
    function loadSizes(groupId) {
        const container = document.getElementById('sizesContainer');
        const grid = document.getElementById('variantsGrid');
        
        if (!groupId || !sizesByGroup[groupId]) {
            container.style.display = 'none';
            return;
        }
        
        container.style.display = 'block';
        grid.innerHTML = '';
        
        sizesByGroup[groupId].forEach(size => {
            const box = document.createElement('div');
            box.className = 'variant-box';
            box.innerHTML = `
                <label>${size.code}</label>
                <input type="number" 
                       name="variants[${size.id}]" 
                       min="0" 
                       value="0" 
                       placeholder="0"
                       style="width: 100%; padding: 8px; border: 2px solid var(--border); border-radius: 5px; text-align: center;">
                <small style="color: #666;">${size.name}</small>
            `;
            grid.appendChild(box);
        });
    }
    
    function previewImages(input) {
        const grid = document.getElementById('imagePreviewGrid');
        grid.innerHTML = '';
        
        if (input.files) {
            Array.from(input.files).forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'image-preview-item';
                    div.innerHTML = `
                        <img src="${e.target.result}">
                        ${index === 0 ? '<small style="position: absolute; bottom: 5px; left: 5px; background: var(--primary-green); color: white; padding: 2px 8px; border-radius: 3px; font-size: 10px;">PRINCIPAL</small>' : ''}
                    `;
                    grid.appendChild(div);
                }
                reader.readAsDataURL(file);
            });
        }
    }
    
    function editProduct(id) {
        fetch(`get-product.php?id=${id}`)
            .then(r => r.json())
            .then(data => {
                document.getElementById('modalTitle').textContent = 'Editar Produto';
                document.getElementById('product_id').value = data.id;
                document.getElementById('name').value = data.name;
                document.getElementById('description').value = data.description || '';
                document.getElementById('price').value = data.price;
                document.getElementById('category_id').value = data.category_id;
                document.getElementById('team').value = data.team || '';
                document.getElementById('season').value = data.season || '';
                document.getElementById('size_group_id').value = data.size_group_id;
                document.getElementById('allow_customization').checked = data.allow_customization == 1;
                document.getElementById('customization_price').value = data.customization_price;
                document.getElementById('featured').checked = data.featured == 1;
                
                // Carregar tamanhos
                loadSizes(data.size_group_id);
                
                // Preencher estoques das variações
                if (data.variants) {
                    data.variants.forEach(v => {
                        const input = document.querySelector(`input[name="variants[${v.size_id}]"]`);
                        if (input) input.value = v.stock;
                    });
                }
                
                // Mostrar imagens existentes
                if (data.images && data.images.length > 0) {
                    let html = '<div style="margin: 20px 0;"><strong>Imagens Atuais:</strong><div class="image-preview-grid" style="margin-top: 10px;">';
                    data.images.forEach(img => {
                        html += `
                            <div class="image-preview-item">
                                <img src="../${img.image_path}">
                                ${img.is_primary ? '<small style="position: absolute; bottom: 5px; left: 5px; background: var(--gold); color: black; padding: 2px 8px; border-radius: 3px; font-size: 10px;">PRINCIPAL</small>' : ''}
                                <button type="button" class="remove-img" onclick="deleteImage(${img.id})">×</button>
                            </div>
                        `;
                    });
                    html += '</div></div>';
                    document.getElementById('existingImagesContainer').innerHTML = html;
                }
                
                document.getElementById('productModal').classList.add('active');
            });
    }
    
    function deleteProduct(id, name) {
        if (confirm(`Deletar produto "${name}"?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="delete_product" value="1">
                <input type="hidden" name="product_id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    function deleteImage(imageId) {
        if (confirm('Deletar esta imagem?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="delete_image" value="1">
                <input type="hidden" name="image_id" value="${imageId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    function toggleFeatured(id, value) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="toggle_featured" value="1">
            <input type="hidden" name="product_id" value="${id}">
            <input type="hidden" name="featured_value" value="${value}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
    
    // Fechar modal ao clicar fora
    document.getElementById('productModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
    
    // Auto-esconder flash
    setTimeout(function() {
        const flash = document.querySelector('.flash-message');
        if (flash) {
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 300);
        }
    }, 4000);
    </script>
</body>
</html>
