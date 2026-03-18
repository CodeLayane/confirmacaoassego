<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
checkLogin();

$pdo = getConnection();
$action = $_GET['action'] ?? 'list';
$message = '';
$error = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($action == 'add' || $action == 'edit') {
        $nome = clean_input($_POST['nome']);
        $descricao = clean_input($_POST['descricao']);

        try {
            if ($action == 'add') {
                $sql = "INSERT INTO cargos (nome, descricao) VALUES (?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nome, $descricao]);
                $message = "Cargo cadastrado com sucesso!";
            } else {
                $id = $_POST['id'];
                $sql = "UPDATE cargos SET nome=?, descricao=? WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nome, $descricao, $id]);
                $message = "Cargo atualizado com sucesso!";
            }
            header("Location: cargos.php?message=" . urlencode($message));
            exit();
        } catch (PDOException $e) {
            $error = "Erro ao salvar: " . $e->getMessage();
        }
    }
}

// Processar exclusão
if ($action == 'delete' && isset($_GET['id'])) {
    try {
        // Verificar se existem membros com este cargo
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM membros WHERE cargo_id = ?");
        $stmt->execute([$_GET['id']]);
        $total = $stmt->fetch()['total'];

        if ($total > 0) {
            $error = "Não é possível excluir este cargo pois existem $total membro(s) associado(s) a ele.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM cargos WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            header("Location: cargos.php?message=" . urlencode("Cargo excluído com sucesso!"));
            exit();
        }
    } catch (PDOException $e) {
        $error = "Erro ao excluir: " . $e->getMessage();
    }
}

// Buscar cargo para edição
$cargo = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM cargos WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $cargo = $stmt->fetch();
}

// Buscar todos os cargos com contagem de membros
$sql = "SELECT c.*, COUNT(m.id) as total_membros 
        FROM cargos c 
        LEFT JOIN membros m ON c.id = m.cargo_id 
        GROUP BY c.id 
        ORDER BY c.nome";
$cargos = $pdo->query($sql)->fetchAll();

// Mensagem da URL
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

// Estatísticas
$total_cargos = count($cargos);
$total_membros_com_cargo = $pdo->query("SELECT COUNT(DISTINCT id) FROM membros WHERE cargo_id IS NOT NULL")->fetchColumn();
$cargo_mais_usado = $pdo->query("SELECT c.nome, COUNT(m.id) as total FROM cargos c JOIN membros m ON c.id = m.cargo_id GROUP BY c.id ORDER BY total DESC LIMIT 1")->fetch();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Turmas - RemiLeal</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

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
            max-width: 100%;
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
            overflow: hidden;
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
            transition: all 0.3s;
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
            margin-bottom: 16px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .stat-label {
            color: var(--gray);
            font-size: 14px;
        }

        /* Content Container */
        .content-container {
            padding: 24px;
        }

        /* Page Header */
        .page-header {
            background: white;
            padding: 20px 24px;
            margin: 0 24px 24px;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            border: 1px solid #dbeafe;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-title {
            font-size: 24px;
            font-weight: 700;
            color: #1e40af;
            margin: 0;
        }

        /* Data Table */
        .table-container {
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
            border: 1px solid #dbeafe;
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
            color: #1e3a8a;
        }

        .table tbody tr {
            border-bottom: 1px solid #e0f2fe;
            transition: all 0.3s;
        }

        .table tbody tr:hover {
            background: #f0f9ff;
            transform: scale(1.01);
        }

        .table tbody td {
            padding: 16px;
            vertical-align: middle;
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
        }

        .btn-primary {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: white;
        }

        .btn-info {
            background: linear-gradient(135deg, #0284c7 0%, #0ea5e9 100%);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-info:hover {
            background: linear-gradient(135deg, #0369a1 0%, #0284c7 100%);
            transform: translateY(-2px);
            color: white;
            box-shadow: var(--shadow-lg);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #b91c1c 0%, #dc2626 100%);
            transform: translateY(-2px);
            color: white;
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #475569;
        }

        .btn-secondary:hover {
            background: #cbd5e1;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
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

        /* Form Card */
        .form-card {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            border: 1px solid #dbeafe;
        }

        .form-label {
            font-weight: 600;
            color: #1e3a8a;
            font-size: 13px;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control,
        .form-select {
            font-size: 14px;
            padding: 10px 15px;
            border: 2px solid #dbeafe;
            border-radius: 10px;
            transition: all 0.3s;
            background-color: #f8fafc;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background-color: white;
            outline: none;
        }

        /* Badge */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-info {
            background: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%);
            color: white;
        }

        /* Alert */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-icon {
            font-size: 48px;
            color: #dbeafe;
            margin-bottom: 16px;
        }

        .empty-text {
            color: var(--gray);
            margin-bottom: 24px;
        }

        /* Info Card */
        .info-card {
            background: #f0f9ff;
            border: 1px solid #dbeafe;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }

        .info-card h4 {
            color: #1e40af;
            font-size: 16px;
            margin-bottom: 12px;
        }

        .member-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .member-list li {
            padding: 8px 12px;
            background: white;
            border-radius: 8px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .member-list li:hover {
            background: #e0f2fe;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 12px;
                padding: 12px 16px;
            }

            .logo-section h1 {
                font-size: 20px;
            }

            .stats-container {
                padding: 16px;
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .content-container {
                padding: 16px;
            }

            .menu-items {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 8px;
            }

            .menu-item {
                padding: 10px 8px;
            }

            .menu-item i {
                font-size: 18px;
            }

            .menu-item span {
                font-size: 11px;
            }
        }
    </style>
</head>

<body>
    <header class="top-header">
        <div class="header-content">
            <div class="logo-section">
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
            <a href="index.php" class="menu-item">
                <i class="fas fa-users"></i>
                <span>Associados</span>
            </a>
            <a href="cargos.php" class="menu-item active">
                <i class="fas fa-briefcase"></i>
                <span>Cargos</span>
            </a>
            <a href="relatorios.php" class="menu-item">
                <i class="fas fa-chart-bar"></i>
                <span>Relatórios</span>
            </a>
            <a href="solicitacoes.php" class="menu-item">
                <i class="fas fa-user-clock"></i>
                <span>Solicitações</span>
            </a>
        </div>
    </nav>

    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo $total_cargos; ?></div>
                    <div class="stat-label">Total de Cargos</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-briefcase"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo $total_membros_com_cargo; ?></div>
                    <div class="stat-label">Alunos com Cargo</div>
                </div>
                <div class="stat-icon" style="background: linear-gradient(135deg, #0284c7 0%, #0ea5e9 100%);">
                    <i class="fas fa-user-tie"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?php echo $cargo_mais_usado ? $cargo_mais_usado['total'] : '0'; ?></div>
                    <div class="stat-label">Cargo Mais Usado
                        <?php if ($cargo_mais_usado): ?>
                            <br><small><?php echo $cargo_mais_usado['nome']; ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="stat-icon" style="background: linear-gradient(135deg, #3730a3 0%, #4f46e5 100%);">
                    <i class="fas fa-star"></i>
                </div>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="content-container">
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="content-container">
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($action == 'list'): ?>
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-briefcase"></i>
                Gestão de Cargos
            </h1>
            <a href="cargos.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Novo Cargo
            </a>
        </div>

        <div class="content-container">
            <div class="table-container">
                <?php if (!empty($cargos)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nome do Cargo</th>
                                <th>Descrição</th>
                                <th class="text-center">Total de Alunos</th>
                                <th>Data de Criação</th>
                                <th class="text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cargos as $cargo): ?>
                                <tr>
                                    <td>
                                        <strong style="color: #1e40af;"><?php echo htmlspecialchars($cargo['nome']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($cargo['descricao'] ?: 'Sem descrição'); ?></td>
                                    <td class="text-center">
                                        <span class="badge badge-info">
                                            <?php echo $cargo['total_membros']; ?>
                                            membro<?php echo $cargo['total_membros'] != 1 ? 's' : ''; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($cargo['created_at'])); ?></td>
                                    <td class="text-center">
                                        <a href="cargos.php?action=edit&id=<?php echo $cargo['id']; ?>"
                                            class="btn btn-info btn-sm">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                        <button
                                            onclick="confirmDelete(<?php echo $cargo['id']; ?>, '<?php echo htmlspecialchars($cargo['nome']); ?>')"
                                            class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i> Excluir
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <p class="empty-text">Nenhum cargo cadastrado ainda.</p>
                        <a href="cargos.php?action=add" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Criar Primeiro Cargo
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($action == 'add' || $action == 'edit'): ?>
        <div class="content-container">
            <div class="form-card">
                <h2 style="color: #1e40af; margin-bottom: 24px;">
                    <i class="fas <?php echo $action == 'add' ? 'fa-plus-circle' : 'fa-edit'; ?>"></i>
                    <?php echo $action == 'add' ? 'Novo Cargo' : 'Editar Cargo'; ?>
                </h2>

                <form method="POST" action="">
                    <?php if ($action == 'edit'): ?>
                        <input type="hidden" name="id" value="<?php echo $cargo['id']; ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome do Cargo *</label>
                        <input type="text" id="nome" name="nome" class="form-control" required
                            value="<?php echo $cargo ? htmlspecialchars($cargo['nome']) : ''; ?>"
                            placeholder="Ex: Iniciante, Intermediário, Avançado">
                    </div>

                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea id="descricao" name="descricao" class="form-control" rows="4"
                            placeholder="Descreva as responsabilidades e características deste cargo..."><?php echo $cargo ? htmlspecialchars($cargo['descricao']) : ''; ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Salvar
                        </button>
                        <a href="cargos.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    </div>
                </form>
            </div>

            <?php if ($action == 'edit'): ?>
                <div class="info-card">
                    <h4><i class="fas fa-info-circle"></i> Informações do Cargo</h4>
                    <p><strong>Criado em:</strong> <?php echo date('d/m/Y H:i', strtotime($cargo['created_at'])); ?></p>
                    <p><strong>Última atualização:</strong>
                        <?php echo date('d/m/Y H:i', strtotime($cargo['updated_at'])); ?>
                    </p>

                    <?php
                    $stmt = $pdo->prepare("SELECT nome, email FROM membros WHERE cargo_id = ? ORDER BY nome");
                    $stmt->execute([$cargo['id']]);
                    $membros_cargo = $stmt->fetchAll();
                    ?>

                    <?php if (!empty($membros_cargo)): ?>
                        <h4 class="mt-3"><i class="fas fa-users"></i> Alunos com este cargo
                            (<?php echo count($membros_cargo); ?>):
                        </h4>
                        <ul class="member-list">
                            <?php foreach ($membros_cargo as $membro): ?>
                                <li>
                                    <span><?php echo htmlspecialchars($membro['nome']); ?></span>
                                    <small class="text-muted"><?php echo htmlspecialchars($membro['email']); ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted mt-3">Nenhum membro possui este cargo ainda.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        function confirmDelete(id, nome) {
            Swal.fire({
                title: 'Confirmar Exclusão',
                html: `Tem certeza que deseja excluir o cargo <strong>${nome}</strong>?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `cargos.php?action=delete&id=${id}`;
                }
            });
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>

</html>