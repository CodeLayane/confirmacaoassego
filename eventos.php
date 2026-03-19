<?php
require_once 'layout.php';
if(!isAdmin()){header('Location: index.php');exit();}
function gerarSlug($s){$s=mb_strtolower($s,'UTF-8');$s=preg_replace('/[áàãâä]/u','a',$s);$s=preg_replace('/[éèêë]/u','e',$s);$s=preg_replace('/[íìîï]/u','i',$s);$s=preg_replace('/[óòõôö]/u','o',$s);$s=preg_replace('/[úùûü]/u','u',$s);$s=preg_replace('/[ç]/u','c',$s);$s=preg_replace('/[^a-z0-9]+/','-',$s);return trim($s,'-');}
$action=$_GET['action']??'list';$message='';$error='';
if($_SERVER['REQUEST_METHOD']=='POST'){
    $nome=clean_input($_POST['nome']);$slug=clean_input($_POST['slug']??'');if(empty($slug))$slug=gerarSlug($nome);else $slug=gerarSlug($slug);
    $descricao=clean_input($_POST['descricao']??'');$data_inicio=$_POST['data_inicio']??null;$data_fim=$_POST['data_fim']??null;
    $local=clean_input($_POST['local']??'');$cor_tema=$_POST['cor_tema']??'#1e40af';$ativo_ev=isset($_POST['ativo'])?1:0;
    $cn=$_POST['campo_nome']??[];$cl=$_POST['campo_label']??[];$ct=$_POST['campo_tipo']??[];$co=$_POST['campo_obrigatorio']??[];$cop=$_POST['campo_opcoes']??[];
    $campos=[];for($i=0;$i<count($cn);$i++){if(empty($cn[$i]))continue;$c=['nome'=>preg_replace('/[^a-z0-9_]/','',strtolower($cn[$i])),'label'=>$cl[$i]??$cn[$i],'tipo'=>$ct[$i]??'text','obrigatorio'=>in_array((string)$i,$co)];if(!empty($cop[$i]))$c['opcoes']=array_map('trim',explode(',',$cop[$i]));$campos[]=$c;}
    $cj=json_encode($campos,JSON_UNESCAPED_UNICODE);$banner=$_POST['banner_base64']??'';
    $config_form=json_encode(['titulo'=>clean_input($_POST['titulo_form']??''),'subtitulo'=>clean_input($_POST['subtitulo_form']??''),'bg_pos_y'=>$_POST['bg_pos_y']??'30','bg_overlay'=>$_POST['bg_overlay']??'0.3','show_title'=>isset($_POST['show_title'])?'1':'0'],JSON_UNESCAPED_UNICODE);
    try{
        $cols=array_column($pdo->query("SHOW COLUMNS FROM eventos")->fetchAll(),'Field');$hs=in_array('slug',$cols);$hc=in_array('config_form',$cols);
        if($action=='add'){$sc="nome,descricao,data_inicio,data_fim,local,cor_tema,campos_extras,banner_base64,ativo";$sv="?,?,?,?,?,?,?,?,?";$p=[$nome,$descricao,$data_inicio?:null,$data_fim?:null,$local,$cor_tema,$cj,$banner?:null,$ativo_ev];if($hs){$sc.=",slug";$sv.=",?";$p[]=$slug;}if($hc){$sc.=",config_form";$sv.=",?";$p[]=$config_form;}$pdo->prepare("INSERT INTO eventos ($sc) VALUES ($sv)")->execute($p);$message="Criado!";}
        else{$id=$_POST['id'];$set="nome=?,descricao=?,data_inicio=?,data_fim=?,local=?,cor_tema=?,campos_extras=?,ativo=?";$p=[$nome,$descricao,$data_inicio?:null,$data_fim?:null,$local,$cor_tema,$cj,$ativo_ev];if($hs){$set.=",slug=?";$p[]=$slug;}if($hc){$set.=",config_form=?";$p[]=$config_form;}$p[]=$id;$pdo->prepare("UPDATE eventos SET $set WHERE id=?")->execute($p);if(!empty($banner))$pdo->prepare("UPDATE eventos SET banner_base64=? WHERE id=?")->execute([$banner,$id]);$message="Atualizado!";}
        header("Location: eventos.php?message=".urlencode($message));exit();
    }catch(PDOException $e){$error="Erro: ".$e->getMessage();}
}
if($action=='delete'&&isset($_GET['id'])){$pdo->prepare("DELETE FROM participantes WHERE evento_id=?")->execute([$_GET['id']]);$pdo->prepare("DELETE FROM eventos WHERE id=?")->execute([$_GET['id']]);header("Location: eventos.php?message=".urlencode("Excluído!"));exit();}
$evento=null;if($action=='edit'&&isset($_GET['id'])){$s=$pdo->prepare("SELECT * FROM eventos WHERE id=?");$s->execute([$_GET['id']]);$evento=$s->fetch();}
$eventos_list=$pdo->query("SELECT e.*,(SELECT COUNT(*) FROM participantes WHERE evento_id=e.id AND aprovado=1) as total_part,(SELECT COUNT(*) FROM participantes WHERE evento_id=e.id AND aprovado=0) as total_pend FROM eventos e ORDER BY e.created_at DESC")->fetchAll();
if(isset($_GET['message']))$message=$_GET['message'];$base=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?"https":"http")."://".$_SERVER['HTTP_HOST'].dirname($_SERVER['SCRIPT_NAME']).'/';
$cf=[];if($evento&&isset($evento['config_form']))$cf=json_decode($evento['config_form'],true)?:[];
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Eventos - ASSEGO</title><?php renderCSS();?>
<style>
/* Preview - simula celular */
.preview-frame{width:360px;margin:0 auto;border-radius:20px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.3);border:3px solid #334155;background:#0d0d1a}
.pv-poster{position:relative;overflow:hidden;height:180px;background:#0a0a2e}
.pv-poster img{width:100%;height:100%;object-fit:cover;display:block;transition:.3s}
.pv-poster .pv-ov{position:absolute;inset:0;transition:.3s}
.pv-poster .pv-title{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:2;color:white;font-weight:900;text-transform:uppercase;letter-spacing:2px;text-shadow:0 3px 15px rgba(0,0,0,.7);text-align:center;padding:10px;font-size:20px;line-height:1.1}
.pv-poster .pv-title small{font-size:10px;font-weight:400;opacity:.7;letter-spacing:0;text-transform:none;margin-top:4px}
.pv-poster .pv-fade{position:absolute;bottom:0;left:0;right:0;height:40px;background:linear-gradient(transparent,#0d0d1a);z-index:2}
.pv-form{background:#0d0d1a;padding:16px}
.pv-form-card{background:rgba(15,15,35,.85);border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:16px;margin-top:-24px;position:relative;z-index:3}
.pv-ftitle{text-align:center;margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid rgba(255,255,255,.08)}
.pv-ftitle h3{color:#f59e0b;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px}
.pv-ftitle p{color:rgba(255,255,255,.4);font-size:9px;margin-top:3px}
.pv-field{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:6px;padding:8px 10px;margin-bottom:6px;color:rgba(255,255,255,.25);font-size:10px;display:flex;align-items:center;gap:6px}
.pv-field i{color:#f59e0b;font-size:9px}
.pv-row{display:grid;grid-template-columns:1fr 1fr;gap:6px}
.pv-btn{background:linear-gradient(135deg,#f59e0b,#d97706);color:#0a0a1a;padding:10px;border-radius:8px;font-weight:900;font-size:12px;text-transform:uppercase;letter-spacing:2px;text-align:center;margin-top:10px}
.pv-logos{display:flex;justify-content:center;gap:8px;padding:10px;background:#0d0d1a}
.pv-logos span{width:24px;height:24px;background:rgba(255,255,255,.12);border-radius:50%;display:inline-block}
.rg{display:flex;align-items:center;gap:12px}.rg input[type=range]{flex:1;accent-color:#4f46e5}.rg span{min-width:50px;text-align:right;font-weight:600;color:var(--primary)}
.sticky-preview{position:sticky;top:160px}
</style></head><body>
<?php renderHeader('eventos');?>
<?php if($message):?><div class="content-container"><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?=htmlspecialchars($message)?></div></div><?php endif;?>
<?php if($error):?><div class="content-container"><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?=htmlspecialchars($error)?></div></div><?php endif;?>

<?php if($action=='list'):?>
<div class="page-header"><h1 class="page-title"><i class="fas fa-calendar-alt"></i> Eventos</h1><a href="eventos.php?action=add" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Novo</a></div>
<div class="content-container"><div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(380px,1fr));gap:20px">
<?php foreach($eventos_list as $ev):$link=$base."inscricao.php?evento=".($ev['slug']??$ev['id']);?>
<div class="form-card" style="padding:0;overflow:hidden">
<?php if($ev['banner_base64']):?><div style="height:120px;overflow:hidden"><img src="<?=$ev['banner_base64']?>" style="width:100%;height:100%;object-fit:cover"></div>
<?php else:?><div style="height:80px;background:linear-gradient(135deg,<?=$ev['cor_tema']?:'#1e40af'?>,#3b82f6);display:flex;align-items:center;justify-content:center;color:white;font-size:24px;font-weight:700"><?=htmlspecialchars($ev['nome'])?></div><?php endif;?>
<div style="padding:20px"><h4 style="color:var(--primary);margin-bottom:8px"><?=htmlspecialchars($ev['nome'])?></h4>
<div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap"><span class="badge badge-info"><?=$ev['total_part']?> cadastrados</span><?php if($ev['total_pend']>0):?><span class="badge" style="background:#fef3c7;color:#92400e"><?=$ev['total_pend']?> pendentes</span><?php endif;?></div>
<div style="background:#f0f9ff;border:1px solid #dbeafe;border-radius:8px;padding:8px 12px;margin-bottom:12px;display:flex;align-items:center;gap:8px">
<code style="font-size:11px;color:var(--primary);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=$link?></code>
<button onclick="navigator.clipboard.writeText('<?=$link?>').then(()=>Swal.fire({icon:'success',title:'Copiado!',timer:1500,showConfirmButton:false}))" style="background:var(--primary);color:white;border:none;border-radius:6px;padding:4px 8px;cursor:pointer;font-size:11px"><i class="fas fa-copy"></i></button></div>
<div class="d-flex gap-2"><a href="eventos.php?action=edit&id=<?=$ev['id']?>" class="btn btn-info btn-sm"><i class="fas fa-edit"></i> Editar</a>
<button onclick="Swal.fire({title:'Excluir?',icon:'warning',showCancelButton:true,confirmButtonColor:'#dc2626',confirmButtonText:'Excluir'}).then(r=>{if(r.isConfirmed)location='eventos.php?action=delete&id=<?=$ev['id']?>'})" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
<a href="?set_evento=<?=$ev['id']?>" class="btn btn-success btn-sm"><i class="fas fa-arrow-right"></i> Acessar</a></div></div></div>
<?php endforeach;?></div></div>

<?php elseif($action=='add'||$action=='edit'):$ce=$evento?(json_decode($evento['campos_extras']??'[]',true)?:[]):[];
$showT=($cf['show_title']??'1')==='1'||!$evento;
?>
<div class="content-container"><div class="row g-4">
<!-- FORMULÁRIO -->
<div class="col-lg-7"><div class="form-card">
<h2 style="color:var(--primary);margin-bottom:24px"><i class="fas <?=$action=='add'?'fa-plus-circle':'fa-edit'?>"></i> <?=$action=='add'?'Novo':'Editar'?> Evento</h2>
<form method="POST" id="evForm">
<?php if($action=='edit'):?><input type="hidden" name="id" value="<?=$evento['id']?>"><?php endif;?>
<h4 style="color:var(--primary);margin-bottom:16px"><i class="fas fa-info-circle"></i> Dados</h4>
<div class="row g-3 mb-4">
<div class="col-md-5"><label class="form-label">Nome *</label><input type="text" name="nome" id="evNome" class="form-control" required value="<?=$evento?htmlspecialchars($evento['nome']):''?>"></div>
<div class="col-md-4"><label class="form-label">Slug</label><input type="text" name="slug" class="form-control" value="<?=$evento?htmlspecialchars($evento['slug']??''):''?>" placeholder="auto"></div>
<div class="col-md-3"><label class="form-label">Cor</label><input type="color" name="cor_tema" class="form-control" value="<?=$evento?$evento['cor_tema']:'#1e40af'?>" style="height:44px"></div>
<div class="col-12"><label class="form-label">Descrição</label><textarea name="descricao" id="evDesc" class="form-control" rows="2"><?=$evento?htmlspecialchars($evento['descricao']):''?></textarea></div>
<div class="col-md-4"><label class="form-label">Data Início</label><input type="date" name="data_inicio" class="form-control" value="<?=$evento?$evento['data_inicio']:''?>"></div>
<div class="col-md-4"><label class="form-label">Data Fim</label><input type="date" name="data_fim" class="form-control" value="<?=$evento?$evento['data_fim']:''?>"></div>
<div class="col-md-4"><label class="form-label">Local</label><input type="text" name="local" class="form-control" value="<?=$evento?htmlspecialchars($evento['local']):''?>"></div>
<div class="col-12"><label style="display:flex;align-items:center;gap:8px;font-weight:600;cursor:pointer"><input type="checkbox" name="ativo" style="width:22px;height:22px" <?=(!$evento||$evento['ativo'])?'checked':''?>> Ativo</label></div>
</div>

<h4 style="color:var(--primary);margin:20px 0 16px"><i class="fas fa-image"></i> Imagem de Fundo</h4>
<div class="row g-3 mb-4">
<div class="col-12"><input type="file" id="bgFile" class="form-control" accept="image/*"><input type="hidden" name="banner_base64" id="bgB64"></div>
<div class="col-md-6"><label class="form-label">Posição Vertical</label><div class="rg"><input type="range" name="bg_pos_y" id="rPosY" min="0" max="100" value="<?=$cf['bg_pos_y']??'30'?>"><span id="vPosY"><?=$cf['bg_pos_y']??'30'?>%</span></div></div>
<div class="col-md-6"><label class="form-label">Escurecimento</label><div class="rg"><input type="range" name="bg_overlay" id="rOv" min="0" max="0.9" step="0.05" value="<?=$cf['bg_overlay']??'0.3'?>"><span id="vOv"><?=round(($cf['bg_overlay']??0.3)*100)?>%</span></div></div>
</div>

<h4 style="color:var(--primary);margin:20px 0 16px"><i class="fas fa-pen-fancy"></i> Textos</h4>
<div class="row g-3 mb-4">
<div class="col-12"><label style="display:flex;align-items:center;gap:8px;font-weight:600;cursor:pointer;margin-bottom:12px"><input type="checkbox" name="show_title" id="showTitle" style="width:22px;height:22px" <?=$showT?'checked':''?>> Exibir título do evento sobre a imagem</label></div>
<div class="col-md-6"><label class="form-label">Título do Formulário</label><input type="text" name="titulo_form" id="evTitulo" class="form-control" value="<?=htmlspecialchars($cf['titulo']??'')?>" placeholder="CADASTRE-SE PARA O..."></div>
<div class="col-md-6"><label class="form-label">Subtítulo</label><input type="text" name="subtitulo_form" id="evSub" class="form-control" value="<?=htmlspecialchars($cf['subtitulo']??'')?>" placeholder="Participe das seletivas..."></div>
</div>

<h4 style="color:var(--primary);margin:20px 0 16px"><i class="fas fa-list-check"></i> Campos Extras <small style="color:var(--gray);font-weight:400;font-size:12px">(Já inclui: Foto, Nome, WhatsApp, Instagram, Endereço)</small></h4>
<div id="camposC"><?php foreach($ce as $i=>$c):?>
<div class="campo-row" style="display:grid;grid-template-columns:1fr 1fr 100px 60px 1fr 36px;gap:6px;margin-bottom:8px;align-items:end">
<div><label class="form-label" style="font-size:10px">ID</label><input type="text" name="campo_nome[]" class="form-control" value="<?=htmlspecialchars($c['nome'])?>"></div>
<div><label class="form-label" style="font-size:10px">Label</label><input type="text" name="campo_label[]" class="form-control" value="<?=htmlspecialchars($c['label'])?>"></div>
<div><label class="form-label" style="font-size:10px">Tipo</label><select name="campo_tipo[]" class="form-select"><option value="text" <?=($c['tipo']??'')=='text'?'selected':''?>>Texto</option><option value="number" <?=($c['tipo']??'')=='number'?'selected':''?>>Número</option><option value="select" <?=($c['tipo']??'')=='select'?'selected':''?>>Seleção</option></select></div>
<div style="text-align:center"><label class="form-label" style="font-size:10px">Obr</label><br><input type="checkbox" name="campo_obrigatorio[]" value="<?=$i?>" <?=($c['obrigatorio']??false)?'checked':''?> style="width:18px;height:18px"></div>
<div><label class="form-label" style="font-size:10px">Opções</label><input type="text" name="campo_opcoes[]" class="form-control" value="<?=htmlspecialchars(implode(',',$c['opcoes']??[]))?>"></div>
<div><button type="button" class="btn btn-danger btn-sm" style="padding:4px 8px" onclick="this.closest('.campo-row').remove()"><i class="fas fa-times"></i></button></div>
</div><?php endforeach;?></div>
<button type="button" class="btn btn-secondary btn-sm mb-4" onclick="addCampo()"><i class="fas fa-plus"></i> Campo</button>
<div class="d-flex gap-2 mt-2"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button><a href="eventos.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a></div>
</form></div></div>

<!-- PREVIEW -->
<div class="col-lg-5"><div class="sticky-preview">
<h4 style="color:var(--primary);margin-bottom:12px;text-align:center"><i class="fas fa-mobile-alt"></i> Preview</h4>
<div class="preview-frame">
    <!-- Poster -->
    <div class="pv-poster" id="pvPoster">
        <?php if($evento&&$evento['banner_base64']):?><img src="<?=$evento['banner_base64']?>" id="pvImg" style="object-position:center <?=$cf['bg_pos_y']??'30'?>%"><?php else:?><img id="pvImg" style="display:none"><?php endif;?>
        <div class="pv-ov" id="pvOv" style="background:rgba(5,5,20,<?=$cf['bg_overlay']??'0.3'?>)"></div>
        <div class="pv-title" id="pvTitle" style="<?=$showT?'':'display:none'?>"><?=$evento?htmlspecialchars($evento['nome']):'NOME'?><small id="pvDesc"><?=$evento?htmlspecialchars($evento['descricao']??''):''?></small></div>
        <div class="pv-fade"></div>
    </div>
    <!-- Form -->
    <div class="pv-form">
        <div class="pv-form-card">
            <div class="pv-ftitle"><h3 id="pvFTitle"><?=htmlspecialchars($cf['titulo']??'CADASTRE-SE')?></h3><p id="pvFSub"><?=htmlspecialchars($cf['subtitulo']??'')?></p></div>
            <div class="pv-field"><i class="fas fa-camera"></i> Foto (opcional)</div>
            <div class="pv-field"><i class="fas fa-user"></i> Nome Completo *</div>
            <div class="pv-row"><div class="pv-field"><i class="fab fa-whatsapp"></i> WhatsApp *</div><div class="pv-field"><i class="fab fa-instagram"></i> Instagram</div></div>
            <div class="pv-field"><i class="fas fa-pen"></i> Campos extras do evento...</div>
            <div style="color:rgba(255,255,255,.3);font-size:9px;margin:6px 0 4px"><i class="fas fa-map-marker-alt" style="color:#f59e0b"></i> Endereço</div>
            <div class="pv-row"><div class="pv-field"><i class="fas fa-map-pin"></i> CEP</div><div class="pv-field"><i class="fas fa-road"></i> Logradouro</div></div>
            <div class="pv-row"><div class="pv-field"><i class="fas fa-city"></i> Cidade</div><div class="pv-field"><i class="fas fa-flag"></i> UF</div></div>
            <div class="pv-btn">PARTICIPAR</div>
        </div>
    </div>
    <div class="pv-logos"><span></span><span></span><span></span><span></span></div>
</div>
</div></div>
</div></div>

<script>
let ci=<?=count($ce)?>;function addCampo(){document.getElementById('camposC').insertAdjacentHTML('beforeend',`<div class="campo-row" style="display:grid;grid-template-columns:1fr 1fr 100px 60px 1fr 36px;gap:6px;margin-bottom:8px;align-items:end"><div><label class="form-label" style="font-size:10px">ID</label><input type="text" name="campo_nome[]" class="form-control" placeholder="cpf"></div><div><label class="form-label" style="font-size:10px">Label</label><input type="text" name="campo_label[]" class="form-control" placeholder="CPF"></div><div><label class="form-label" style="font-size:10px">Tipo</label><select name="campo_tipo[]" class="form-select"><option value="text">Texto</option><option value="number">Número</option><option value="select">Seleção</option></select></div><div style="text-align:center"><label class="form-label" style="font-size:10px">Obr</label><br><input type="checkbox" name="campo_obrigatorio[]" value="${ci}" style="width:18px;height:18px"></div><div><label class="form-label" style="font-size:10px">Opções</label><input type="text" name="campo_opcoes[]" class="form-control" placeholder="Op1,Op2"></div><div><button type="button" class="btn btn-danger btn-sm" style="padding:4px 8px" onclick="this.closest('.campo-row').remove()"><i class="fas fa-times"></i></button></div></div>`);ci++;}
const img=document.getElementById('pvImg'),ov=document.getElementById('pvOv'),tt=document.getElementById('pvTitle'),ft=document.getElementById('pvFTitle'),fs=document.getElementById('pvFSub'),desc=document.getElementById('pvDesc');
document.getElementById('bgFile')?.addEventListener('change',function(e){const f=e.target.files[0];if(!f)return;const r=new FileReader();r.onload=function(ev){document.getElementById('bgB64').value=ev.target.result;img.src=ev.target.result;img.style.display='block'};r.readAsDataURL(f)});
document.getElementById('rPosY')?.addEventListener('input',function(){document.getElementById('vPosY').textContent=this.value+'%';img.style.objectPosition='center '+this.value+'%'});
document.getElementById('rOv')?.addEventListener('input',function(){document.getElementById('vOv').textContent=Math.round(this.value*100)+'%';ov.style.background='rgba(5,5,20,'+this.value+')'});
document.getElementById('evNome')?.addEventListener('input',function(){const txt=this.value||'NOME';tt.childNodes[0].textContent=txt});
document.getElementById('evDesc')?.addEventListener('input',function(){desc.textContent=this.value});
document.getElementById('evTitulo')?.addEventListener('input',function(){ft.textContent=this.value||'CADASTRE-SE'});
document.getElementById('evSub')?.addEventListener('input',function(){fs.textContent=this.value});
document.getElementById('showTitle')?.addEventListener('change',function(){tt.style.display=this.checked?'flex':'none'});
</script>
<?php endif;?>
<?php renderScripts();?></body></html>
