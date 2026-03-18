<?php
// Configuração do Banco de Dados (Mesma do config.php)
// Como é uma página pública, replicamos a conexão para evitar dependências de sessão do config original
try {
    // Ajuste as credenciais se necessário, ou inclua seu config.php se ele não forçar login
    if (file_exists('config.php')) {
        require_once 'config.php';
        $pdo = getConnection();
    } else {
        // Fallback caso config.php não seja carregável publicamente
        $host = 'localhost';
        $dbname = 'remileal';
        $username = 'root';
        $password = '';
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

$message = '';
$error = '';
$success = false;

// Função de limpeza
function clean_input_public($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

// Processar Formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Coletar dados
    $nome      = clean_input_public($_POST['nome']);
    $whatsapp  = preg_replace('/[^0-9]/', '', $_POST['whatsapp'] ?? '');
    $instagram = clean_input_public($_POST['instagram'] ?? '');
    $endereco  = clean_input_public($_POST['endereco'] ?? '');
    $cidade    = clean_input_public($_POST['cidade'] ?? '');
    $estado    = clean_input_public($_POST['estado'] ?? '');
    $cep       = preg_replace('/[^0-9-]/', '', $_POST['cep'] ?? '');
    
    // Valores Padrão

    
    // Validações
    if (empty($nome) || strlen($nome) < 3) $error = "Por favor, preencha seu nome completo.";
    elseif (empty($whatsapp) || strlen($whatsapp) < 10) $error = "WhatsApp/Celular é obrigatório.";
    
    // Inserir no Banco
    if (!$error) {
        try {
            $pdo->beginTransaction();

            $sql = "INSERT INTO membros (nome, whatsapp, instagram, endereco, cidade, estado, cep, aprovado) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 0)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nome, $whatsapp, $instagram, $endereco, $cidade, $estado, $cep]);
            $membro_id = $pdo->lastInsertId();

            // Foto via base64 (enviada pelo JS)
            $foto_base64 = $_POST['foto_base64'] ?? '';
            if (!empty($foto_base64) && strpos($foto_base64, 'data:image') === 0) {
                $pdo->prepare("INSERT INTO fotos (membro_id, dados) VALUES (?, ?)")
                    ->execute([$membro_id, $foto_base64]);
            }

            $pdo->commit();
            $success = true;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Erro ao processar cadastro. Tente novamente mais tarde.";
            error_log($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro Online - RemiLeal</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        :root {
            --primary: #1e40af;
            --primary-light: #3b82f6;
            --secondary: #2563eb;
            --success: #059669;
            --dark: #0f172a;
            --light: #f8fafc;
            --border-blue: #dbeafe;
            --gradient-primary: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f9ff;
            color: var(--dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .hero-section {
            background: var(--gradient-primary);
            color: white;
            padding: 60px 0 100px;
            text-align: center;
            position: relative;
            clip-path: polygon(0 0, 100% 0, 100% 85%, 0 100%);
        }

        .form-container {
            max-width: 800px;
            margin: -80px auto 40px;
            padding: 0 20px;
            position: relative;
            z-index: 10;
        }

        .form-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            padding: 40px;
            border: 1px solid white;
        }

        .form-title {
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-blue);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control, .form-select {
            padding: 12px 16px;
            border: 2px solid #e0f2fe;
            border-radius: 10px;
            background: #f8fafc;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-light);
            background: white;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .btn-submit {
            background: var(--gradient-primary);
            color: white;
            padding: 14px 30px;
            font-weight: 600;
            border-radius: 12px;
            border: none;
            width: 100%;
            font-size: 16px;
            transition: all 0.3s;
            margin-top: 20px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
            color: white;
        }

        /* Foto Upload Simplificado */
        .photo-upload-wrapper {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .photo-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #e0f2fe;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 3px solid white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.3s;
        }

        .photo-preview:hover {
            transform: scale(1.05);
            border-color: var(--primary-light);
        }

        .photo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-preview i {
            font-size: 40px;
            color: var(--primary-light);
        }

        /* Loading e Steps */
        .success-message {
            text-align: center;
            padding: 40px 20px;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: #d1fae5;
            color: #059669;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 20px;
        }

        /* Loading do CEP */
        .loading-cep {
            background-image: url("data:image/svg+xml;charset=utf8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%233b82f6' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='12' r='10'/%3E%3Cpath d='M12 6v6l4 2'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 20px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 768px) {
            .hero-section { padding: 40px 0 80px; }
            .form-card { padding: 25px; }
            .form-container { margin-top: -60px; }
        }

        /* ── RESPONSIVIDADE MOBILE ── */
        @media (max-width: 768px) {
            .header-content { flex-direction: row; justify-content: space-between; padding: 0 12px; gap: 8px; }
            .logo-section h1 { font-size: 18px; }
            .logo-badge { display: none; }
            .logo-section img { height: 38px !important; width: 38px !important; }
            .user-section .btn span { display: none; }
            .user-section { gap: 6px; }
            .nav-menu { padding: 10px 8px; }
            .menu-items { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
            .menu-item { padding: 10px 4px; border-radius: 10px; }
            .menu-item span { font-size: 10px; }
            .content-wrapper, .main-content, .container-fluid { padding: 10px !important; }
            .stats-container, .stats-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 10px; padding: 12px; }
            .stat-value { font-size: 22px; }
            .filter-container, .table-container, .info-card, .main-card { margin: 0 0 12px !important; padding: 14px !important; }
            .filter-header, .table-header { flex-direction: column; gap: 8px; align-items: flex-start; }
            .filter-actions, .action-buttons-top { display: flex; flex-wrap: wrap; gap: 6px; width: 100%; }
            .filter-actions .btn, .action-buttons-top .btn { flex: 1; min-width: 100px; font-size: 12px; padding: 8px 6px; }
            .filter-grid, .row.g-3 { gap: 8px; }
            .col-md-4, .col-md-6, .col-md-3 { width: 100% !important; max-width: 100% !important; flex: 0 0 100% !important; }
            .table th, .table td { font-size: 12px; padding: 8px 6px; }
            .btn-action { width: 30px; height: 30px; font-size: 11px; }
            .member-header { flex-direction: column; gap: 12px; }
            .action-buttons { flex-wrap: wrap; gap: 6px; }
            .action-buttons .btn { flex: 1; min-width: 90px; font-size: 12px; }
            .pagination-container { flex-direction: column; gap: 10px; padding: 12px; }
            .modal-dialog { margin: 8px; }
            .request-card { flex-direction: column; gap: 12px; }
            .request-actions { flex-direction: row; justify-content: flex-end; }
        }
        @media (max-width: 400px) {
            .stats-container, .stats-grid { grid-template-columns: 1fr !important; }
        }
    </style>
</head>
<body>

    <div class="hero-section">
        <div class="container">
            <h1 class="fw-bold mb-3">Faça parte da RemiLeal</h1>
            <p class="lead opacity-75">Preencha seus dados abaixo para realizar sua filiação.</p>
        </div>
    </div>

    <div class="form-container">
        <div class="form-card animate__animated animate__fadeInUp">
            
            <?php if ($success): ?>
                <div class="success-message">
                    <div class="success-icon animate__animated animate__bounceIn">
                        <i class="fas fa-check"></i>
                    </div>
                    <h3 class="fw-bold text-dark mb-3">Solicitação Recebida!</h3>
                    <p class="text-muted mb-4">
                        Seu cadastro foi enviado com sucesso e está com o status <strong>Pendente</strong>.<br>
                        Nossa equipe analisará seus dados e entrará em contato em breve.
                    </p>
                    <a href="filiacao.php" class="btn btn-outline-primary">Realizar novo cadastro</a>
                </div>
            <?php else: ?>
                
                <h2 class="form-title">
                    <i class="fas fa-user-plus"></i> Ficha de Cadastro
                </h2>

                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="filiacaoForm">
                    
                    <div class="photo-upload-wrapper">
                        <label class="form-label d-block">Sua Foto (Opcional)</label>
                        <div class="photo-preview" id="photoPreview">
                            <i class="fas fa-user" style="font-size:2.5rem;color:#cbd5e1;"></i>
                        </div>
                        <input type="hidden" name="foto_base64" id="fotoBase64">
                        <!-- input câmera -->
                        <input type="file" id="fotoCamera" class="d-none" accept="image/*" capture="environment">
                        <!-- input galeria (sem capture) -->
                        <input type="file" id="fotoGaleria" class="d-none" accept="image/*">
                        <div class="d-flex gap-2 justify-content-center mt-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('fotoCamera').click()">
                                <i class="fas fa-camera"></i> Tirar foto
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('fotoGaleria').click()">
                                <i class="fas fa-image"></i> Galeria
                            </button>
                        </div>
                        <small class="text-muted d-block text-center mt-1">Tire uma foto agora ou importe da galeria</small>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <label for="nome" class="form-label">Nome Completo *</label>
                            <input type="text" class="form-control" id="nome" name="nome" required placeholder="Seu nome completo" value="<?php echo $_POST['nome'] ?? ''; ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="whatsapp" class="form-label">WhatsApp / Celular *</label>
                            <input type="text" class="form-control" id="whatsapp" name="whatsapp" required placeholder="(00) 00000-0000" value="<?php echo $_POST['whatsapp'] ?? ''; ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="instagram" class="form-label">Instagram</label>
                            <div class="input-group">
                                <span class="input-group-text">@</span>
                                <input type="text" class="form-control" id="instagram" name="instagram" placeholder="seuperfil" value="<?php echo ltrim($_POST['instagram'] ?? '', '@'); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h5 class="text-primary mb-3"><i class="fas fa-map-marker-alt me-2"></i>Endereço</h5>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="cep" class="form-label">CEP</label>
                                <input type="text" class="form-control" id="cep" name="cep" placeholder="00000-000" value="<?php echo $_POST['cep'] ?? ''; ?>">
                            </div>
                            <div class="col-md-8">
                                <label for="endereco" class="form-label">Logradouro</label>
                                <input type="text" class="form-control" id="endereco" name="endereco" placeholder="Rua, Av, Bairro..." value="<?php echo $_POST['endereco'] ?? ''; ?>">
                            </div>
                            <div class="col-md-8">
                                <label for="cidade" class="form-label">Cidade</label>
                                <input type="text" class="form-control" id="cidade" name="cidade" value="<?php echo $_POST['cidade'] ?? ''; ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="estado" class="form-label">Estado</label>
                                <select class="form-select" id="estado" name="estado">
                                    <option value="">UF</option>
                                    <option value="GO">Goiás</option><option value="DF">Distrito Federal</option><option value="SP">São Paulo</option><option value="MG">Minas Gerais</option><option value="AC">Acre</option><option value="AL">Alagoas</option><option value="AP">Amapá</option><option value="AM">Amazonas</option><option value="BA">Bahia</option><option value="CE">Ceará</option><option value="ES">Espírito Santo</option><option value="MA">Maranhão</option><option value="MT">Mato Grosso</option><option value="MS">Mato Grosso do Sul</option><option value="PA">Pará</option><option value="PB">Paraíba</option><option value="PR">Paraná</option><option value="PE">Pernambuco</option><option value="PI">Piauí</option><option value="RJ">Rio de Janeiro</option><option value="RN">Rio Grande do Norte</option><option value="RS">Rio Grande do Sul</option><option value="RO">Rondônia</option><option value="RR">Roraima</option><option value="SC">Santa Catarina</option><option value="SE">Sergipe</option><option value="TO">Tocantins</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn-submit shadow-lg" id="btnSubmit">
                            <i class="fas fa-paper-plane me-2"></i> Enviar Solicitação
                        </button>
                    </div>
                    
                    <div class="text-center mt-3">
                        <small class="text-muted">Ao enviar, você concorda que seus dados serão analisados pela administração.</small>
                    </div>

                </form>
            <?php endif; ?>
        </div>
        
        <div class="text-center mt-4">
            <a href="index.php" class="text-decoration-none text-muted small">
                <i class="fas fa-lock me-1"></i> Área Administrativa
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Foto Preview - câmera e galeria
        function previewFoto(file) {
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('photoPreview');
                preview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`;
                preview.style.background = 'white';
                // Sincronizar: copiar arquivo para o input correto (fotoCamera é o que vai no form)
            };
            reader.readAsDataURL(file);
        }

        function processarFoto(file) {
            if (!file || !file.type.startsWith('image/')) return;
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    const canvas = document.createElement('canvas');
                    let w = img.width, h = img.height, max = 800;
                    if (w > max || h > max) {
                        if (w > h) { h = Math.round(h * max / w); w = max; }
                        else { w = Math.round(w * max / h); h = max; }
                    }
                    canvas.width = w; canvas.height = h;
                    canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                    const b64 = canvas.toDataURL('image/jpeg', 0.8);
                    document.getElementById('fotoBase64').value = b64;
                    const preview = document.getElementById('photoPreview');
                    preview.innerHTML = `<img src="${b64}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`;
                    preview.style.background = 'white';
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        document.getElementById('fotoCamera')?.addEventListener('change', function() {
            processarFoto(this.files[0]);
        });

        document.getElementById('fotoGaleria')?.addEventListener('change', function() {
            processarFoto(this.files[0]);
        });

        // Máscara WhatsApp
        document.getElementById('whatsapp')?.addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '');
            if(v.length > 11) v = v.slice(0, 11);
            v = v.replace(/^(\d{2})(\d)/g, '($1) $2');
            v = v.replace(/(\d)(\d{4})$/, '$1-$2');
            e.target.value = v;
        });

        document.getElementById('cep')?.addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '');
            if(v.length > 8) v = v.slice(0, 8);
            v = v.replace(/^(\d{5})(\d)/, '$1-$2');
            e.target.value = v;

            if(v.replace(/\D/g, '').length === 8) {
                buscarCEP(v);
            }
        });

        // Buscar CEP
        function buscarCEP(cep) {
            const cepField = document.getElementById('cep');
            cepField.classList.add('loading-cep');
            
            fetch(`https://viacep.com.br/ws/${cep.replace(/\D/g, '')}/json/`)
            .then(res => res.json())
            .then(data => {
                cepField.classList.remove('loading-cep');
                if(!data.erro) {
                    document.getElementById('endereco').value = data.logradouro + (data.bairro ? `, ${data.bairro}` : '');
                    document.getElementById('cidade').value = data.localidade;
                    document.getElementById('estado').value = data.uf;
                }
            })
            .catch(() => cepField.classList.remove('loading-cep'));
        }

        // Loading no Submit
        document.getElementById('filiacaoForm')?.addEventListener('submit', function() {
            const btn = document.getElementById('btnSubmit');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Enviando...';
            btn.disabled = true;
        });
    </script>
</body>
</html>