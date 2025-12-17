<?php
// Funções para gerenciar configurações do sistema

// Chave de criptografia (gerada única por instalação)
define('ENCRYPTION_KEY', 'MaP2024_SecureKey_' . md5(__DIR__));

/**
 * Criptografar valor sensível
 */
function encryptSetting($value) {
    if (empty($value)) return '';
    
    $cipher = "AES-256-CBC";
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);
    $encrypted = openssl_encrypt($value, $cipher, ENCRYPTION_KEY, 0, $iv);
    
    return base64_encode($encrypted . '::' . $iv);
}

/**
 * Descriptografar valor sensível
 */
function decryptSetting($encrypted) {
    if (empty($encrypted)) return '';
    
    $cipher = "AES-256-CBC";
    $data = base64_decode($encrypted);
    
    if (strpos($data, '::') === false) return '';
    
    list($encrypted_data, $iv) = explode('::', $data, 2);
    
    return openssl_decrypt($encrypted_data, $cipher, ENCRYPTION_KEY, 0, $iv);
}

/**
 * Obter configuração do banco
 */
function getSetting($key, $default = '') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value, is_encrypted FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return $default;
        }
        
        $value = $result['setting_value'];
        
        // Descriptografar se necessário
        if ($result['is_encrypted']) {
            $value = decryptSetting($value);
        }
        
        return $value ?: $default;
        
    } catch (Exception $e) {
        logError('Error getting setting: ' . $e->getMessage());
        return $default;
    }
}

/**
 * Salvar configuração no banco
 */
function saveSetting($key, $value, $encrypt = false) {
    global $pdo;
    
    try {
        // Criptografar se necessário
        if ($encrypt && !empty($value)) {
            $value = encryptSetting($value);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, is_encrypted) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                is_encrypted = VALUES(is_encrypted)
        ");
        
        return $stmt->execute([$key, $value, $encrypt ? 1 : 0]);
        
    } catch (Exception $e) {
        logError('Error saving setting: ' . $e->getMessage());
        return false;
    }
}

/**
 * Obter todas as configurações de uma categoria
 */
function getSettingsByCategory($category) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM settings WHERE category = ? ORDER BY setting_key");
        $stmt->execute([$category]);
        $settings = $stmt->fetchAll();
        
        $result = [];
        foreach ($settings as $setting) {
            $value = $setting['setting_value'];
            
            // Descriptografar se necessário
            if ($setting['is_encrypted']) {
                $value = decryptSetting($value);
            }
            
            $result[$setting['setting_key']] = [
                'value' => $value,
                'type' => $setting['setting_type'],
                'encrypted' => $setting['is_encrypted']
            ];
        }
        
        return $result;
        
    } catch (Exception $e) {
        logError('Error getting settings by category: ' . $e->getMessage());
        return [];
    }
}

/**
 * Verificar se configurações estão completas
 */
function checkRequiredSettings($category) {
    $settings = getSettingsByCategory($category);
    
    foreach ($settings as $key => $data) {
        if (empty($data['value']) && $data['type'] === 'password') {
            return false;
        }
    }
    
    return true;
}

/**
 * Obter configuração como constante (compatibilidade)
 */
function defineFromSettings() {
    // Site
    if (!defined('SITE_NAME')) define('SITE_NAME', getSetting('site_name', 'Mantos Premium'));
    if (!defined('SITE_URL')) define('SITE_URL', getSetting('site_url', 'https://seusite.com.br'));
    if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', getSetting('admin_email', 'contato@mantospremium.com'));
    
    // Mailgun
    if (!defined('MAILGUN_API_KEY')) define('MAILGUN_API_KEY', getSetting('mailgun_api_key', ''));
    if (!defined('MAILGUN_DOMAIN')) define('MAILGUN_DOMAIN', getSetting('mailgun_domain', ''));
    if (!defined('MAILGUN_FROM_EMAIL')) define('MAILGUN_FROM_EMAIL', getSetting('mailgun_from_email', ''));
    if (!defined('MAILGUN_FROM_NAME')) define('MAILGUN_FROM_NAME', getSetting('mailgun_from_name', 'Mantos Premium'));
    
    // PayPal
    if (!defined('PAYPAL_MODE')) define('PAYPAL_MODE', getSetting('paypal_mode', 'sandbox'));
    if (!defined('PAYPAL_CLIENT_ID')) define('PAYPAL_CLIENT_ID', getSetting('paypal_client_id', ''));
    if (!defined('PAYPAL_SECRET')) define('PAYPAL_SECRET', getSetting('paypal_secret', ''));
    
    // Skrill
    if (!defined('SKRILL_EMAIL')) define('SKRILL_EMAIL', getSetting('skrill_email', ''));
    if (!defined('SKRILL_SECRET_WORD')) define('SKRILL_SECRET_WORD', getSetting('skrill_secret_word', ''));
    if (!defined('SKRILL_MERCHANT_ID')) define('SKRILL_MERCHANT_ID', getSetting('skrill_merchant_id', ''));
    
    // Stripe
    if (!defined('STRIPE_MODE')) define('STRIPE_MODE', getSetting('stripe_mode', 'test'));
    if (!defined('STRIPE_PUBLIC_KEY')) define('STRIPE_PUBLIC_KEY', getSetting('stripe_public_key', ''));
    if (!defined('STRIPE_SECRET_KEY')) define('STRIPE_SECRET_KEY', getSetting('stripe_secret_key', ''));
    
    // PIX
    if (!defined('PIX_KEY')) define('PIX_KEY', getSetting('pix_key', ''));
    if (!defined('PIX_KEY_TYPE')) define('PIX_KEY_TYPE', getSetting('pix_key_type', 'email'));
    if (!defined('PIX_HOLDER')) define('PIX_HOLDER', getSetting('pix_holder', ''));
    
    // Banco
    if (!defined('BANK_NAME')) define('BANK_NAME', getSetting('bank_name', ''));
    if (!defined('BANK_BRANCH')) define('BANK_BRANCH', getSetting('bank_branch', ''));
    if (!defined('BANK_ACCOUNT')) define('BANK_ACCOUNT', getSetting('bank_account', ''));
    if (!defined('BANK_ACCOUNT_TYPE')) define('BANK_ACCOUNT_TYPE', getSetting('bank_account_type', 'Conta Corrente'));
    if (!defined('BANK_HOLDER')) define('BANK_HOLDER', getSetting('bank_holder', ''));
    if (!defined('BANK_CPF_CNPJ')) define('BANK_CPF_CNPJ', getSetting('bank_cpf_cnpj', ''));
    
    // URLs do PayPal
    if (!defined('PAYPAL_API_URL')) {
        define('PAYPAL_API_URL', PAYPAL_MODE == 'sandbox' 
            ? 'https://api-m.sandbox.paypal.com' 
            : 'https://api-m.paypal.com');
    }
    
    // URLs do Skrill
    if (!defined('SKRILL_PAY_URL')) define('SKRILL_PAY_URL', 'https://pay.skrill.com');
    if (!defined('SKRILL_STATUS_URL')) define('SKRILL_STATUS_URL', 'https://www.skrill.com/app/query.pl');
}
?>
