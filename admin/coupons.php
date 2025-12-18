<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireAdmin();

$pageTitle = 'Gerenciar Cupons';

// Adicionar/Editar cupom
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_coupon'])) {
    $couponId = intval($_POST['coupon_id'] ?? 0);
    $code = strtoupper(sanitize($_POST['code']));
    $type = sanitize($_POST['discount_type']);
    $value = floatval($_POST['discount_value']);
    $minOrder = floatval($_POST['min_order_value']);
    $maxUses = intval($_POST['max_uses']);
    $validFrom = $_POST['valid_from'];
    $validUntil = $_POST['valid_until'];
    $active = isset($_POST['active']) ? 1 : 0;
    
    if (empty($code) || empty($type) || $value <= 0) {
        setFlashMessage('Preencha todos os campos obrigatórios!', 'error');
    } else {
        try {
            if ($couponId > 0) {
                // Atualizar cupom existente
                $stmt = $pdo->prepare("
                    UPDATE coupons 
                    SET code = ?, discount_type = ?, discount_value = ?, 
                        min_order_value = ?, max_uses = ?, valid_from = ?, 
                        valid_until = ?, active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$code, $type, $value, $minOrder, $maxUses, $validFrom, $validUntil, $active, $couponId]);
                setFlashMessage('Cupom atualizado com sucesso!', 'success');
            } else {
                // Verificar se o código já existe
                $stmt = $pdo->prepare("SELECT id FROM coupons WHERE code = ?");
                $stmt->execute([$code]);
                
                if ($stmt->fetch()) {
                    setFlashMessage('Este código de cupom já existe!', 'error');
                } else {
                    // Criar novo cupom
                    $stmt = $pdo->prepare("
                        INSERT INTO coupons (code, discount_type, discount_value, min_order_value, 
                                            max_uses, valid_from, valid_until, active)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$code, $type, $value, $minOrder, $maxUses, $validFrom, $validUntil, $active]);
                    setFlashMessage('Cupom criado com sucesso!', 'success');
                }
            }
        } catch (Exception $e) {
            setFlashMessage('Erro ao salvar cupom.', 'error');
            logError('Erro ao salvar cupom: ' . $e->getMessage());
        }
    }
    
    redirect('coupons.php');
}

// Deletar cupom
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_coupon'])) {
    $couponId = intval($_POST['coupon_id']);
    
    $stmt = $pdo->prepare("DELETE FROM coupons WHERE id = ?");
    $stmt->execute([$couponId]);
    
    setFlashMessage('Cupom deletado com sucesso!', 'success');
    redirect('coupons.php');
}

// Buscar cupons
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$sql = "SELECT c.*, COUNT(DISTINCT o.id) as times_used 
        FROM coupons c
        LEFT JOIN orders o ON c.code = o.coupon_code
        WHERE 1=1";

$params = [];

if ($search) {
    $sql .= " AND c.code LIKE ?";
    $params[] = "%$search%";
}

if ($status !== '') {
    $sql .= " AND c.active = ?";
    $params[] = $status;
}

$sql .= " GROUP BY c.id ORDER BY c.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$coupons = $stmt->fetchAll();

// Estatísticas
$stats = [];
$stats['total_coupons'] = $pdo->query("SELECT COUNT(*) FROM coupons")->fetchColumn();
$stats['active_coupons'] = $pdo->query("SELECT COUNT(*) FROM coupons WHERE active = 1 AND valid_until >= NOW()")->fetchColumn();
$stats['used_coupons'] = $pdo->query("SELECT COUNT(DISTINCT coupon_code) FROM orders WHERE coupon_code IS NOT NULL")->fetchColumn();
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
            max-height: 90vh;
            overflow-y: auto;
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
                <li><a href="coupons.php" class="active">🎫 Cupons</a></li>
                <li><a href="shipping.php">🚚 Frete</a></li>
                <li><a href="emails.php">📧 Emails</a></li>
                <li><a href="settings.php">⚙️ Configurações</a></li>
                <li><a href="../index.php" style="margin-top: 30px;">🏠 Ir para o Site</a></li>
                <li><a href="../logout.php">🚪 Sair</a></li>
            </ul>
        </aside>
        
        <main class="admin-content">
            <div class="admin-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1>🎫 Gerenciar Cupons de Desconto</h1>
                        <p>Crie e gerencie cupons promocionais</p>
                    </div>
                    <button onclick="openModal()" class="btn btn-primary">
                        ➕ Novo Cupom
                    </button>
                </div>
            </div>
            
            <!-- Estatísticas -->
            <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 30px;">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_coupons']; ?></div>
                    <div class="stat-label">Total de Cupons</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['active_coupons']; ?></div>
                    <div class="stat-label">Cupons Ativos</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['used_coupons']; ?></div>
                    <div class="stat-label">Cupons Utilizados</div>
                </div>
            </div>
            
            <!-- Filtros -->
            <div style="background: white; padding: 20px; border-radius: 15px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <form method="GET" style="flex: 1; min-width: 250px;">
                        <input type="text" name="search" placeholder="Buscar por código..." 
                               value="<?php echo htmlspecialchars($search); ?>"
                               style="width: 100%; padding: 10px; border: 2px solid var(--border); border-radius: 5px;">
                    </form>
                    
                    <select onchange="window.location.href='coupons.php?status=' + this.value" 
                            style="padding: 10px 20px; border: 2px solid var(--border); border-radius: 5px;">
                        <option value="">Todos os Status</option>
                        <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>Ativos</option>
                        <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>Inativos</option>
                    </select>
                </div>
            </div>
            
            <!-- Tabela de Cupons -->
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Tipo</th>
                            <th>Desconto</th>
                            <th>Pedido Mínimo</th>
                            <th>Usos</th>
                            <th>Validade</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($coupons)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px;">
                                    Nenhum cupom cadastrado
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($coupons as $coupon): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($coupon['code']); ?></strong></td>
                                    <td>
                                        <?php 
                                        echo $coupon['discount_type'] === 'percentage' ? '📊 Percentual' : '💰 Fixo';
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($coupon['discount_type'] === 'percentage') {
                                            echo $coupon['discount_value'] . '%';
                                        } else {
                                            echo formatPrice($coupon['discount_value']);
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo formatPrice($coupon['min_order_value']); ?></td>
                                    <td>
                                        <?php echo $coupon['times_used']; ?>
                                        <?php if ($coupon['max_uses']): ?>
                                            / <?php echo $coupon['max_uses']; ?>
                                        <?php else: ?>
                                            / ∞
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 12px;">
                                        <?php echo date('d/m/Y', strtotime($coupon['valid_from'])); ?><br>
                                        até<br>
                                        <?php echo date('d/m/Y', strtotime($coupon['valid_until'])); ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $now = date('Y-m-d H:i:s');
                                        $isValid = $coupon['active'] && $coupon['valid_until'] >= $now;
                                        ?>
                                        <?php if ($isValid): ?>
                                            <span class="badge badge-success">Ativo</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button onclick='editCoupon(<?php echo json_encode($coupon); ?>)' class="btn-icon" title="Editar">✏️</button>
                                        <button onclick="deleteCoupon(<?php echo $coupon['id']; ?>, '<?php echo htmlspecialchars($coupon['code']); ?>')" class="btn-icon" title="Deletar">🗑️</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Modal de Cupom -->
    <div id="couponModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h2 id="modalTitle">Novo Cupom</h2>
                <button onclick="closeModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">×</button>
            </div>
            
            <form method="POST" action="" id="couponForm">
                <input type="hidden" name="coupon_id" id="coupon_id">
                
                <div class="form-group">
                    <label>Código do Cupom *</label>
                    <input type="text" name="code" id="code" required 
                           placeholder="Ex: PRIMEIRACOMPRA, NATAL2024"
                           style="text-transform: uppercase;">
                    <p style="font-size: 12px; color: #666; margin-top: 5px;">
                        Use letras e números sem espaços
                    </p>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo de Desconto *</label>
                        <select name="discount_type" id="discount_type" required onchange="updateDiscountLabel()">
                            <option value="percentage">Percentual (%)</option>
                            <option value="fixed">Valor Fixo (¥)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label id="discountLabel">Valor do Desconto (%) *</label>
                        <input type="number" name="discount_value" id="discount_value" required 
                               min="0" step="0.01" placeholder="10">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Valor Mínimo do Pedido (¥)</label>
                    <input type="number" name="min_order_value" id="min_order_value" 
                           min="0" step="0.01" value="0" placeholder="0.00">
                    <p style="font-size: 12px; color: #666; margin-top: 5px;">
                        0 = sem valor mínimo
                    </p>
                </div>
                
                <div class="form-group">
                    <label>Máximo de Usos</label>
                    <input type="number" name="max_uses" id="max_uses" 
                           min="0" value="0" placeholder="0">
                    <p style="font-size: 12px; color: #666; margin-top: 5px;">
                        0 = uso ilimitado
                    </p>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Válido De *</label>
                        <input type="datetime-local" name="valid_from" id="valid_from" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Válido Até *</label>
                        <input type="datetime-local" name="valid_until" id="valid_until" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" name="active" id="active" checked style="margin-right: 10px;">
                        <span>Cupom Ativo</span>
                    </label>
                </div>
                
                <div style="display: flex; gap: 15px; margin-top: 30px;">
                    <button type="button" onclick="closeModal()" class="btn btn-secondary" style="flex: 1;">
                        Cancelar
                    </button>
                    <button type="submit" name="save_coupon" class="btn btn-primary" style="flex: 1;">
                        💾 Salvar Cupom
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openModal() {
        document.getElementById('modalTitle').textContent = 'Novo Cupom';
        document.getElementById('couponForm').reset();
        document.getElementById('coupon_id').value = '';
        
        // Data atual
        const now = new Date();
        const dateStr = now.toISOString().slice(0, 16);
        document.getElementById('valid_from').value = dateStr;
        
        // 30 dias depois
        const futureDate = new Date(now.getTime() + 30 * 24 * 60 * 60 * 1000);
        document.getElementById('valid_until').value = futureDate.toISOString().slice(0, 16);
        
        document.getElementById('couponModal').classList.add('active');
    }
    
    function closeModal() {
        document.getElementById('couponModal').classList.remove('active');
    }
    
    function editCoupon(coupon) {
        document.getElementById('modalTitle').textContent = 'Editar Cupom';
        document.getElementById('coupon_id').value = coupon.id;
        document.getElementById('code').value = coupon.code;
        document.getElementById('discount_type').value = coupon.discount_type;
        document.getElementById('discount_value').value = coupon.discount_value;
        document.getElementById('min_order_value').value = coupon.min_order_value;
        document.getElementById('max_uses').value = coupon.max_uses;
        document.getElementById('valid_from').value = coupon.valid_from.replace(' ', 'T');
        document.getElementById('valid_until').value = coupon.valid_until.replace(' ', 'T');
        document.getElementById('active').checked = coupon.active == 1;
        
        updateDiscountLabel();
        document.getElementById('couponModal').classList.add('active');
    }
    
    function deleteCoupon(id, code) {
        if (confirm(`Tem certeza que deseja deletar o cupom "${code}"?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="delete_coupon" value="1">
                <input type="hidden" name="coupon_id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    function updateDiscountLabel() {
        const type = document.getElementById('discount_type').value;
        const label = document.getElementById('discountLabel');
        if (type === 'percentage') {
            label.textContent = 'Valor do Desconto (%) *';
            document.getElementById('discount_value').placeholder = '10';
        } else {
            label.textContent = 'Valor do Desconto (¥) *';
            document.getElementById('discount_value').placeholder = '50.00';
        }
    }
    
    // Auto-esconder flash message
    setTimeout(function() {
        const flash = document.querySelector('.flash-message');
        if (flash) {
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 300);
        }
    }, 4000);
    
    // Fechar modal ao clicar fora
    document.getElementById('couponModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
    </script>
</body>
</html>