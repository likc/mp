// Mantos Premium - JavaScript Principal

document.addEventListener('DOMContentLoaded', function() {
    
    // Busca de produtos
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const query = e.target.value;
            if (query.length >= 3) {
                searchProducts(query);
            }
        });
    }
    
    // Atualização de quantidade no carrinho
    const quantityInputs = document.querySelectorAll('.quantity-input');
    quantityInputs.forEach(input => {
        input.addEventListener('change', function() {
            updateCartQuantity(this.dataset.key, this.value);
        });
    });
    
    // Botões de quantidade
    const qtyButtons = document.querySelectorAll('.qty-btn');
    qtyButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.parentElement.querySelector('.quantity-input');
            let value = parseInt(input.value);
            
            if (this.classList.contains('qty-minus') && value > 1) {
                value--;
            } else if (this.classList.contains('qty-plus')) {
                value++;
            }
            
            input.value = value;
            updateCartQuantity(input.dataset.key, value);
        });
    });
    
    // Aplicar cupom
    const couponForm = document.getElementById('couponForm');
    if (couponForm) {
        couponForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const code = document.getElementById('couponCode').value;
            applyCoupon(code);
        });
    }
    
    // Validação de formulários
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
    });
    
    // Preview de imagem no upload
    const imageInputs = document.querySelectorAll('input[type="file"][accept*="image"]');
    imageInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            previewImage(e.target);
        });
    });
    
});

// Função para buscar produtos
function searchProducts(query) {
    fetch(`/api/search.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            displaySearchResults(data);
        })
        .catch(error => console.error('Erro na busca:', error));
}

// Exibir resultados da busca
function displaySearchResults(products) {
    // Implementar dropdown de resultados
    console.log('Resultados:', products);
}

// Atualizar quantidade no carrinho
function updateCartQuantity(key, quantity) {
    fetch('/api/cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'update',
            key: key,
            quantity: quantity
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Erro ao atualizar quantidade');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao atualizar carrinho');
    });
}

// Adicionar ao carrinho
function addToCart(productId, size) {
    const quantity = document.getElementById('quantity')?.value || 1;
    
    fetch('/api/cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'add',
            product_id: productId,
            size: size,
            quantity: parseInt(quantity)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Produto adicionado ao carrinho!', 'success');
            updateCartCount(data.cart_count);
        } else {
            showNotification(data.message || 'Erro ao adicionar produto', 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showNotification('Erro ao adicionar ao carrinho', 'error');
    });
}

// Remover do carrinho
function removeFromCart(key) {
    if (!confirm('Deseja remover este item do carrinho?')) {
        return;
    }
    
    fetch('/api/cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'remove',
            key: key
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Erro ao remover item');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao remover do carrinho');
    });
}

// Aplicar cupom
function applyCoupon(code) {
    fetch('/api/coupon.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'apply',
            code: code
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Cupom aplicado com sucesso!', 'success');
            location.reload();
        } else {
            showNotification(data.message || 'Cupom inválido', 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showNotification('Erro ao aplicar cupom', 'error');
    });
}

// Atualizar contador do carrinho
function updateCartCount(count) {
    const cartCount = document.querySelector('.cart-count');
    if (cartCount) {
        cartCount.textContent = count;
        cartCount.style.animation = 'pulse 0.3s';
        setTimeout(() => {
            cartCount.style.animation = '';
        }, 300);
    }
}

// Mostrar notificação
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `flash-message flash-${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Validar formulário
function validateForm(form) {
    const inputs = form.querySelectorAll('[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            isValid = false;
            input.style.borderColor = '#e74c3c';
            
            setTimeout(() => {
                input.style.borderColor = '';
            }, 2000);
        }
    });
    
    return isValid;
}

// Preview de imagem
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const preview = document.getElementById('imagePreview');
            if (preview) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Confirmar exclusão
function confirmDelete(message = 'Tem certeza que deseja excluir?') {
    return confirm(message);
}

// Formatar preço
function formatPrice(value) {
    return 'R$ ' + parseFloat(value).toFixed(2).replace('.', ',');
}

// CEP lookup (opcional - integração com API)
function searchCEP(cep) {
    cep = cep.replace(/\D/g, '');
    
    if (cep.length !== 8) {
        return;
    }
    
    fetch(`https://viacep.com.br/ws/${cep}/json/`)
        .then(response => response.json())
        .then(data => {
            if (!data.erro) {
                document.getElementById('street').value = data.logradouro;
                document.getElementById('neighborhood').value = data.bairro;
                document.getElementById('city').value = data.localidade;
                document.getElementById('state').value = data.uf;
            }
        })
        .catch(error => console.error('Erro ao buscar CEP:', error));
}
