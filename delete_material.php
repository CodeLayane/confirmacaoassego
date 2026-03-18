<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
checkLogin();

header('Content-Type: application/json');

try {
    // Obter dados JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        throw new Exception('ID não fornecido');
    }
    
    $id = (int)$input['id'];
    $pdo = getConnection();
    
    // Buscar o arquivo para deletar fisicamente
    $stmt = $pdo->prepare("SELECT arquivo FROM materiais WHERE id = ?");
    $stmt->execute([$id]);
    $material = $stmt->fetch();
    
    if (!$material) {
        throw new Exception('Material não encontrado');
    }
    
    // Deletar arquivo físico se existir
    if (file_exists($material['arquivo'])) {
        unlink($material['arquivo']);
    }
    
    // Deletar do banco
    $stmt = $pdo->prepare("DELETE FROM materiais WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Documento excluído com sucesso'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>