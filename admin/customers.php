<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireAdmin();

$pageTitle = 'Gerenciar Clientes';

// Deletar cliente
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_customer'])) {
    $customerId = intval($_POST['customer_id']);
    
    try {
        // Verificar se o cliente tem pedidos
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
        $stmt->execute([$customerId]);
        $orderCount = $stmt->fetchColumn();
        
        if ($orderCount > 0) {
            setFlashMessage('Não é possível deletar cliente com pedidos.', 'error');
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND is_admin = 0");
            $stmt->execute([$customerId]);
            setFlashMessage('Cliente deletado com sucesso!', 'success');
        }
    } catch (Exception $e) {
        setFlashMessage('Erro ao deletar cliente.', 'error');
        logError('Erro ao deletar cliente: ' . $e->getMessage());
    }
    
    redirect('customers.php');
}

// Filtros
$search = $_GET['search'] ?? '';

// Query base
$sql = "SELECT u.*, 
        COUNT(DISTINCT o.id) as order_count,
        SUM(o.total) as total_spent
        FROM users u
        LEFT JOIN orders o ON u.id = o.user_id
        WHERE u.is_admin = 0";

$params = [];

// Filtro por busca
if ($search) {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " GROUP BY u.id ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

// Estatísticas
$stats = [];
$stats['total_customers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 0")->fetchColumn();
$stats['customers_with_orders'] = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM orders")->fetchColumn();
$stats['new_this_month'] = $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 0 AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())")->fetchColumn();
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
                <li><a href="orders.php">🛒 Pedidos</a></li>
                <li><a href="customers.php" class="active">👥 Clientes</a></li>
                <li><a href="coupons.php">🎫 Cupons</a></li>
                <li><a href="shipping.php">🚚 Frete</a></li>
                <li><a href="settings.php">⚙️ Configurações</a></li>
                <li><a href="../index.php" style="margin-top: 30px;">🏠 Ir para o Site</a></li>
                <li><a href="../logout.php">🚪 Sair</a></li>
            </ul>
        </aside>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>👥 Gerenciar Clientes</h1>
                <p>Visualize e gerencie todos os clientes da loja</p>
            </div>
            
            <!-- Estatísticas -->
            <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 30px;">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_customers']; ?></div>
                    <div class="stat-label">Total de Clientes</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['customers_with_orders']; ?></div>
                    <div class="stat-label">Com Pedidos</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['new_this_month']; ?></div>
                    <div class="stat-label">Novos Este Mês</div>
                </div>
            </div>
            
            <!-- Filtros -->
            <div style="background: white; padding: 20px; border-radius: 15px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <form method="GET">
                    <input type="text" name="search" placeholder="Buscar por nome, email ou telefone..." 
                           value="<?php echo htmlspecialchars($search); ?>"
                           style="width: 100%; padding: 10px; border: 2px solid var(--border); border-radius: 5px;">
                </form>
            </div>
            
            <!-- Tabela de Clientes -->
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Telefone</th>
                            <th>Pedidos</th>
                            <th>Total Gasto</th>
                            <th>Cadastro</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px;">
                                    Nenhum cliente encontrado
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td><strong>#<?php echo $customer['id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($customer['name']); ?></td>
                                    <td>
                                        <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>" style="color: var(--primary-green);">
                                            <?php echo htmlspecialchars($customer['email']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($customer['phone'] ?: '-'); ?></td>
                                    <td><?php echo $customer['order_count']; ?></td>
                                    <td><?php echo formatPrice($customer['total_spent'] ?? 0); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($customer['created_at'])); ?></td>
                                    <td>
                                        <button onclick="viewCustomer(<?php echo $customer['id']; ?>)" class="btn-icon" title="Ver Detalhes">👁️</button>
                                        <?php if ($customer['order_count'] == 0): ?>
                                            <button onclick="deleteCustomer(<?php echo $customer['id']; ?>)" class="btn-icon" title="Deletar">🗑️</button>
                                        <?php endif; ?>
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
    function viewCustomer(customerId) {
        alert('Visualizar detalhes do cliente #' + customerId + '\n\nEm produção, isso abrirá uma página com:\n- Histórico de pedidos\n- Endereços cadastrados\n- Estatísticas de compra');
    }
    
    function deleteCustomer(customerId) {
        if (confirm('Tem certeza que deseja DELETAR permanentemente este cliente?\n\nEsta ação não pode ser desfeita!')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="delete_customer" value="1">
                <input type="hidden" name="customer_id" value="${customerId}">
            `;
            document.body.appendChild(form);
            form.submit();
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
    </script>
</body>
</html>