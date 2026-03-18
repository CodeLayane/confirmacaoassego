<?php
session_start();

// Gerar números aleatórios para o captcha apenas se não existirem ou após login
if (!isset($_SESSION['captcha_num1']) || !isset($_SESSION['captcha_num2']) || !isset($_SESSION['captcha_generated'])) {
    $_SESSION['captcha_num1'] = rand(1, 9);
    $_SESSION['captcha_num2'] = rand(1, 9);
    $_SESSION['captcha_generated'] = true;
}

// Login estático conforme solicitado
$valid_username = "admin";
$valid_password = "admin123";

$error_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $captcha_answer = trim($_POST['captcha'] ?? '');
    
    // Pegar os números que foram mostrados ao usuário
    $num1 = intval($_SESSION['captcha_num1']);
    $num2 = intval($_SESSION['captcha_num2']);
    $correct_answer = $num1 + $num2;
    $user_answer = intval($captcha_answer);
    
    // Verificar todas as condições
    if (empty($captcha_answer)) {
        $error_message = "Por favor, resolva a soma do captcha!";
    } elseif ($user_answer != $correct_answer) {
        $error_message = "Soma incorreta! Por favor, tente novamente.";
    } elseif ($username !== $valid_username || $password !== $valid_password) {
        $error_message = "Usuário ou senha incorretos!";
    } else {
        // Login bem sucedido
        $_SESSION['logged_in'] = true;
        $_SESSION['user_email'] = "Administrador RemiLeal";
        $_SESSION['username'] = $username;
        
        // Limpar captcha
        unset($_SESSION['captcha_num1']);
        unset($_SESSION['captcha_num2']);
        unset($_SESSION['captcha_generated']);
        
        header('Location: index.php');
        exit();
    }
    
    // Se houve erro, gerar novos números
    if ($error_message) {
        $_SESSION['captcha_num1'] = rand(1, 9);
        $_SESSION['captcha_num2'] = rand(1, 9);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RemiLeal - Prof. Ritmos</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #1e40af;
            --primary-dark: #1e3a8a;
            --primary-light: #3b82f6;
            --secondary: #2563eb;
            --rl-green: #059669;
            --rl-yellow: #fbbf24;
            --rl-blue: #1e3a8a;
            --success: #059669;
            --danger: #dc2626;
            --warning: #d97706;
            --dark: #0f172a;
            --gray: #64748b;
            --light: #f8fafc;
            --white: #ffffff;
            
            --gradient-primary: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            --gradient-green: linear-gradient(135deg, #059669 0%, #10b981 100%);
            
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f9ff;
            overflow-y: auto;
            position: relative;
        }
        
        /* Background animado */
        .bg-animation {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 1;
            background: linear-gradient(135deg, #1e3a8a, #059669, #fbbf24, #3b82f6);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .pattern-overlay {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 2;
            opacity: 0.1;
            background-image: repeating-linear-gradient(45deg, transparent, transparent 35px, rgba(255,255,255,.1) 35px, rgba(255,255,255,.1) 70px);
        }
        
        /* Container principal */
        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 480px;
            padding: 20px;
            animation: slideUp 0.8s ease-out;
            margin: 20px auto;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(50px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Logo Section */
        .logo-section {
            text-align: center;
            margin-bottom: 28px;
            cursor: pointer;
            user-select: none;
        }
        .logo-wrapper {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            background: transparent;
            transition: transform 0.2s;
        }
        .logo-wrapper:active { transform: scale(0.96); }
        
        /* Logo CSS Customizada */
        .custom-logo { display: flex; flex-direction: column; align-items: center; gap: 20px; }
        .logo-flag-modern {
            width: 140px; height: 90px; position: relative; border-radius: 12px; overflow: hidden;
            box-shadow: 0 8px 32px rgba(30, 58, 138, 0.3);
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
        }
        .flag-canton {
            position: absolute; top: 0; left: 0; width: 50%; height: 60%; background: var(--rl-blue);
            display: flex; flex-wrap: wrap; align-items: center; justify-content: center; padding: 8px; gap: 4px;
        }
        .flag-canton i { color: white; font-size: 11px; animation: starPulse 3s ease-in-out infinite; }
        .flag-stripes { position: absolute; bottom: 0; left: 0; right: 0; height: 45%; display: flex; flex-direction: column; }
        .flag-stripes > div { flex: 1; }
        .stripe-green { background: var(--rl-green); }
        .stripe-yellow { background: var(--rl-yellow); }
        
        .logo-text-modern h1 {
            font-size: 48px; font-weight: 800; letter-spacing: 3px; margin: 0;
            background: linear-gradient(135deg, #1e3a8a 0%, #059669 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .logo-text-modern p { color: var(--gray); font-size: 11px; font-weight: 600; margin-top: 8px; text-transform: uppercase; }
        
        /* Login Box */
        .login-box {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: var(--shadow-xl);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .login-header {
            background: var(--gradient-primary);
            padding: 20px 30px;
            text-align: center;
        }
        
        .login-header h2 { color: white; font-size: 22px; margin: 0; }
        .login-content { padding: 30px 35px; }
        
        /* Form Styles */
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; color: var(--rl-blue); font-size: 13px; font-weight: 700; margin-bottom: 8px; text-transform: uppercase; }
        .input-wrapper {
            position: relative; border-radius: 12px; background: #f0f9ff; border: 2px solid #dbeafe;
            transition: all 0.3s ease;
        }
        .input-wrapper:focus-within { background: white; border-color: var(--primary); }
        .input-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .input-wrapper:focus-within .input-icon { color: var(--primary); }
        .form-input {
            width: 100%; padding: 12px 20px 12px 50px; border: none; background: transparent;
            font-size: 14px; font-weight: 500; color: var(--dark); outline: none;
        }
        
        /* Captcha */
        .captcha-section {
            background: var(--gradient-green); padding: 15px; border-radius: 16px; margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(5, 150, 105, 0.2); text-align: center; color: white;
        }
        .captcha-numbers {
            display: inline-flex; align-items: center; gap: 15px; background: white;
            padding: 8px 20px; border-radius: 30px; margin-top: 10px;
            color: var(--rl-green); font-weight: 700; font-size: 24px;
        }
        .captcha-input-wrapper { display: flex; justify-content: center; margin-top: 15px; }
        .captcha-input {
            width: 100px; padding: 12px; border: 2px solid white; border-radius: 12px;
            font-size: 20px; font-weight: 600; text-align: center; color: var(--rl-green); outline: none;
        }
        
        /* Submit */
        .submit-button {
            width: 100%; padding: 14px; background: var(--gradient-primary); color: white;
            border: none; border-radius: 12px; font-size: 15px; font-weight: 700; cursor: pointer;
            transition: all 0.3s ease;
        }
        .submit-button:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(30, 64, 175, 0.6); }
        
        /* Help Section (Easter Egg) */
        .help-section {
            margin-top: 20px;
            padding: 15px;
            background: #f0f9ff;
            border-radius: 12px;
            text-align: center;
            border: 2px solid #dbeafe;
            
            /* O SEGREDO ESTÁ AQUI: Escondido por padrão */
            display: none; 
            animation: fadeIn 0.5s ease-out;
        }
        
        .help-section h3 { color: var(--rl-blue); font-size: 13px; margin-bottom: 6px; }
        .credentials {
            display: inline-flex; flex-direction: column; gap: 4px; margin-top: 8px;
            padding: 10px 20px; background: white; border-radius: 8px; box-shadow: var(--shadow-md);
        }
        .credential { font-size: 13px; color: var(--dark); font-family: monospace; }
        .credential strong { color: var(--primary); }
        
        .error-message {
            background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 12px;
            margin-bottom: 20px; font-size: 13px; font-weight: 500; border: 1px solid #fca5a5;
        }

        @keyframes starPulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.2); } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <div class="bg-animation"></div>
    <div class="pattern-overlay"></div>
    
    <div class="login-container">
        <div class="logo-section" id="logoTrigger" title="RemiLeal">
            <div class="logo-wrapper">
                <img src="assets/img/logo_remi.png" alt="RemiLeal Logo"
                     style="width:220px; height:220px; object-fit:contain;
                            filter: drop-shadow(0 8px 24px rgba(0,0,0,0.35)) drop-shadow(0 2px 6px rgba(0,0,0,0.2));">
            </div>
        </div>
        
        <div class="login-box">
            <div class="login-header">
                <h2><i class="fas fa-shield-halved"></i> Sistema de Gestão</h2>
            </div>
            
            <div class="login-content">
                <?php if ($error_message): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" autocomplete="off">
                    <div class="form-group">
                        <label class="form-label" for="username">Usuário</label>
                        <div class="input-wrapper">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" class="form-input" id="username" name="username" placeholder="Digite seu usuário" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="password">Senha</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" class="form-input" id="password" name="password" placeholder="Digite sua senha" required>
                        </div>
                    </div>
                    
                    <div class="captcha-section">
                        <label class="captcha-label">Resolva a soma:
                            <div class="captcha-numbers">
                                <span class="captcha-number"><?php echo $_SESSION['captcha_num1']; ?></span>
                                <span>+</span>
                                <span class="captcha-number"><?php echo $_SESSION['captcha_num2']; ?></span>
                                <span>=</span>
                            </div>
                        </label>
                        <div class="captcha-input-wrapper">
                            <input type="number" class="captcha-input" name="captcha" placeholder="?" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="submit-button">
                        <i class="fas fa-sign-in-alt"></i> Acessar Sistema
                    </button>
                </form>
                
                <div class="help-section" id="secretSection">
                    <h3><i class="fas fa-user-secret"></i> Modo Desenvolvedor</h3>
                    <div class="credentials">
                        <div class="credential"><strong>User:</strong> <?php echo $valid_username; ?></div>
                        <div class="credential"><strong>Pass:</strong> <?php echo $valid_password; ?></div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
    
    <script>
        // Lógica do Easter Egg (Clique Triplo)
        let clickCount = 0;
        let clickTimer = null;
        
        const logoTrigger = document.getElementById('logoTrigger');
        const secretSection = document.getElementById('secretSection');
        
        logoTrigger.addEventListener('click', function(e) {
            // Prevenir seleção de texto no clique rápido
            e.preventDefault();
            
            clickCount++;
            
            // Feedback visual sutil ao clicar (opcional)
            this.style.transform = 'scale(0.98)';
            setTimeout(() => this.style.transform = 'scale(1)', 100);
            
            // Se for o primeiro clique, inicia o timer
            if (clickCount === 1) {
                clickTimer = setTimeout(function() {
                    // Se passar 1 segundo e não completar 3 cliques, reseta
                    clickCount = 0;
                }, 1000);
            }
            
            // Se atingir 3 cliques
            if (clickCount === 3) {
                clearTimeout(clickTimer);
                clickCount = 0;
                
                // Alternar visibilidade
                if (secretSection.style.display === 'none' || secretSection.style.display === '') {
                    secretSection.style.display = 'block';
                    // Rolar suavemente até mostrar a senha
                    secretSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    secretSection.style.display = 'none';
                }
            }
        });
    </script>
</body>
</html>