<?php
require_once 'layout.php';
if(!$evento_atual){header('Location: index.php');exit();}
$eid=$evento_atual['id'];$campos_extras=json_decode($evento_atual['campos_extras']??'[]',true)?:[];
$action=$_GET['action']??'list';$message='';$errors=[];
$participante=null;
if($action=='edit'&&isset($_GET['id'])){$s=$pdo->prepare("SELECT * FROM participantes WHERE id=? AND evento_id=?");$s->execute([$_GET['id'],$eid]);$participante=$s->fetch();if(!$participante){header("Location: participantes.php");exit();}}
if($_SERVER['REQUEST_METHOD']=='POST'&&($action=='add'||$action=='edit')){
    $nome=clean_input($_POST['nome']??'');$whatsapp=preg_replace('/[^0-9]/','',$_POST['whatsapp']??'');$instagram=clean_input($_POST['instagram']??'');
    $endereco=clean_input($_POST['endereco']??'');$cidade=clean_input($_POST['cidade']??'');$estado=clean_input($_POST['estado']??'');$cep=preg_replace('/[^0-9-]/','',$_POST['cep']??'');
    $observacoes=clean_input($_POST['observacoes']??'');$ativo=isset($_POST['ativo'])?1:0;
    $extras=[];foreach($campos_extras as $ce){$v=clean_input($_POST['extra_'.$ce['nome']]??'');$extras[$ce['nome']]=$v;if(($ce['obrigatorio']??false)&&empty($v))$errors[]=$ce['label']." obrigatório.";}
    if(empty($nome)||strlen($nome)<3)$errors[]="Nome obrigatório.";
    if(empty($errors)){
        try{$ej=json_encode($extras,JSON_UNESCAPED_UNICODE);
            if($action=='add'){$pdo->prepare("INSERT INTO participantes (evento_id,nome,whatsapp,instagram,endereco,cidade,estado,cep,observacoes,campos_extras,aprovado,ativo) VALUES (?,?,?,?,?,?,?,?,?,?,1,?)")->execute([$eid,$nome,$whatsapp,$instagram,$endereco,$cidade,$estado,$cep,$observacoes,$ej,$ativo]);$pid=$pdo->lastInsertId();}
            else{$id=$_POST['id'];$pdo->prepare("UPDATE participantes SET nome=?,whatsapp=?,instagram=?,endereco=?,cidade=?,estado=?,cep=?,observacoes=?,campos_extras=?,ativo=? WHERE id=? AND evento_id=?")->execute([$nome,$whatsapp,$instagram,$endereco,$cidade,$estado,$cep,$observacoes,$ej,$ativo,$id,$eid]);$pid=$id;}
            $fb=$_POST['foto_base64']??'';
            if(!empty($fb)&&strpos($fb,'data:image')===0){$chk=$pdo->prepare("SELECT id FROM fotos WHERE participante_id=?");$chk->execute([$pid]);if($chk->fetch())$pdo->prepare("UPDATE fotos SET dados=?,updated_at=NOW() WHERE participante_id=?")->execute([$fb,$pid]);else $pdo->prepare("INSERT INTO fotos (participante_id,dados) VALUES (?,?)")->execute([$pid,$fb]);}
            if(isset($_POST['remove_photo'])&&$_POST['remove_photo']=='1')$pdo->prepare("DELETE FROM fotos WHERE participante_id=?")->execute([$pid]);
            header("Location: participantes.php?message=".urlencode($action=='add'?"Cadastrado!":"Atualizado!"));exit();
        }catch(PDOException $e){$errors[]="Erro: ".$e->getMessage();}
    }
}
if($action=='delete'&&isset($_GET['id'])){$pdo->prepare("DELETE FROM fotos WHERE participante_id=?")->execute([$_GET['id']]);$pdo->prepare("DELETE FROM materiais WHERE participante_id=?")->execute([$_GET['id']]);$pdo->prepare("DELETE FROM participantes WHERE id=? AND evento_id=?")->execute([$_GET['id'],$eid]);if(isset($_GET['ajax'])){header('Content-Type:application/json');echo json_encode(['ok'=>true]);exit();}header("Location: participantes.php?message=".urlencode("Excluído!"));exit();}
$search=$_GET['search']??'';$page=max(1,(int)($_GET['page']??1));$per_page=(int)($_GET['per_page']??25);$offset=($page-1)*$per_page;
$where="WHERE p.aprovado=1 AND p.evento_id=?";$params=[$eid];
if($search){$where.=" AND (p.nome LIKE ? OR p.whatsapp LIKE ? OR p.instagram LIKE ? OR p.campos_extras LIKE ?)";$l="%$search%";$params=array_merge($params,[$l,$l,$l,$l]);}
$s=$pdo->prepare("SELECT COUNT(*) FROM participantes p $where");$s->execute($params);$total_records=(int)$s->fetchColumn();$total_pages=ceil($total_records/$per_page);
$s=$pdo->prepare("SELECT p.* FROM participantes p $where ORDER BY p.nome LIMIT $per_page OFFSET $offset");$s->execute($params);$lista=$s->fetchAll();
$foto_atual=null;if($participante){$s=$pdo->prepare("SELECT * FROM fotos WHERE participante_id=? LIMIT 1");$s->execute([$participante['id']]);$foto_atual=$s->fetch();}
$extras_vals=$participante?(json_decode($participante['campos_extras']??'{}',true)?:[]):[];
if(isset($_GET['message']))$message=$_GET['message'];
$slug_ev=$evento_atual['slug']??null;$base=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?"https":"http")."://".$_SERVER['HTTP_HOST'].dirname($_SERVER['SCRIPT_NAME']).'/';
$link_cadastro=$base."inscricao.php?evento=".($slug_ev?:$evento_atual['id']);
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Participantes</title><?php renderCSS();?></head><body>
<?php renderHeader('participantes');?>
<?php if($message):?><div class="content-container"><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?=htmlspecialchars($message)?></div></div><?php endif;?>

<?php if($action=='list'):?>
<div class="page-header"><h1 class="page-title"><i class="fas fa-users"></i> Participantes — <?=htmlspecialchars($evento_atual['nome'])?></h1>
<div class="d-flex gap-2 flex-wrap"><a href="participantes.php?action=add" class="btn btn-success"><i class="fas fa-plus-circle"></i> Adicionar</a>
<button class="btn" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;border:none" data-bs-toggle="modal" data-bs-target="#mLink"><i class="fas fa-share-alt"></i> Link</button>
<a href="export.php?format=excel" class="btn btn-info"><i class="fas fa-download"></i> Excel</a></div></div>
<div style="background:white;padding:16px 24px;margin:0 24px 16px;border-radius:12px;border:1px solid #dbeafe">
<form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end">
<div style="flex:1;min-width:200px"><input type="text" name="search" class="form-control" placeholder="Buscar..." value="<?=htmlspecialchars($search)?>"></div>
<select name="per_page" class="form-select" style="width:auto" onchange="this.form.submit()"><option value="25" <?=$per_page==25?'selected':''?>>25</option><option value="50" <?=$per_page==50?'selected':''?>>50</option><option value="100" <?=$per_page==100?'selected':''?>>100</option></select>
<button class="btn btn-primary"><i class="fas fa-search"></i></button></form></div>
<div class="table-container"><div class="table-header"><h3 class="table-title"><?=$total_records?> participantes</h3></div>
<div style="overflow-x:auto"><table class="table"><thead><tr><th>Nome</th><th>WhatsApp</th><th>Instagram</th>
<?php foreach(array_slice($campos_extras,0,2) as $ce):?><th><?=htmlspecialchars($ce['label'])?></th><?php endforeach;?>
<th class="text-center">Ações</th></tr></thead><tbody>
<?php if(!empty($lista)):foreach($lista as $p):$ex=json_decode($p['campos_extras']??'{}',true)?:[];?>
<tr><td class="fw-semibold"><?=htmlspecialchars(strtoupper($p['nome']))?></td><td><?=htmlspecialchars($p['whatsapp']??'-')?></td><td><?=!empty($p['instagram'])?'@'.htmlspecialchars(ltrim($p['instagram'],'@')):'-'?></td>
<?php foreach(array_slice($campos_extras,0,2) as $ce):?><td><?=htmlspecialchars($ex[$ce['nome']]??'-')?></td><?php endforeach;?>
<td><div class="action-buttons"><a href="participante_view.php?id=<?=$p['id']?>" class="btn-action view"><i class="fas fa-eye"></i></a><a href="participantes.php?action=edit&id=<?=$p['id']?>" class="btn-action edit"><i class="fas fa-edit"></i></a><button onclick="Swal.fire({title:'Excluir?',icon:'warning',showCancelButton:true,confirmButtonColor:'#dc2626',confirmButtonText:'Excluir'}).then(r=>{if(r.isConfirmed)location='participantes.php?action=delete&id=<?=$p['id']?>'})" class="btn-action delete"><i class="fas fa-trash"></i></button></div></td></tr>
<?php endforeach;else:?><tr><td colspan="<?=4+count(array_slice($campos_extras,0,2))?>"><div class="empty-state"><div class="empty-icon"><i class="fas fa-users-slash"></i></div><p class="empty-text">Nenhum participante</p></div></td></tr><?php endif;?>
</tbody></table></div>
<?php if($total_pages>1):?><div class="pagination-container"><div style="color:var(--gray)"><?=min($offset+1,$total_records)?> a <?=min($offset+$per_page,$total_records)?> de <?=$total_records?></div><nav><ul class="pagination"><?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++):?><li class="page-item <?=$i==$page?'active':''?>"><a class="page-link" href="?page=<?=$i?><?=$search?"&search=$search":''?>"><?=$i?></a></li><?php endfor;?></ul></nav></div><?php endif;?></div>
<div class="modal fade" id="mLink" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="border-radius:16px;border:none"><div class="modal-header" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;border-radius:16px 16px 0 0"><h5 class="modal-title"><i class="fas fa-link"></i> Link</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body p-4 text-center"><div class="input-group mb-3"><input type="text" class="form-control bg-light" id="lI" value="<?=$link_cadastro?>" readonly><button class="btn btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('lI').value).then(()=>Swal.fire({icon:'success',title:'Copiado!',timer:1500,showConfirmButton:false}))"><i class="fas fa-copy"></i></button></div></div></div></div></div>

<?php elseif($action=='add'||$action=='edit'):?>
<div class="content-container"><div class="form-card">
<h2 style="color:var(--primary);margin-bottom:24px;padding-bottom:16px;border-bottom:2px solid #dbeafe"><i class="fas <?=$action=='add'?'fa-user-plus':'fa-user-edit'?>"></i> <?=$action=='add'?'Adicionar':'Editar'?></h2>
<?php if(!empty($errors)):?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e):?><li><?=htmlspecialchars($e)?></li><?php endforeach;?></ul></div><?php endif;?>
<form method="POST">
<?php if($action=='edit'):?><input type="hidden" name="id" value="<?=$participante['id']?>"><?php endif;?>
<div style="text-align:center;margin-bottom:24px"><div id="pP" style="width:100px;height:100px;border-radius:50%;background:#e0f2fe;margin:8px auto;display:flex;align-items:center;justify-content:center;overflow:hidden;border:3px solid white;box-shadow:0 4px 6px rgba(0,0,0,.1);cursor:pointer" onclick="document.getElementById('fI').click()">
<?php if($foto_atual&&!empty($foto_atual['dados'])):?><img src="<?=$foto_atual['dados']?>" style="width:100%;height:100%;object-fit:cover"><?php else:?><i class="fas fa-camera" style="font-size:28px;color:#94a3b8"></i><?php endif;?>
</div><input type="file" id="fI" class="d-none" accept="image/*"><input type="hidden" name="foto_base64" id="fB"><input type="hidden" name="remove_photo" id="rP" value="0">
<button type="button" class="btn btn-sm btn-secondary" onclick="document.getElementById('fI').click()"><i class="fas fa-image"></i> Foto</button></div>
<div class="row g-3 mb-4">
<div class="col-md-8"><label class="form-label">Nome *</label><input type="text" name="nome" class="form-control" required value="<?=$participante?htmlspecialchars($participante['nome']):''?>"></div>
<div class="col-md-6"><label class="form-label">WhatsApp</label><input type="text" name="whatsapp" id="wa" class="form-control" placeholder="(00) 00000-0000" value="<?=$participante?htmlspecialchars($participante['whatsapp']??''):''?>"></div>
<div class="col-md-6"><label class="form-label">Instagram</label><div class="input-group"><span class="input-group-text">@</span><input type="text" name="instagram" class="form-control" value="<?=$participante?htmlspecialchars(ltrim($participante['instagram']??'','@')):''?>"></div></div>
</div>
<?php if(!empty($campos_extras)):?><h4 style="color:var(--primary);margin-bottom:16px"><i class="fas fa-list-check"></i> Dados do Evento</h4><div class="row g-3 mb-4">
<?php foreach($campos_extras as $ce):$val=$extras_vals[$ce['nome']]??'';?>
<div class="col-md-<?=($ce['tipo']??'text')=='select'?'4':'6'?>"><label class="form-label"><?=htmlspecialchars($ce['label'])?> <?=($ce['obrigatorio']??false)?'*':''?></label>
<?php if(($ce['tipo']??'text')=='select'&&!empty($ce['opcoes'])):?><select name="extra_<?=$ce['nome']?>" class="form-select" <?=($ce['obrigatorio']??false)?'required':''?>><option value="">Selecione...</option><?php foreach($ce['opcoes'] as $op):?><option value="<?=htmlspecialchars($op)?>" <?=$val==$op?'selected':''?>><?=htmlspecialchars($op)?></option><?php endforeach;?></select>
<?php else:?><input type="<?=$ce['tipo']??'text'?>" name="extra_<?=$ce['nome']?>" class="form-control" value="<?=htmlspecialchars($val)?>" <?=($ce['obrigatorio']??false)?'required':''?>><?php endif;?></div><?php endforeach;?></div><?php endif;?>
<h4 style="color:var(--primary);margin-bottom:16px"><i class="fas fa-map-marker-alt"></i> Endereço</h4>
<div class="row g-3 mb-4">
<div class="col-md-3"><label class="form-label">CEP</label><input type="text" name="cep" id="cep" class="form-control" value="<?=$participante?htmlspecialchars($participante['cep']??''):''?>"></div>
<div class="col-md-9"><label class="form-label">Endereço</label><input type="text" name="endereco" id="endereco" class="form-control" value="<?=$participante?htmlspecialchars($participante['endereco']??''):''?>"></div>
<div class="col-md-6"><label class="form-label">Cidade</label><input type="text" name="cidade" id="cidade" class="form-control" value="<?=$participante?htmlspecialchars($participante['cidade']??''):''?>"></div>
<div class="col-md-6"><label class="form-label">Estado</label><select name="estado" id="estado" class="form-select"><option value="">UF</option><?php foreach(['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $u):?><option value="<?=$u?>" <?=($participante&&($participante['estado']??'')==$u)?'selected':''?>><?=$u?></option><?php endforeach;?></select></div>
</div>
<div class="mb-4"><label class="form-label">Observações</label><textarea name="observacoes" class="form-control" rows="3"><?=$participante?htmlspecialchars($participante['observacoes']??''):''?></textarea></div>
<div class="d-flex align-items-center gap-3 mb-4"><input type="checkbox" name="ativo" style="width:24px;height:24px" <?=(!$participante||($participante['ativo']??1))?'checked':''?>><label style="font-weight:600">Ativo</label></div>
<div class="d-flex gap-2"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button><a href="participantes.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a></div>
</form></div></div>
<?php endif;?>
<?php renderScripts();?>
<script>
document.getElementById('fI')?.addEventListener('change',function(e){const f=e.target.files[0];if(!f)return;const r=new FileReader();r.onload=function(ev){const img=new Image();img.onload=function(){const c=document.createElement('canvas');let w=img.width,h=img.height,m=800;if(w>m||h>m){if(w>h){h=Math.round(h*m/w);w=m}else{w=Math.round(w*m/h);h=m}}c.width=w;c.height=h;c.getContext('2d').drawImage(img,0,0,w,h);const b=c.toDataURL('image/jpeg',.8);document.getElementById('fB').value=b;document.getElementById('pP').innerHTML=`<img src="${b}" style="width:100%;height:100%;object-fit:cover">`};img.src=ev.target.result};r.readAsDataURL(f)});
document.getElementById('wa')?.addEventListener('input',function(e){let v=e.target.value.replace(/\D/g,'').substring(0,11);if(v.length>6)v='('+v.substring(0,2)+') '+v.substring(2,7)+'-'+v.substring(7);else if(v.length>2)v='('+v.substring(0,2)+') '+v.substring(2);else if(v.length>0)v='('+v;e.target.value=v});
document.getElementById('cep')?.addEventListener('input',function(e){let v=e.target.value.replace(/\D/g,'');if(v.length>8)v=v.slice(0,8);v=v.replace(/^(\d{5})(\d)/,'$1-$2');e.target.value=v;if(v.replace(/\D/g,'').length===8)fetch(`https://viacep.com.br/ws/${v.replace(/\D/g,'')}/json/`).then(r=>r.json()).then(d=>{if(!d.erro){document.getElementById('endereco').value=d.logradouro+(d.bairro?', '+d.bairro:'');document.getElementById('cidade').value=d.localidade;document.getElementById('estado').value=d.uf}}).catch(()=>{})});
</script></body></html>
