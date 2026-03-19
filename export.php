<?php
if(session_status()===PHP_SESSION_NONE)session_start();require_once 'config.php';checkLogin();$pdo=getConnection();
$eid=$_SESSION['evento_atual']??0;if(!$eid)die('Selecione um evento.');
$ev=$pdo->prepare("SELECT * FROM eventos WHERE id=?");$ev->execute([$eid]);$evento=$ev->fetch();if(!$evento)die('Evento?');
$ce=json_decode($evento['campos_extras']??'[]',true)?:[];$format=$_GET['format']??'excel';
$s=$pdo->prepare("SELECT * FROM participantes WHERE aprovado=1 AND evento_id=? ORDER BY nome");$s->execute([$eid]);$lista=$s->fetchAll();
$ta=0;$ti=0;foreach($lista as $i){if($i['ativo']??1)$ta++;else $ti++;}
if($format=='excel'){
    header("Content-Type:application/vnd.ms-excel");header("Content-Disposition:attachment;filename=\"".preg_replace('/[^a-z0-9]/','_',strtolower($evento['nome']))."_".date('Y-m-d').".xls\"");
    echo "\xEF\xBB\xBF<table border='1'><tr style='background:#1e40af;color:white;font-weight:bold'><th>#</th><th>Nome</th><th>WhatsApp</th><th>Instagram</th>";
    foreach($ce as $c)echo "<th>".htmlspecialchars($c['label'])."</th>";
    echo "<th>Cidade</th><th>Estado</th><th>Status</th><th>Cadastro</th></tr>";
    $n=1;foreach($lista as $i){$ex=json_decode($i['campos_extras']??'{}',true)?:[];$st=($i['ativo']??1)?'Ativo':'Inativo';$bg=($i['ativo']??1)?'':"style='background:#fff1f2'";
    echo "<tr $bg><td>$n</td><td>".htmlspecialchars($i['nome'])."</td><td>".htmlspecialchars($i['whatsapp']??'-')."</td><td>".(!empty($i['instagram'])?'@'.htmlspecialchars(ltrim($i['instagram'],'@')):'-')."</td>";
    foreach($ce as $c)echo "<td>".htmlspecialchars($ex[$c['nome']]??'-')."</td>";
    echo "<td>".htmlspecialchars($i['cidade']??'-')."</td><td>".htmlspecialchars($i['estado']??'-')."</td><td>$st</td><td>".date('d/m/Y',strtotime($i['created_at']))."</td></tr>";$n++;}
    echo "</table><br><table border='1'><tr style='background:#e0f2fe'><td colspan='2'><strong>RESUMO — ".htmlspecialchars($evento['nome'])."</strong></td></tr><tr><td>Total:</td><td>".count($lista)."</td></tr><tr><td>Ativos:</td><td>$ta</td></tr><tr><td>Inativos:</td><td>$ti</td></tr><tr><td>Gerado:</td><td>".date('d/m/Y H:i')."</td></tr></table>";
}elseif($format=='pdf'){?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title><?=htmlspecialchars($evento['nome'])?></title>
<style>@page{size:A4 landscape;margin:1cm}body{font-family:Arial,sans-serif;font-size:9pt;color:#333;margin:0}.header{text-align:center;margin-bottom:16px;padding-bottom:10px;border-bottom:2px solid #1e40af}.header h1{color:#1e40af;font-size:16pt;margin:0}.header p{color:#666;font-size:9pt;margin:4px 0 0}.summary{background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:10px 16px;margin-bottom:16px;display:flex;gap:30px}.summary p{margin:0}table{width:100%;border-collapse:collapse}th{background:#1e40af;color:white;padding:6px 8px;font-size:8pt;text-align:left}td{padding:5px 8px;border-bottom:1px solid #e5e7eb;font-size:8pt}tr:nth-child(even){background:#f8faff}.no-print{margin:16px;text-align:center}.btn{background:#1e40af;color:white;padding:8px 18px;text-decoration:none;border-radius:5px;display:inline-block;margin:4px;border:none;cursor:pointer}@media print{.no-print{display:none}}</style></head><body>
<div class="no-print"><button onclick="window.print()" class="btn">Imprimir / PDF</button> <a href="relatorios.php" class="btn" style="background:#6b7280">Voltar</a></div>
<div class="header"><h1>ASSEGO — <?=htmlspecialchars($evento['nome'])?></h1><p>Relatório — <?=date('d/m/Y H:i')?></p></div>
<div class="summary"><p><strong>Total:</strong> <?=count($lista)?></p><p><strong>Ativos:</strong> <?=$ta?></p><p><strong>Inativos:</strong> <?=$ti?></p></div>
<table><thead><tr><th>#</th><th>Nome</th><th>WhatsApp</th><th>Instagram</th>
<?php foreach($ce as $c):?><th><?=htmlspecialchars($c['label'])?></th><?php endforeach;?>
<th>Cidade/UF</th><th>Status</th><th>Cadastro</th></tr></thead><tbody>
<?php $n=1;foreach($lista as $i):$ex=json_decode($i['campos_extras']??'{}',true)?:[];?>
<tr><td><?=$n++?></td><td><strong><?=htmlspecialchars($i['nome'])?></strong></td><td><?=htmlspecialchars($i['whatsapp']??'-')?></td><td><?=!empty($i['instagram'])?'@'.htmlspecialchars(ltrim($i['instagram'],'@')):'-'?></td>
<?php foreach($ce as $c):?><td><?=htmlspecialchars($ex[$c['nome']]??'-')?></td><?php endforeach;?>
<td><?=htmlspecialchars(($i['cidade']??'').($i['estado']?'/'.$i['estado']:''))?></td>
<td><?=($i['ativo']??1)?'Ativo':'Inativo'?></td><td><?=date('d/m/Y',strtotime($i['created_at']))?></td></tr>
<?php endforeach;?></tbody></table>
<div style="text-align:center;font-size:8pt;color:#999;margin-top:16px;border-top:1px solid #e5e7eb;padding-top:8px">ASSEGO | <?=htmlspecialchars($evento['nome'])?> | <?=date('d/m/Y H:i:s')?></div>
</body></html>
<?php }?>
