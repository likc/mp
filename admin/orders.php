<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/mailgun.php';

requireAdmin();

$pageTitle = 'Gerenciar Pedidos';

// Atualizar status do pedido
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_order'])) {
    $orderId = intval($_POST['order_id']);
    $status = sanitize($_POST['order_status']);
    $trackingCode = sanitize($_POST['tracking_code'] ?? '');
    
    $stmt = $pdo->prepare("UPDATE orders SET order_status = ?, tracking_code = ? WHERE id = ?");
    $stmt->execute([$status, $trackingCode, $orderId]);
    
    // Enviar email se foi marcado como enviado
    if ($status == 'shipped' && !empty($trackingCode)) {
        sendShippingNotificationEmail($orderId);
    }
    
    setFlashMessage('Pedido atualizado com sucesso!', 'success');
    redirect('orders.php');
}

// Filtros
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Query base
$sql = "SELECT o.*, u.name as customer_name, u.email as customer_email 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE 1=1";

$params = [];

if ($status) {
    $sql .= " AND o.order_status = ?";
    $params[] = $status;
}

if ($search) {
    $sql .= " AND (o.order_number LIKE ? OR u.name LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Mantos Premium</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;600;700;900&display=swap" rel="stylesheet">
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
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 15px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <form method="GET" style="flex: 1; min-width: 250px;">
                        <input type="text" name="search" placeholder="Buscar por número ou cliente..." 
                               value="<?php echo htmlspecialchars($search); ?>"
                               style="width: 100%; padding: 10px; border: 2px solid var(--border); border-radius: 5px;">
                    </form>
                    
                    <select onchange="window.location.href='orders.php?status=' + this.value" 
                            style="padding: 10px 20px; border: 2px solid var(--border); border-radius: 5px;">
                        <option value="">Todos os Status</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pendente</option>
                        <option value="processing" <?php echo $status === 'processing' ? 'selected' : ''; ?>>Processando</option>
                        <option value="shipped" <?php echo $status === 'shipped' ? 'selected' : ''; ?>>Enviado</option>
                        <option value="delivered" <?php echo $status === 'delivered' ? 'selected' : ''; ?>>Entregue</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelado</option>
                    </select>
                </div>
            </div>
            
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Total</th>
                            <th>Pagamento</th>
                            <th>Status</th>
                            <th>Data</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px;">
                                    Nenhum pedido encontrado
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><strong>#<?php echo $order['order_number']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td><?php echo formatPrice($order['total']); ?></td>
                                    <td>
                                        <?php
                                        $paymentColors = [
                                            'pending' => 'warning',
                                            'paid' => 'success',
                                            'failed' => 'danger'
                                        ];
                                        $paymentLabels = [
                                            'pending' => 'Pendente',
                                            'paid' => 'Pago',
                                            'failed' => 'Falhou'
                                        ];
                                        ?>
                                        <span class="badge badge-<?php echo $paymentColors[$order['payment_status']]; ?>">
                                            <?php echo $paymentLabels[$order['payment_status']]; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'pending' => 'warning',
                                            'processing' => 'info',
                                            'shipped' => 'info',
                                            'delivered' => 'success',
                                            'cancelled' => 'danger'
                                        ];
                                        $statusLabels = [
                                            'pending' => 'Pendente',
                                            'processing' => 'Processando',
                                            'shipped' => 'Enviado',
                                            'delivered' => 'Entregue',
                                            'cancelled' => 'Cancelado'
                                        ];
                                        ?>
                                        <span class="badge badge-<?php echo $statusColors[$order['order_status']]; ?>">
                                            <?php echo $statusLabels[$order['order_status']]; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
                                    <td>
                                        <button onclick="viewOrder(<?php echo $order['id']; ?>)" class="btn-icon" title="Ver Detalhes">👁️</button>
                                        <button onclick="editOrder(<?php echo $order['id']; ?>)" class="btn-icon" title="Editar Status">✏️</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
    function viewOrder(orderId) {
        window.open('order-details.php?id=' + orderId, '_blank');
    }
    
    function editOrder(orderId) {
        const newStatus = prompt('Novo status (pending/processing/shipped/delivered/cancelled):');
        if (newStatus) {
            const trackingCode = prompt('Código de rastreamento (se aplicável):');
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="update_order" value="1">
                <input type="hidden" name="order_id" value="${orderId}">
                <input type="hidden" name="order_status" value="${newStatus}">
                <input type="hidden" name="tracking_code" value="${trackingCode || ''}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
</body>
</html>