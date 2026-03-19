<?php
require_once 'layout.php';
if (!isset($_GET['id'])) { header('Location: index.php'); exit(); }

$id = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT i.*, e.nome as evento_nome, e.campos_extras as evento_campos FROM inscritos i LEFT JOIN eventos e ON i.evento_id=e.id WHERE i.id=?");
$stmt->execute([$id]); $inscrito = $stmt->fetch();
if (!$inscrito) { header('Location: index.php'); exit(); }

// Foto
$foto = null;
$stmt = $pdo->prepare("SELECT * FROM fotos WHERE inscrito_id=? ORDER BY id DESC LIMIT 1"); $stmt->execute([$id]); $foto = $stmt->fetch();

// Campos extras
$campos = json_decode($inscrito['evento_campos'] ?? '[]', true) ?: [];
$extras = json_decode($inscrito['campos_extras'] ?? '{}', true) ?: [];

// Materiais
$stmt = $pdo->prepare("SELECT * FROM materiais WHERE inscrito_id=? ORDER BY created_at DESC"); $stmt->execute([$id]); $materiais = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes - <?php echo htmlspecialchars($inscrito['nome']); ?></title>
    <?php renderCSS(); ?>
</head>
<body>
    <?php renderHeader('index'); ?>
    
    <!-- Header do inscrito -->
    <div style="background:linear-gradient(135deg,#1e3a8a,#3b82f6,#2563eb);color:white;padding:30px 24px;">
        <div style="max-width:1200px;margin:0 auto;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:20px;">
            <div style="display:flex;align-items:center;gap:20px;">
                <div style="width:100px;height:100px;border-radius:16px;overflow:hidden;background:white;box-shadow:var(--shadow-lg);">
                    <?php if ($foto && !empty($foto['dados'])): ?>
                    <img src="<?php echo $foto['dados']; ?>" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#e2e8f0;color:#64748b;font-size:40px;"><i class="fas fa-user"></i></div>
                    <?php endif; ?>
                </div>
                <div>
                    <h1 style="font-size:28px;margin:0 0 8px;"><?php echo htmlspecialchars($inscrito['nome']); ?></h1>
                    <div style="display:flex;gap:16px;flex-wrap:wrap;opacity:0.9;font-size:14px;">
                        <?php if ($inscrito['whatsapp']): ?><span><i class="fab fa-whatsapp"></i> <?php echo htmlspecialchars($inscrito['whatsapp']); ?></span><?php endif; ?>
                        <?php if ($inscrito['instagram']): ?><span><i class="fab fa-instagram"></i> @<?php echo htmlspecialchars(ltrim($inscrito['instagram'],'@')); ?></span><?php endif; ?>
                    </div>
                    <div style="margin-top:8px;">
                        <span style="background:rgba(255,255,255,0.2);padding:4px 12px;border-radius:20px;font-size:12px;">
                            <i class="fas fa-calendar"></i> <?php echo htmlspecialchars($inscrito['evento_nome']); ?>
                        </span>
                        <?php if ($inscrito['ativo']??1): ?>
                        <span style="background:rgba(16,185,129,0.3);padding:4px 12px;border-radius:20px;font-size:12px;margin-left:8px;">Ativo</span>
                        <?php else: ?>
                        <span style="background:rgba(239,68,68,0.3);padding:4px 12px;border-radius:20px;font-size:12px;margin-left:8px;">Inativo</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="inscritos.php?action=edit&id=<?php echo $inscrito['id']; ?>" class="btn btn-secondary"><i class="fas fa-edit"></i> Editar</a>
                <button onclick="window.print()" class="btn btn-secondary"><i class="fas fa-print"></i> Imprimir</button>
                <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
            </div>
        </div>
    </div>
    
    <div style="max-width:1200px;margin:-20px auto 40px;padding:0 24px;">
        <!-- Dados Pessoais -->
        <div class="form-card" style="margin-bottom:20px;">
            <h3 style="color:var(--primary);font-size:18px;margin-bottom:20px;padding-bottom:12px;border-bottom:2px solid #dbeafe;"><i class="fas fa-user"></i> Dados Pessoais</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;">
                <div><span style="font-size:12px;color:#64748b;text-transform:uppercase;font-weight:600;">Nome</span><div style="font-size:16px;font-weight:500;margin-top:4px;"><?php echo htmlspecialchars($inscrito['nome']); ?></div></div>
                <div><span style="font-size:12px;color:#64748b;text-transform:uppercase;font-weight:600;">WhatsApp</span><div style="font-size:16px;margin-top:4px;"><?php echo htmlspecialchars($inscrito['whatsapp']??'Não informado'); ?></div></div>
                <div><span style="font-size:12px;color:#64748b;text-transform:uppercase;font-weight:600;">Instagram</span><div style="font-size:16px;margin-top:4px;"><?php echo !empty($inscrito['instagram'])?'@'.htmlspecialchars(ltrim($inscrito['instagram'],'@')):'Não informado'; ?></div></div>
            </div>
        </div>
        
        <!-- Campos Extras -->
        <?php if (!empty($campos) && !empty($extras)): ?>
        <div class="form-card" style="margin-bottom:20px;">
            <h3 style="color:var(--primary);font-size:18px;margin-bottom:20px;padding-bottom:12px;border-bottom:2px solid #dbeafe;"><i class="fas fa-list-check"></i> Dados do Evento</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;">
                <?php foreach ($campos as $ce): ?>
                <div>
                    <span style="font-size:12px;color:#64748b;text-transform:uppercase;font-weight:600;"><?php echo htmlspecialchars($ce['label']); ?></span>
                    <div style="font-size:16px;margin-top:4px;font-weight:500;"><?php echo htmlspecialchars($extras[$ce['nome']] ?? 'Não informado'); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Endereço -->
        <div class="form-card" style="margin-bottom:20px;">
            <h3 style="color:var(--primary);font-size:18px;margin-bottom:20px;padding-bottom:12px;border-bottom:2px solid #dbeafe;"><i class="fas fa-map-marker-alt"></i> Endereço</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;">
                <div><span style="font-size:12px;color:#64748b;text-transform:uppercase;font-weight:600;">Endereço</span><div style="margin-top:4px;"><?php echo htmlspecialchars($inscrito['endereco']??'Não informado'); ?></div></div>
                <div><span style="font-size:12px;color:#64748b;text-transform:uppercase;font-weight:600;">Cidade/UF</span><div style="margin-top:4px;"><?php echo htmlspecialchars(($inscrito['cidade']??'').($inscrito['estado']?' - '.$inscrito['estado']:'')); ?></div></div>
                <div><span style="font-size:12px;color:#64748b;text-transform:uppercase;font-weight:600;">CEP</span><div style="margin-top:4px;"><?php echo htmlspecialchars($inscrito['cep']??'Não informado'); ?></div></div>
            </div>
        </div>
        
        <!-- Observações -->
        <?php if (!empty($inscrito['observacoes'])): ?>
        <div class="form-card" style="margin-bottom:20px;">
            <h3 style="color:var(--primary);font-size:18px;margin-bottom:12px;"><i class="fas fa-sticky-note"></i> Observações</h3>
            <p style="white-space:pre-wrap;"><?php echo htmlspecialchars($inscrito['observacoes']); ?></p>
        </div>
        <?php endif; ?>
        
        <!-- Documentos -->
        <div class="form-card">
            <h3 style="color:var(--primary);font-size:18px;margin-bottom:20px;padding-bottom:12px;border-bottom:2px solid #dbeafe;">
                <i class="fas fa-paperclip"></i> Documentos
                <?php if (count($materiais) > 0): ?><span class="badge badge-info" style="margin-left:8px;"><?php echo count($materiais); ?></span><?php endif; ?>
            </h3>
            <?php if (count($materiais) > 0): ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;">
                <?php foreach ($materiais as $doc): ?>
                <div style="background:#f8fafc;border:2px solid #dbeafe;border-radius:12px;padding:16px;text-align:center;">
                    <i class="fas fa-file" style="font-size:32px;color:var(--primary);margin-bottom:8px;"></i>
                    <div style="font-weight:600;margin-bottom:4px;"><?php echo htmlspecialchars($doc['titulo']??'Sem título'); ?></div>
                    <div style="font-size:12px;color:#64748b;margin-bottom:8px;"><?php echo date('d/m/Y', strtotime($doc['created_at'])); ?></div>
                    <a href="download_file.php?id=<?php echo $doc['id']; ?>" class="btn btn-primary btn-sm"><i class="fas fa-download"></i> Baixar</a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div style="text-align:center;padding:30px;color:#94a3b8;"><i class="fas fa-folder-open" style="font-size:32px;margin-bottom:8px;"></i><p>Nenhum documento</p></div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php renderScripts(); ?>
</body>
</html>
