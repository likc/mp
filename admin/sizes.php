<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireAdmin();

$pageTitle = 'Gerenciar Tamanhos';

// ========================================
// GRUPOS DE TAMANHOS
// ========================================

// Salvar grupo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_group'])) {
    $groupId = intval($_POST['group_id'] ?? 0);
    $name = sanitize($_POST['group_name']);
    $slug = sanitize(strtolower(str_replace(' ', '-', $_POST['group_name'])));
    $displayOrder = intval($_POST['group_display_order']);
    
    try {
        if ($groupId > 0) {
            $stmt = $pdo->prepare("UPDATE size_groups SET name = ?, slug = ?, display_order = ? WHERE id = ?");
            $stmt->execute([$name, $slug, $displayOrder, $groupId]);
            setFlashMessage('Grupo atualizado com sucesso!', 'success');
        } else {
            $stmt = $pdo->prepare("INSERT INTO size_groups (name, slug, display_order) VALUES (?, ?, ?)");
            $stmt->execute([$name, $slug, $displayOrder]);
            setFlashMessage('Grupo criado com sucesso!', 'success');
        }
    } catch (Exception $e) {
        setFlashMessage('Erro ao salvar grupo.', 'error');
        logError('Erro ao salvar grupo: ' . $e->getMessage());
    }
    
    redirect('sizes.php');
}

// Deletar grupo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_group'])) {
    $groupId = intval($_POST['group_id']);
    
    try {
        // Verificar se tem tamanhos
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sizes WHERE group_id = ?");
        $stmt->execute([$groupId]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            setFlashMessage('Não é possível deletar grupo com tamanhos cadastrados.', 'error');
        } else {
            $stmt = $pdo->prepare("DELETE FROM size_groups WHERE id = ?");
            $stmt->execute([$groupId]);
            setFlashMessage('Grupo deletado!', 'success');
        }
    } catch (Exception $e) {
        setFlashMessage('Erro ao deletar grupo.', 'error');
    }
    
    redirect('sizes.php');
}

// ========================================
// TAMANHOS
// ========================================

// Salvar tamanho
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_size'])) {
    $sizeId = intval($_POST['size_id'] ?? 0);
    $groupId = intval($_POST['size_group_id']);
    $name = sanitize($_POST['size_name']);
    $code = sanitize(strtoupper($_POST['size_code']));
    $displayOrder = intval($_POST['size_display_order']);
    
    try {
        if ($sizeId > 0) {
            $stmt = $pdo->prepare("UPDATE sizes SET group_id = ?, name = ?, code = ?, display_order = ? WHERE id = ?");
            $stmt->execute([$groupId, $name, $code, $displayOrder, $sizeId]);
            setFlashMessage('Tamanho atualizado com sucesso!', 'success');
        } else {
            $stmt = $pdo->prepare("INSERT INTO sizes (group_id, name, code, display_order) VALUES (?, ?, ?, ?)");
            $stmt->execute([$groupId, $name, $code, $displayOrder]);
            setFlashMessage('Tamanho criado com sucesso!', 'success');
        }
    } catch (Exception $e) {
        setFlashMessage('Erro ao salvar tamanho. Código pode estar duplicado.', 'error');
        logError('Erro ao salvar tamanho: ' . $e->getMessage());
    }
    
    redirect('sizes.php');
}

// Deletar tamanho
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_size'])) {
    $sizeId = intval($_POST['size_id']);
    
    try {
        // Verificar se está sendo usado
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_variants WHERE size_id = ?");
        $stmt->execute([$sizeId]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            setFlashMessage('Não é possível deletar tamanho em uso por produtos.', 'error');
        } else {
            $stmt = $pdo->prepare("DELETE FROM sizes WHERE id = ?");
            $stmt->execute([$sizeId]);
            setFlashMessage('Tamanho deletado!', 'success');
        }
    } catch (Exception $e) {
        setFlashMessage('Erro ao deletar tamanho.', 'error');
    }
    
    redirect('sizes.php');
}

// Toggle active
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_size'])) {
    $sizeId = intval($_POST['size_id']);
    $active = intval($_POST['active']);
    
    $stmt = $pdo->prepare("UPDATE sizes SET active = ? WHERE id = ?");
    $stmt->execute([$active, $sizeId]);
    
    setFlashMessage('Status atualizado!', 'success');
    redirect('sizes.php');
}

// Buscar dados
$groups = $pdo->query("SELECT * FROM size_groups ORDER BY display_order, name")->fetchAll();

$sizes = $pdo->query("
    SELECT s.*, sg.name as group_name 
    FROM sizes s
    JOIN size_groups sg ON s.group_id = sg.id
    ORDER BY sg.display_order, s.display_order
")->fetchAll();
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
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            padding: 40px;
            border-radius: 15px;
            max-width: 600px;
            width: 90%;
        }
        .size-badge {
            display: inline-block;
            padding: 5px 15px;
            background: var(--primary-green);
            color: white;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin: 3px;
        }
        .size-badge.inactive {
            background: #ccc;
        }
        .group-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
                <li><a href="products.php">📦 Produtos</a></li>
                <li><a href="orders.php">🛒 Pedidos</a></li>
                <li><a href="customers.php">👥 Clientes</a></li>
                <li><a href="coupons.php">🎫 Cupons</a></li>
                <li><a href="sizes.php" class="active">📏 Tamanhos</a></li>
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
                        <h1>📏 Gerenciar Tamanhos</h1>
                        <p>Configure grupos e tamanhos para seus produtos</p>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button onclick="openGroupModal()" class="btn btn-secondary">
                            ➕ Novo Grupo
                        </button>
                        <button onclick="openSizeModal()" class="btn btn-primary">
                            ➕ Novo Tamanho
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Info Box -->
            <div style="background: #e3f2fd; padding: 20px; border-radius: 10px; margin-bottom: 30px; border-left: 4px solid #2196f3;">
                <strong>💡 Como funciona:</strong><br>
                1. <strong>Grupos</strong> são categorias (Adulto, Infantil, Bebê, etc)<br>
                2. <strong>Tamanhos</strong> pertencem a um grupo (S, M, L dentro de "Adulto")<br>
                3. Ao criar um produto, você escolhe qual grupo de tamanhos aplicar<br>
                4. Você pode adicionar/editar tamanhos a qualquer momento!
            </div>
            
            <!-- Grupos de Tamanhos -->
            <div class="group-section">
                <h2 style="margin-bottom: 20px;">📂 Grupos de Tamanhos</h2>
                
                <div class="data-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Nome do Grupo</th>
                                <th>Tamanhos Cadastrados</th>
                                <th>Ordem</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($groups as $group): ?>
                                <?php
                                $groupSizes = array_filter($sizes, function($s) use ($group) {
                                    return $s['group_id'] == $group['id'];
                                });
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($group['name']); ?></strong></td>
                                    <td>
                                        <?php foreach ($groupSizes as $size): ?>
                                            <span class="size-badge <?php echo $size['active'] ? '' : 'inactive'; ?>">
                                                <?php echo htmlspecialchars($size['code']); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td><?php echo $group['display_order']; ?></td>
                                    <td>
                                        <button onclick='editGroup(<?php echo json_encode($group); ?>)' class="btn-icon" title="Editar">✏️</button>
                                        <?php if (count($groupSizes) == 0): ?>
                                            <button onclick="deleteGroup(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars($group['name']); ?>')" class="btn-icon" title="Deletar">🗑️</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Tamanhos por Grupo -->
            <?php foreach ($groups as $group): ?>
                <?php
                $groupSizes = array_filter($sizes, function($s) use ($group) {
                    return $s['group_id'] == $group['id'];
                });
                ?>
                
                <div class="group-section">
                    <h2 style="margin-bottom: 20px;">
                        👕 Tamanhos - <?php echo htmlspecialchars($group['name']); ?>
                        <span style="font-size: 14px; font-weight: normal; color: #666;">
                            (<?php echo count($groupSizes); ?> tamanhos)
                        </span>
                    </h2>
                    
                    <div class="data-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Nome</th>
                                    <th>Ordem</th>
                                    <th>Status</th>
                                    <th>Produtos Usando</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($groupSizes)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 30px;">
                                            Nenhum tamanho cadastrado neste grupo
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($groupSizes as $size): ?>
                                        <?php
                                        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT product_id) FROM product_variants WHERE size_id = ?");
                                        $stmt->execute([$size['id']]);
                                        $productsUsing = $stmt->fetchColumn();
                                        ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($size['code']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($size['name']); ?></td>
                                            <td><?php echo $size['display_order']; ?></td>
                                            <td>
                                                <button onclick="toggleSize(<?php echo $size['id']; ?>, <?php echo $size['active'] ? 0 : 1; ?>)" 
                                                        class="btn-icon">
                                                    <?php echo $size['active'] ? '✅' : '❌'; ?>
                                                </button>
                                            </td>
                                            <td><?php echo $productsUsing; ?> produtos</td>
                                            <td>
                                                <button onclick='editSize(<?php echo json_encode($size); ?>)' class="btn-icon" title="Editar">✏️</button>
                                                <?php if ($productsUsing == 0): ?>
                                                    <button onclick="deleteSize(<?php echo $size['id']; ?>, '<?php echo htmlspecialchars($size['code']); ?>')" class="btn-icon" title="Deletar">🗑️</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </main>
    </div>

    <!-- Modal de Grupo -->
    <div id="groupModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h2 id="groupModalTitle">Novo Grupo de Tamanhos</h2>
                <button onclick="closeGroupModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">×</button>
            </div>
            
            <form method="POST" id="groupForm">
                <input type="hidden" name="group_id" id="group_id">
                
                <div class="form-group">
                    <label>Nome do Grupo *</label>
                    <input type="text" name="group_name" id="group_name" required 
                           placeholder="Ex: Adulto, Infantil, Bebê">
                    <small>Ex: Adulto, Infantil, Plus Size, etc</small>
                </div>
                
                <div class="form-group">
                    <label>Ordem de Exibição</label>
                    <input type="number" name="group_display_order" id="group_display_order" value="0" min="0">
                    <small>Menor número aparece primeiro</small>
                </div>
                
                <div style="display: flex; gap: 15px; margin-top: 30px;">
                    <button type="button" onclick="closeGroupModal()" class="btn btn-secondary" style="flex: 1;">
                        Cancelar
                    </button>
                    <button type="submit" name="save_group" class="btn btn-primary" style="flex: 1;">
                        💾 Salvar Grupo
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Tamanho -->
    <div id="sizeModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h2 id="sizeModalTitle">Novo Tamanho</h2>
                <button onclick="closeSizeModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">×</button>
            </div>
            
            <form method="POST" id="sizeForm">
                <input type="hidden" name="size_id" id="size_id">
                
                <div class="form-group">
                    <label>Grupo *</label>
                    <select name="size_group_id" id="size_group_id" required>
                        <option value="">Selecione o grupo...</option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?php echo $group['id']; ?>">
                                <?php echo htmlspecialchars($group['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Código *</label>
                    <input type="text" name="size_code" id="size_code" required 
                           placeholder="Ex: S, M, XL, 10">
                    <small>Código curto (ex: S, M, L, XL, 4, 6, 8, 10)</small>
                </div>
                
                <div class="form-group">
                    <label>Nome Completo *</label>
                    <input type="text" name="size_name" id="size_name" required 
                           placeholder="Ex: M (Médio), 10 Anos">
                    <small>Nome descritivo (ex: "M (Médio)", "10 Anos")</small>
                </div>
                
                <div class="form-group">
                    <label>Ordem de Exibição</label>
                    <input type="number" name="size_display_order" id="size_display_order" value="0" min="0">
                    <small>Menor número aparece primeiro</small>
                </div>
                
                <div style="display: flex; gap: 15px; margin-top: 30px;">
                    <button type="button" onclick="closeSizeModal()" class="btn btn-secondary" style="flex: 1;">
                        Cancelar
                    </button>
                    <button type="submit" name="save_size" class="btn btn-primary" style="flex: 1;">
                        💾 Salvar Tamanho
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // ===== GRUPOS =====
    function openGroupModal() {
        document.getElementById('groupModalTitle').textContent = 'Novo Grupo de Tamanhos';
        document.getElementById('groupForm').reset();
        document.getElementById('group_id').value = '';
        document.getElementById('groupModal').classList.add('active');
    }
    
    function closeGroupModal() {
        document.getElementById('groupModal').classList.remove('active');
    }
    
    function editGroup(group) {
        document.getElementById('groupModalTitle').textContent = 'Editar Grupo';
        document.getElementById('group_id').value = group.id;
        document.getElementById('group_name').value = group.name;
        document.getElementById('group_display_order').value = group.display_order;
        document.getElementById('groupModal').classList.add('active');
    }
    
    function deleteGroup(id, name) {
        if (confirm(`Deletar grupo "${name}"?\n\nApenas grupos sem tamanhos podem ser deletados.`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="delete_group" value="1">
                <input type="hidden" name="group_id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    // ===== TAMANHOS =====
    function openSizeModal() {
        document.getElementById('sizeModalTitle').textContent = 'Novo Tamanho';
        document.getElementById('sizeForm').reset();
        document.getElementById('size_id').value = '';
        document.getElementById('sizeModal').classList.add('active');
    }
    
    function closeSizeModal() {
        document.getElementById('sizeModal').classList.remove('active');
    }
    
    function editSize(size) {
        document.getElementById('sizeModalTitle').textContent = 'Editar Tamanho';
        document.getElementById('size_id').value = size.id;
        document.getElementById('size_group_id').value = size.group_id;
        document.getElementById('size_code').value = size.code;
        document.getElementById('size_name').value = size.name;
        document.getElementById('size_display_order').value = size.display_order;
        document.getElementById('sizeModal').classList.add('active');
    }
    
    function deleteSize(id, code) {
        if (confirm(`Deletar tamanho "${code}"?\n\nApenas tamanhos não usados podem ser deletados.`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="delete_size" value="1">
                <input type="hidden" name="size_id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    function toggleSize(id, active) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="toggle_size" value="1">
            <input type="hidden" name="size_id" value="${id}">
            <input type="hidden" name="active" value="${active}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
    
    // Fechar modals clicando fora
    document.getElementById('groupModal').addEventListener('click', function(e) {
        if (e.target === this) closeGroupModal();
    });
    
    document.getElementById('sizeModal').addEventListener('click', function(e) {
        if (e.target === this) closeSizeModal();
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
