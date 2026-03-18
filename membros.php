<?php
require_once 'config.php';
checkLogin();

$pdo = getConnection();

// Contar solicitações pendentes para badge na navbar
try {
    $stmt_nav = $pdo->query("SELECT COUNT(*) as total FROM membros WHERE aprovado = 0");
    $nav_pendentes = $stmt_nav->fetch()['total'] ?? 0;
} catch (Exception $e) { $nav_pendentes = 0; }
$action = $_GET['action'] ?? 'list';
$message = '';
$error = '';
$errors = []; // Array para múltiplos erros
$formSubmitted = false; // Flag para controlar se o formulário foi submetido

// Buscar dados básicos
$cargos = []; // mantido para compatibilidade

// Buscar membro para edição ANTES de qualquer processamento
$membro = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM membros WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $membro = $stmt->fetch();
    
    if (!$membro) {
        header("Location: membros.php?message=" . urlencode("Aluno não encontrado!"));
        exit();
    }
}

// Array para armazenar campos com erro
$fieldsWithError = [];

// Processar formulário de adição/edição SOMENTE em POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($action == 'add' || $action == 'edit')) {
    $formSubmitted = true; // Marcar que o formulário foi submetido
    
    // Limpar e validar dados
    $nome      = clean_input($_POST['nome'] ?? '');
    $cpf       = preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? '');
    $whatsapp  = preg_replace('/[^0-9]/', '', $_POST['whatsapp'] ?? '');
    $instagram = clean_input($_POST['instagram'] ?? '');
    $endereco  = clean_input($_POST['endereco'] ?? '');
    $cidade    = clean_input($_POST['cidade'] ?? '');
    $estado    = clean_input($_POST['estado'] ?? '');
    $cep       = preg_replace('/[^0-9-]/', '', $_POST['cep'] ?? '');
    $observacoes = clean_input($_POST['observacoes'] ?? '');
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    
    // Validações
    if (empty($nome)) {
        $errors[] = "Nome completo é obrigatório.";
        $fieldsWithError['nome'] = "Por favor, informe o nome completo";
    } elseif (strlen($nome) < 3) {
        $errors[] = "Nome deve ter pelo menos 3 caracteres.";
        $fieldsWithError['nome'] = "Nome deve ter pelo menos 3 caracteres";
    }
    
    if (empty($whatsapp) || strlen($whatsapp) < 10) {
        $errors[] = "WhatsApp/Celular é obrigatório.";
        $fieldsWithError['whatsapp'] = "WhatsApp é obrigatório";
    }
    
    if (!empty($cep) && strlen(preg_replace('/[^0-9]/', '', $cep)) != 8) {
        $errors[] = "CEP deve ter 8 dígitos.";
        $fieldsWithError['cep'] = "CEP deve ter 8 dígitos";
    }
    
    // Se não houver erros, salvar
    if (empty($errors)) {
        try {
            if ($action == 'add') {
                $sql = "INSERT INTO membros (nome, whatsapp, instagram, endereco, cidade, estado, cep, observacoes, aprovado, ativo) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nome, $whatsapp, $instagram, $endereco, $cidade, $estado, $cep, $observacoes, $ativo]);
                $message = "Aluno cadastrado com sucesso!";
            } else {
                $id = $_POST['id'];
                $sql = "UPDATE membros SET nome=?, whatsapp=?, instagram=?, endereco=?, cidade=?, estado=?, cep=?, observacoes=?, ativo=? WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nome, $whatsapp, $instagram, $endereco, $cidade, $estado, $cep, $observacoes, $ativo, $id]);
                $message = "Aluno atualizado com sucesso!";
            }
            
            // Obter ID do membro
            $membro_id = $action == 'add' ? $pdo->lastInsertId() : $_POST['id'];
            
            // Processar upload de foto (base64 - sem dependência de pasta)
            $foto_base64 = $_POST['foto_base64'] ?? '';
            if (!empty($foto_base64) && strpos($foto_base64, 'data:image') === 0) {
                $stmt_chk = $pdo->prepare("SELECT id FROM fotos WHERE membro_id = ?");
                $stmt_chk->execute([$membro_id]);
                $foto_existente = $stmt_chk->fetch();
                if ($foto_existente) {
                    $pdo->prepare("UPDATE fotos SET dados=?, updated_at=NOW() WHERE membro_id=?")
                        ->execute([$foto_base64, $membro_id]);
                } else {
                    $pdo->prepare("INSERT INTO fotos (membro_id, dados) VALUES (?, ?)")
                        ->execute([$membro_id, $foto_base64]);
                }
            }
            
            // Processar remoção de foto
            if (isset($_POST['remove_photo']) && $_POST['remove_photo'] == '1' && $action == 'edit') {
                $pdo->prepare("DELETE FROM fotos WHERE membro_id = ?")
                    ->execute([$membro_id]);
            }
            
            // Redirecionar — em add vai para edit do novo aluno (para salvar foto via AJAX)
            $novo_id = $action == 'add' ? $membro_id : null;
            if ($novo_id) {
                header("Location: membros.php?action=edit&id={$novo_id}&message=" . urlencode($message . " Agora adicione a foto se desejar."));
            } else {
                header("Location: membros.php?message=" . urlencode($message));
            }
            exit();
            
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                $errors[] = "Erro de duplicidade: Registro já cadastrado.";
            } else {
                // Mostrar erro real para diagnóstico
                $errors[] = "Erro BD: " . $e->getMessage();
                error_log("Erro PDO: " . $e->getMessage());
            }
        }
    }
}

// Processar exclusão
if ($action == 'delete' && isset($_GET['id'])) {
    try {
        $del_id = (int)$_GET['id'];
        // Remover materiais/fotos vinculados primeiro
        $pdo->prepare("DELETE FROM materiais WHERE membro_id = ?")->execute([$del_id]);
        // Remover o aluno
        $pdo->prepare("DELETE FROM membros WHERE id = ?")->execute([$del_id]);
        header("Location: membros.php?message=" . urlencode("Aluno excluído com sucesso!"));
        exit();
    } catch (PDOException $e) {
        $error = "Erro ao excluir: " . $e->getMessage();
    }
}

// Buscar membros para listagem
$search = $_GET['search'] ?? '';

$sql = "SELECT * FROM membros WHERE aprovado = 1";
$params = [];

if ($search) {
    $sql .= " AND (nome LIKE ? OR whatsapp LIKE ? OR instagram LIKE ?)";
    $searchParam = "%$search%";
    $params = [$searchParam, $searchParam, $searchParam];
}

$sql .= " ORDER BY nome";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$membros = $stmt->fetchAll();

// Mensagem da URL
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

// Estatísticas
$stats = [];
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM membros");
    $stats['total'] = $stmt->fetch()['total'] ?? 0;
    $stats['filiados']   = $stats['total'];
    $stats['desfiliados'] = 0;
    $stats['pendentes']   = 0;
} catch (PDOException $e) {
    $stats = ['total' => 0, 'filiados' => 0, 'desfiliados' => 0, 'pendentes' => 0];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RemiLeal - Gestão de Alunos</title>
    
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
            --success: #059669;
            --danger: #dc2626;
            --warning: #d97706;
            --info: #0891b2;
            --dark: #0f172a;
            --light: #f8fafc;
            --gray: #64748b;
            --white: #ffffff;
            --light-blue: #f0f9ff;
            --border-blue: #dbeafe;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f9ff;
            color: #1e293b;
            font-size: 14px;
            line-height: 1.6;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        /* Header Banner */
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
        
        .header-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .header-title h1 {
            font-size: 32px;
            margin: 0;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        /* Content Container */
        .content-wrapper {
            max-width: 1200px;
            margin: -30px auto 40px;
            padding: 0 20px;
        }
        
        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--border-blue);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: var(--gradient-primary);
            opacity: 0.1;
            border-radius: 50%;
            transform: translate(30px, -30px);
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card.total { --gradient-primary: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%); }
        .stat-card.success { --gradient-primary: linear-gradient(135deg, #059669 0%, #10b981 100%); }
        .stat-card.danger { --gradient-primary: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); }
        .stat-card.warning { --gradient-primary: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            background: var(--gradient-primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 8px;
        }
        
        .stat-label {
            color: var(--gray);
            font-size: 14px;
            font-weight: 500;
        }
        
        /* Main Card */
        .main-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 1px solid var(--border-blue);
            margin-bottom: 24px;
        }
        
        .card-header {
            padding: 24px;
            background: #f0f9ff;
            border-bottom: 1px solid #e0f2fe;
        }
        
        .card-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-body {
            padding: 24px;
        }
        
        /* Filter Section */
        .filter-section {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            border: 1px solid #e0f2fe;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            align-items: end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-control, .form-select {
            padding: 10px 16px;
            font-size: 14px;
            border: 2px solid #e0f2fe;
            border-radius: 10px;
            background: white;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #2563eb;
            background: white;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
        }
        
        /* Validação de campos */
        .form-control.is-invalid, .form-select.is-invalid {
            border-color: #dc2626;
            background-color: #fef2f2;
        }
        
        .form-control.is-invalid:focus, .form-select.is-invalid:focus {
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }
        
        .invalid-feedback {
            display: none;
            color: #dc2626;
            font-size: 12px;
            margin-top: 4px;
            font-weight: 500;
        }
        
        .is-invalid ~ .invalid-feedback {
            display: block;
        }
        
        /* Table */
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            margin: 0;
            border-collapse: collapse;
        }
        
        .table thead {
            background: #f0f9ff;
            border-bottom: 2px solid var(--border-blue);
        }
        
        .table thead th {
            padding: 16px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray);
            white-space: nowrap;
            text-align: left;
        }
        
        .table tbody tr {
            border-bottom: 1px solid #e0f2fe;
            transition: all 0.3s;
        }
        
        .table tbody tr:hover {
            background: #f0f9ff;
        }
        
        .table tbody td {
            padding: 16px;
            vertical-align: middle;
            font-size: 14px;
            color: var(--dark);
        }
        
        .table tbody tr:last-child {
            border-bottom: none;
        }
        
        /* Form Card */
        .form-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 30px;
            border: 1px solid var(--border-blue);
            margin-bottom: 24px;
        }
        
        .form-card h2 {
            color: var(--primary);
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-blue);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .form-section-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .status-badge.filiada,
        .status-badge.ativo {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            color: white;
        }
        
        .status-badge.desfiliada,
        .status-badge.inativo {
            background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
            color: white;
        }
        
        .status-badge.pendente {
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
            color: white;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        
        .btn-action {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
            background: #e0f2fe;
            color: #1e40af;
            text-decoration: none;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            color: white;
        }
        
        .btn-action.view:hover { background: #2563eb; }
        .btn-action.edit:hover { background: #0891b2; }
        .btn-action.delete:hover { background: #dc2626; }
        
        /* Buttons */
        .btn {
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            color: white;
        }
        
        .btn-secondary {
            background: white;
            color: var(--primary);
            border: 2px solid #e0f2fe;
        }
        
        .btn-secondary:hover {
            background: #f0f9ff;
            border-color: #3b82f6;
            color: var(--primary);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            color: white;
        }
        
        /* Alerts */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        .alert-info {
            background: #dbeafe;
            color: #1e3a8a;
            border: 1px solid #93c5fd;
        }
        
        /* Alert de múltiplos erros */
        .alert-errors {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        
        .alert-errors h5 {
            margin: 0 0 12px 0;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .alert-errors ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .alert-errors li {
            margin-bottom: 4px;
            list-style-type: disc;
        }
        
        /* Empty State */
        .empty-state {
            padding: 80px 20px;
            text-align: center;
        }
        
        .empty-icon {
            font-size: 64px;
            color: #dbeafe;
            margin-bottom: 24px;
        }
        
        .empty-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
        }
        
        .empty-text {
            color: var(--gray);
            margin-bottom: 24px;
        }
        
        /* Badge */
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: var(--primary);
            color: white;
        }
        
        /* Loading Spinner */
        .spinner-border {
            width: 1rem;
            height: 1rem;
            border-width: 2px;
        }
        
        /* Photo Upload Section */
        .photo-upload-section {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            border: 2px dashed #dbeafe;
            transition: all 0.3s;
        }
        
        .photo-upload-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }
        
        .photo-preview {
            width: 150px;
            height: 150px;
            border-radius: 12px;
            overflow: hidden;
            background: white;
            border: 2px solid #e0f2fe;
            position: relative;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .photo-preview:hover {
            border-color: #3b82f6;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }
        
        .photo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .photo-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: #94a3b8;
            background: #f1f5f9;
            transition: all 0.3s;
        }
        
        .photo-preview:hover .photo-placeholder {
            background: #e2e8f0;
            color: #64748b;
        }
        
        .photo-placeholder i {
            font-size: 36px;
        }
        
        .photo-placeholder span {
            font-size: 12px;
            font-weight: 500;
        }
        
        .photo-actions {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.7);
            padding: 8px;
            display: flex;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .photo-preview:hover .photo-actions {
            opacity: 1;
        }
        
        .photo-info {
            text-align: center;
        }
        
        /* Animação de loading para CEP */
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Estados do campo CEP */
        .form-control.loading-cep {
            background-image: url("data:image/svg+xml;charset=utf8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%233b82f6' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='12' r='10'/%3E%3Cpath d='M12 6v6l4 2'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 20px;
            animation: spin 1s linear infinite;
        }
        
        .form-control.cep-found {
            background-image: url("data:image/svg+xml;charset=utf8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2310b981' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M20 6L9 17l-5-5'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 20px;
            border-color: #10b981;
        }
        
        .form-control.cep-error {
            background-image: url("data:image/svg+xml;charset=utf8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23dc2626' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='12' r='10'/%3E%3Cpath d='M12 8v4m0 4h.01'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 20px;
            border-color: #dc2626;
        }
        
        /* Transição suave para campos preenchidos automaticamente */
        .form-control, .form-select {
            padding: 10px 16px;
            font-size: 14px;
            border: 2px solid #e0f2fe;
            border-radius: 10px;
            background: white;
            transition: all 0.3s, background-color 0.5s;
            font-weight: 500;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-title {
                flex-direction: column;
                gap: 16px;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .table-responsive {
                margin: 0 -24px;
                padding: 0 24px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 4px;
            }
            
            .btn-action {
                width: 100%;
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
            <div class="header-title">
                <h1>
                    <i class="fas fa-users"></i>
                    Gestão de Alunos
                </h1>
                <div class="header-actions">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a href="logout.php" class="btn btn-danger" title="Sair">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="content-wrapper">
        <?php if ($action == 'list'): ?>
            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card total">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($stats['total'], 0, ',', '.'); ?></div>
                            <div class="stat-label">Total de Alunos</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($stats['filiados'], 0, ',', '.'); ?></div>
                            <div class="stat-label">Alunos Ativos</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card danger">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($stats['desfiliados'], 0, ',', '.'); ?></div>
                            <div class="stat-label">Alunos Inativos</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-user-times"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($stats['pendentes'], 0, ',', '.'); ?></div>
                            <div class="stat-label">Alunos Pendentes</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-user-clock"></i>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Main Card -->
            <div class="main-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-list"></i>
                        Lista de Alunos
                    </h3>
                </div>
                
                <div class="card-body">
                    <!-- Filter Section -->
                    <div class="filter-section">
                        <form method="GET" action="">
                            <div class="filter-grid">
                                <div class="form-group">
                                    <label class="form-label">Buscar</label>
                                    <input type="text" class="form-control" placeholder="Nome, WhatsApp ou Instagram..." 
                                           name="search" value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Situação</label>
                                    <select name="situacao" class="form-select">
                                        <option value="">Todas</option>
                                        <option value="Filiada" <?php echo $filter_situacao == 'Filiada' ? 'selected' : ''; ?>>Filiada</option>
                                        <option value="Desfiliada" <?php echo $filter_situacao == 'Desfiliada' ? 'selected' : ''; ?>>Desfiliada</option>
                                        <option value="Pendente" <?php echo $filter_situacao == 'Pendente' ? 'selected' : ''; ?>>Pendente</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search"></i>
                                            Buscar
                                        </button>
                                        <a href="membros.php?action=add" class="btn btn-success">
                                            <i class="fas fa-plus"></i>
                                            Novo
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Table -->
                    <?php if (count($membros) > 0): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>WhatsApp</th>
                                        <th>Instagram</th>
                                        <th class="text-center">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($membros as $membro): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($membro['nome']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($membro['whatsapp'] ?? '-'); ?></td>
                                            <td><?php echo $membro['instagram'] ? '@'.htmlspecialchars(ltrim($membro['instagram'],'@')) : '-'; ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="membros_view.php?id=<?php echo $membro['id']; ?>" 
                                                       class="btn-action view" title="Visualizar">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="membros.php?action=edit&id=<?php echo $membro['id']; ?>" 
                                                       class="btn-action edit" title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button onclick="confirmDelete(<?php echo $membro['id']; ?>)" 
                                                            class="btn-action delete" title="Excluir">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-users-slash"></i>
                            </div>
                            <div class="empty-title">Nenhum aluno encontrado</div>
                            <div class="empty-text">
                                Não há membros cadastrados com os filtros selecionados.
                            </div>
                            <a href="membros.php?action=add" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i>
                                Cadastrar Primeiro Aluno
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php elseif ($action == 'add' || $action == 'edit'): ?>
            <!-- Form Card -->
            <div class="form-card">
                <h2>
                    <i class="fas <?php echo $action == 'add' ? 'fa-user-plus' : 'fa-user-edit'; ?>"></i>
                    <?php echo $action == 'add' ? 'Novo Aluno' : 'Editar Aluno'; ?>
                </h2>
                
                <!-- Mostrar mensagem informativa apenas ao editar -->
                <?php if ($action == 'edit' && !$formSubmitted && $membro): ?>
                    <div class="alert alert-info" style="display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-info-circle"></i>
                        <span>Você está editando os dados do membro. As alterações só serão salvas ao clicar em "Salvar".</span>
                    </div>
                <?php endif; ?>
                
                <!-- Mostrar erros apenas após submissão do formulário -->
                <?php if ($formSubmitted && !empty($errors)): ?>
                    <div class="alert-errors">
                        <h5>
                            <i class="fas fa-exclamation-triangle"></i>
                            Por favor, corrija os seguintes erros:
                        </h5>
                        <ul>
                            <?php foreach ($errors as $err): ?>
                                <li><?php echo htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="memberForm" enctype="multipart/form-data">
                    <?php if ($action == 'edit'): ?>
                        <input type="hidden" name="id" value="<?php echo $membro['id']; ?>">
                        <input type="hidden" id="membroIdField" value="<?php echo $membro['id']; ?>">
                    <?php else: ?>
                        <input type="hidden" id="membroIdField" value="0">
                    <?php endif; ?>
                    
                    <!-- Dados Pessoais -->
                    <div class="form-section">
                        <h4 class="form-section-title">
                            <i class="fas fa-user"></i> Dados Pessoais
                        </h4>
                        
                        <!-- Seção de Foto -->
                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <div class="photo-upload-section">
                                    <label class="form-label">Foto do Aluno</label>
                                    <div class="photo-upload-container">
                                        <div class="photo-preview" id="photoPreview">
                                            <?php 
                                            // Buscar foto existente se estiver editando
                                            $foto_atual = null;
                                            if ($action == 'edit' && $membro) {
                                                $stmt_foto = $pdo->prepare("SELECT * FROM fotos WHERE membro_id = ? ORDER BY id DESC LIMIT 1");
                                                $stmt_foto->execute([$membro['id']]);
                                                $foto_atual = $stmt_foto->fetch();
                                            }
                                            
                                            if ($foto_atual && !empty($foto_atual['dados'])): 
                                            ?>
                                                <img src="<?php echo $foto_atual['dados']; ?>" alt="Foto atual" id="currentPhoto" style="width:100%;height:100%;object-fit:cover;border-radius:12px;">
                                                <div class="photo-actions">
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="removePhoto()">
                                                        <i class="fas fa-trash"></i> Remover
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <div class="photo-placeholder">
                                                    <i class="fas fa-camera"></i>
                                                    <span>Clique para adicionar foto</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <input type="file" id="foto" name="foto" class="d-none" accept="image/*">
                                        <input type="hidden" name="foto_base64" id="fotoBase64">
                                        <input type="hidden" name="remove_photo" id="removePhotoFlag" value="0">
                                        <div class="photo-info d-flex gap-2 mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnGaleria">
                                                <i class="fas fa-image"></i> Selecionar Foto
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="nome" class="form-label">Nome Completo *</label>
                                <input type="text" id="nome" name="nome" 
                                       class="form-control <?php echo isset($fieldsWithError['nome']) ? 'is-invalid' : ''; ?>" 
                                       required
                                       value="<?php echo $formSubmitted && isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : ($membro ? htmlspecialchars($membro['nome']) : ''); ?>">
                                <?php if (isset($fieldsWithError['nome'])): ?>
                                    <div class="invalid-feedback"><?php echo $fieldsWithError['nome']; ?></div>
                                <?php endif; ?>
                            </div>


                            <div class="col-md-6">
                                <label for="whatsapp" class="form-label">WhatsApp / Celular *</label>
                                <input type="text" id="whatsapp" name="whatsapp" 
                                       class="form-control <?php echo isset($fieldsWithError['whatsapp']) ? 'is-invalid' : ''; ?>"
                                       placeholder="(00) 00000-0000"
                                       value="<?php echo $formSubmitted && isset($_POST['whatsapp']) ? htmlspecialchars($_POST['whatsapp']) : ($membro ? htmlspecialchars($membro['whatsapp'] ?? '') : ''); ?>">
                                <?php if (isset($fieldsWithError['whatsapp'])): ?>
                                    <div class="invalid-feedback"><?php echo $fieldsWithError['whatsapp']; ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="instagram" class="form-label">Instagram</label>
                                <div class="input-group">
                                    <span class="input-group-text">@</span>
                                    <input type="text" id="instagram" name="instagram" class="form-control"
                                           placeholder="seuperfil"
                                           value="<?php echo $formSubmitted && isset($_POST['instagram']) ? htmlspecialchars(ltrim($_POST['instagram'],'@')) : ($membro ? htmlspecialchars(ltrim($membro['instagram'] ?? '','@')) : ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Endereço -->
                    <div class="form-section">
                        <h4 class="form-section-title">
                            <i class="fas fa-map-marker-alt"></i> Endereço
                        </h4>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="cep" class="form-label">CEP</label>
                                <input type="text" id="cep" name="cep" 
                                       class="form-control <?php echo isset($fieldsWithError['cep']) ? 'is-invalid' : ''; ?>"
                                       placeholder="00000-000"
                                       value="<?php echo $formSubmitted && isset($_POST['cep']) ? htmlspecialchars($_POST['cep']) : ($membro ? htmlspecialchars($membro['cep']) : ''); ?>">
                                <?php if (isset($fieldsWithError['cep'])): ?>
                                    <div class="invalid-feedback"><?php echo $fieldsWithError['cep']; ?></div>
                                <?php endif; ?>
                                <small class="text-muted mt-1 d-block">
                                    <i class="fas fa-magic"></i> Digite o CEP completo para busca automática
                                </small>
                            </div>
                            
                            <div class="col-md-9">
                                <label for="endereco" class="form-label">Endereço</label>
                                <input type="text" id="endereco" name="endereco" class="form-control"
                                       placeholder="Rua, Avenida, etc..."
                                       value="<?php echo $formSubmitted && isset($_POST['endereco']) ? htmlspecialchars($_POST['endereco']) : ($membro ? htmlspecialchars($membro['endereco']) : ''); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="cidade" class="form-label">Cidade</label>
                                <input type="text" id="cidade" name="cidade" class="form-control"
                                       placeholder="Nome da cidade"
                                       value="<?php echo $formSubmitted && isset($_POST['cidade']) ? htmlspecialchars($_POST['cidade']) : ($membro ? htmlspecialchars($membro['cidade']) : ''); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="estado" class="form-label">Estado</label>
                                <select id="estado" name="estado" class="form-select">
                                    <option value="">Selecione...</option>
                                    <?php $estadoSelecionado = $formSubmitted && isset($_POST['estado']) ? $_POST['estado'] : ($membro ? $membro['estado'] : ''); ?>
                                    <option value="GO" <?php echo $estadoSelecionado == 'GO' ? 'selected' : ''; ?>>Goiás</option>
                                    <option value="AC" <?php echo $estadoSelecionado == 'AC' ? 'selected' : ''; ?>>Acre</option>
                                    <option value="AL" <?php echo $estadoSelecionado == 'AL' ? 'selected' : ''; ?>>Alagoas</option>
                                    <option value="AP" <?php echo $estadoSelecionado == 'AP' ? 'selected' : ''; ?>>Amapá</option>
                                    <option value="AM" <?php echo $estadoSelecionado == 'AM' ? 'selected' : ''; ?>>Amazonas</option>
                                    <option value="BA" <?php echo $estadoSelecionado == 'BA' ? 'selected' : ''; ?>>Bahia</option>
                                    <option value="CE" <?php echo $estadoSelecionado == 'CE' ? 'selected' : ''; ?>>Ceará</option>
                                    <option value="DF" <?php echo $estadoSelecionado == 'DF' ? 'selected' : ''; ?>>Distrito Federal</option>
                                    <option value="ES" <?php echo $estadoSelecionado == 'ES' ? 'selected' : ''; ?>>Espírito Santo</option>
                                    <option value="MA" <?php echo $estadoSelecionado == 'MA' ? 'selected' : ''; ?>>Maranhão</option>
                                    <option value="MT" <?php echo $estadoSelecionado == 'MT' ? 'selected' : ''; ?>>Mato Grosso</option>
                                    <option value="MS" <?php echo $estadoSelecionado == 'MS' ? 'selected' : ''; ?>>Mato Grosso do Sul</option>
                                    <option value="MG" <?php echo $estadoSelecionado == 'MG' ? 'selected' : ''; ?>>Minas Gerais</option>
                                    <option value="PA" <?php echo $estadoSelecionado == 'PA' ? 'selected' : ''; ?>>Pará</option>
                                    <option value="PB" <?php echo $estadoSelecionado == 'PB' ? 'selected' : ''; ?>>Paraíba</option>
                                    <option value="PR" <?php echo $estadoSelecionado == 'PR' ? 'selected' : ''; ?>>Paraná</option>
                                    <option value="PE" <?php echo $estadoSelecionado == 'PE' ? 'selected' : ''; ?>>Pernambuco</option>
                                    <option value="PI" <?php echo $estadoSelecionado == 'PI' ? 'selected' : ''; ?>>Piauí</option>
                                    <option value="RJ" <?php echo $estadoSelecionado == 'RJ' ? 'selected' : ''; ?>>Rio de Janeiro</option>
                                    <option value="RN" <?php echo $estadoSelecionado == 'RN' ? 'selected' : ''; ?>>Rio Grande do Norte</option>
                                    <option value="RS" <?php echo $estadoSelecionado == 'RS' ? 'selected' : ''; ?>>Rio Grande do Sul</option>
                                    <option value="RO" <?php echo $estadoSelecionado == 'RO' ? 'selected' : ''; ?>>Rondônia</option>
                                    <option value="RR" <?php echo $estadoSelecionado == 'RR' ? 'selected' : ''; ?>>Roraima</option>
                                    <option value="SC" <?php echo $estadoSelecionado == 'SC' ? 'selected' : ''; ?>>Santa Catarina</option>
                                    <option value="SP" <?php echo $estadoSelecionado == 'SP' ? 'selected' : ''; ?>>São Paulo</option>
                                    <option value="SE" <?php echo $estadoSelecionado == 'SE' ? 'selected' : ''; ?>>Sergipe</option>
                                    <option value="TO" <?php echo $estadoSelecionado == 'TO' ? 'selected' : ''; ?>>Tocantins</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Observações -->
                    <div class="form-section">
                        <h4 class="form-section-title">
                            <i class="fas fa-sticky-note"></i> Observações
                        </h4>
                        <div class="row">
                            <div class="col-12">
                                <textarea id="observacoes" name="observacoes" class="form-control" rows="4"
                                          placeholder="Informações adicionais sobre o membro..."><?php echo $formSubmitted && isset($_POST['observacoes']) ? htmlspecialchars($_POST['observacoes']) : ($membro ? htmlspecialchars($membro['observacoes']) : ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status do Aluno -->
                    <div class="form-section mt-4">
                        <h4 class="section-title">
                            <i class="fas fa-toggle-on"></i> Status
                        </h4>
                        <div class="row">
                            <div class="col-12">
                                <div class="d-flex align-items-center gap-3">
                                    <input class="form-check-input m-0" type="checkbox" role="switch" id="ativo" name="ativo" style="width:52px;height:28px;cursor:pointer;flex-shrink:0;"
                                        <?php echo (!$membro || ($membro['ativo'] ?? 1)) ? 'checked' : ''; ?>>
                                    <label for="ativo" style="cursor:pointer;margin:0;">
                                        <span id="ativoLabel" style="font-weight:600;font-size:15px;">
                                            <?php echo (!$membro || ($membro['ativo'] ?? 1)) ? '<span style="color:#16a34a">Aluno Ativo</span>' : '<span style="color:#dc2626">Aluno Inativo</span>'; ?>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botões -->
                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save"></i> Salvar
                        </button>
                        <a href="membros.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Confirm delete
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
        
        // Validação de CPF no frontend
        function validaCPF(cpf) {
            cpf = cpf.replace(/[^\d]+/g,'');
            if(cpf == '') return false;
            if (cpf.length != 11 || 
                cpf == "00000000000" || 
                cpf == "11111111111" || 
                cpf == "22222222222" || 
                cpf == "33333333333" || 
                cpf == "44444444444" || 
                cpf == "55555555555" || 
                cpf == "66666666666" || 
                cpf == "77777777777" || 
                cpf == "88888888888" || 
                cpf == "99999999999")
                    return false;
            add = 0;
            for (i=0; i < 9; i ++)
                add += parseInt(cpf.charAt(i)) * (10 - i);
            rev = 11 - (add % 11);
            if (rev == 10 || rev == 11)
                rev = 0;
            if (rev != parseInt(cpf.charAt(9)))
                return false;
            add = 0;
            for (i = 0; i < 10; i ++)
                add += parseInt(cpf.charAt(i)) * (11 - i);
            rev = 11 - (add % 11);
            if (rev == 10 || rev == 11)
                rev = 0;
            if (rev != parseInt(cpf.charAt(10)))
                return false;
            return true;
        }
        
// CEP - Busca automática
        let cepAnterior = '';
        let camposPreenchidosAutomaticamente = false;
        
        document.getElementById('cep')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            // Se apagou o CEP e os campos foram preenchidos automaticamente, limpar
            if (value.length < cepAnterior.length && value.length < 8 && camposPreenchidosAutomaticamente) {
                document.getElementById('endereco').value = '';
                document.getElementById('cidade').value = '';
                document.getElementById('estado').value = '';
                camposPreenchidosAutomaticamente = false;
            }
            
            cepAnterior = value;
            
            if (value.length <= 8) {
                value = value.replace(/(\d{5})(\d{3})/, '$1-$2');
            }
            e.target.value = value;
            
            // Buscar CEP quando tiver 8 dígitos
            if (value.replace(/\D/g, '').length === 8) {
                buscarCEP(value);
            }
        });
        
        // Adicionar busca ao perder foco também
        document.getElementById('cep')?.addEventListener('blur', function(e) {
            const value = e.target.value.replace(/\D/g, '');
            if (value.length === 8) {
                buscarCEP(value);
            }
        });
        
        // Função para buscar CEP
        function buscarCEP(cep) {
            // Limpar CEP
            cep = cep.replace(/\D/g, '');
            
            // Validar CEP
            if (cep.length !== 8 || cep === '00000000' || cep === '11111111' || cep === '22222222') {
                if (cep.length === 8) {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'warning',
                        title: 'CEP inválido',
                        text: 'Digite um CEP válido',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });
                }
                return;
            }
            
            // Mostrar loading
            const enderecoField = document.getElementById('endereco');
            const cidadeField = document.getElementById('cidade');
            const estadoField = document.getElementById('estado');
            const cepField = document.getElementById('cep');
            
            // Desabilitar campos durante busca
            enderecoField.disabled = true;
            cidadeField.disabled = true;
            estadoField.disabled = true;
            
            // Adicionar indicador de carregamento
            cepField.classList.add('loading-cep');
            cepField.style.paddingRight = '40px';
            
            // Fazer requisição para API ViaCEP
            fetch(`https://viacep.com.br/ws/${cep}/json/`)
                .then(response => {
                    if (!response.ok) throw new Error('Erro na requisição');
                    return response.json();
                })
                .then(data => {
                    // Remover loading
                    cepField.classList.remove('loading-cep');
                    cepField.style.paddingRight = '';
                    
                    // Habilitar campos
                    enderecoField.disabled = false;
                    cidadeField.disabled = false;
                    estadoField.disabled = false;
                    
                    if (!data.erro) {
                        // Preencher campos
                        if (enderecoField && data.logradouro) {
                            let endereco = data.logradouro;
                            if (data.complemento) {
                                endereco += ', ' + data.complemento;
                            }
                            if (data.bairro) {
                                endereco += ' - ' + data.bairro;
                            }
                            enderecoField.value = endereco;
                            
                            // Animar campo preenchido
                            enderecoField.style.backgroundColor = '#d1fae5';
                            setTimeout(() => {
                                enderecoField.style.backgroundColor = '';
                            }, 1000);
                        }
                        
                        if (cidadeField && data.localidade) {
                            cidadeField.value = data.localidade;
                            cidadeField.style.backgroundColor = '#d1fae5';
                            setTimeout(() => {
                                cidadeField.style.backgroundColor = '';
                            }, 1000);
                        }
                        
                        if (estadoField && data.uf) {
                            estadoField.value = data.uf;
                            estadoField.style.backgroundColor = '#d1fae5';
                            setTimeout(() => {
                                estadoField.style.backgroundColor = '';
                            }, 1000);
                        }
                        
                        // Marcar que os campos foram preenchidos automaticamente
                        camposPreenchidosAutomaticamente = true;
                        
                        // Feedback visual no campo CEP
                        cepField.classList.add('cep-found');
                        cepField.style.paddingRight = '40px';
                        setTimeout(() => {
                            cepField.classList.remove('cep-found');
                            cepField.style.paddingRight = '';
                        }, 3000);
                        
                        // Toast de sucesso
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'CEP encontrado!',
                            text: `${data.localidade} - ${data.uf}`,
                            showConfirmButton: false,
                            timer: 3000,
                            timerProgressBar: true
                        });
                    } else {
                        // CEP não encontrado
                        cepField.classList.add('cep-error');
                        cepField.style.paddingRight = '40px';
                        setTimeout(() => {
                            cepField.classList.remove('cep-error');
                            cepField.style.paddingRight = '';
                        }, 3000);
                        
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'warning',
                            title: 'CEP não encontrado',
                            text: 'Verifique o número digitado',
                            showConfirmButton: false,
                            timer: 3000,
                            timerProgressBar: true
                        });
                    }
                })
                .catch(error => {
                    // Remover loading
                    cepField.classList.remove('loading-cep');
                    cepField.style.paddingRight = '';
                    
                    // Habilitar campos
                    enderecoField.disabled = false;
                    cidadeField.disabled = false;
                    estadoField.disabled = false;
                    
                    // Erro na busca
                    cepField.classList.add('cep-error');
                    cepField.style.paddingRight = '40px';
                    setTimeout(() => {
                        cepField.classList.remove('cep-error');
                        cepField.style.paddingRight = '';
                    }, 3000);
                    
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'error',
                        title: 'Erro ao buscar CEP',
                        text: 'Verifique sua conexão',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });
                    console.error('Erro ao buscar CEP:', error);
                });
        }
        
        // Validação e submit com foto via AJAX
        document.getElementById('memberForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            let isValid = true;

            const nome = document.getElementById('nome');
            if (!nome || !nome.value || nome.value.trim().length < 3) {
                nome?.classList.add('is-invalid'); isValid = false;
            } else { nome.classList.remove('is-invalid'); }

            const whatsapp = document.getElementById('whatsapp');
            if (!whatsapp || whatsapp.value.replace(/\D/g,'').length < 10) {
                whatsapp?.classList.add('is-invalid'); isValid = false;
            } else { whatsapp.classList.remove('is-invalid'); }

            if (!isValid) {
                const firstError = document.querySelector('.is-invalid');
                if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                Swal.fire({ icon: 'error', title: 'Erro de validação', text: 'Por favor, corrija os campos destacados.', confirmButtonColor: '#dc2626' });
                return;
            }

            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) { submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...'; submitBtn.disabled = true; }

            const fotoBase64 = document.getElementById('fotoBase64')?.value || '';
            const membroId   = document.getElementById('membroIdField')?.value || '0';

            if (fotoBase64 && fotoBase64.startsWith('data:image')) {
                if (membroId && membroId !== '0') {
                    // Edição: salva foto via AJAX agora
                    try {
                        const fd = new FormData();
                        fd.append('membro_id', membroId);
                        fd.append('foto', fotoBase64);
                        await fetch('salvar_foto.php', { method: 'POST', body: fd });
                    } catch(err) { console.warn('Foto AJAX error:', err); }
                    document.getElementById('fotoBase64').value = '';
                } else {
                    // Novo aluno: guarda foto no sessionStorage para salvar após redirect
                    try { sessionStorage.setItem('pendingPhoto', fotoBase64); } catch(e) {}
                    document.getElementById('fotoBase64').value = '';
                }
            }

            this.submit();
        });

        // Ao carregar página de edição: verificar se há foto pendente no sessionStorage
        (async function() {
            const membroId = document.getElementById('membroIdField')?.value || '0';
            if (membroId && membroId !== '0') {
                let foto = '';
                try { foto = sessionStorage.getItem('pendingPhoto') || ''; } catch(e) {}
                if (foto && foto.startsWith('data:image')) {
                    try {
                        sessionStorage.removeItem('pendingPhoto');
                        const fd = new FormData();
                        fd.append('membro_id', membroId);
                        fd.append('foto', foto);
                        const res = await fetch('salvar_foto.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.ok) {
                            // Atualizar preview
                            const prev = document.getElementById('photoPreview');
                            if (prev) prev.innerHTML = `<img src="${foto}" style="width:100%;height:100%;object-fit:cover;border-radius:12px;">`;
                        }
                    } catch(e) { console.warn('pendingPhoto error:', e); }
                }
            }
        })();
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
        
        // Show success message from URL
        <?php if (isset($_GET['message'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: '<?php echo htmlspecialchars($_GET['message']); ?>',
                timer: 3000,
                showConfirmButton: false
            });
        });
        <?php endif; ?>
        
        // Add smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
        
        // Add animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);
        
        // Observe all cards
        document.querySelectorAll('.stat-card, .main-card, .form-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.6s ease-out';
            observer.observe(card);
        });
        
        // Remove invalid class on input
        document.querySelectorAll('.form-control, .form-select').forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
        });
        
        // Photo Upload Functionality
        const photoPreview = document.getElementById('photoPreview');
        const photoInput = document.getElementById('foto');

        // Click no preview para abrir câmera/arquivo
        photoPreview?.addEventListener('click', function() {
            photoInput.removeAttribute('capture');
            photoInput.click();
        });

        // Botão galeria
        document.getElementById('btnGaleria')?.addEventListener('click', function(e) {
            e.stopPropagation();
            photoInput.removeAttribute('capture');
            photoInput.click();
        });

        // Ao selecionar imagem — converter para base64 e salvar no hidden input
        photoInput?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file || !file.type.startsWith('image/')) return;

            const reader = new FileReader();
            reader.onload = function(event) {
                const base64 = event.target.result; // data:image/jpeg;base64,...
                document.getElementById('fotoBase64').value = base64;

                // Redimensionar para max 800px antes de mostrar/salvar
                const img = new Image();
                img.onload = function() {
                    const canvas = document.createElement('canvas');
                    let w = img.width, h = img.height;
                    const max = 800;
                    if (w > max || h > max) {
                        if (w > h) { h = Math.round(h * max / w); w = max; }
                        else { w = Math.round(w * max / h); h = max; }
                    }
                    canvas.width = w; canvas.height = h;
                    canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                    const compressed = canvas.toDataURL('image/jpeg', 0.8);
                    document.getElementById('fotoBase64').value = compressed;
                    photoPreview.innerHTML = `
                        <img src="${compressed}" alt="Preview" style="width:100%;height:100%;object-fit:cover;border-radius:12px;">
                        <div class="photo-actions">
                            <button type="button" class="btn btn-sm btn-danger" onclick="removePhotoPreview()">
                                <i class="fas fa-trash"></i> Remover
                            </button>
                        </div>`;
                    document.getElementById('removePhotoFlag').value = '0';
                };
                img.src = base64;
            };
            reader.readAsDataURL(file);
        });

        // Remover foto atual (ao editar)
        function removePhoto() {
            Swal.fire({
                title: 'Remover foto?', text: 'A foto atual será removida ao salvar',
                icon: 'warning', showCancelButton: true,
                confirmButtonText: 'Sim, remover', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc2626'
            }).then((result) => {
                if (result.isConfirmed) {
                    photoPreview.innerHTML = `<div class="photo-placeholder"><i class="fas fa-camera"></i><span>Clique para adicionar foto</span></div>`;
                    document.getElementById('removePhotoFlag').value = '1';
                    photoInput.value = '';
                }
            });
        }

        // Máscara WhatsApp
        const whatsappInput = document.getElementById('whatsapp');
        if (whatsappInput) {
            whatsappInput.addEventListener('input', function(e) {
                let v = e.target.value.replace(/\D/g, '').substring(0,11);
                if (v.length > 6) v = '(' + v.substring(0,2) + ') ' + v.substring(2,7) + '-' + v.substring(7);
                else if (v.length > 2) v = '(' + v.substring(0,2) + ') ' + v.substring(2);
                else if (v.length > 0) v = '(' + v;
                e.target.value = v;
            });
        }
        // Toggle Ativo/Inativo
        const ativoCheck = document.getElementById('ativo');
        const ativoLabel = document.getElementById('ativoLabel');
        if (ativoCheck && ativoLabel) {
            ativoCheck.addEventListener('change', function() {
                ativoLabel.innerHTML = this.checked
                    ? '<span style="color:#16a34a">Aluno Ativo</span>'
                    : '<span style="color:#dc2626">Aluno Inativo</span>';
            });
        }
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