<?php
// Configuração do Sistema - Mantos Premium

// Iniciar sessão ANTES de qualquer configuração
if (session_status() === PHP_SESSION_NONE) {
    // Configurar sessão ANTES de session_start
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Mude para 1 se usar HTTPS
    
    session_start();
}

// Timezone - Japão (Tokyo)
date_default_timezone_set('Asia/Tokyo');

// Base Path - Subdiretório
define('BASE_PATH', '');

// Configuração do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'minec761_mantospremium');
define('DB_USER', 'minec761_mantospremium');
define('DB_PASS', 'bvncm203o490');

// Charset
define('DB_CHARSET', 'utf8mb4');

// Conexão PDO
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

// Chave de criptografia para settings
define('ENCRYPTION_KEY', md5(__DIR__));

// Error reporting (desabilitar em produção)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não mostrar erros na tela
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php-errors.log');