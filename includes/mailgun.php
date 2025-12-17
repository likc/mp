<?php
// Integração com Mailgun para envio de emails

function sendMailgunEmail($to, $subject, $htmlBody, $type = 'general') {
    global $pdo;
    
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.mailgun.net/v3/' . MAILGUN_DOMAIN . '/messages',
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => 'api:' . MAILGUN_API_KEY,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'from' => MAILGUN_FROM_NAME . ' <' . MAILGUN_FROM_EMAIL . '>',
            'to' => $to,
            'subject' => $subject,
            'html' => $htmlBody
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Log do email
    $status = ($httpCode == 200) ? 'sent' : 'failed';
    $stmt = $pdo->prepare("INSERT INTO email_logs (recipient, subject, type, status) VALUES (?, ?, ?, ?)");
    $stmt->execute([$to, $subject, $type, $status]);
    
    return $httpCode == 200;
}

function sendWelcomeEmail($userEmail, $userName) {
    $subject = "Bem-vindo à Mantos Premium! ⚽";
    
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #1a472a 0%, #2d7a4a 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .button { display: inline-block; background: #2d7a4a; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🏆 Mantos Premium 🏆</h1>
                <p>Bem-vindo à família!</p>
            </div>
            <div class='content'>
                <h2>Olá, $userName!</h2>
                <p>É um prazer tê-lo(a) conosco na <strong>Mantos Premium</strong>, sua loja oficial de camisas e produtos dos maiores times do mundo!</p>
                
                <p>Aqui você encontra:</p>
                <ul>
                    <li>✅ Produtos oficiais e licenciados</li>
                    <li>✅ Entrega rápida e segura</li>
                    <li>✅ Atendimento especializado</li>
                    <li>✅ Promoções exclusivas</li>
                </ul>
                
                <p style='text-align: center;'>
                    <a href='" . SITE_URL . "' class='button'>Começar a Comprar</a>
                </p>
                
                <p>Fique atento às nossas newsletters com promoções imperdíveis!</p>
            </div>
            <div class='footer'>
                <p>Mantos Premium - Paixão em cada fio</p>
                <p>" . SITE_URL . "</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendMailgunEmail($userEmail, $subject, $body, 'welcome');
}

function sendOrderConfirmationEmail($orderId) {
    global $pdo;
    
    // Buscar dados do pedido
    $stmt = $pdo->prepare("
        SELECT o.*, u.name, u.email 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if (!$order) return false;
    
    // Buscar itens do pedido
    $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll();
    
    $subject = "Pedido #" . $order['order_number'] . " confirmado! 🎉";
    
    $itemsHtml = '';
    foreach ($items as $item) {
        $itemsHtml .= "
            <tr>
                <td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$item['product_name']} - Tamanho: {$item['size']}</td>
                <td style='padding: 10px; border-bottom: 1px solid #ddd; text-align: center;'>{$item['quantity']}</td>
                <td style='padding: 10px; border-bottom: 1px solid #ddd; text-align: right;'>" . formatPrice($item['price']) . "</td>
                <td style='padding: 10px; border-bottom: 1px solid #ddd; text-align: right;'>" . formatPrice($item['price'] * $item['quantity']) . "</td>
            </tr>
        ";
    }
    
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #1a472a 0%, #2d7a4a 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; background: white; }
            th { background: #2d7a4a; color: white; padding: 10px; text-align: left; }
            .total-row { background: #e8f5e9; font-weight: bold; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; border-radius: 0 0 10px 10px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🏆 Pedido Confirmado!</h1>
                <p>Pedido #{$order['order_number']}</p>
            </div>
            <div class='content'>
                <h2>Olá, {$order['name']}!</h2>
                <p>Recebemos seu pedido com sucesso! Estamos preparando tudo com muito carinho para enviar até você.</p>
                
                <h3>Detalhes do Pedido:</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th style='text-align: center;'>Qtd</th>
                            <th style='text-align: right;'>Preço Unit.</th>
                            <th style='text-align: right;'>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        $itemsHtml
                        <tr>
                            <td colspan='3' style='padding: 10px; text-align: right;'><strong>Subtotal:</strong></td>
                            <td style='padding: 10px; text-align: right;'>" . formatPrice($order['subtotal']) . "</td>
                        </tr>
                        <tr>
                            <td colspan='3' style='padding: 10px; text-align: right;'><strong>Frete:</strong></td>
                            <td style='padding: 10px; text-align: right;'>" . formatPrice($order['shipping_cost']) . "</td>
                        </tr>";
    
    if ($order['discount'] > 0) {
        $body .= "
                        <tr>
                            <td colspan='3' style='padding: 10px; text-align: right;'><strong>Desconto:</strong></td>
                            <td style='padding: 10px; text-align: right; color: #2d7a4a;'>-" . formatPrice($order['discount']) . "</td>
                        </tr>";
    }
    
    $body .= "
                        <tr class='total-row'>
                            <td colspan='3' style='padding: 15px; text-align: right; font-size: 18px;'><strong>TOTAL:</strong></td>
                            <td style='padding: 15px; text-align: right; font-size: 18px;'><strong>" . formatPrice($order['total']) . "</strong></td>
                        </tr>
                    </tbody>
                </table>
                
                <h3>Endereço de Entrega:</h3>
                <p style='background: white; padding: 15px; border-left: 4px solid #2d7a4a;'>
                    " . nl2br($order['shipping_address']) . "
                </p>
                
                <p><strong>Forma de Pagamento:</strong> " . strtoupper($order['payment_method']) . "</p>
                <p><strong>Status:</strong> " . ucfirst($order['order_status']) . "</p>
                
                <p>Você receberá um novo email assim que o pedido for enviado, com o código de rastreamento.</p>
            </div>
            <div class='footer'>
                <p>Dúvidas? Entre em contato: " . ADMIN_EMAIL . "</p>
                <p>Mantos Premium - Paixão em cada fio</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendMailgunEmail($order['email'], $subject, $body, 'order_confirmation');
}

function sendShippingNotificationEmail($orderId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT o.*, u.name, u.email 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if (!$order || !$order['tracking_code']) return false;
    
    $subject = "Seu pedido #" . $order['order_number'] . " foi enviado! 📦";
    
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #1a472a 0%, #2d7a4a 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .tracking { background: white; padding: 20px; border-left: 4px solid #2d7a4a; margin: 20px 0; text-align: center; }
            .tracking-code { font-size: 24px; font-weight: bold; color: #2d7a4a; letter-spacing: 2px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>📦 Pedido Enviado!</h1>
                <p>Pedido #{$order['order_number']}</p>
            </div>
            <div class='content'>
                <h2>Olá, {$order['name']}!</h2>
                <p>Ótimas notícias! Seu pedido já está a caminho! 🚚</p>
                
                <div class='tracking'>
                    <p>Código de Rastreamento:</p>
                    <p class='tracking-code'>{$order['tracking_code']}</p>
                    <p style='font-size: 12px; color: #666; margin-top: 10px;'>
                        Acompanhe sua entrega nos Correios ou transportadora
                    </p>
                </div>
                
                <p>O prazo de entrega pode variar de acordo com sua região. Geralmente o pedido chega em 5-10 dias úteis.</p>
                
                <p>Você receberá uma notificação quando o pedido for entregue.</p>
                
                <p style='margin-top: 30px;'>Obrigado por comprar na Mantos Premium! ⚽</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendMailgunEmail($order['email'], $subject, $body, 'shipping');
}

function sendPromotionalEmail($to, $subject, $message) {
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #1a472a 0%, #2d7a4a 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .button { display: inline-block; background: #2d7a4a; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🏆 Mantos Premium 🏆</h1>
            </div>
            <div class='content'>
                $message
                <p style='text-align: center;'>
                    <a href='" . SITE_URL . "' class='button'>Aproveitar Agora</a>
                </p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendMailgunEmail($to, $subject, $body, 'promotional');
}
?>
