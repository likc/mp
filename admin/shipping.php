<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireAdmin();

$pageTitle = 'Configurar Frete';

// Salvar configurações de frete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_shipping'])) {
    $freeShippingEnabled = isset($_POST['free_shipping_enabled']) ? 1 : 0;
    $freeShippingMinValue = floatval($_POST['free_shipping_min_value']);
    $freeShippingMinQuantity = intval($_POST['free_shipping_min_quantity']);
    $defaultShippingCost = floatval($_POST['default_shipping_cost']);
    
    try {
        // Verificar se já existe configuração
        $stmt = $pdo->query("SELECT id FROM shipping_config LIMIT 1");
        $exists = $stmt->fetch();
        
        if ($exists) {
            // Atualizar
            $stmt = $pdo->prepare("
                UPDATE shipping_config 
                SET free_shipping_enabled = ?,
                    free_shipping_min_value = ?,
                    free_shipping_min_quantity = ?,
                    default_shipping_cost = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $freeShippingEnabled,
                $freeShippingMinValue,
                $freeShippingMinQuantity,
                $defaultShippingCost,
                $exists['id']
            ]);
        } else {
            // Criar
            $stmt = $pdo->prepare("
                INSERT INTO shipping_config (
                    free_shipping_enabled,
                    free_shipping_min_value,
                    free_shipping_min_quantity,
                    default_shipping_cost
                ) VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $freeShippingEnabled,
                $freeShippingMinValue,
                $freeShippingMinQuantity,
                $defaultShippingCost
            ]);
        }
        
        setFlashMessage('Configurações de frete salvas com sucesso!', 'success');
    } catch (Exception $e) {
        setFlashMessage('Erro ao salvar configurações.', 'error');
        logError('Erro ao salvar frete: ' . $e->getMessage());
    }
    
    redirect('shipping.php');
}

// Buscar configurações atuais
$stmt = $pdo->query("SELECT * FROM shipping_config LIMIT 1");
$config = $stmt->fetch();

// Se não existir, usar valores padrão (em Yen)
if (!$config) {
    $config = [
        'free_shipping_enabled' => 1,
        'free_shipping_min_value' => 10000.00,  // ¥10,000
        'free_shipping_min_quantity' => 3,
        'default_shipping_cost' => 800.00  // ¥800
    ];
}

// Estatísticas de frete
$stats = [];
$stats['orders_with_free_shipping'] = $pdo->query("SELECT COUNT(*) FROM orders WHERE shipping_cost = 0")->fetchColumn();
$stats['orders_with_paid_shipping'] = $pdo->query("SELECT COUNT(*) FROM orders WHERE shipping_cost > 0")->fetchColumn();
$stats['total_shipping_revenue'] = $pdo->query("SELECT SUM(shipping_cost) FROM orders")->fetchColumn() ?? 0;
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
        .shipping-preview {
            background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%);
            padding: 30px;
            border-radius: 15px;
            margin-top: 30px;
            border: 3px solid var(--primary-green);
        }
        .preview-example {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .preview-example:last-child {
            margin-bottom: 0;
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
                <li><a href="shipping.php" class="active">🚚 Frete</a></li>
                <li><a href="emails.php">📧 Emails</a></li>
                <li><a href="settings.php">⚙️ Configurações</a></li>
                <li><a href="../index.php" style="margin-top: 30px;">🏠 Ir para o Site</a></li>
                <li><a href="../logout.php">🚪 Sair</a></li>
            </ul>
        </aside>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>🚚 Configurar Frete</h1>
                <p>Gerencie as regras de cálculo de frete da loja</p>
            </div>
            
            <!-- Estatísticas -->
            <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 30px;">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['orders_with_free_shipping']; ?></div>
                    <div class="stat-label">Pedidos com Frete Grátis</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['orders_with_paid_shipping']; ?></div>
                    <div class="stat-label">Pedidos com Frete Pago</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo formatPrice($stats['total_shipping_revenue']); ?></div>
                    <div class="stat-label">Total Arrecadado em Frete</div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <!-- Formulário de Configuração -->
                <div>
                    <form method="POST" action="">
                        <div style="background: white; padding: 30px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 30px;">
                            <h2 style="margin-bottom: 25px; color: var(--primary-green);">
                                ⚙️ Configurações de Frete
                            </h2>
                            
                            <!-- Valor Padrão do Frete -->
                            <div class="form-group">
                                <label>Valor Padrão do Frete (R$) *</label>
                                <input type="number" name="default_shipping_cost" 
                                       value="<?php echo $config['default_shipping_cost']; ?>"
                                       min="0" step="0.01" required
                                       placeholder="15.00">
                                <p style="font-size: 12px; color: #666; margin-top: 5px;">
                                    Valor cobrado quando não há frete grátis
                                </p>
                            </div>
                            
                            <!-- Habilitar Frete Grátis -->
                            <div class="form-group">
                                <label style="display: flex; align-items: center; cursor: pointer; padding: 15px; background: #f9f9f9; border-radius: 8px;">
                                    <input type="checkbox" name="free_shipping_enabled" 
                                           id="free_shipping_enabled"
                                           <?php echo $config['free_shipping_enabled'] ? 'checked' : ''; ?>
                                           style="margin-right: 10px;"
                                           onchange="toggleFreeShipping()">
                                    <div>
                                        <strong>🎉 Habilitar Frete Grátis</strong>
                                        <div style="font-size: 12px; color: #666; margin-top: 5px;">
                                            Ative para oferecer frete grátis sob condições
                                        </div>
                                    </div>
                                </label>
                            </div>
                            
                            <!-- Opções de Frete Grátis -->
                            <div id="freeShippingOptions" style="display: <?php echo $config['free_shipping_enabled'] ? 'block' : 'none'; ?>;">
                                <div style="background: #E8F5E9; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                                    <h3 style="color: var(--primary-green); margin-bottom: 15px; font-size: 18px;">
                                        Condições para Frete Grátis
                                    </h3>
                                    <p style="color: #666; font-size: 14px; margin-bottom: 20px;">
                                        O frete será grátis quando <strong>qualquer uma</strong> das condições for atendida:
                                    </p>
                                    
                                    <div class="form-group">
                                        <label>💰 Valor Mínimo do Pedido (R$)</label>
                                        <input type="number" name="free_shipping_min_value" 
                                               value="<?php echo $config['free_shipping_min_value']; ?>"
                                               min="0" step="0.01"
                                               placeholder="200.00">
                                        <p style="font-size: 12px; color: #666; margin-top: 5px;">
                                            Ex: Frete grátis acima de R$ 200,00 (0 = desabilitar esta condição)
                                        </p>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>📦 Quantidade Mínima de Produtos</label>
                                        <input type="number" name="free_shipping_min_quantity" 
                                               value="<?php echo $config['free_shipping_min_quantity']; ?>"
                                               min="0"
                                               placeholder="3">
                                        <p style="font-size: 12px; color: #666; margin-top: 5px;">
                                            Ex: Frete grátis em 3 ou mais produtos (0 = desabilitar esta condição)
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" name="save_shipping" class="btn btn-primary" style="width: 100%;">
                                💾 Salvar Configurações
                            </button>
                        </div>
                    </form>
                    
                    <!-- Informações Importantes -->
                    <div style="background: #FFF3CD; padding: 25px; border-radius: 15px; border-left: 4px solid #FFD700;">
                        <h3 style="color: #D97706; margin-bottom: 15px;">💡 Informações Importantes</h3>
                        <ul style="color: #666; line-height: 2; padding-left: 20px;">
                            <li>As condições de frete grátis funcionam com <strong>OU lógico</strong></li>
                            <li>Se o cliente atingir <strong>qualquer uma</strong> das condições, ganha frete grátis</li>
                            <li>Coloque <strong>0</strong> em uma condição para desabilitá-la</li>
                            <li>O desconto de cupom é considerado no cálculo do valor</li>
                            <li>As regras aparecem automaticamente no carrinho do cliente</li>
                        </ul>
                    </div>
                </div>
                
                <!-- Preview/Exemplos -->
                <div>
                    <div style="background: white; padding: 30px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                        <h2 style="margin-bottom: 25px; color: var(--primary-green);">
                            👁️ Preview - Como Funciona
                        </h2>
                        
                        <div class="shipping-preview">
                            <h3 style="color: var(--primary-green); margin-bottom: 20px; text-align: center;">
                                Exemplos de Cálculo
                            </h3>
                            
                            <div class="preview-example">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong>Carrinho: R$ 150,00</strong><br>
                                        <small style="color: #666;">2 produtos</small>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-size: 18px; font-weight: 700; color: #e74c3c;">
                                            <?php echo formatPrice($config['default_shipping_cost']); ?>
                                        </div>
                                        <small style="color: #666;">Frete Normal</small>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($config['free_shipping_enabled']): ?>
                                <?php if ($config['free_shipping_min_value'] > 0): ?>
                                    <div class="preview-example">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <div>
                                                <strong>Carrinho: <?php echo formatPrice($config['free_shipping_min_value']); ?></strong><br>
                                                <small style="color: #666;">2 produtos</small>
                                            </div>
                                            <div style="text-align: right;">
                                                <div style="font-size: 18px; font-weight: 700; color: var(--primary-green);">
                                                    GRÁTIS 🎉
                                                </div>
                                                <small style="color: #666;">Valor atingido!</small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($config['free_shipping_min_quantity'] > 0): ?>
                                    <div class="preview-example">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <div>
                                                <strong>Carrinho: R$ 150,00</strong><br>
                                                <small style="color: #666;"><?php echo $config['free_shipping_min_quantity']; ?> produtos</small>
                                            </div>
                                            <div style="text-align: right;">
                                                <div style="font-size: 18px; font-weight: 700; color: var(--primary-green);">
                                                    GRÁTIS 🎉
                                                </div>
                                                <small style="color: #666;">Quantidade atingida!</small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Como os clientes veem -->
                        <div style="margin-top: 30px; padding: 25px; background: #f9f9f9; border-radius: 10px;">
                            <h3 style="margin-bottom: 15px;">📱 O que os clientes veem:</h3>
                            
                            <?php if ($config['free_shipping_enabled']): ?>
                                <div style="background: #FFF3CD; padding: 15px; border-radius: 8px; margin-bottom: 10px;">
                                    <strong>💡 No Carrinho:</strong><br>
                                    <small style="color: #666;">
                                        <?php if ($config['free_shipping_min_value'] > 0): ?>
                                            "Faltam apenas R$ XX,XX para ganhar frete grátis!"
                                        <?php endif; ?>
                                        <?php if ($config['free_shipping_min_value'] > 0 && $config['free_shipping_min_quantity'] > 0): ?>
                                            <br>ou<br>
                                        <?php endif; ?>
                                        <?php if ($config['free_shipping_min_quantity'] > 0): ?>
                                            "Adicione mais X produto(s) para ganhar frete grátis!"
                                        <?php endif; ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                            
                            <div style="background: #E8F5E9; padding: 15px; border-radius: 8px;">
                                <strong>🏠 No Header:</strong><br>
                                <small style="color: #666;">
                                    <?php if ($config['free_shipping_enabled']): ?>
                                        "⚽ FRETE GRÁTIS acima de R$ <?php echo number_format($config['free_shipping_min_value'], 0, ',', '.'); ?> ou em <?php echo $config['free_shipping_min_quantity']; ?>+ produtos"
                                    <?php else: ?>
                                        "⚽ Entrega rápida em todo o Brasil"
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dicas de Otimização -->
                    <div style="background: #E3F2FD; padding: 25px; border-radius: 15px; margin-top: 30px;">
                        <h3 style="color: #1976D2; margin-bottom: 15px;">🎯 Dicas para Otimizar</h3>
                        <ul style="color: #666; line-height: 2; padding-left: 20px; font-size: 14px;">
                            <li><strong>Valor mínimo:</strong> Defina um valor 20-30% acima do ticket médio</li>
                            <li><strong>Quantidade:</strong> Incentive vendas múltiplas (2-3 produtos)</li>
                            <li><strong>Teste A/B:</strong> Experimente diferentes valores e analise conversão</li>
                            <li><strong>Promoções:</strong> Combine com cupons para aumentar conversão</li>
                            <li><strong>Comunicação:</strong> Deixe bem visível no site todo</li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    function toggleFreeShipping() {
        const checkbox = document.getElementById('free_shipping_enabled');
        const options = document.getElementById('freeShippingOptions');
        options.style.display = checkbox.checked ? 'block' : 'none';
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
