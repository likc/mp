<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireAdmin();

$pageTitle = 'Dashboard Admin';

// Estatísticas
$stats = [];

// Total de pedidos
$stats['total_orders'] = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();

// Total de vendas
$stats['total_sales'] = $pdo->query("SELECT SUM(total) FROM orders WHERE payment_status = 'paid'")->fetchColumn() ?? 0;

// Pedidos pendentes
$stats['pending_orders'] = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetchColumn();

// Total de produtos
$stats['total_products'] = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

// Produtos com estoque baixo
$stats['low_stock'] = $pdo->query("SELECT COUNT(*) FROM products WHERE stock < 5 AND stock > 0")->fetchColumn();

// Total de clientes
$stats['total_customers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 0")->fetchColumn();

// Pedidos recentes
$recentOrders = $pdo->query("
    SELECT o.*, u.name as customer_name 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 10
")->fetchAll();

// Produtos mais vendidos
$topProducts = $pdo->query("
    SELECT p.name, p.team, SUM(oi.quantity) as total_sold
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    GROUP BY oi.product_id
    ORDER BY total_sold DESC
    LIMIT 5
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
</head>
<body>
    <div class="admin-container">
        <aside class="admin-sidebar">
            <div style="padding: 0 30px; margin-bottom: 30px;">
                <h2 style="font-family: 'Bebas Neue', sans-serif; font-size: 28px; letter-spacing: 2px;">
                    🏆 MANTOS<br>PREMIUM
                </h2>
                <p style="font-size: 12px; opacity: 0.8;">Painel Administrativo</p>
            </div>
            
            <ul class="admin-menu">
                <li><a href="dashboard.php" class="active">📊 Dashboard</a></li>
                <li><a href="products.php">📦 Produtos</a></li>
                <li><a href="orders.php">🛒 Pedidos</a></li>
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
                <h1>Dashboard</h1>
                <p>Bem-vindo de volta, <?php echo $_SESSION['user_name']; ?>! 👋</p>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_orders']; ?></div>
                    <div class="stat-label">Total de Pedidos</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo formatPrice($stats['total_sales']); ?></div>
                    <div class="stat-label">Total de Vendas</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['pending_orders']; ?></div>
                    <div class="stat-label">Pedidos Pendentes</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_products']; ?></div>
                    <div class="stat-label">Produtos Cadastrados</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['low_stock']; ?></div>
                    <div class="stat-label">Produtos com Estoque Baixo</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_customers']; ?></div>
                    <div class="stat-label">Clientes Cadastrados</div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-top: 30px;">
                <div class="data-table">
                    <h2 style="padding: 20px; background: var(--primary-green); color: white; margin: 0;">
                        Pedidos Recentes
                    </h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Pedido</th>
                                <th>Cliente</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentOrders)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 40px; color: #666;">
                                        Nenhum pedido ainda
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td><strong>#<?php echo $order['order_number']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                        <td><?php echo formatPrice($order['total']); ?></td>
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
                                        <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="data-table">
                    <h2 style="padding: 20px; background: var(--primary-green); color: white; margin: 0;">
                        Produtos Mais Vendidos
                    </h2>
                    <div style="padding: 20px;">
                        <?php if (empty($topProducts)): ?>
                            <p style="text-align: center; color: #666; padding: 20px;">
                                Nenhuma venda ainda
                            </p>
                        <?php else: ?>
                            <?php foreach ($topProducts as $product): ?>
                                <div style="padding: 15px 0; border-bottom: 1px solid var(--border);">
                                    <strong><?php echo htmlspecialchars($product['name']); ?></strong><br>
                                    <small style="color: #666;"><?php echo htmlspecialchars($product['team']); ?></small><br>
                                    <span style="color: var(--primary-green); font-weight: 600;">
                                        <?php echo $product['total_sold']; ?> vendidos
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>