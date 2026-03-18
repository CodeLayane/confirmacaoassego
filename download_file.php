<?php
// download_file.php - Download seguro de arquivos
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
checkLogin();

// Verificar se foi passado um ID
if (!isset($_GET['id'])) {
    die('Arquivo não especificado');
}

$id = (int)$_GET['id'];

try {
    $pdo = getConnection();
    
    // Buscar o arquivo no banco
    $stmt = $pdo->prepare("
        SELECT m.*, mb.nome as membro_nome 
        FROM materiais m 
        LEFT JOIN membros mb ON m.membro_id = mb.id 
        WHERE m.id = ?
    ");
    $stmt->execute([$id]);
    $material = $stmt->fetch();
    
    if (!$material) {
        die('Arquivo não encontrado');
    }
    
    $arquivo = $material['arquivo'];
    
    // Verificar se o arquivo existe
    if (!file_exists($arquivo)) {
        die('Arquivo não encontrado no servidor');
    }
    
    // Obter informações do arquivo
    $nome_arquivo = basename($arquivo);
    $tamanho = filesize($arquivo);
    $tipo_mime = mime_content_type($arquivo);
    
    // Definir headers para download
    header('Content-Type: ' . $tipo_mime);
    header('Content-Disposition: attachment; filename="' . $nome_arquivo . '"');
    header('Content-Length: ' . $tamanho);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Enviar o arquivo
    readfile($arquivo);
    exit();
    
} catch (Exception $e) {
    die('Erro ao baixar arquivo: ' . $e->getMessage());
}
?>