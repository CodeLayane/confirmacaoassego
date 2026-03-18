<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config.php';
checkLogin();

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$pdo = getConnection();
$action = $_GET['action'] ?? 'stats';

try {
    switch ($action) {

        // ── Stats para o dashboard ───────────────────────────────────────────
        case 'stats':
            $total    = $pdo->query("SELECT COUNT(*) FROM membros WHERE aprovado=1 AND (ativo IS NULL OR ativo=1)")->fetchColumn();
            $inativos = $pdo->query("SELECT COUNT(*) FROM membros WHERE aprovado=1 AND ativo=0")->fetchColumn();
            $novos    = $pdo->query("SELECT COUNT(*) FROM membros WHERE aprovado=1 AND (ativo IS NULL OR ativo=1) AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
            $pendentes= $pdo->query("SELECT COUNT(*) FROM membros WHERE aprovado=0")->fetchColumn();
            echo json_encode([
                'total'     => (int)$total,
                'inativos'  => (int)$inativos,
                'novos'     => (int)$novos,
                'pendentes' => (int)$pendentes,
            ]);
            break;

        // ── Lista de alunos paginada ─────────────────────────────────────────
        case 'lista':
            $search   = $_GET['search'] ?? '';
            $page     = max(1, (int)($_GET['page'] ?? 1));
            $per_page = (int)($_GET['per_page'] ?? 10);
            $offset   = ($page - 1) * $per_page;

            $where  = "WHERE aprovado=1";
            $params = [];
            if ($search) {
                $where  .= " AND (nome LIKE ? OR whatsapp LIKE ? OR instagram LIKE ?)";
                $like    = "%$search%";
                $params  = [$like, $like, $like];
            }

            $total_rows = $pdo->prepare("SELECT COUNT(*) FROM membros $where");
            $total_rows->execute($params);
            $total_rows = (int)$total_rows->fetchColumn();

            $stmt = $pdo->prepare("SELECT id, nome, whatsapp, instagram, ativo FROM membros $where ORDER BY nome LIMIT $per_page OFFSET $offset");
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'rows'       => $rows,
                'total'      => $total_rows,
                'page'       => $page,
                'per_page'   => $per_page,
                'total_pages'=> ceil($total_rows / $per_page),
            ]);
            break;

        // ── Solicitações pendentes ───────────────────────────────────────────
        case 'solicitacoes':
            $stmt = $pdo->query("SELECT id, nome, whatsapp, instagram, cidade, estado, created_at FROM membros WHERE aprovado=0 ORDER BY created_at DESC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                'total' => count($rows),
                'rows'  => $rows,
            ]);
            break;

        // ── Badge de pendentes (usado em todas as páginas) ───────────────────
        case 'badge':
            $count = $pdo->query("SELECT COUNT(*) FROM membros WHERE aprovado=0")->fetchColumn();
            echo json_encode(['pendentes' => (int)$count]);
            break;

        default:
            echo json_encode(['error' => 'action invalida']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
