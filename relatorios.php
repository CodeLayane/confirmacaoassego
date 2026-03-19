<?php
require_once 'layout.php';
if(!$evento_atual){header('Location: index.php');exit();}
$eid=$evento_atual['id'];$ce=json_decode($evento_atual['campos_extras']??'[]',true)?:[];
$s=$pdo->prepare("SELECT COUNT(*) FROM participantes WHERE aprovado=1 AND evento_id=?");$s->execute([$eid]);$total=(int)$s->fetchColumn();
$s=$pdo->prepare("SELECT COUNT(*) FROM participantes WHERE aprovado=1 AND ativo=0 AND evento_id=?");$s->execute([$eid]);$inat=(int)$s->fetchColumn();
$s=$pdo->prepare("SELECT COUNT(*) FROM participantes WHERE aprovado=0 AND evento_id=?");$s->execute([$eid]);$pend=(int)$s->fetchColumn();
$ativos=$total-$inat;
$cidades=$pdo->prepare("SELECT cidade,COUNT(*) as t FROM participantes WHERE aprovado=1 AND evento_id=? AND cidade IS NOT NULL AND cidade!='' GROUP BY cidade ORDER BY t DESC LIMIT 10");$cidades->execute([$eid]);$cidades=$cidades->fetchAll();
$extras_stats=[];foreach($ce as $c){if(($c['tipo']??'')=='select'&&!empty($c['opcoes'])){$s=$pdo->prepare("SELECT campos_extras FROM participantes WHERE aprovado=1 AND evento_id=?");$s->execute([$eid]);$counts=[];while($r=$s->fetch()){$vals=json_decode($r['campos_extras']??'{}',true)?:[];$v=$vals[$c['nome']]??'';if($v)$counts[$v]=($counts[$v]??0)+1;}$extras_stats[$c['label']]=$counts;}}
$mensal=$pdo->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') as mes,COUNT(*) as t FROM participantes WHERE aprovado=1 AND evento_id=? GROUP BY mes ORDER BY mes DESC LIMIT 6");$mensal->execute([$eid]);$mensal=array_reverse($mensal->fetchAll());
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Relatórios - ASSEGO</title><?php renderCSS();?></head><body>
<?php renderHeader('relatorios');?>
<div class="page-header"><h1 class="page-title"><i class="fas fa-chart-bar"></i> Relatórios — <?=htmlspecialchars($evento_atual['nome'])?></h1>
<div class="d-flex gap-2"><a href="export.php?format=excel" class="btn btn-success"><i class="fas fa-file-excel"></i> Excel</a><a href="export.php?format=pdf" class="btn btn-danger"><i class="fas fa-file-pdf"></i> PDF</a></div></div>
<div class="stats-container">
<div class="stat-card"><div class="stat-header"><div><div class="stat-value"><?=$total?></div><div class="stat-label">Cadastrados</div></div><div class="stat-icon"><i class="fas fa-users"></i></div></div></div>
<div class="stat-card"><div class="stat-header"><div><div class="stat-value" style="color:#059669"><?=$ativos?></div><div class="stat-label">Ativos</div></div><div class="stat-icon" style="background:linear-gradient(135deg,#059669,#10b981)"><i class="fas fa-user-check"></i></div></div></div>
<div class="stat-card"><div class="stat-header"><div><div class="stat-value"><?=$inat?></div><div class="stat-label">Inativos</div></div><div class="stat-icon" style="background:linear-gradient(135deg,#dc2626,#ef4444)"><i class="fas fa-user-times"></i></div></div></div>
<div class="stat-card"><div class="stat-header"><div><div class="stat-value"><?=$pend?></div><div class="stat-label">Pendentes</div></div><div class="stat-icon" style="background:linear-gradient(135deg,#d97706,#f59e0b)"><i class="fas fa-user-clock"></i></div></div></div>
</div>
<div class="content-container"><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:20px">
<?php if(!empty($cidades)):?><div class="form-card"><h3 style="color:var(--primary);margin-bottom:16px"><i class="fas fa-map-marker-alt"></i> Por Cidade</h3>
<?php foreach($cidades as $c):$pct=$total>0?round(($c['t']/$total)*100):0;?>
<div style="margin-bottom:12px"><div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:13px"><span style="font-weight:600"><?=htmlspecialchars($c['cidade'])?></span><span style="color:var(--gray)"><?=$c['t']?> (<?=$pct?>%)</span></div>
<div style="height:8px;background:#e0f2fe;border-radius:4px;overflow:hidden"><div style="height:100%;background:var(--gradient);width:<?=$pct?>%;border-radius:4px"></div></div></div>
<?php endforeach;?></div><?php endif;?>
<?php if(!empty($mensal)):?><div class="form-card"><h3 style="color:var(--primary);margin-bottom:16px"><i class="fas fa-chart-line"></i> Por Mês</h3>
<?php $maxm=max(array_column($mensal,'t'));foreach($mensal as $m):$pct=$maxm>0?round(($m['t']/$maxm)*100):0;?>
<div style="margin-bottom:12px"><div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:13px"><span style="font-weight:600"><?=date('M/Y',strtotime($m['mes'].'-01'))?></span><span style="color:var(--gray)"><?=$m['t']?></span></div>
<div style="height:8px;background:#e0f2fe;border-radius:4px;overflow:hidden"><div style="height:100%;background:linear-gradient(135deg,#059669,#10b981);width:<?=$pct?>%;border-radius:4px"></div></div></div>
<?php endforeach;?></div><?php endif;?>
<?php foreach($extras_stats as $label=>$counts):?><div class="form-card"><h3 style="color:var(--primary);margin-bottom:16px"><i class="fas fa-chart-pie"></i> Por <?=htmlspecialchars($label)?></h3>
<?php $mc=!empty($counts)?max($counts):1;arsort($counts);foreach($counts as $opt=>$cnt):$pct=round(($cnt/$mc)*100);?>
<div style="margin-bottom:12px"><div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:13px"><span style="font-weight:600"><?=htmlspecialchars($opt)?></span><span style="color:var(--gray)"><?=$cnt?></span></div>
<div style="height:8px;background:#e0f2fe;border-radius:4px;overflow:hidden"><div style="height:100%;background:linear-gradient(135deg,#7c3aed,#8b5cf6);width:<?=$pct?>%;border-radius:4px"></div></div></div>
<?php endforeach;?></div><?php endforeach;?>
</div></div>
<?php renderScripts();?></body></html>
