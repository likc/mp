<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireAdmin();

$pageTitle = 'Gerenciar Pedidos';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['update_status'])) {
            $orderId = intval($_POST['order_id']);
            $newStatus = sanitize($_POST['status']);
            
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $orderId]);
            
            setFlashMessage('Status atualizado com sucesso!', 'success');
            
        } elseif (isset($_POST['delete_order'])) {
            $orderId = intval($_POST['order_id']);
            
            // Deletar itens primeiro
            $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$orderId]);
            
            // Deletar pedido
            $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$orderId]);
            
            setFlashMessage('Pedido deletado com sucesso!', 'success');
        }
    } catch (Exception $e) {
        setFlashMessage('Erro: ' . $e->getMessage(), 'error');
    }
    
    redirect('orders.php');
}

// Filtros
$statusFilter = $_GET['status'] ?? '';
$paymentFilter = $_GET['payment'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Query base
$sql = "SELECT o.*, u.name as customer_name, u.email as customer_email 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        WHERE 1=1";

$params = [];

// Aplicar filtros
if (!empty($statusFilter)) {
    $sql .= " AND o.status = ?";
    $params[] = $statusFilter;
}

if (!empty($paymentFilter)) {
    $sql .= " AND o.payment_method = ?";
    $params[] = $paymentFilter;
}

if (!empty($searchQuery)) {
    $sql .= " AND (o.order_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%{$searchQuery}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($dateFrom)) {
    $sql .= " AND DATE(o.created_at) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $sql .= " AND DATE(o.created_at) <= ?";
    $params[] = $dateTo;
}

$sql .= " ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Estatísticas
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn(),
    'processing' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'processing'")->fetchColumn(),
    'completed' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'completed'")->fetchColumn(),
    'cancelled' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'cancelled'")->fetchColumn(),
    'revenue' => $pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status IN ('completed', 'processing')")->fetchColumn()
];
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
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 13px;
        }
        
        .stat-card.total .stat-value { color: var(--primary-green); }
        .stat-card.pending .stat-value { color: #ffc107; }
        .stat-card.processing .stat-value { color: #17a2b8; }
        .stat-card.completed .stat-value { color: #28a745; }
        .stat-card.cancelled .stat-value { color: #dc3545; }
        .stat-card.revenue .stat-value { color: var(--primary-green); font-size: 24px; }
        
        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--border);
            border-radius: 5px;
            font-size: 14px;
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-filter {
            padding: 10px 20px;
            background: var(--primary-green);
            color: white;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .btn-clear {
            padding: 10px 20px;
            background: #666;
            color: white;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .orders-table-wrapper {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table-header {
            padding: 20px;
            background: var(--primary-green);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .orders-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }
        
        .orders-table td {
            padding: 12px;
            border-bottom: 1px solid #f5f5f5;
            font-size: 14px;
        }
        
        .orders-table tr:hover {
            background: #f9f9f9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #d1ecf1; color: #0c5460; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            color: white;
        }
        
        .btn-view { background: #17a2b8; }
        .btn-delete { background: #dc3545; }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 10000;
            overflow-y: auto;
            padding: 20px;
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f5f5f5;
        }
        
        .modal-title {
            font-size: 24px;
            font-weight: bold;
        }
        
        .btn-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #666;
        }
        
        .order-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .detail-section {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
        }
        
        .detail-section h3 {
            margin-bottom: 12px;
            font-size: 16px;
            color: var(--primary-green);
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid #e5e5e5;
            font-size: 14px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #666;
        }
        
        .items-table {
            width: 100%;
            margin-top: 15px;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        .items-table th {
            background: #f8f9fa;
            padding: 10px;
            text-align: left;
            font-weight: 600;
        }
        
        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .update-status-form {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #f5f5f5;
        }
        
        .update-status-form select {
            flex: 1;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 5px;
        }
        
        .update-status-form button {
            padding: 10px 20px;
            background: var(--primary-green);
            color: white;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        @media (max-width: 768px) {
            .order-details-grid {
                grid-template-columns: 1fr;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
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
                <p style="font-size: 12px; opacity: 0.8;">Painel Administrativo</p>
            </div>
            
            <ul class="admin-menu">
                <li><a href="dashboard.php">📊 Dashboard</a></li>
                <li><a href="products.php">📦 Produtos</a></li>
                <li><a href="orders.php" class="active">🛒 Pedidos</a></li>
                <li><a href="customers.php">👥 Clientes</a></li>
                <li><a href="coupons.php">🎫 Cupons</a></li>
                <li><a href="shipping.php">🚚 Frete</a></li>
                <li><a href="emails.php">📧 Emails</a></li>
                <li><a href="settings.php">⚙️ Configurações</a></li>
                <li><a href="../index.php" style="margin-top: 30px;">🏠 Ir para o Site</a></li>
                <li><a href="../logout.php">🚪 Sair</a></li>
            </ul>
        </aside>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>🛒 Gerenciar Pedidos</h1>
                <p>Visualize e gerencie todos os pedidos da loja</p>
            </div>
            
            <!-- Estatísticas -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total de Pedidos</div>
                </div>
                <div class="stat-card pending">
                    <div class="stat-value"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">Pendentes</div>
                </div>
                <div class="stat-card processing">
                    <div class="stat-value"><?php echo $stats['processing']; ?></div>
                    <div class="stat-label">Em Processamento</div>
                </div>
                <div class="stat-card completed">
                    <div class="stat-value"><?php echo $stats['completed']; ?></div>
                    <div class="stat-label">Concluídos</div>
                </div>
                <div class="stat-card cancelled">
                    <div class="stat-value"><?php echo $stats['cancelled']; ?></div>
                    <div class="stat-label">Cancelados</div>
                </div>
                <div class="stat-card revenue">
                    <div class="stat-value"><?php echo formatPrice($stats['revenue']); ?></div>
                    <div class="stat-label">Receita Total</div>
                </div>
            </div>
            
            <!-- Filtros -->
            <div class="filters-section">
                <h3 style="margin-bottom: 15px;">🔍 Filtros</h3>
                <form method="GET">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label>Buscar</label>
                            <input type="text" name="search" placeholder="Nº pedido, cliente, email..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="">Todos</option>
                                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pendente</option>
                                <option value="processing" <?php echo $statusFilter === 'processing' ? 'selected' : ''; ?>>Em Processamento</option>
                                <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Concluído</option>
                                <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelado</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Método de Pagamento</label>
                            <select name="payment">
                                <option value="">Todos</option>
                                <option value="paypal" <?php echo $paymentFilter === 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                                <option value="paypay" <?php echo $paymentFilter === 'paypay' ? 'selected' : ''; ?>>PayPay</option>
                                <option value="bank_transfer" <?php echo $paymentFilter === 'bank_transfer' ? 'selected' : ''; ?>>Transferência</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Data De</label>
                            <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label>Data Até</label>
                            <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn-filter">Filtrar</button>
                        <a href="orders.php" class="btn-clear">Limpar</a>
                    </div>
                </form>
            </div>
            
            <!-- Tabela de Pedidos -->
            <div class="orders-table-wrapper">
                <div class="table-header">
                    <h3>📋 Lista de Pedidos</h3>
                    <span><?php echo count($orders); ?> pedido(s)</span>
                </div>
                
                <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <div style="font-size: 60px; margin-bottom: 15px;">📦</div>
                    <h3>Nenhum pedido encontrado</h3>
                    <p>Não há pedidos com os filtros selecionados</p>
                </div>
                <?php else: ?>
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Nº Pedido</th>
                            <th>Cliente</th>
                            <th>Data</th>
                            <th>Total</th>
                            <th>Pagamento</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                            <td>
                                <?php echo htmlspecialchars($order['customer_name']); ?><br>
                                <small style="color: #666;"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                            <td><strong><?php echo formatPrice($order['total']); ?></strong></td>
                            <td>
                                <?php
                                $paymentIcons = [
                                    'paypal' => '💳 PayPal',
                                    'paypay' => '📱 PayPay',
                                    'bank_transfer' => '🏦 Transfer'
                                ];
                                echo $paymentIcons[$order['payment_method']] ?? $order['payment_method'];
                                ?>
                            </td>
                            <td>
                                <?php
                                $statusClasses = [
                                    'pending' => 'status-pending',
                                    'processing' => 'status-processing',
                                    'completed' => 'status-completed',
                                    'cancelled' => 'status-cancelled'
                                ];
                                $statusLabels = [
                                    'pending' => 'Pendente',
                                    'processing' => 'Processando',
                                    'completed' => 'Concluído',
                                    'cancelled' => 'Cancelado'
                                ];
                                $class = $statusClasses[$order['status']] ?? 'status-pending';
                                $label = $statusLabels[$order['status']] ?? $order['status'];
                                ?>
                                <span class="status-badge <?php echo $class; ?>">
                                    <?php echo $label; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-action btn-view" onclick='viewOrder(<?php echo json_encode($order); ?>)'>
                                        👁️ Ver
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Deletar este pedido?');">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <button type="submit" name="delete_order" class="btn-action btn-delete">
                                            🗑️
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Modal Ver Pedido -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalOrderNumber">Pedido #</h2>
                <button class="btn-close" onclick="closeModal()">&times;</button>
            </div>
            
            <div class="order-details-grid">
                <div class="detail-section">
                    <h3>👤 Cliente</h3>
                    <div class="detail-row">
                        <span class="detail-label">Nome:</span>
                        <span id="detailCustomerName"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Email:</span>
                        <span id="detailCustomerEmail"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Telefone:</span>
                        <span id="detailPhone"></span>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h3>📦 Pedido</h3>
                    <div class="detail-row">
                        <span class="detail-label">Data:</span>
                        <span id="detailDate"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Pagamento:</span>
                        <span id="detailPayment"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status:</span>
                        <span id="detailStatus"></span>
                    </div>
                </div>
            </div>
            
            <div class="detail-section" style="margin-bottom: 20px;">
                <h3>📍 Endereço de Entrega</h3>
                <div id="detailAddress"></div>
            </div>
            
            <div class="detail-section">
                <h3>🛍️ Itens do Pedido</h3>
                <div id="detailItems"></div>
            </div>
            
            <div class="detail-section" style="margin-top: 20px;">
                <h3>💰 Totais</h3>
                <div class="detail-row">
                    <span class="detail-label">Subtotal:</span>
                    <span id="detailSubtotal"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Frete:</span>
                    <span id="detailShipping"></span>
                </div>
                <div class="detail-row" id="detailDiscountRow" style="display: none;">
                    <span class="detail-label">Desconto:</span>
                    <span id="detailDiscount"></span>
                </div>
                <div class="detail-row" style="font-size: 18px; font-weight: bold; border-top: 2px solid #ddd; padding-top: 10px; margin-top: 10px;">
                    <span class="detail-label">TOTAL:</span>
                    <span id="detailTotal"></span>
                </div>
            </div>
            
            <form method="POST" class="update-status-form">
                <input type="hidden" name="order_id" id="updateOrderId">
                <select name="status" required>
                    <option value="pending">Pendente</option>
                    <option value="processing">Em Processamento</option>
                    <option value="completed">Concluído</option>
                    <option value="cancelled">Cancelado</option>
                </select>
                <button type="submit" name="update_status">
                    💾 Atualizar Status
                </button>
            </form>
        </div>
    </div>
    
    <script>
    function viewOrder(order) {
        fetch(`api/get-order-items.php?order_id=${order.id}`)
            .then(response => response.json())
            .then(data => {
                showOrderModal(order, data.items || []);
            })
            .catch(error => {
                console.error('Erro:', error);
                showOrderModal(order, []);
            });
    }
    
    function showOrderModal(order, items) {
        document.getElementById('modalOrderNumber').textContent = `Pedido #${order.order_number}`;
        document.getElementById('detailCustomerName').textContent = order.shipping_name || order.customer_name || 'N/A';
        document.getElementById('detailCustomerEmail').textContent = order.shipping_email || order.customer_email || 'N/A';
        document.getElementById('detailPhone').textContent = order.shipping_phone || 'N/A';
        
        const date = new Date(order.created_at);
        document.getElementById('detailDate').textContent = date.toLocaleString('pt-BR');
        
        const paymentLabels = {
            'paypal': '💳 PayPal',
            'paypay': '📱 PayPay',
            'bank_transfer': '🏦 Transferência'
        };
        document.getElementById('detailPayment').textContent = paymentLabels[order.payment_method] || order.payment_method;
        
        const statusLabels = {
            'pending': '⏳ Pendente',
            'processing': '🔄 Processando',
            'completed': '✅ Concluído',
            'cancelled': '❌ Cancelado'
        };
        document.getElementById('detailStatus').textContent = statusLabels[order.status] || order.status;
        
        const address = `
            <div style="line-height: 1.8;">
                <strong>${order.shipping_name || 'N/A'}</strong><br>
                〒${order.shipping_postal_code || 'N/A'}<br>
                ${order.shipping_prefecture || ''} ${order.shipping_city || ''}<br>
                ${order.shipping_address_line1 || ''}
                ${order.shipping_address_line2 ? '<br>' + order.shipping_address_line2 : ''}
            </div>
        `;
        document.getElementById('detailAddress').innerHTML = address;
        
        let itemsHtml = '<table class="items-table"><thead><tr><th>Produto</th><th>Tamanho</th><th>Qtd</th><th>Preço</th><th>Total</th></tr></thead><tbody>';
        
        if (items.length > 0) {
            items.forEach(item => {
                itemsHtml += `
                    <tr>
                        <td>
                            ${item.product_name || 'N/A'}
                            ${item.customization_name ? '<br><small>✏️ ' + item.customization_name + ' #' + item.customization_number + '</small>' : ''}
                        </td>
                        <td>${item.size_code || 'N/A'}</td>
                        <td>${item.quantity || 0}</td>
                        <td>${formatPrice(item.unit_price || 0)}</td>
                        <td><strong>${formatPrice(item.total_price || 0)}</strong></td>
                    </tr>
                `;
            });
        } else {
            itemsHtml += '<tr><td colspan="5" style="text-align: center;">Nenhum item encontrado</td></tr>';
        }
        
        itemsHtml += '</tbody></table>';
        document.getElementById('detailItems').innerHTML = itemsHtml;
        
        document.getElementById('detailSubtotal').textContent = formatPrice(order.subtotal || 0);
        document.getElementById('detailShipping').textContent = order.shipping_cost > 0 ? formatPrice(order.shipping_cost) : 'GRÁTIS';
        document.getElementById('detailTotal').textContent = formatPrice(order.total || 0);
        
        if (order.discount > 0) {
            document.getElementById('detailDiscountRow').style.display = 'flex';
            document.getElementById('detailDiscount').textContent = '-' + formatPrice(order.discount);
        } else {
            document.getElementById('detailDiscountRow').style.display = 'none';
        }
        
        document.getElementById('updateOrderId').value = order.id;
        document.querySelector('.update-status-form select[name="status"]').value = order.status;
        
        document.getElementById('viewModal').classList.add('show');
    }
    
    function closeModal() {
        document.getElementById('viewModal').classList.remove('show');
    }
    
    function formatPrice(value) {
        return '¥' + Math.round(value).toLocaleString('ja-JP');
    }
    
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            closeModal();
        }
    }
    </script>
</body>
</html>