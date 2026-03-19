<?php
require_once 'layout.php';
if(!isAdmin()){header('Location: index.php');exit();}
$action=$_GET['action']??'list';$message='';$error='';
if($_SERVER['REQUEST_METHOD']=='POST'){
    $nome=clean_input($_POST['nome']);$email=clean_input($_POST['email']);$senha=$_POST['senha']??'';$role=$_POST['role']??'operador';$ativo=isset($_POST['ativo'])?1:0;$eventos_ids=$_POST['eventos']??[];
    try{
        if($action=='add'){
            if(empty($senha)){$error="Senha obrigatória.";}
            else{
                // Salvar senha já com hash para funcionar imediatamente
                $hash=password_hash($senha,PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO usuarios (nome,email,senha,role,ativo) VALUES (?,?,?,?,?)")->execute([$nome,$email,$hash,$role,$ativo]);
                $uid=$pdo->lastInsertId();
                foreach($eventos_ids as $eid) $pdo->prepare("INSERT IGNORE INTO usuario_eventos (usuario_id,evento_id) VALUES (?,?)")->execute([$uid,$eid]);
                header("Location: usuarios.php?message=".urlencode("Usuário '$nome' criado! Login: $email / Senha: $senha"));exit();
            }
        }else{
            $id=$_POST['id'];
            $pdo->prepare("UPDATE usuarios SET nome=?,email=?,role=?,ativo=? WHERE id=?")->execute([$nome,$email,$role,$ativo,$id]);
            if(!empty($senha)){
                $hash=password_hash($senha,PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE usuarios SET senha=? WHERE id=?")->execute([$hash,$id]);
            }
            $pdo->prepare("DELETE FROM usuario_eventos WHERE usuario_id=?")->execute([$id]);
            foreach($eventos_ids as $eid) $pdo->prepare("INSERT IGNORE INTO usuario_eventos (usuario_id,evento_id) VALUES (?,?)")->execute([$id,$eid]);
            header("Location: usuarios.php?message=".urlencode("Usuário atualizado!"));exit();
        }
    }catch(PDOException $e){
        $error=($e->getCode()=='23000')?"Login já existe.":"Erro: ".$e->getMessage();
    }
}
if($action=='delete'&&isset($_GET['id'])){
    $did=(int)$_GET['id'];
    if($did==getUserId()) $error="Não pode excluir seu próprio usuário.";
    else{$pdo->prepare("DELETE FROM usuarios WHERE id=?")->execute([$did]);header("Location: usuarios.php?message=".urlencode("Excluído!"));exit();}
}
$usuario=null;$usuario_eventos=[];
if($action=='edit'&&isset($_GET['id'])){
    $s=$pdo->prepare("SELECT * FROM usuarios WHERE id=?");$s->execute([$_GET['id']]);$usuario=$s->fetch();
    $s=$pdo->prepare("SELECT evento_id FROM usuario_eventos WHERE usuario_id=?");$s->execute([$_GET['id']]);$usuario_eventos=$s->fetchAll(PDO::FETCH_COLUMN);
}
$usuarios_list=$pdo->query("SELECT u.*,(SELECT COUNT(*) FROM usuario_eventos WHERE usuario_id=u.id) as total_eventos FROM usuarios u ORDER BY u.nome")->fetchAll();
$todos_eventos=$pdo->query("SELECT id,nome FROM eventos ORDER BY nome")->fetchAll();
if(isset($_GET['message']))$message=$_GET['message'];
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Usuários - ASSEGO</title><?php renderCSS();?></head><body>
<?php renderHeader('usuarios');?>
<?php if($message):?><div class="content-container"><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?=htmlspecialchars($message)?></div></div><?php endif;?>
<?php if($error):?><div class="content-container"><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?=htmlspecialchars($error)?></div></div><?php endif;?>

<?php if($action=='list'):?>
<div class="page-header"><h1 class="page-title"><i class="fas fa-user-shield"></i> Gestão de Usuários</h1><a href="usuarios.php?action=add" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Novo Usuário</a></div>
<div class="content-container"><div class="table-container" style="margin:0">
<table class="table"><thead><tr><th>Nome</th><th>Login</th><th>Perfil</th><th class="text-center">Eventos</th><th class="text-center">Status</th><th class="text-center">Ações</th></tr></thead>
<tbody>
<?php foreach($usuarios_list as $u):?>
<tr>
<td><strong><?=htmlspecialchars($u['nome'])?></strong></td>
<td><?=htmlspecialchars($u['email'])?></td>
<td><?php if($u['role']=='admin'):?><span style="background:linear-gradient(135deg,#f59e0b,#fbbf24);color:#78350f;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700"><i class="fas fa-crown"></i> Admin</span><?php else:?><span style="background:#dbeafe;color:#1e40af;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600">Operador</span><?php endif;?></td>
<td class="text-center"><span class="badge badge-info"><?=$u['role']=='admin'?'Todos':$u['total_eventos']?></span></td>
<td class="text-center"><?php if($u['ativo']):?><span style="background:#dcfce7;color:#16a34a;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600">Ativo</span><?php else:?><span style="background:#fee2e2;color:#dc2626;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600">Inativo</span><?php endif;?></td>
<td class="text-center"><div class="action-buttons" style="justify-content:center">
<a href="usuarios.php?action=edit&id=<?=$u['id']?>" class="btn-action edit"><i class="fas fa-edit"></i></a>
<?php if($u['id']!=getUserId()):?><button onclick="Swal.fire({title:'Excluir?',icon:'warning',showCancelButton:true,confirmButtonColor:'#dc2626',confirmButtonText:'Excluir'}).then(r=>{if(r.isConfirmed)location='usuarios.php?action=delete&id=<?=$u['id']?>'})" class="btn-action delete"><i class="fas fa-trash"></i></button><?php endif;?>
</div></td></tr>
<?php endforeach;?></tbody></table></div></div>

<?php elseif($action=='add'||$action=='edit'):?>
<div class="content-container"><div class="form-card">
<h2 style="color:var(--primary);margin-bottom:24px"><i class="fas <?=$action=='add'?'fa-user-plus':'fa-user-edit'?>"></i> <?=$action=='add'?'Novo Usuário':'Editar Usuário'?></h2>
<form method="POST">
<?php if($action=='edit'):?><input type="hidden" name="id" value="<?=$usuario['id']?>"><?php endif;?>
<div class="row g-3 mb-4">
    <div class="col-md-6"><label class="form-label">Nome Completo *</label><input type="text" name="nome" class="form-control" required value="<?=$usuario?htmlspecialchars($usuario['nome']):''?>"></div>
    <div class="col-md-6"><label class="form-label">Login (email) *</label><input type="text" name="email" class="form-control" required value="<?=$usuario?htmlspecialchars($usuario['email']):''?>"></div>
    <div class="col-md-4"><label class="form-label">Senha <?=$action=='edit'?'(vazio=manter)':'*'?></label><input type="text" name="senha" class="form-control" <?=$action=='add'?'required':''?> placeholder="<?=$action=='edit'?'Manter atual':'Criar senha'?>">
    <small class="text-muted">O usuário usará esta senha para entrar</small></div>
    <div class="col-md-4"><label class="form-label">Perfil</label><select name="role" class="form-select">
        <option value="operador" <?=($usuario&&$usuario['role']=='operador')?'selected':''?>>Operador (acesso limitado)</option>
        <option value="admin" <?=($usuario&&$usuario['role']=='admin')?'selected':''?>>Administrador (acesso total)</option>
    </select></div>
    <div class="col-md-4"><label class="form-label">Status</label><div class="d-flex align-items-center gap-3 mt-1"><input type="checkbox" name="ativo" style="width:24px;height:24px" <?=(!$usuario||$usuario['ativo'])?'checked':''?>><label>Ativo</label></div></div>
</div>
<h4 style="color:var(--primary);margin-bottom:16px"><i class="fas fa-calendar-check"></i> Eventos Permitidos <small style="color:var(--gray);font-weight:400;font-size:12px">(Admin acessa todos)</small></h4>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:10px;margin-bottom:24px">
<?php foreach($todos_eventos as $ev):?>
<label style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:#f0f9ff;border:2px solid #dbeafe;border-radius:10px;cursor:pointer">
    <input type="checkbox" name="eventos[]" value="<?=$ev['id']?>" style="width:20px;height:20px" <?=in_array($ev['id'],$usuario_eventos)?'checked':''?>>
    <span style="font-weight:500"><?=htmlspecialchars($ev['nome'])?></span>
</label>
<?php endforeach;?>
<?php if(empty($todos_eventos)):?><p style="color:var(--gray)">Nenhum evento. <a href="eventos.php?action=add">Criar</a></p><?php endif;?>
</div>
<div class="d-flex gap-2"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button><a href="usuarios.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a></div>
</form></div></div>
<?php endif;?>
<?php renderScripts();?></body></html>
