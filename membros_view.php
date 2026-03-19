<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
checkLogin();

$pdo = getConnection();

// Contar solicitações pendentes para badge na navbar
try {
    $stmt_nav = $pdo->query("SELECT COUNT(*) as total FROM membros WHERE aprovado = 0");
    $nav_pendentes = $stmt_nav->fetch()['total'] ?? 0;
} catch (Exception $e) { $nav_pendentes = 0; }

// Verificar se foi passado um ID
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$id = $_GET['id'];

// Buscar dados do membro
$stmt = $pdo->prepare("SELECT * FROM membros WHERE id = ?");
$stmt->execute([$id]);
$membro = $stmt->fetch();

if (!$membro) {
    header('Location: index.php?message=' . urlencode('Aluno não encontrado'));
    exit();
}

// Buscar foto da tabela fotos (base64)
$foto_principal = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM fotos WHERE membro_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$id]);
    $foto_principal = $stmt->fetch() ?: null;
} catch (Exception $e) { $foto_principal = null; }

// Buscar outros materiais (documentos, não fotos)
$outros_materiais = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM materiais WHERE membro_id = ? ORDER BY created_at DESC");
    $stmt->execute([$id]);
    $outros_materiais = $stmt->fetchAll();
} catch (Exception $e) { $outros_materiais = []; }

// Buscar atividades do membro
$hasAtividades = false;
$atividades = [];
try {
    $checkAtividades = $pdo->query("SHOW TABLES LIKE 'atividades'");
    if ($checkAtividades->rowCount() > 0) {
        $hasAtividades = true;
        $stmt = $pdo->prepare("SELECT * FROM atividades WHERE membro_id = ? ORDER BY data_atividade DESC LIMIT 10");
        $stmt->execute([$id]);
        $atividades = $stmt->fetchAll();
    }
} catch (Exception $e) {
    // Tabela não existe
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Aluno - RemiLeal</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary: #1e40af;
            --primary-dark: #1e3a8a;
            --primary-light: #3b82f6;
            --secondary: #2563eb;
            --light-blue: #f0f9ff;
            --border-blue: #dbeafe;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f9ff;
            color: #1e293b;
        }
        
        .header-banner {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 50%, #2563eb 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .member-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .member-profile {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .member-photo {
            width: 120px;
            height: 120px;
            border-radius: 16px;
            overflow: hidden;
            background: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .member-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .member-photo-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e2e8f0;
            color: #64748b;
            font-size: 48px;
        }
        
        .member-info h1 {
            font-size: 32px;
            margin: 0 0 10px 0;
            font-weight: 700;
        }
        
        .member-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .member-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            opacity: 0.9;
        }
        
        .content-wrapper {
            max-width: 1200px;
            margin: -30px auto 40px;
            padding: 0 20px;
        }
        
        .info-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 24px;
            border: 1px solid var(--border-blue);
        }
        
        .info-card h3 {
            color: var(--primary);
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-blue);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .info-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .info-value {
            font-size: 16px;
            color: #1e293b;
            font-weight: 500;
        }
        
        .info-value.empty {
            color: #94a3b8;
            font-style: italic;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 24px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .status-badge.filiado {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            color: white;
        }
        
        .status-badge.desfiliado {
            background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
            color: white;
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
            color: white;
        }
        
        .btn-secondary {
            background: white;
            color: var(--primary);
            border: 2px solid var(--border-blue);
        }
        
        .btn-secondary:hover {
            background: var(--light-blue);
            border-color: var(--primary-light);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
            color: white;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #b91c1c 0%, #dc2626 100%);
            transform: translateY(-2px);
            color: white;
        }
        
        /* Dependentes */
        .dependente-card {
            background: #f0f9ff;
            border: 1px solid var(--border-blue);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            transition: all 0.3s;
        }
        
        .dependente-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .dependente-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        
        .dependente-icon {
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .dependente-nome {
            color: var(--primary);
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }
        
        .dependente-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            font-size: 14px;
            margin-left: 52px;
        }
        
        .dependente-info-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .dependente-label {
            color: #64748b;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .dependente-value {
            color: #1e293b;
            font-weight: 500;
        }
        
        /* Materiais */
        .materiais-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .material-card {
            background: #f8fafc;
            border: 2px solid var(--border-blue);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .material-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px -2px rgba(0, 0, 0, 0.1);
            border-color: var(--primary-light);
        }
        
        .material-icon {
            font-size: 48px;
            color: var(--primary);
            margin-bottom: 12px;
        }
        
        .material-title {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
            word-break: break-word;
        }
        
        .material-type {
            display: inline-block;
            padding: 4px 12px;
            background: var(--light-blue);
            color: var(--primary);
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .material-date {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 12px;
        }
        
        .material-actions {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .counter-badge {
            background: var(--primary);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: auto;
        }
        
        @media print {
            .header-banner {
                background: none;
                color: black;
                box-shadow: none;
                border-bottom: 2px solid #000;
            }
            
            .action-buttons, .material-actions {
                display: none !important;
            }
            
            .info-card {
                box-shadow: none;
                border: 1px solid #000;
                page-break-inside: avoid;
            }
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
    <!-- Header Banner -->
    <div class="header-banner">
        <div class="header-content">
            <div class="member-header">
                <div class="member-profile">
                    <div class="member-photo">
                        <?php if ($foto_principal && !empty($foto_principal['dados'])): ?>
                            <img src="<?php echo $foto_principal['dados']; ?>" 
                                 alt="Foto de <?php echo htmlspecialchars($membro['nome']); ?>"
                                 style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                        <?php else: ?>
                            <div class="member-photo-placeholder">
                                <i class="fas fa-user"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="member-info">
                        <h1><?php echo htmlspecialchars($membro['nome']); ?></h1>
                        <div class="member-meta">
                            <?php if (!empty($membro['whatsapp'])): ?>
                            <div class="member-meta-item">
                                <i class="fab fa-whatsapp"></i>
                                <span><?php echo htmlspecialchars($membro['whatsapp']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($membro['instagram'])): ?>
                            <div class="member-meta-item">
                                <i class="fab fa-instagram"></i>
                                <span>@<?php echo htmlspecialchars(ltrim($membro['instagram'],'@')); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <a href="membros.php?action=edit&id=<?php echo $membro['id']; ?>" class="btn btn-secondary">
                        <i class="fas fa-edit"></i> Editar
                    </a>
                    <button onclick="window.print()" class="btn btn-secondary">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                    <button onclick="addDocument(<?php echo $membro['id']; ?>)" class="btn btn-primary">
                        <i class="fas fa-file-upload"></i> Add Documento
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="content-wrapper">
        <!-- Dados Pessoais -->
        <div class="info-card">
            <h3><i class="fas fa-user"></i> Dados Pessoais</h3>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Nome Completo</span>
                    <span class="info-value"><?php echo htmlspecialchars($membro['nome']); ?></span>
                </div>
                
<div class="info-item">
                    <span class="info-label">WhatsApp</span>
                    <span class="info-value <?php echo empty($membro['whatsapp']) ? 'empty' : ''; ?>">
                        <?php echo !empty($membro['whatsapp']) ? htmlspecialchars($membro['whatsapp']) : 'Não informado'; ?>
                    </span>
                </div>

                <div class="info-item">
                    <span class="info-label">Instagram</span>
                    <span class="info-value <?php echo empty($membro['instagram']) ? 'empty' : ''; ?>">
                        <?php echo !empty($membro['instagram']) ? '@'.htmlspecialchars(ltrim($membro['instagram'],'@')) : 'Não informado'; ?>
                    </span>
                </div>
            </div>
        </div>



        <!-- Endereço -->
        <div class="info-card">
            <h3><i class="fas fa-map-marker-alt"></i> Endereço</h3>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Endereço</span>
                    <span class="info-value <?php echo empty($membro['endereco']) ? 'empty' : ''; ?>">
                        <?php echo !empty($membro['endereco']) ? htmlspecialchars($membro['endereco']) : 'Não informado'; ?>
                    </span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Cidade</span>
                    <span class="info-value <?php echo empty($membro['cidade']) ? 'empty' : ''; ?>">
                        <?php 
                        if (!empty($membro['cidade'])) {
                            echo htmlspecialchars($membro['cidade']);
                            if (!empty($membro['estado'])) {
                                echo ' - ' . $membro['estado'];
                            }
                        } else {
                            echo 'Não informado';
                        }
                        ?>
                    </span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">CEP</span>
                    <span class="info-value <?php echo empty($membro['cep']) ? 'empty' : ''; ?>">
                        <?php echo !empty($membro['cep']) ? htmlspecialchars($membro['cep']) : 'Não informado'; ?>
                    </span>
                </div>
            </div>
        </div>



        <!-- Materiais/Documentos -->
        <div class="info-card">
            <h3>
                <i class="fas fa-paperclip"></i> 
                Documentos e Materiais
                <?php if (count($outros_materiais) > 0): ?>
                <span class="counter-badge"><?php echo count($outros_materiais); ?></span>
                <?php endif; ?>
            </h3>
            
            <?php if (count($outros_materiais) > 0): ?>
                <div class="materiais-grid">
                    <?php foreach ($outros_materiais as $doc): ?>
                        <div class="material-card">
                            <div class="material-icon">
                                <?php
                                $icon = 'fa-file';
                                $type_label = 'Documento';
                                if ($doc['tipo'] == 'video') {
                                    $icon = 'fa-video';
                                    $type_label = 'Vídeo';
                                } elseif ($doc['tipo'] == 'foto') {
                                    $icon = 'fa-image';
                                    $type_label = 'Imagem';
                                } elseif (strpos($doc['arquivo'], '.pdf') !== false) {
                                    $icon = 'fa-file-pdf';
                                    $type_label = 'PDF';
                                }
                                ?>
                                <i class="fas <?php echo $icon; ?>"></i>
                            </div>
                            <div class="material-type"><?php echo $type_label; ?></div>
                            <div class="material-title"><?php echo htmlspecialchars($doc['titulo'] ?? 'Sem título'); ?></div>
                            <?php if (!empty($doc['descricao'])): ?>
                                <p style="font-size: 12px; color: #64748b; margin: 8px 0;">
                                    <?php echo htmlspecialchars($doc['descricao']); ?>
                                </p>
                            <?php endif; ?>
                            <div class="material-date">
                                <i class="fas fa-clock"></i>
                                <?php echo date('d/m/Y H:i', strtotime($doc['created_at'])); ?>
                            </div>
                            <div class="material-actions">
                                <?php if (file_exists(__DIR__ . '/' . $doc['arquivo'])): ?>
                                    <a href="download_file.php?id=<?php echo $doc['id']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-download"></i> Baixar
                                    </a>
                                <?php else: ?>
                                    <span class="text-danger" style="font-size: 12px;">
                                        <i class="fas fa-exclamation-triangle"></i> Arquivo não encontrado
                                    </span>
                                <?php endif; ?>
                                <button onclick="deleteDocument(<?php echo $doc['id']; ?>)" 
                                        class="btn btn-sm btn-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <p>Nenhum documento anexado</p>
                    <button onclick="addDocument(<?php echo $membro['id']; ?>)" class="btn btn-primary btn-sm mt-2">
                        <i class="fas fa-plus"></i> Adicionar Documento
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Observações -->
        <?php if (!empty($membro['observacoes'])): ?>
        <div class="info-card">
            <h3><i class="fas fa-sticky-note"></i> Observações</h3>
            <p style="white-space: pre-wrap;"><?php echo htmlspecialchars($membro['observacoes']); ?></p>
        </div>
        <?php endif; ?>


    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        function confirmDelete(id) {
            Swal.fire({
                title: 'Confirmar Exclusão',
                text: 'Tem certeza que deseja excluir este membro? Esta ação não pode ser desfeita.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `membros.php?action=delete&id=${id}`;
                }
            });
        }
        
        function addDocument(memberId) {
            Swal.fire({
                title: 'Adicionar Documento',
                html: `
                    <form id="uploadForm" enctype="multipart/form-data">
                        <div class="mb-3 text-start">
                            <label class="form-label">Tipo de Documento</label>
                            <select name="tipo" class="form-select" required>
                                <option value="">Selecione...</option>
                                <option value="documento">Documento</option>
                                <option value="video">Vídeo</option>
                                <option value="foto">Foto</option>
                            </select>
                        </div>
                        <div class="mb-3 text-start">
                            <label class="form-label">Título</label>
                            <input type="text" name="titulo" class="form-control" required placeholder="Ex: RG, Certidão, etc">
                        </div>
                        <div class="mb-3 text-start">
                            <label class="form-label">Descrição (opcional)</label>
                            <textarea name="descricao" class="form-control" rows="3" placeholder="Detalhes sobre o documento..."></textarea>
                        </div>
                        <div class="mb-3 text-start">
                            <label class="form-label">Arquivo</label>
                            <input type="file" name="arquivo" class="form-control" required>
                            <small class="text-muted">Máximo: 5MB</small>
                        </div>
                    </form>
                `,
                showCancelButton: true,
                confirmButtonText: 'Enviar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#1e40af',
                width: '600px',
                preConfirm: () => {
                    const form = document.getElementById('uploadForm');
                    const formData = new FormData(form);
                    formData.append('membro_id', memberId);
                    
                    // Validar campos
                    if (!form.tipo.value || !form.titulo.value || !form.arquivo.files[0]) {
                        Swal.showValidationMessage('Por favor, preencha todos os campos obrigatórios');
                        return false;
                    }
                    
                    return fetch('upload_material.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || 'Erro ao fazer upload');
                        }
                        return data;
                    })
                    .catch(error => {
                        Swal.showValidationMessage(`Erro: ${error.message}`);
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('Sucesso!', 'Documento enviado com sucesso!', 'success')
                        .then(() => location.reload());
                }
            });
        }
        
        function deleteDocument(docId) {
            Swal.fire({
                title: 'Confirmar Exclusão',
                text: 'Tem certeza que deseja excluir este documento?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#ef4444',
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('delete_material.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ id: docId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Excluído!', 'Documento removido com sucesso.', 'success')
                                .then(() => location.reload());
                        } else {
                            Swal.fire('Erro!', data.message || 'Erro ao excluir documento.', 'error');
                        }
                    })
                    .catch(error => {
                        Swal.fire('Erro!', 'Erro ao processar solicitação.', 'error');
                    });
                }
            });
        }
        
        // Debug info
        console.log('Materiais:', <?php echo json_encode($materiais); ?>);
    </script>

    <!-- ── BADGE TEMPO REAL ─────────────────────────────────────────────── -->
    <script>
    (function() {
        let last = 0;
        function updateBadge(count) {
            document.querySelectorAll('.rt-badge').forEach(b => {
                b.textContent = count;
                b.style.display = count > 0 ? 'flex' : 'none';
            });
        }
        function pulseNotify(msg) {
            const t = document.createElement('div');
            t.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#1e40af;color:#fff;padding:12px 20px;border-radius:12px;font-size:14px;font-weight:600;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,.25);animation:slideIn .3s ease';
            t.innerHTML = '🔔 ' + msg;
            document.body.appendChild(t);
            setTimeout(()=>{t.style.opacity='0';t.style.transition='opacity .4s';setTimeout(()=>t.remove(),400);},4000);
        }
        async function poll() {
            try {
                const r = await fetch('api_realtime.php?action=badge', {cache:'no-store'});
                if (!r.ok) return;
                const d = await r.json();
                updateBadge(d.pendentes);
                if (d.pendentes > last && last >= 0) pulseNotify('Nova solicitação de cadastro!');
                last = d.pendentes;
            } catch(e){}
        }
        document.querySelectorAll('[style*="position:absolute"]').forEach(el => {
            if (el.closest('a[href="solicitacoes.php"]')) el.classList.add('rt-badge');
        });
        poll(); setInterval(poll, 15000);


        const s=document.createElement('style');
        s.textContent='@keyframes slideIn{from{transform:translateX(100px);opacity:0}to{transform:translateX(0);opacity:1}}@keyframes rtpulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.3)}}';
        document.head.appendChild(s);
    })();
    </script>
</body>
</html>