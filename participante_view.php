<?php
require_once 'layout.php';
if(!isset($_GET['id'])){header('Location: index.php');exit();}
$id=(int)$_GET['id'];
$s=$pdo->prepare("SELECT p.*,e.nome as ev_nome,e.campos_extras as ev_campos FROM participantes p LEFT JOIN eventos e ON p.evento_id=e.id WHERE p.id=?");$s->execute([$id]);$p=$s->fetch();
if(!$p){header('Location: index.php');exit();}
$s=$pdo->prepare("SELECT * FROM fotos WHERE participante_id=? LIMIT 1");$s->execute([$id]);$foto=$s->fetch();
$campos=json_decode($p['ev_campos']??'[]',true)?:[];$extras=json_decode($p['campos_extras']??'{}',true)?:[];
$s=$pdo->prepare("SELECT * FROM materiais WHERE participante_id=? ORDER BY created_at DESC");$s->execute([$id]);$materiais=$s->fetchAll();
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?=htmlspecialchars($p['nome'])?> - ASSEGO</title><?php renderCSS();?></head><body>
<?php renderHeader('participantes');?>
<div style="background:linear-gradient(135deg,#1e3a8a,#3b82f6,#2563eb);color:white;padding:30px 24px">
<div style="max-width:1200px;margin:0 auto;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:20px">
<div style="display:flex;align-items:center;gap:20px">
<div style="width:100px;height:100px;border-radius:16px;overflow:hidden;background:white;box-shadow:var(--shadow-lg)">
<?php if($foto&&!empty($foto['dados'])):?><img src="<?=$foto['dados']?>" style="width:100%;height:100%;object-fit:cover"><?php else:?><div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#e2e8f0;color:#64748b;font-size:40px"><i class="fas fa-user"></i></div><?php endif;?>
</div>
<div><h1 style="font-size:28px;margin:0 0 8px"><?=htmlspecialchars($p['nome'])?></h1>
<div style="display:flex;gap:16px;flex-wrap:wrap;opacity:.9;font-size:14px">
<?php if($p['whatsapp']):?><span><i class="fab fa-whatsapp"></i> <?=htmlspecialchars($p['whatsapp'])?></span><?php endif;?>
<?php if($p['instagram']):?><span><i class="fab fa-instagram"></i> @<?=htmlspecialchars(ltrim($p['instagram'],'@'))?></span><?php endif;?>
</div>
<div style="margin-top:8px"><span style="background:rgba(255,255,255,.2);padding:4px 12px;border-radius:20px;font-size:12px"><?=htmlspecialchars($p['ev_nome'])?></span>
<?php if($p['ativo']??1):?><span style="background:rgba(16,185,129,.4);padding:4px 12px;border-radius:20px;font-size:12px;margin-left:8px">Ativo</span>
<?php else:?><span style="background:rgba(239,68,68,.3);padding:4px 12px;border-radius:20px;font-size:12px;margin-left:8px">Inativo</span><?php endif;?>
</div></div></div>
<div style="display:flex;gap:10px;flex-wrap:wrap">
<a href="participante_form.php?action=edit&id=<?=$p['id']?>" class="btn btn-secondary"><i class="fas fa-edit"></i> Editar</a>
<button onclick="window.print()" class="btn btn-secondary"><i class="fas fa-print"></i> Imprimir</button>
<a href="participantes.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
</div></div></div>
<div style="max-width:1200px;margin:-20px auto 40px;padding:0 24px">
<div class="form-card" style="margin-bottom:20px"><h3 style="color:var(--primary);font-size:18px;margin-bottom:20px;padding-bottom:12px;border-bottom:2px solid #dbeafe"><i class="fas fa-user"></i> Dados Pessoais</h3>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px">
<div><span style="font-size:12px;color:#64748b;text-transform:uppercase;font-weight:600">Nome</span><div style="font-size:16px;font-weight:500;margin-top:4px"><?=htmlspecialchars($p['nome'])?></div></div>
<div><span style="font-size:12px;color:#64748b;text-transform:uppercase;font-weight:600">WhatsApp</span><div style="margin-top:4px"><?=htmlspecialchars($p['whatsapp']??'Não informado')?></div></div>
<div><span style="font-size:12px;color:#64748b;text-transform:uppercase;font-weight:600">Instagram</span><div style="margin-top:4px"><?=!empty($p['instagram'])?'@'.htmlspecialchars(ltrim($p['instagram'],'@')):'Não informado'?></div></div>
</div></div>
<?php if(!empty($campos)&&!empty($extras)):?><div class="form-card" style="margin-bottom:20px"><h3 style="color:var(--primary);font-size:18px;margin-bottom:20px;padding-bottom:12px;border-bottom:2px solid #dbeafe"><i class="fas fa-list-check"></i> Dados do Evento</h3>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px"><?php foreach($campos as $c):?><div><span style="font-size:12px;color:#64748b;text-transform:uppercase;font-weight:600"><?=htmlspecialchars($c['label'])?></span><div style="font-size:16px;margin-top:4px;font-weight:500"><?=htmlspecialchars($extras[$c['nome']]??'-')?></div></div><?php endforeach;?></div></div><?php endif;?>
<div class="form-card" style="margin-bottom:20px"><h3 style="color:var(--primary);font-size:18px;margin-bottom:20px;padding-bottom:12px;border-bottom:2px solid #dbeafe"><i class="fas fa-map-marker-alt"></i> Endereço</h3>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px">
<div><span style="font-size:12px;color:#64748b;text-transform:uppercase;font-weight:600">Endereço</span><div style="margin-top:4px"><?=htmlspecialchars($p['endereco']??'Não informado')?></div></div>
<div><span style="font-size:12px;color:#64748b;text-transform:uppercase;font-weight:600">Cidade/UF</span><div style="margin-top:4px"><?=htmlspecialchars(($p['cidade']??'').($p['estado']?' - '.$p['estado']:''))?></div></div>
<div><span style="font-size:12px;color:#64748b;text-transform:uppercase;font-weight:600">CEP</span><div style="margin-top:4px"><?=htmlspecialchars($p['cep']??'-')?></div></div>
</div></div>
<?php if(!empty($p['observacoes'])):?><div class="form-card"><h3 style="color:var(--primary);font-size:18px;margin-bottom:12px"><i class="fas fa-sticky-note"></i> Observações</h3><p style="white-space:pre-wrap"><?=htmlspecialchars($p['observacoes'])?></p></div><?php endif;?>
</div>
<?php renderScripts();?></body></html>
