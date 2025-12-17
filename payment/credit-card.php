<?php
require_once '../config/config.php';

require_once '../includes/functions.php';

requireLogin();

$pageTitle = 'Pagamento Cartão';
$orderNumber = $_GET['order'] ?? '';

if (empty($orderNumber)) {
    redirect('/index.php');
}

// Buscar pedido
$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? AND user_id = ?");
$stmt->execute([$orderNumber, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    redirect('/index.php');
}

include '../includes/header.php';
?>

<section class="section">
    <div class="container" style="max-width: 700px;">
        <div style="background: white; padding: 50px; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
            <div style="text-align: center; margin-bottom: 40px;">
                <div style="font-size: 60px; margin-bottom: 20px;">💳</div>
                <h1 style="font-size: 36px; margin-bottom: 10px;">Pagamento com Cartão</h1>
                <p style="color: #666;">Pedido #<?php echo htmlspecialchars($orderNumber); ?></p>
            </div>
            
            <!-- Valor a Pagar -->
            <div style="background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%); padding: 30px; border-radius: 15px; margin-bottom: 30px; text-align: center;">
                <div style="font-size: 16px; color: #666; margin-bottom: 10px;">Valor a Pagar:</div>
                <div style="font-size: 42px; font-weight: 900; color: var(--primary-green);">
                    <?php echo formatPrice($order['total']); ?>
                </div>
            </div>
            
            <!-- Formulário de Cartão -->
            <form id="payment-form" style="margin-bottom: 30px;">
                <div class="form-group">
                    <label>Número do Cartão *</label>
                    <input type="text" 
                           id="card-number" 
                           placeholder="1234 5678 9012 3456" 
                           maxlength="19"
                           required
                           style="font-size: 18px; letter-spacing: 2px;">
                    <div style="margin-top: 10px; display: flex; gap: 10px; justify-content: flex-end;">
                        <img src="https://img.icons8.com/color/48/000000/visa.png" alt="Visa" style="height: 30px;">
                        <img src="https://img.icons8.com/color/48/000000/mastercard.png" alt="Mastercard" style="height: 30px;">
                        <img src="https://img.icons8.com/color/48/000000/amex.png" alt="Amex" style="height: 30px;">
                        <img src="https://img.icons8.com/color/48/000000/elo.png" alt="Elo" style="height: 30px;">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Nome no Cartão *</label>
                    <input type="text" 
                           id="card-name" 
                           placeholder="Como está escrito no cartão" 
                           required
                           style="text-transform: uppercase;">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Validade *</label>
                        <input type="text" 
                               id="card-expiry" 
                               placeholder="MM/AA" 
                               maxlength="5"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label>CVV *</label>
                        <input type="text" 
                               id="card-cvv" 
                               placeholder="123" 
                               maxlength="4"
                               required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Número de Parcelas</label>
                    <select id="installments" style="font-size: 16px;">
                        <option value="1">1x de <?php echo formatPrice($order['total']); ?> sem juros</option>
                        <?php
                        $installmentValue = $order['total'] / 2;
                        for ($i = 2; $i <= 12; $i++) {
                            $value = $order['total'] / $i;
                            echo "<option value='$i'>{$i}x de " . formatPrice($value) . " sem juros</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <!-- CPF do Titular -->
                <div class="form-group">
                    <label>CPF do Titular *</label>
                    <input type="text" 
                           id="card-cpf" 
                           placeholder="000.000.000-00" 
                           maxlength="14"
                           required>
                </div>
                
                <!-- Endereço de Cobrança -->
                <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 10px;">
                    <h3 style="margin-bottom: 15px; font-size: 18px;">Endereço de Cobrança</h3>
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" id="use-shipping-address" checked style="margin-right: 10px;">
                        <span>Usar o mesmo endereço de entrega</span>
                    </label>
                </div>
                
                <!-- Botão de Pagamento -->
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 30px; font-size: 18px; padding: 15px;">
                    🔒 Pagar <?php echo formatPrice($order['total']); ?>
                </button>
            </form>
            
            <!-- Segurança -->
            <div style="background: #E8F5E9; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
                <h3 style="color: var(--primary-green); margin-bottom: 15px; text-align: center;">
                    🔒 Pagamento 100% Seguro
                </h3>
                <ul style="color: #666; line-height: 2; list-style: none; padding: 0;">
                    <li>✅ Conexão criptografada SSL</li>
                    <li>✅ Seus dados não são armazenados</li>
                    <li>✅ Proteção antifraude</li>
                    <li>✅ Processamento seguro certificado PCI-DSS</li>
                </ul>
            </div>
            
            <!-- Informações -->
            <div style="text-align: center; color: #666; font-size: 14px;">
                <p>💳 Aceitamos todos os principais cartões</p>
                <p style="margin-top: 10px;">Parcelamento em até 12x sem juros</p>
            </div>
            
            <!-- Link para voltar -->
            <div style="text-align: center; margin-top: 30px;">
                <a href="/checkout.php" style="color: var(--text-light); text-decoration: none;">
                    ← Escolher outra forma de pagamento
                </a>
            </div>
        </div>
    </div>
</section>

<script>
// Máscara para número do cartão
document.getElementById('card-number').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\s/g, '');
    let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
    e.target.value = formattedValue;
});

// Máscara para validade
document.getElementById('card-expiry').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length >= 2) {
        e.target.value = value.slice(0, 2) + '/' + value.slice(2, 4);
    } else {
        e.target.value = value;
    }
});

// Máscara para CPF
document.getElementById('card-cpf').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 11) {
        value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    }
    e.target.value = value;
});

// Apenas números no CVV
document.getElementById('card-cvv').addEventListener('input', function(e) {
    e.target.value = e.target.value.replace(/\D/g, '');
});

// Processar pagamento
document.getElementById('payment-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validações básicas
    const cardNumber = document.getElementById('card-number').value.replace(/\s/g, '');
    const cardName = document.getElementById('card-name').value;
    const cardExpiry = document.getElementById('card-expiry').value;
    const cardCvv = document.getElementById('card-cvv').value;
    const cardCpf = document.getElementById('card-cpf').value;
    
    if (cardNumber.length < 13) {
        alert('Número do cartão inválido');
        return;
    }
    
    if (!cardName || cardName.length < 3) {
        alert('Nome no cartão inválido');
        return;
    }
    
    if (cardExpiry.length !== 5) {
        alert('Validade inválida');
        return;
    }
    
    if (cardCvv.length < 3) {
        alert('CVV inválido');
        return;
    }
    
    if (cardCpf.replace(/\D/g, '').length !== 11) {
        alert('CPF inválido');
        return;
    }
    
    // Simular processamento
    const btn = this.querySelector('button');
    btn.disabled = true;
    btn.innerHTML = '⏳ Processando pagamento...';
    
    // Em produção, aqui você faria a chamada para a API do Stripe ou outro gateway
    setTimeout(function() {
        // Simular sucesso
        window.location.href = '/order-success.php?order=<?php echo $orderNumber; ?>';
    }, 2000);
});
</script>

<style>
input:focus {
    outline: none;
    border-color: var(--primary-green) !important;
}
</style>

<?php include '../includes/footer.php'; ?>
