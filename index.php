<?php
// Verificar se o arquivo membros.php existe
if (!file_exists('membros.php')) {
    // Se não existir, criar um alerta
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            console.error('ATENÇÃO: Arquivo membros.php não encontrado!');
            // Substituir links para membros.php por alertas
            document.querySelectorAll('a[href*=\"membros.php\"]').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    alert('Arquivo membros.php não encontrado! Por favor, faça o upload do arquivo.');
                });
            });
        });
    </script>";
}
?>
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
checkLogin();

// Buscar estatísticas
try {
    $pdo = getConnection();

// Contar solicitações pendentes para badge na navbar
try {
    $stmt_nav = $pdo->query("SELECT COUNT(*) as total FROM membros WHERE aprovado = 0");
    $nav_pendentes = $stmt_nav->fetch()['total'] ?? 0;
} catch (Exception $e) { $nav_pendentes = 0; }

    if (!$pdo) {
        throw new Exception("Falha ao conectar com o banco de dados");
    }

    // Verificar se há mensagem de sucesso/erro na URL
    if (isset($_GET['message'])) {
        $message = htmlspecialchars($_GET['message']);
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Sucesso!',
                    text: '{$message}',
                    timer: 3000,
                    showConfirmButton: false
                });
            });
        </script>";
    }

} catch (Exception $e) {
    die("<div style='padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; margin: 20px; border-radius: 5px;'>
         <h3>Erro de Conexão</h3>
         <p>" . $e->getMessage() . "</p>
         </div>");
}

// Paginação
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10;
$offset = ($page - 1) * $per_page;

// Filtros
$search = $_GET['search'] ?? '';

// Construir query
$where = "WHERE m.aprovado = 1";
$params = [];

if ($search) {
    $where .= " AND (m.nome LIKE :search OR m.whatsapp LIKE :search OR m.instagram LIKE :search)";
    $params[':search'] = "%$search%";
}

// Contar total de registros
$count_sql = "SELECT COUNT(*) as total FROM membros m $where";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// Buscar registros
$sql = "SELECT m.* FROM membros m $where ORDER BY m.nome ASC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$membros = $stmt->fetchAll();

// Estatísticas
$stats = [];
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM membros WHERE aprovado = 1 AND (ativo IS NULL OR ativo = 1)");
    $stats['total'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM membros WHERE aprovado = 1 AND (ativo IS NULL OR ativo = 1) AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['novos'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM membros WHERE aprovado = 1 AND ativo = 0");
    $stats['inativos'] = $stmt->fetch()['total'] ?? 0;

    $stats['filiados'] = $stats['total'];
    $stats['desfiliados'] = $stats['inativos'];
} catch (PDOException $e) {
    $stats = ['total' => 0, 'filiados' => 0, 'desfiliados' => 0, 'novos' => 0];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RemiLeal - Gestão de Alunos</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

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

            --gradient-primary: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            --gradient-secondary: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%);
            --gradient-success: linear-gradient(135deg, #059669 0%, #10b981 100%);
            --gradient-danger: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);

            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f9ff;
            color: var(--dark);
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

        /* Top Header */
        .top-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 50%, #2563eb 100%);
            padding: 12px 0;
            box-shadow: var(--shadow-lg);
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(10px);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 24px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .logo-section h1 {
            color: white;
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.5px;
        }

        .logo-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        /* Navigation Menu */
        .nav-menu {
            background: white;
            padding: 16px 24px;
            box-shadow: var(--shadow-md);
            border-bottom: 2px solid #dbeafe;
            position: sticky;
            top: 60px;
            z-index: 999;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }

        .menu-items {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .menu-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 12px 20px;
            border-radius: 12px;
            text-decoration: none;
            color: var(--gray);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: visible;
            background: #f0f9ff;
            border: 1px solid #e0f2fe;
        }

        .menu-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            opacity: 0;
            transition: opacity 0.3s;
            z-index: -1;
        }

        .menu-item:hover {
            color: #1e40af;
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            border-color: #3b82f6;
            background: #dbeafe;
        }

        .menu-item.active {
            color: white;
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            box-shadow: var(--shadow-lg);
            border-color: transparent;
        }

        .menu-item.active::before {
            opacity: 1;
        }

        .menu-item i {
            font-size: 20px;
            margin-bottom: 4px;
        }

        .menu-item span {
            font-size: 12px;
            font-weight: 500;
        }

        /* Stats Cards */
        .stats-container {
            padding: 24px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid #dbeafe;
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
            box-shadow: var(--shadow-xl);
        }

        .stat-card.total {
            --gradient-primary: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
        }

        .stat-card.success {
            --gradient-primary: linear-gradient(135deg, #0284c7 0%, #0ea5e9 100%);
        }

        .stat-card.danger {
            --gradient-primary: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 100%);
        }

        .stat-card.warning {
            --gradient-primary: linear-gradient(135deg, #3730a3 0%, #4f46e5 100%);
        }

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
            box-shadow: var(--shadow-md);
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

        .stat-trend {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            color: var(--success);
            margin-top: 8px;
        }

        /* Filter Section */
        .filter-container {
            background: white;
            padding: 20px 24px;
            margin: 0 24px 24px;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            border: 1px solid #dbeafe;
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .filter-title {
            font-size: 18px;
            font-weight: 600;
            color: #1e40af;
            display: flex;
            align-items: center;
            gap: 8px;
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

        .form-control,
        .form-select {
            padding: 10px 16px;
            font-size: 14px;
            border: 2px solid #e0f2fe;
            border-radius: 10px;
            background: #f0f9ff;
            transition: all 0.3s;
            font-weight: 500;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #2563eb;
            background: white;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
        }

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

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: white;
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
        }

        .btn-success {
            background: linear-gradient(135deg, #0284c7 0%, #0ea5e9 100%);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: white;
            background: linear-gradient(135deg, #0369a1 0%, #0284c7 100%);
        }

        .btn-info {
            background: linear-gradient(135deg, #3730a3 0%, #4f46e5 100%);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: white;
            background: linear-gradient(135deg, #312e81 0%, #3730a3 100%);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: white;
            background: linear-gradient(135deg, #b91c1c 0%, #dc2626 100%);
        }

        .btn-icon {
            width: 40px;
            height: 40px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }

        /* Table Section */
        .table-container {
            background: white;
            margin: 0 24px 24px;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
            border: 1px solid #dbeafe;
        }

        .table-header {
            padding: 20px 24px;
            background: #f0f9ff;
            border-bottom: 1px solid #e0f2fe;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 18px;
            font-weight: 600;
            color: #1e40af;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            margin: 0;
        }

        .table thead {
            background: #f0f9ff;
            border-bottom: 2px solid #dbeafe;
        }

        .table thead th {
            padding: 16px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray);
            white-space: nowrap;
            position: relative;
            user-select: none;
            cursor: pointer;
            transition: all 0.3s;
        }

        .table thead th:hover {
            color: #1e40af;
            background: #e0f2fe;
        }

        .table tbody tr {
            border-bottom: 1px solid #e0f2fe;
            transition: all 0.3s;
        }

        .table tbody tr:hover {
            background: #dbeafe;
            transform: scale(1.01);
            box-shadow: var(--shadow-md);
        }

        .table tbody td {
            padding: 16px;
            vertical-align: middle;
            font-size: 14px;
            color: var(--dark);
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-badge.filiado {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-badge.desfiliado {
            background: #fef3c7;
            color: #92400e;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
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
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-action.view:hover {
            background: #2563eb;
            color: white;
        }

        .btn-action.edit:hover {
            background: #0891b2;
            color: white;
        }

        .btn-action.delete:hover {
            background: #dc2626;
            color: white;
        }

        /* Pagination */
        .pagination-container {
            padding: 20px 24px;
            background: #f0f9ff;
            border-top: 1px solid #dbeafe;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .pagination-info {
            color: var(--gray);
            font-size: 14px;
            font-weight: 500;
        }

        .pagination {
            display: flex;
            gap: 8px;
            margin: 0;
        }

        .page-link {
            padding: 8px 12px;
            border-radius: 8px;
            background: white;
            color: #2563eb;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            border: 1px solid #dbeafe;
            text-decoration: none;
        }

        .page-link:hover {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .page-item.active .page-link {
            background: #1e40af;
            color: white;
            border-color: #1e40af;
            box-shadow: var(--shadow-md);
        }

        .page-item.disabled .page-link {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* User Menu */
        .user-menu {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #2563eb;
            box-shadow: var(--shadow-md);
        }

        .user-name {
            color: white;
            font-weight: 600;
            font-size: 14px;
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

        /* Loading */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(219, 234, 254, 0.95);
            backdrop-filter: blur(10px);
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid #dbeafe;
            border-top-color: #2563eb;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            /* Header */
            .header-content {
                flex-direction: row;
                justify-content: space-between;
                padding: 0 12px;
                gap: 8px;
            }
            .logo-section h1 { font-size: 18px; }
            .logo-section { gap: 8px; }
            .logo-badge { display: none; }
            .logo-section img { height: 38px !important; width: 38px !important; }
            .user-section .btn span { display: none; }
            .user-section { gap: 6px; }

            /* Nav */
            .nav-menu { padding: 10px 8px; top: 52px; }
            .menu-items {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 6px;
            }
            .menu-item { padding: 10px 4px; border-radius: 10px; }
            .menu-item span { font-size: 10px; }
            .menu-item i { font-size: 16px; }

            /* Stats — 2 colunas no mobile */
            .stats-container {
                padding: 12px;
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            .stat-value { font-size: 24px; }
            .stat-icon { width: 38px; height: 38px; font-size: 18px; }

            /* Filtros */
            .filter-container { margin: 0 12px 12px; padding: 14px; }
            .filter-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .filter-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
                width: 100%;
            }
            .filter-actions .btn { flex: 1; min-width: 100px; font-size: 12px; padding: 8px 6px; }
            .filter-grid { grid-template-columns: 1fr; gap: 8px; }

            /* Tabela */
            .table-container { margin: 0 12px 12px; }
            .table-header {
                flex-direction: column;
                gap: 8px;
                align-items: flex-start;
            }
            .table th, .table td { font-size: 12px; padding: 8px 6px; }
            .action-buttons { gap: 4px; }
            .btn-action { width: 28px; height: 28px; font-size: 11px; }

            /* Paginação */
            .pagination-container {
                flex-direction: column;
                gap: 10px;
                padding: 12px;
            }
        }

        @media (max-width: 400px) {
            .stats-container { grid-template-columns: 1fr; }
            .menu-items { grid-template-columns: repeat(3, 1fr); }
        }

        /* Animations */
        .animate-in {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }
    </style>
</head>

<body>
    <div class="loading-overlay" id="loadingOverlay" style="display: none;">
        <div class="spinner"></div>
    </div>

    <header class="top-header">
        <div class="header-content">
            <div class="logo-section">
                <img src="assets/img/logo_remi.png" alt="RemiLeal" style="height:54px;width:54px;object-fit:contain;">
                <h1>RemiLeal</h1>
                <span class="logo-badge">Prof. Ritmos</span>
            </div>

            <div class="user-menu">
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <span class="user-name"><?php echo $_SESSION['user_email'] ?? 'Administrador'; ?></span>
                </div>
                <a href="logout.php" class="btn btn-danger btn-icon" title="Sair">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </header>

    <nav class="nav-menu">
        <div class="menu-items">
            <a href="index.php" class="menu-item active">
                <i class="fas fa-users"></i>
                <span>Associados</span>
            </a>
            <a href="relatorios.php" class="menu-item">
                <i class="fas fa-chart-bar"></i>
                <span>Relatórios</span>
            </a>
            <a href="solicitacoes.php" class="menu-item" style="position:relative;">
                <i class="fas fa-user-clock"></i>
                <span>Solicitações</span>
                <?php if ($nav_pendentes > 0): ?>
                <span style="position:absolute;top:-8px;right:-8px;background:#ef4444;color:#fff;border-radius:50%;width:20px;height:20px;font-size:11px;display:flex;align-items:center;justify-content:center;font-weight:700;z-index:10;box-shadow:0 2px 4px rgba(0,0,0,0.3);"><?php echo $nav_pendentes; ?></span>
                <?php endif; ?>
            </a>
        </div>
    </nav>

    <div class="stats-container">
        <div class="stat-card total animate__animated animate__fadeInUp">
            <div class="stat-header">
                <div>
                    <div class="stat-value" id="rt-total"><?php echo number_format($stats['total'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Total de Alunos</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>

        <div class="stat-card success animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
            <div class="stat-header">
                <div>
                    <div class="stat-value" id="rt-ativos"><?php echo number_format($stats['filiados'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Alunos Ativos</div>
                    <div class="stat-trend" id="rt-trend">
                        <i class="fas fa-arrow-up"></i>
                        <?php echo round(($stats['filiados'] / max($stats['total'], 1)) * 100); ?>% do total
                    </div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-user-check"></i>
                </div>
            </div>
        </div>

        <div class="stat-card danger animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
            <div class="stat-header">
                <div>
                    <div class="stat-value" id="rt-inativos"><?php echo number_format($stats['inativos'] ?? 0, 0, ',', '.'); ?></div>
                    <div class="stat-label">Alunos Inativos</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-user-times"></i>
                </div>
            </div>
        </div>

        <div class="stat-card warning animate__animated animate__fadeInUp" style="animation-delay: 0.3s">
            <div class="stat-header">
                <div>
                    <div class="stat-value" id="rt-novos"><?php echo number_format($stats['novos'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Novos (30 dias)</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="filter-container animate__animated animate__fadeIn">
        <div class="filter-header">
            <h3 class="filter-title">
                <i class="fas fa-filter"></i>
                Filtros de Busca
            </h3>
            
            <div class="d-flex gap-2">
                <a href="membros.php?action=add" class="btn btn-success">
                    <i class="fas fa-plus-circle"></i>
                    Novo Aluno
                </a>

                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalLinkCadastro"
                    style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); border: none; color: white;">
                    <i class="fas fa-share-alt"></i>
                    Link de Filiação
                </button>
                
                <button class="btn btn-info" onclick="exportData()">
                    <i class="fas fa-download"></i>
                    Exportar
                </button>
            </div>
        </div>

        <form method="GET" action="" id="filterForm">
            <div class="filter-grid">
                <div class="form-group">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" placeholder="Buscar por nome, WhatsApp ou Instagram..." name="search"
                        value="<?php echo htmlspecialchars($search); ?>">
                </div>

<div class="form-group">
                    <label class="form-label">Registros</label>
                    <select name="per_page" class="form-select" onchange="this.form.submit()">
                        <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10 por página</option>
                        <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25 por página</option>
                        <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50 por página</option>
                        <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100 por página</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i>
                        Buscar
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="table-container animate__animated animate__fadeIn">
        <div class="table-header">
            <h3 class="table-title">Lista de Alunos</h3>
            <div class="text-muted">
                <?php echo $total_records; ?> registros encontrados
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th width="150">WhatsApp</th>
                        <th width="150">Instagram</th>
                        <th width="100" class="text-center">Status</th>
                        <th width="120" class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($membros)): ?>
                        <?php foreach ($membros as $membro): ?>
                            <tr class="fade-in">
                                <td class="fw-semibold">
                                    <?php echo htmlspecialchars(strtoupper($membro['nome'])); ?>
                                </td>
                                <td><?php echo htmlspecialchars($membro['whatsapp'] ?? '-'); ?></td>
                                <td><?php echo !empty($membro['instagram']) ? '@'.htmlspecialchars(ltrim($membro['instagram'],'@')) : '-'; ?></td>
                                <td class="text-center">
                                    <?php if (($membro['ativo'] ?? 1)): ?>
                                        <span style="display:inline-flex;align-items:center;gap:5px;background:#dcfce7;color:#16a34a;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;">
                                            <span style="width:7px;height:7px;background:#16a34a;border-radius:50%;display:inline-block;"></span> Ativo
                                        </span>
                                    <?php else: ?>
                                        <span style="display:inline-flex;align-items:center;gap:5px;background:#fee2e2;color:#dc2626;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;">
                                            <span style="width:7px;height:7px;background:#dc2626;border-radius:50%;display:inline-block;"></span> Inativo
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="membros_view.php?id=<?php echo $membro['id']; ?>" class="btn-action view"
                                            title="Visualizar">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="membros.php?action=edit&id=<?php echo $membro['id']; ?>"
                                            class="btn-action edit" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="confirmDelete(<?php echo $membro['id']; ?>)" class="btn-action delete"
                                            title="Excluir">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">
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
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    Mostrando <?php echo min($offset + 1, $total_records); ?> a
                    <?php echo min($offset + $per_page, $total_records); ?> de <?php echo $total_records; ?> registros
                </div>

                <nav>
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link"
                                    href="?page=1<?php echo $search ? '&search=' . $search : ''; ?>">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link"
                                    href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . $search : ''; ?>">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);

                        for ($i = $start; $i <= $end; $i++):
                            ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link"
                                    href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . $search : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link"
                                    href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . $search : ''; ?>">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link"
                                    href="?page=<?php echo $total_pages; ?><?php echo $search ? '&search=' . $search : ''; ?>">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>

    <div class="modal fade" id="modalLinkCadastro" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: var(--shadow-xl);">
                <div class="modal-header"
                    style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; border-radius: 16px 16px 0 0;">
                    <h5 class="modal-title">
                        <i class="fas fa-link me-2"></i> Link de Auto-Cadastro
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <div class="mb-4">
                        <div
                            style="width: 64px; height: 64px; background: #e0e7ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; color: #6366f1; font-size: 32px;">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <h6 class="fw-bold mb-2">Convide novos membros</h6>
                        <p class="text-muted small">Copie o link abaixo e envie para que as pessoas possam
                            preencher seus dados e se filiarem sozinhas.</p>
                    </div>

                    <div class="input-group mb-3">
                        <?php
                        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                        $host = $_SERVER['HTTP_HOST'];
                        // Ajuste 'filiacao.php' para o nome do arquivo que você vai criar para o formulário público
                        $link_cadastro = "$protocol://$host/filiacao.php";
                        ?>
                        <input type="text" class="form-control bg-light" id="linkCadastroInput"
                            value="<?php echo $link_cadastro; ?>" readonly style="border: 2px solid #e0e7ff;">
                        <button class="btn btn-primary" type="button" onclick="copiarLink()"
                            style="background: #6366f1; border-color: #6366f1;">
                            <i class="fas fa-copy"></i> Copiar
                        </button>
                    </div>

                    <div class="alert alert-primary d-flex align-items-center small py-2" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <div>O cadastro entrará como "Pendente" no sistema.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Loading functions
        function showLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.style.display = 'flex';
                // Timeout de segurança para evitar loading infinito
                setTimeout(() => {
                    hideLoading();
                }, 5000);
            }
        }

        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.style.display = 'none';
            }
        }

        // Form submit with loading
        document.getElementById('filterForm').addEventListener('submit', function (e) {
            // Não prevenir o default aqui, pois queremos que o form seja enviado
            showLoading();
        });

        // Auto-submit on select change (exceto per_page que já tem onchange)
        document.querySelectorAll('.form-select').forEach(select => {
            if (select.name !== 'per_page') {
                select.addEventListener('change', function () {
                    // Pequeno delay para evitar múltiplos submits
                    setTimeout(() => {
                        showLoading();
                        document.getElementById('filterForm').submit();
                    }, 100);
                });
            }
        });

        // Export data
        function exportData() {
            Swal.fire({
                title: 'Exportar Dados',
                text: 'Selecione o formato de exportação:',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-file-excel"></i> Excel',
                cancelButtonText: '<i class="fas fa-file-pdf"></i> PDF',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#ef4444',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    showLoading();
                    window.location.href = 'export.php?format=excel';
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    showLoading();
                    window.location.href = 'export.php?format=pdf';
                }
            });
        }

        // Confirm delete
        function confirmDelete(id) {
            Swal.fire({
                title: 'Confirmar Exclusão',
                text: 'Tem certeza que deseja excluir este membro?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
            }).then((result) => {
                if (result.isConfirmed) {
                    showLoading();
                    window.location.href = `membros.php?action=delete&id=${id}`;
                }
            });
        }

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Garantir que o loading seja escondido ao carregar a página
        (function () {
            // Esconder imediatamente se já estiver carregado
            if (document.readyState === 'complete') {
                hideLoading();
            }

            // Adicionar múltiplos listeners para garantir
            window.addEventListener('load', hideLoading);
            document.addEventListener('DOMContentLoaded', hideLoading);

            // Timeout final de segurança
            setTimeout(hideLoading, 1000);
        })();

        // Adicionar listener para cliques no botão Novo Aluno
        document.addEventListener('DOMContentLoaded', function () {
            // Verificar todos os links para membros.php
            document.querySelectorAll('a[href*="membros.php"]').forEach(link => {
                link.addEventListener('click', function (e) {
                    // Se for link para adicionar ou editar, mostrar loading
                    if (this.href.includes('action=')) {
                        showLoading();
                    }
                });
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function (e) {
            // Ctrl + N = New member
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                window.location.href = 'membros.php?action=add';
            }
            // Ctrl + F = Focus search
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.querySelector('input[name="search"]').focus();
            }
            // Ctrl + E = Export
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                exportData();
            }
        });

        // Add hover effect to table rows
        document.querySelectorAll('tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function () {
                this.style.transform = 'scale(1.01)';
            });
            row.addEventListener('mouseleave', function () {
                this.style.transform = 'scale(1)';
            });
        });

        // Função para copiar o link
        function copiarLink() {
            var copyText = document.getElementById("linkCadastroInput");

            // Seleciona o texto
            copyText.select();
            copyText.setSelectionRange(0, 99999); // Para mobile

            // Copia para a área de transferência
            try {
                navigator.clipboard.writeText(copyText.value).then(function () {
                    // Sucesso usando API moderna
                    Swal.fire({
                        icon: 'success',
                        title: 'Link Copiado!',
                        text: 'O link foi copiado para sua área de transferência.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    // Fecha o modal após copiar
                    var modalEl = document.getElementById('modalLinkCadastro');
                    var modal = bootstrap.Modal.getInstance(modalEl);
                    modal.hide();
                });
            } catch (err) {
                // Fallback para navegadores antigos
                document.execCommand("copy");
                Swal.fire({
                    icon: 'success',
                    title: 'Link Copiado!',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        }
    </script>

    <!-- ── TEMPO REAL ──────────────────────────────────────────────────── -->
    <script>
    (function() {
        const INTERVAL = 10000; // 10 segundos
        let lastPendentes = <?php echo $nav_pendentes ?? 0; ?>;
        let lastTotal     = <?php echo $stats['total'] ?? 0; ?>;

        function animateValue(el, newVal) {
            if (!el) return;
            const cur = parseInt(el.textContent.replace(/\D/g,'')) || 0;
            if (cur === newVal) return;
            el.style.transition = 'transform .25s ease, opacity .25s ease';
            el.style.transform  = 'scale(1.2)';
            el.style.opacity    = '0.5';
            setTimeout(() => {
                el.textContent = newVal.toLocaleString('pt-BR');
                el.style.transform = 'scale(1)';
                el.style.opacity   = '1';
            }, 200);
        }

        function updateBadge(count) {
            document.querySelectorAll('.rt-badge').forEach(b => {
                if (count > 0) {
                    b.textContent = count;
                    b.style.display = 'flex';
                } else {
                    b.style.display = 'none';
                }
            });
        }

        function pulseNotify(msg) {
            const toast = document.createElement('div');
            toast.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#1e40af;color:#fff;padding:12px 20px;border-radius:12px;font-size:14px;font-weight:600;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,0.25);animation:slideIn .3s ease';
            toast.innerHTML = '🔔 ' + msg;
            document.body.appendChild(toast);
            setTimeout(() => { toast.style.opacity='0'; toast.style.transition='opacity .4s'; setTimeout(()=>toast.remove(), 400); }, 4000);
        }

        async function pollStats() {
            try {
                const r = await fetch('api_realtime.php?action=stats', {cache:'no-store'});
                if (!r.ok) return;
                const d = await r.json();

                animateValue(document.getElementById('rt-total'),   d.total);
                animateValue(document.getElementById('rt-ativos'),  d.total - d.inativos);
                animateValue(document.getElementById('rt-inativos'),d.inativos);
                animateValue(document.getElementById('rt-novos'),   d.novos);

                // Trend %
                const trend = document.getElementById('rt-trend');
                if (trend && d.total > 0) {
                    const pct = Math.round(((d.total - d.inativos) / d.total) * 100);
                    trend.innerHTML = '<i class="fas fa-arrow-up"></i> ' + pct + '% do total';
                }

                // Badge
                updateBadge(d.pendentes);

                // Notificações
                if (d.pendentes > lastPendentes) {
                    pulseNotify('Nova solicitação de cadastro!');
                }
                if (d.total > lastTotal) {
                    pulseNotify('Novo aluno adicionado!');
                }
                lastPendentes = d.pendentes;
                lastTotal     = d.total;

            } catch(e) { /* silencioso */ }
        }

        // Substituir badges estáticos por dinâmicos
        document.querySelectorAll('[style*="position:absolute"]').forEach(el => {
            if (el.closest('a[href="solicitacoes.php"]')) {
                el.classList.add('rt-badge');
            }
        });

        // Iniciar polling
        pollStats();
        setInterval(pollStats, INTERVAL);


    })();

    // Animação do indicador
    const rtStyle = document.createElement('style');
    rtStyle.textContent = '@keyframes slideIn{from{transform:translateX(100px);opacity:0}to{transform:translateX(0);opacity:1}} @keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.3)}}';
    document.head.appendChild(rtStyle);
    </script>
</body>

</html>