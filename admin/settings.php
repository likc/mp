<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/settings.php';

requireAdmin();

$pageTitle = 'Configurações';

// Salvar configurações
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_settings'])) {
    $category = $_POST['category'];
    $success = true;
    
    foreach ($_POST as $key => $value) {
        if ($key === 'save_settings' || $key === 'category') continue;
        
        // Verificar se deve ser criptografado
        $encrypt = (strpos($key, 'key') !== false || 
                   strpos($key, 'secret') !== false || 
                   strpos($key, 'password') !== false ||
                   $key === 'mailgun_api_key' ||
                   $key === 'paypal_client_id' ||
                   $key === 'paypal_secret' ||
                   $key === 'stripe_public_key' ||
                   $key === 'stripe_secret_key' ||
                   $key === 'skrill_secret_word');
        
        if (!saveSetting($key, sanitize($value), $encrypt)) {
            $success = false;
        }
    }
    
    if ($success) {
        setFlashMessage('Configurações salvas com sucesso!', 'success');
    } else {
        setFlashMessage('Erro ao salvar algumas configurações.', 'error');
    }
    
    redirect('settings.php?tab=' . $category);
}

// Carregar configurações
$generalSettings = getSettingsByCategory('general');
$emailSettings = getSettingsByCategory('email');
$paymentSettings = getSettingsByCategory('payment');

$activeTab = $_GET['tab'] ?? 'general';
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
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid var(--border);
        }
        .tab {
            padding: 15px 30px;
            background: transparent;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            color: var(--text-light);
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        .tab:hover {
            color: var(--primary-green);
        }
        .tab.active {
            color: var(--primary-green);
            border-bottom-color: var(--primary-green);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .settings-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .settings-section h3 {
            color: var(--primary-green);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border);
        }
        .alert-info {
            background: #E8F5E9;
            border-left: 4px solid var(--primary-green);
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .help-text {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
            font-style: italic;
        }
        .password-field {
            position: relative;
        }
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 18px;
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
                <li><a href="shipping.php">🚚 Frete</a></li>
                <li><a href="emails.php">📧 Emails</a></li>
                <li><a href="settings.php" class="active">⚙️ Configurações</a></li>
                <li><a href="../index.php" style="margin-top: 30px;">🏠 Ir para o Site</a></li>
                <li><a href="../logout.php">🚪 Sair</a></li>
            </ul>
        </aside>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>⚙️ Configurações do Sistema</h1>
                <p>Gerencie todas as configurações da loja</p>
            </div>
            
            <div class="tabs">
                <button class="tab <?php echo $activeTab === 'general' ? 'active' : ''; ?>" onclick="switchTab('general')">
                    🏪 Informações Gerais
                </button>
                <button class="tab <?php echo $activeTab === 'email' ? 'active' : ''; ?>" onclick="switchTab('email')">
                    📧 Email (Mailgun)
                </button>
                <button class="tab <?php echo $activeTab === 'payment' ? 'active' : ''; ?>" onclick="switchTab('payment')">
                    💳 Pagamentos
                </button>
            </div>
            
            <div id="tab-general" class="tab-content <?php echo $activeTab === 'general' ? 'active' : ''; ?>">
                <form method="POST" action="">
                    <input type="hidden" name="category" value="general">
                    
                    <div class="settings-section">
                        <h3>📌 Informações da Loja</h3>
                        
                        <div class="form-group">
                            <label>Nome da Loja *</label>
                            <input type="text" name="site_name" value="<?php echo htmlspecialchars($generalSettings['site_name']['value']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>URL do Site *</label>
                            <input type="url" name="site_url" value="<?php echo htmlspecialchars($generalSettings['site_url']['value']); ?>" required placeholder="https://mantospremium.com">
                            <p class="help-text">URL completa do seu site (com https://)</p>
                        </div>
                        
                        <div class="form-group">
                            <label>Email Principal *</label>
                            <input type="email" name="admin_email" value="<?php echo htmlspecialchars($generalSettings['admin_email']['value']); ?>" required>
                            <p class="help-text">Email principal para contato e notificações</p>
                        </div>
                        
                        <div class="form-group">
                            <label>Telefone</label>
                            <input type="text" name="site_phone" value="<?php echo htmlspecialchars($generalSettings['site_phone']['value']); ?>" placeholder="(11) 98765-4321">
                        </div>
                    </div>
                    
                    <button type="submit" name="save_settings" class="btn btn-primary" style="width: 100%;">
                        💾 Salvar Configurações Gerais
                    </button>
                </form>
            </div>
            
            <div id="tab-email" class="tab-content <?php echo $activeTab === 'email' ? 'active' : ''; ?>">
                <form method="POST" action="">
                    <input type="hidden" name="category" value="email">
                    
                    <div class="alert-info">
                        <strong>📌 Como configurar Mailgun:</strong><br>
                        1. Crie uma conta em <a href="https://www.mailgun.com" target="_blank" style="color: var(--primary-green);">mailgun.com</a><br>
                        2. Vá em Sending → Domains<br>
                        3. Copie sua API Key e Domain<br>
                        4. Cole aqui abaixo
                    </div>
                    
                    <div class="settings-section">
                        <h3>📧 Configurações do Mailgun</h3>
                        
                        <div class="form-group">
                            <label>API Key * 🔒</label>
                            <div class="password-field">
                                <input type="password" id="mailgun_api_key" name="mailgun_api_key" 
                                       value="<?php echo htmlspecialchars($emailSettings['mailgun_api_key']['value']); ?>" 
                                       required placeholder="key-xxxxxxxxxxxxxxxxxx">
                                <span class="toggle-password" onclick="togglePassword('mailgun_api_key')">👁️</span>
                            </div>
                            <p class="help-text">Obtenha em: Settings → API Keys</p>
                        </div>
                        
                        <div class="form-group">
                            <label>Domain *</label>
                            <input type="text" name="mailgun_domain" value="<?php echo htmlspecialchars($emailSettings['mailgun_domain']['value']); ?>" required placeholder="mg.seudominio.com">
                            <p class="help-text">Exemplo: mg.mantospremium.com ou sandbox123.mailgun.org</p>
                        </div>
                        
                        <div class="form-group">
                            <label>Email de Envio *</label>
                            <input type="email" name="mailgun_from_email" value="<?php echo htmlspecialchars($emailSettings['mailgun_from_email']['value']); ?>" required placeholder="noreply@mantospremium.com">
                            <p class="help-text">Email que aparecerá como remetente</p>
                        </div>
                        
                        <div class="form-group">
                            <label>Nome do Remetente *</label>
                            <input type="text" name="mailgun_from_name" value="<?php echo htmlspecialchars($emailSettings['mailgun_from_name']['value']); ?>" required placeholder="Mantos Premium">
                        </div>
                    </div>
                    
                    <button type="submit" name="save_settings" class="btn btn-primary" style="width: 100%;">
                        💾 Salvar Configurações de Email
                    </button>
                </form>
            </div>
            
            <div id="tab-payment" class="tab-content <?php echo $activeTab === 'payment' ? 'active' : ''; ?>">
                <form method="POST" action="">
                    <input type="hidden" name="category" value="payment">
                    
                    <div class="settings-section">
                        <h3>💙 PayPal</h3>
                        
                        <div class="form-group">
                            <label>Modo</label>
                            <select name="paypal_mode">
                                <option value="sandbox" <?php echo $paymentSettings['paypal_mode']['value'] === 'sandbox' ? 'selected' : ''; ?>>Sandbox (Teste)</option>
                                <option value="live" <?php echo $paymentSettings['paypal_mode']['value'] === 'live' ? 'selected' : ''; ?>>Live (Produção)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Client ID 🔒</label>
                            <div class="password-field">
                                <input type="password" id="paypal_client_id" name="paypal_client_id" 
                                       value="<?php echo htmlspecialchars($paymentSettings['paypal_client_id']['value']); ?>" 
                                       placeholder="Obtenha em developer.paypal.com">
                                <span class="toggle-password" onclick="togglePassword('paypal_client_id')">👁️</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Secret 🔒</label>
                            <div class="password-field">
                                <input type="password" id="paypal_secret" name="paypal_secret" 
                                       value="<?php echo htmlspecialchars($paymentSettings['paypal_secret']['value']); ?>">
                                <span class="toggle-password" onclick="togglePassword('paypal_secret')">👁️</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="settings-section">
                        <h3>💜 Skrill</h3>
                        
                        <div class="form-group">
                            <label>Email Skrill</label>
                            <input type="email" name="skrill_email" value="<?php echo htmlspecialchars($paymentSettings['skrill_email']['value']); ?>" placeholder="seu@email.com">
                        </div>
                        
                        <div class="form-group">
                            <label>Secret Word 🔒</label>
                            <div class="password-field">
                                <input type="password" id="skrill_secret_word" name="skrill_secret_word" 
                                       value="<?php echo htmlspecialchars($paymentSettings['skrill_secret_word']['value']); ?>">
                                <span class="toggle-password" onclick="togglePassword('skrill_secret_word')">👁️</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Merchant ID</label>
                            <input type="text" name="skrill_merchant_id" value="<?php echo htmlspecialchars($paymentSettings['skrill_merchant_id']['value']); ?>">
                        </div>
                    </div>
                    
                    <div class="settings-section">
                        <h3>💳 Stripe (Cartão de Crédito)</h3>
                        
                        <div class="form-group">
                            <label>Modo</label>
                            <select name="stripe_mode">
                                <option value="test" <?php echo $paymentSettings['stripe_mode']['value'] === 'test' ? 'selected' : ''; ?>>Test (Teste)</option>
                                <option value="live" <?php echo $paymentSettings['stripe_mode']['value'] === 'live' ? 'selected' : ''; ?>>Live (Produção)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Publishable Key 🔒</label>
                            <div class="password-field">
                                <input type="password" id="stripe_public_key" name="stripe_public_key" 
                                       value="<?php echo htmlspecialchars($paymentSettings['stripe_public_key']['value']); ?>" 
                                       placeholder="pk_test_...">
                                <span class="toggle-password" onclick="togglePassword('stripe_public_key')">👁️</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Secret Key 🔒</label>
                            <div class="password-field">
                                <input type="password" id="stripe_secret_key" name="stripe_secret_key" 
                                       value="<?php echo htmlspecialchars($paymentSettings['stripe_secret_key']['value']); ?>" 
                                       placeholder="sk_test_...">
                                <span class="toggle-password" onclick="togglePassword('stripe_secret_key')">👁️</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="settings-section">
                        <h3>📱 PIX (Brasil)</h3>
                        
                        <div class="form-group">
                            <label>Chave PIX</label>
                            <input type="text" name="pix_key" value="<?php echo htmlspecialchars($paymentSettings['pix_key']['value']); ?>" placeholder="email@exemplo.com ou telefone ou CPF/CNPJ">
                        </div>
                        
                        <div class="form-group">
                            <label>Tipo de Chave</label>
                            <select name="pix_key_type">
                                <option value="email" <?php echo $paymentSettings['pix_key_type']['value'] === 'email' ? 'selected' : ''; ?>>Email</option>
                                <option value="phone" <?php echo $paymentSettings['pix_key_type']['value'] === 'phone' ? 'selected' : ''; ?>>Telefone</option>
                                <option value="cpf" <?php echo $paymentSettings['pix_key_type']['value'] === 'cpf' ? 'selected' : ''; ?>>CPF</option>
                                <option value="cnpj" <?php echo $paymentSettings['pix_key_type']['value'] === 'cnpj' ? 'selected' : ''; ?>>CNPJ</option>
                                <option value="random" <?php echo $paymentSettings['pix_key_type']['value'] === 'random' ? 'selected' : ''; ?>>Chave Aleatória</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Titular da Conta</label>
                            <input type="text" name="pix_holder" value="<?php echo htmlspecialchars($paymentSettings['pix_holder']['value']); ?>" placeholder="Nome completo ou razão social">
                        </div>
                    </div>
                    
                    <div class="settings-section">
                        <h3>🏦 Dados Bancários (Depósito/Transferência)</h3>
                        
                        <div class="form-group">
                            <label>Nome do Banco</label>
                            <input type="text" name="bank_name" value="<?php echo htmlspecialchars($paymentSettings['bank_name']['value']); ?>" placeholder="Banco do Brasil">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Agência</label>
                                <input type="text" name="bank_branch" value="<?php echo htmlspecialchars($paymentSettings['bank_branch']['value']); ?>" placeholder="1234-5">
                            </div>
                            
                            <div class="form-group">
                                <label>Conta</label>
                                <input type="text" name="bank_account" value="<?php echo htmlspecialchars($paymentSettings['bank_account']['value']); ?>" placeholder="12345-6">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Tipo de Conta</label>
                            <input type="text" name="bank_account_type" value="<?php echo htmlspecialchars($paymentSettings['bank_account_type']['value']); ?>" placeholder="Conta Corrente">
                        </div>
                        
                        <div class="form-group">
                            <label>Titular da Conta</label>
                            <input type="text" name="bank_holder" value="<?php echo htmlspecialchars($paymentSettings['bank_holder']['value']); ?>" placeholder="MANTOS PREMIUM LTDA">
                        </div>
                        
                        <div class="form-group">
                            <label>CPF/CNPJ</label>
                            <input type="text" name="bank_cpf_cnpj" value="<?php echo htmlspecialchars($paymentSettings['bank_cpf_cnpj']['value']); ?>" placeholder="12.345.678/0001-90">
                        </div>
                    </div>
                    
                    <button type="submit" name="save_settings" class="btn btn-primary" style="width: 100%;">
                        💾 Salvar Configurações de Pagamento
                    </button>
                </form>
            </div>
        </main>
    </div>

    <script>
    function switchTab(tabName) {
        // Ocultar todos os conteúdos
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        
        // Remover active de todas as tabs
        document.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Ativar tab e conteúdo selecionados
        document.getElementById('tab-' + tabName).classList.add('active');
        event.target.classList.add('active');
        
        // Atualizar URL
        const url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        window.history.pushState({}, '', url);
    }
    
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        if (field.type === 'password') {
            field.type = 'text';
        } else {
            field.type = 'password';
        }
    }
    </script>
</body>
</html>