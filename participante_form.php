<?php
require_once 'layout.php';
if(!$evento_atual){header('Location: index.php');exit();}
$eid=$evento_atual['id'];$campos_extras=json_decode($evento_atual['campos_extras']??'[]',true)?:[];
$action=$_GET['action']??'add';$errors=[];

$participante=null;
if($action=='edit'&&isset($_GET['id'])){
    $s=$pdo->prepare("SELECT * FROM participantes WHERE id=? AND evento_id=?");$s->execute([$_GET['id'],$eid]);$participante=$s->fetch();
    if(!$participante){header("Location: participantes.php");exit();}
}

if($_SERVER['REQUEST_METHOD']=='POST'&&($action=='add'||$action=='edit')){
    $nome=clean_input($_POST['nome']??'');$whatsapp=preg_replace('/[^0-9]/','',$_POST['whatsapp']??'');$instagram=clean_input($_POST['instagram']??'');
    $endereco=clean_input($_POST['endereco']??'');$cidade=clean_input($_POST['cidade']??'');$estado=clean_input($_POST['estado']??'');$cep=preg_replace('/[^0-9-]/','',$_POST['cep']??'');
    $observacoes=clean_input($_POST['observacoes']??'');$ativo=isset($_POST['ativo'])?1:0;
    $extras=[];
    foreach($campos_extras as $ce){$v=clean_input($_POST['extra_'.$ce['nome']]??'');$extras[$ce['nome']]=$v;if(($ce['obrigatorio']??false)&&empty($v))$errors[]=$ce['label']." é obrigatório.";}
    if(empty($nome)||strlen($nome)<3)$errors[]="Nome obrigatório.";
    if(empty($errors)){
        try{$ej=json_encode($extras,JSON_UNESCAPED_UNICODE);
            if($action=='add'){
                $pdo->prepare("INSERT INTO participantes (evento_id,nome,whatsapp,instagram,endereco,cidade,estado,cep,observacoes,campos_extras,aprovado,ativo) VALUES (?,?,?,?,?,?,?,?,?,?,1,?)")->execute([$eid,$nome,$whatsapp,$instagram,$endereco,$cidade,$estado,$cep,$observacoes,$ej,$ativo]);$pid=$pdo->lastInsertId();
            }else{$id=$_POST['id'];
                $pdo->prepare("UPDATE participantes SET nome=?,whatsapp=?,instagram=?,endereco=?,cidade=?,estado=?,cep=?,observacoes=?,campos_extras=?,ativo=? WHERE id=? AND evento_id=?")->execute([$nome,$whatsapp,$instagram,$endereco,$cidade,$estado,$cep,$observacoes,$ej,$ativo,$id,$eid]);$pid=$id;}
            $fb=$_POST['foto_base64']??'';
            if(!empty($fb)&&strpos($fb,'data:image')===0){$chk=$pdo->prepare("SELECT id FROM fotos WHERE participante_id=?");$chk->execute([$pid]);
                if($chk->fetch())$pdo->prepare("UPDATE fotos SET dados=?,updated_at=NOW() WHERE participante_id=?")->execute([$fb,$pid]);
                else $pdo->prepare("INSERT INTO fotos (participante_id,dados) VALUES (?,?)")->execute([$pid,$fb]);}
            if(isset($_POST['remove_photo'])&&$_POST['remove_photo']=='1')$pdo->prepare("DELETE FROM fotos WHERE participante_id=?")->execute([$pid]);
            header("Location: participantes.php?message=".urlencode($action=='add'?"Cadastrado com sucesso!":"Atualizado!"));exit();
        }catch(PDOException $e){$errors[]="Erro: ".$e->getMessage();}
    }
}

if($action=='delete'&&isset($_GET['id'])){
    $pdo->prepare("DELETE FROM fotos WHERE participante_id=?")->execute([$_GET['id']]);
    $pdo->prepare("DELETE FROM materiais WHERE participante_id=?")->execute([$_GET['id']]);
    $pdo->prepare("DELETE FROM participantes WHERE id=? AND evento_id=?")->execute([$_GET['id'],$eid]);
    header("Location: participantes.php?message=".urlencode("Excluído!"));exit();
}

$foto_atual=null;
if($participante){$s=$pdo->prepare("SELECT * FROM fotos WHERE participante_id=? LIMIT 1");$s->execute([$participante['id']]);$foto_atual=$s->fetch();}
$extras_vals=$participante?(json_decode($participante['campos_extras']??'{}',true)?:[]):[];
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?=$action=='add'?'Novo Cadastro':'Editar Cadastro'?> - ASSEGO</title><?php renderCSS();?></head><body>
<?php renderHeader('participantes');?>
<div class="content-container"><div class="form-card">
<h2 style="color:var(--primary);margin-bottom:24px;padding-bottom:16px;border-bottom:2px solid #dbeafe">
<i class="fas <?=$action=='add'?'fa-user-plus':'fa-user-edit'?>"></i> <?=$action=='add'?'Novo Cadastro':'Editar Cadastro'?>
<small style="color:var(--gray);font-weight:400;font-size:14px">— <?=htmlspecialchars($evento_atual['nome'])?></small></h2>
<?php if(!empty($errors)):?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e):?><li><?=htmlspecialchars($e)?></li><?php endforeach;?></ul></div><?php endif;?>
<form method="POST">
<?php if($action=='edit'):?><input type="hidden" name="id" value="<?=$participante['id']?>"><?php endif;?>
<div style="text-align:center;margin-bottom:24px">
<div id="photoPreview" style="width:100px;height:100px;border-radius:50%;background:#e0f2fe;margin:8px auto;display:flex;align-items:center;justify-content:center;overflow:hidden;border:3px solid white;box-shadow:0 4px 6px rgba(0,0,0,.1);cursor:pointer" onclick="document.getElementById('fotoInput').click()">
<?php if($foto_atual&&!empty($foto_atual['dados'])):?><img src="<?=$foto_atual['dados']?>" style="width:100%;height:100%;object-fit:cover"><?php else:?><i class="fas fa-camera" style="font-size:28px;color:#94a3b8"></i><?php endif;?>
</div>
<input type="file" id="fotoInput" class="d-none" accept="image/*"><input type="hidden" name="foto_base64" id="fotoBase64"><input type="hidden" name="remove_photo" id="rmPhoto" value="0">
<button type="button" class="btn btn-sm btn-secondary" onclick="document.getElementById('fotoInput').click()"><i class="fas fa-image"></i> Foto</button>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-8"><label class="form-label">Nome Completo *</label><input type="text" name="nome" class="form-control" required value="<?=$participante?htmlspecialchars($participante['nome']):''?>"></div>
    <div class="col-md-6"><label class="form-label">WhatsApp</label><input type="text" name="whatsapp" id="whatsapp" class="form-control" placeholder="(00) 00000-0000" value="<?=$participante?htmlspecialchars($participante['whatsapp']??''):''?>"></div>
    <div class="col-md-6"><label class="form-label">Instagram</label><div class="input-group"><span class="input-group-text">@</span><input type="text" name="instagram" class="form-control" value="<?=$participante?htmlspecialchars(ltrim($participante['instagram']??'','@')):''?>"></div></div>
</div>
<?php if(!empty($campos_extras)):?><h4 style="color:var(--primary);margin-bottom:16px"><i class="fas fa-list-check"></i> Dados do Evento</h4><div class="row g-3 mb-4">
<?php foreach($campos_extras as $ce):$val=$extras_vals[$ce['nome']]??'';?>
<div class="col-md-<?=($ce['tipo']??'text')=='select'?'4':'6'?>"><label class="form-label"><?=htmlspecialchars($ce['label'])?> <?=($ce['obrigatorio']??false)?'*':''?></label>
<?php if(($ce['tipo']??'text')=='select'&&!empty($ce['opcoes'])):?><select name="extra_<?=$ce['nome']?>" class="form-select" <?=($ce['obrigatorio']??false)?'required':''?>><option value="">Selecione...</option><?php foreach($ce['opcoes'] as $op):?><option value="<?=htmlspecialchars($op)?>" <?=$val==$op?'selected':''?>><?=htmlspecialchars($op)?></option><?php endforeach;?></select>
<?php else:?><input type="<?=$ce['tipo']??'text'?>" name="extra_<?=$ce['nome']?>" class="form-control" value="<?=htmlspecialchars($val)?>" <?=($ce['obrigatorio']??false)?'required':''?>><?php endif;?>
</div><?php endforeach;?></div><?php endif;?>
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
<?php renderScripts();?>
<script>
document.getElementById('fotoInput')?.addEventListener('change',function(e){const f=e.target.files[0];if(!f)return;const r=new FileReader();r.onload=function(ev){const img=new Image();img.onload=function(){const c=document.createElement('canvas');let w=img.width,h=img.height,m=800;if(w>m||h>m){if(w>h){h=Math.round(h*m/w);w=m}else{w=Math.round(w*m/h);h=m}}c.width=w;c.height=h;c.getContext('2d').drawImage(img,0,0,w,h);const b=c.toDataURL('image/jpeg',.8);document.getElementById('fotoBase64').value=b;document.getElementById('photoPreview').innerHTML=`<img src="${b}" style="width:100%;height:100%;object-fit:cover">`};img.src=ev.target.result};r.readAsDataURL(f)});
document.getElementById('whatsapp')?.addEventListener('input',function(e){let v=e.target.value.replace(/\D/g,'').substring(0,11);if(v.length>6)v='('+v.substring(0,2)+') '+v.substring(2,7)+'-'+v.substring(7);else if(v.length>2)v='('+v.substring(0,2)+') '+v.substring(2);else if(v.length>0)v='('+v;e.target.value=v});
document.getElementById('cep')?.addEventListener('input',function(e){let v=e.target.value.replace(/\D/g,'');if(v.length>8)v=v.slice(0,8);v=v.replace(/^(\d{5})(\d)/,'$1-$2');e.target.value=v;if(v.replace(/\D/g,'').length===8)fetch(`https://viacep.com.br/ws/${v.replace(/\D/g,'')}/json/`).then(r=>r.json()).then(d=>{if(!d.erro){document.getElementById('endereco').value=d.logradouro+(d.bairro?', '+d.bairro:'');document.getElementById('cidade').value=d.localidade;document.getElementById('estado').value=d.uf}}).catch(()=>{})});
</script>
</body></html>
