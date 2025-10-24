<?php
/**
 * Template Name: Login Cliente
 */

// Não carregar header e footer para ter design limpo

// Sistema de autenticação ativo
$message = '';

if (isset($_GET['login'])) {
    if ($_GET['login'] === 'failed') {
        $message = '<div class="alert alert-danger">Usuário ou senha incorretos.</div>';
    } elseif ($_GET['login'] === 'success') {
        $message = '<div class="alert alert-success">Login realizado com sucesso!</div>';
    }
}
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Cliente - <?php bloginfo('name'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

.client-login-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.client-login-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    padding: 40px;
    width: 100%;
    max-width: 400px;
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.client-login-header {
    margin-bottom: 30px;
}

.client-login-logo {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
}

.client-login-logo i {
    font-size: 32px;
    color: white;
}

.client-login-title {
    font-size: 28px;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 10px;
}

.client-login-subtitle {
    color: #718096;
    font-size: 16px;
}

.client-login-form {
    margin-top: 30px;
}

.form-group {
    margin-bottom: 20px;
    text-align: left;
}

.form-label {
    display: block;
    font-weight: 600;
    color: #4a5568;
    margin-bottom: 8px;
    font-size: 14px;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 16px;
    transition: all 0.3s ease;
    background: white;
    color: #2d3748;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.login-btn {
    width: 100%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    padding: 14px 20px;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: 10px;
}

.login-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
}

.login-btn:active {
    transform: translateY(0);
}

.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
    font-weight: 500;
}

.alert-danger {
    background: #fed7d7;
    color: #c53030;
    border: 1px solid #feb2b2;
}

.alert-success {
    background: #c6f6d5;
    color: #2f855a;
    border: 1px solid #9ae6b4;
}

.client-login-footer {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #e2e8f0;
}

.client-login-footer p {
    color: #718096;
    font-size: 14px;
    margin: 0;
}

@media (max-width: 480px) {
    .client-login-card {
        padding: 30px 20px;
        margin: 10px;
    }
    
    .client-login-title {
        font-size: 24px;
    }
}
</style>

<div class="client-login-container">
    <div class="client-login-card">
        <div class="client-login-header">
            <div class="client-login-logo">
                <img src="<?php echo get_theme_file_uri('assets/logo (1).png'); ?>" alt="F2F Logo" style="width: 60px; height: 60px; object-fit: contain;">
            </div>
            <h1 class="client-login-title">F2F-DADOS</h1>
            <p class="client-login-subtitle">Sistema de Acompanhamento de Projetos</p>
        </div>
        
        <div class="login-instructions" style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 30px; text-align: left;">
            <div style="display: flex; align-items: center; margin-bottom: 15px;">
                <i class="fas fa-user-shield" style="color: #6f42c1; margin-right: 10px; width: 20px;"></i>
                <div>
                    <strong>Administrador:</strong><br>
                    <small style="color: #666;">Use suas credenciais</small>
                </div>
            </div>
            <div style="display: flex; align-items: center;">
                <i class="fas fa-building" style="color: #6f42c1; margin-right: 10px; width: 20px;"></i>
                <div>
                    <strong>Cliente:</strong><br>
                    <small style="color: #666;">Use as credenciais fornecidas pela F2F</small>
                </div>
            </div>
        </div>
        
        <?php echo $message; ?>
        
        <form class="client-login-form" method="post" action="">
            <?php wp_nonce_field('f2f_client_login', 'f2f_nonce'); ?>
            <input type="hidden" name="f2f_client_login" value="1">
            
            <div class="form-group">
                <label for="username" class="form-label">Usuário</label>
                <input type="text" id="username" name="username" class="form-control" required 
                       placeholder="Digite seu usuário" value="<?php echo isset($_POST['username']) ? esc_attr($_POST['username']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Senha</label>
                <input type="password" id="password" name="password" class="form-control" required 
                       placeholder="Digite sua senha">
            </div>
            
            <button type="submit" class="login-btn">
                <i class="fas fa-sign-in-alt"></i> Entrar
            </button>
        </form>
        
        <div class="client-login-footer">
            <p>© <?php echo date('Y'); ?> F2F Marketing. Todos os direitos reservados.</p>
        </div>
    </div>
</div>

<script>
// Adicionar ícones Font Awesome se não estiver carregado
if (!document.querySelector('link[href*="font-awesome"]')) {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css';
    document.head.appendChild(link);
}

// Animação suave no carregamento
document.addEventListener('DOMContentLoaded', function() {
    const card = document.querySelector('.client-login-card');
    card.style.opacity = '0';
    card.style.transform = 'translateY(20px)';
    
    setTimeout(() => {
        card.style.transition = 'all 0.6s ease';
        card.style.opacity = '1';
        card.style.transform = 'translateY(0)';
    }, 100);
});

// Validação do formulário
document.querySelector('.client-login-form').addEventListener('submit', function(e) {
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value.trim();
    
    if (!username || !password) {
        e.preventDefault();
        alert('Por favor, preencha todos os campos.');
        return false;
    }
    
    // Mostrar loading no botão
    const btn = document.querySelector('.login-btn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Entrando...';
    btn.disabled = true;
    
    // Reverter após 3 segundos (caso não redirecione)
    setTimeout(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }, 3000);
});
</script>

</body>
</html>
