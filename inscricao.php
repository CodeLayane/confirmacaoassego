<?php
try{require_once 'config.php';$pdo=getConnection();}catch(Exception $e){die("Erro.");}
$param=$_GET['evento']??$_GET['slug']??'';$evento=null;
if($param){
    try{$s=$pdo->prepare("SELECT * FROM eventos WHERE slug=? AND ativo=1");$s->execute([$param]);$evento=$s->fetch();}catch(Exception $e){}
    if(!$evento&&is_numeric($param)){$s=$pdo->prepare("SELECT * FROM eventos WHERE id=? AND ativo=1");$s->execute([(int)$param]);$evento=$s->fetch();}
    if(!$evento){$s=$pdo->prepare("SELECT * FROM eventos WHERE ativo=1 LIMIT 1");$s->execute();$evento=$s->fetch();}
}
if(!$evento)die("<div style='font-family:Inter,sans-serif;text-align:center;padding:80px 20px;color:#64748b'><h2>Evento não encontrado</h2></div>");
$campos_extras=json_decode($evento['campos_extras']??'[]',true)?:[];
$cf=[];try{if(isset($evento['config_form'])&&$evento['config_form'])$cf=json_decode($evento['config_form'],true)?:[];}catch(Exception $e){}
$titulo_form=$cf['titulo']??('CADASTRE-SE PARA O '.strtoupper($evento['nome']));
$subtitulo_form=$cf['subtitulo']??($evento['descricao']??'');
$bg_pos_y=$cf['bg_pos_y']??'30';
$bg_overlay=$cf['bg_overlay']??'0.3';
$show_title=true;if(isset($cf['show_title']))$show_title=($cf['show_title']==='1'||$cf['show_title']===1||$cf['show_title']===true);
$has_bg=!empty($evento['banner_base64']);
$success=false;$error='';
if($_SERVER['REQUEST_METHOD']=='POST'){
    $nome=htmlspecialchars(stripslashes(trim($_POST['nome']??'')));$whatsapp=preg_replace('/[^0-9]/','',$_POST['whatsapp']??'');
    $instagram=htmlspecialchars(stripslashes(trim($_POST['instagram']??'')));$endereco=htmlspecialchars(stripslashes(trim($_POST['endereco']??'')));
    $cidade=htmlspecialchars(stripslashes(trim($_POST['cidade']??'')));$estado=htmlspecialchars(stripslashes(trim($_POST['estado']??'')));
    $cep=preg_replace('/[^0-9-]/','',$_POST['cep']??'');
    $extras=[];foreach($campos_extras as $ce){$v=htmlspecialchars(stripslashes(trim($_POST['extra_'.$ce['nome']]??'')));$extras[$ce['nome']]=$v;if(($ce['obrigatorio']??false)&&empty($v))$error=$ce['label']." é obrigatório.";}
    if(empty($nome)||strlen($nome)<3)$error="Nome é obrigatório.";elseif(empty($whatsapp)||strlen($whatsapp)<10)$error="WhatsApp é obrigatório.";
    if(!$error){
        try{$pdo->beginTransaction();
            $pdo->prepare("INSERT INTO participantes (evento_id,nome,whatsapp,instagram,endereco,cidade,estado,cep,campos_extras,aprovado) VALUES (?,?,?,?,?,?,?,?,?,0)")
                ->execute([$evento['id'],$nome,$whatsapp,$instagram,$endereco,$cidade,$estado,$cep,json_encode($extras,JSON_UNESCAPED_UNICODE)]);
            $pid=$pdo->lastInsertId();$fb=$_POST['foto_base64']??'';
            if(!empty($fb)&&strpos($fb,'data:image')===0)$pdo->prepare("INSERT INTO fotos (participante_id,dados) VALUES (?,?)")->execute([$pid,$fb]);
            $pdo->commit();$success=true;
        }catch(Exception $e){$pdo->rollBack();$error="Erro. Tente novamente.";}
    }
}
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?=htmlspecialchars($evento['nome'])?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#0d0d1a;color:white;min-height:100vh}
.poster{width:100%;position:relative;overflow:hidden;
<?php if($has_bg):?>background:url('<?=$evento['banner_base64']?>') center <?=$bg_pos_y?>% / cover no-repeat;
<?php else:?>background:linear-gradient(180deg,#0a0a2e,#1a1a4e);<?php endif;?>}
.poster-ov{position:absolute;inset:0;background:rgba(5,5,20,<?=$bg_overlay?>)}
.poster-fade{position:absolute;bottom:0;left:0;right:0;height:100px;background:linear-gradient(transparent,#0d0d1a);z-index:2}
<?php if($show_title):?>
.poster{min-height:300px}
.poster-text{position:relative;z-index:3;text-align:center;padding:70px 20px 90px;min-height:300px;display:flex;flex-direction:column;align-items:center;justify-content:center}
.poster-text h1{font-size:clamp(32px,7vw,64px);font-weight:900;text-transform:uppercase;letter-spacing:4px;text-shadow:0 6px 30px rgba(0,0,0,.8);line-height:1}
.poster-text p{font-size:15px;opacity:.7;margin-top:8px}
@media(min-width:768px){.poster{min-height:380px}.poster-text{min-height:380px;padding:80px 20px 100px}}
<?php else:?>
.poster{min-height:250px}.poster-text{display:none}
@media(min-width:768px){.poster{min-height:420px}}
@media(min-width:1200px){.poster{min-height:500px}}
<?php endif;?>

.form-wrap{background:#0d0d1a;padding:0 20px 40px;display:flex;flex-direction:column;align-items:center}
.form-card{width:100%;max-width:680px;background:rgba(15,15,35,.85);border:1.5px solid rgba(255,255,255,.1);border-radius:16px;padding:32px;backdrop-filter:blur(8px);margin-top:-50px;position:relative;z-index:3}
@media(min-width:768px){.form-card{padding:40px;margin-top:-70px}}
.form-title{text-align:center;margin-bottom:24px;padding-bottom:16px;border-bottom:1.5px solid rgba(255,255,255,.08)}
.form-title h2{font-size:17px;font-weight:800;text-transform:uppercase;letter-spacing:2px;color:#f59e0b}
.form-title p{color:rgba(255,255,255,.45);font-size:13px;margin-top:6px}
.fg{margin-bottom:16px}
.fl{display:flex;align-items:center;gap:8px;color:white;font-size:13px;font-weight:700;margin-bottom:6px}
.fl i{color:#f59e0b;font-size:14px}
.fi{width:100%;padding:13px 16px;background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.15);border-radius:10px;color:white;font-size:14px;font-weight:500;transition:.3s;outline:none}
.fi::placeholder{color:rgba(255,255,255,.28)}.fi:focus{border-color:#f59e0b;background:rgba(255,255,255,.1);box-shadow:0 0 0 3px rgba(245,158,11,.1)}
select.fi{appearance:auto;cursor:pointer}select.fi option{background:#1a1a3e;color:white}
.fr{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:600px){.fr{grid-template-columns:1fr}}
.sl{color:#f59e0b;font-size:14px;font-weight:700;margin:22px 0 14px;display:flex;align-items:center;gap:8px}
.btn-submit{width:100%;padding:16px;margin-top:22px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#0a0a1a;border:none;border-radius:12px;font-size:17px;font-weight:900;text-transform:uppercase;letter-spacing:3px;cursor:pointer;transition:.3s;box-shadow:0 8px 30px rgba(245,158,11,.3)}
.btn-submit:hover{transform:translateY(-2px);box-shadow:0 12px 40px rgba(245,158,11,.5)}
.disc{text-align:center;margin-top:14px;font-size:11px;color:rgba(255,255,255,.3)}
.sbox{text-align:center;padding:50px 20px}
.sicon{width:90px;height:90px;background:linear-gradient(135deg,#059669,#10b981);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:44px;margin:0 auto 20px}
.bagain{display:inline-flex;align-items:center;gap:8px;padding:12px 24px;background:rgba(255,255,255,.1);border:2px solid rgba(255,255,255,.2);border-radius:12px;color:white;text-decoration:none;font-weight:600}
.emsg{background:rgba(220,38,38,.2);border:1px solid rgba(220,38,38,.4);color:#fca5a5;padding:12px;border-radius:10px;margin-bottom:16px;font-size:13px;display:flex;align-items:center;gap:8px}
.logos-row{display:flex;justify-content:center;align-items:center;gap:24px;padding:30px 20px;background:#0d0d1a}.logos-row img{height:55px;object-fit:contain}
/* Foto */
.photo-sec{text-align:center;margin-bottom:22px;padding-bottom:18px;border-bottom:1px solid rgba(255,255,255,.06)}
.photo-lbl{color:rgba(255,255,255,.6);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px}
.photo-circle{width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,.06);border:2px solid rgba(255,255,255,.12);margin:0 auto 10px;display:flex;align-items:center;justify-content:center;overflow:hidden;cursor:pointer;transition:.3s}
.photo-circle:hover{border-color:#f59e0b;transform:scale(1.05)}.photo-circle img{width:100%;height:100%;object-fit:cover}.photo-circle i{font-size:32px;color:rgba(255,255,255,.18)}
.pbtns{display:flex;gap:8px;justify-content:center}
.pb{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:8px;font-size:11px;font-weight:600;cursor:pointer;border:none;transition:.2s}
.pb.cam{background:rgba(59,130,246,.25);color:#93c5fd;border:1px solid rgba(59,130,246,.35)}
.pb.gal{background:rgba(255,255,255,.08);color:rgba(255,255,255,.5);border:1px solid rgba(255,255,255,.15)}
.pb:hover{transform:translateY(-1px)}.phint{font-size:10px;color:rgba(255,255,255,.25);margin-top:6px}
</style></head><body>
<div class="poster"><div class="poster-ov"></div>
<div class="poster-text"><h1><?=htmlspecialchars($evento['nome'])?></h1><?php if($evento['descricao']):?><p><?=htmlspecialchars($evento['descricao'])?></p><?php endif;?></div>
<div class="poster-fade"></div></div>

<div class="form-wrap"><div class="form-card">
<?php if($success):?>
<div class="sbox"><div class="sicon"><i class="fas fa-check"></i></div><h3 style="font-size:22px;font-weight:800;margin-bottom:10px">Cadastro Realizado!</h3><p style="color:rgba(255,255,255,.5);margin-bottom:20px">Seus dados foram enviados com sucesso.</p><a href="inscricao.php?evento=<?=htmlspecialchars($param)?>" class="bagain"><i class="fas fa-plus"></i> Novo cadastro</a></div>
<?php else:?>
<div class="form-title"><h2><?=htmlspecialchars($titulo_form)?></h2><?php if($subtitulo_form):?><p><?=htmlspecialchars($subtitulo_form)?></p><?php endif;?></div>
<?php if($error):?><div class="emsg"><i class="fas fa-exclamation-triangle"></i> <?=htmlspecialchars($error)?></div><?php endif;?>
<form method="POST" id="F">
<!-- FOTO -->
<div class="photo-sec">
<div class="photo-lbl">Sua Foto (Opcional)</div>
<div class="photo-circle" id="pP" onclick="document.getElementById('fCam').click()"><i class="fas fa-user"></i></div>
<input type="hidden" name="foto_base64" id="fB64"><input type="file" id="fCam" class="d-none" accept="image/*" capture="environment"><input type="file" id="fGal" class="d-none" accept="image/*">
<div class="pbtns"><button type="button" class="pb cam" onclick="document.getElementById('fCam').click()"><i class="fas fa-camera"></i> Tirar foto</button><button type="button" class="pb gal" onclick="document.getElementById('fGal').click()"><i class="fas fa-image"></i> Galeria</button></div>
<div class="phint">Tire uma foto agora ou importe da galeria</div>
</div>

<!-- NOME -->
<div class="fg"><label class="fl"><i class="fas fa-user"></i> Nome Completo *</label><input type="text" class="fi" name="nome" required placeholder="Seu nome completo" value="<?=$_POST['nome']??''?>"></div>

<!-- WHATSAPP + INSTAGRAM -->
<div class="fr">
<div class="fg"><label class="fl"><i class="fab fa-whatsapp"></i> WhatsApp / Celular *</label><input type="text" class="fi" name="whatsapp" id="wa" required placeholder="(00) 00000-0000" value="<?=$_POST['whatsapp']??''?>"></div>
<div class="fg"><label class="fl"><i class="fab fa-instagram"></i> Instagram</label><input type="text" class="fi" name="instagram" placeholder="@ seuperfil" value="<?=ltrim($_POST['instagram']??'','@')?>"></div>
</div>

<!-- CAMPOS EXTRAS DO EVENTO -->
<?php foreach($campos_extras as $ce):?>
<div class="fg"><label class="fl"><i class="fas fa-<?=($ce['tipo']??'')=='number'?'hashtag':(($ce['tipo']??'')=='select'?'list':'pen')?>"></i> <?=htmlspecialchars($ce['label'])?> <?=($ce['obrigatorio']??false)?'*':''?></label>
<?php if(($ce['tipo']??'text')=='select'&&!empty($ce['opcoes'])):?>
<select name="extra_<?=$ce['nome']?>" class="fi" <?=($ce['obrigatorio']??false)?'required':''?>><option value="">Selecione...</option><?php foreach($ce['opcoes'] as $op):?><option value="<?=htmlspecialchars($op)?>" <?=($_POST['extra_'.$ce['nome']]??'')==$op?'selected':''?>><?=htmlspecialchars($op)?></option><?php endforeach;?></select>
<?php else:?><input type="<?=$ce['tipo']??'text'?>" name="extra_<?=$ce['nome']?>" class="fi" placeholder="<?=($ce['tipo']??'')=='number'?'Digite...':'Digite seu '.strtolower($ce['label'])?>" value="<?=htmlspecialchars($_POST['extra_'.$ce['nome']]??'')?>" <?=($ce['obrigatorio']??false)?'required':''?>><?php endif;?></div>
<?php endforeach;?>

<!-- ENDEREÇO -->
<div class="sl"><i class="fas fa-map-marker-alt"></i> Endereço</div>
<div class="fr">
<div class="fg"><label class="fl"><i class="fas fa-map-pin"></i> CEP</label><input type="text" class="fi" name="cep" id="cep" placeholder="00000-000"></div>
<div class="fg"><label class="fl"><i class="fas fa-road"></i> Logradouro</label><input type="text" class="fi" name="endereco" id="endereco" placeholder="Rua, Av, Bairro..."></div>
</div>
<div class="fr">
<div class="fg"><label class="fl"><i class="fas fa-city"></i> Cidade</label><input type="text" class="fi" name="cidade" id="cidade"></div>
<div class="fg"><label class="fl"><i class="fas fa-flag"></i> Estado</label><select class="fi" name="estado" id="estado"><option value="">UF</option><?php foreach(['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $u):?><option value="<?=$u?>"><?=$u?></option><?php endforeach;?></select></div>
</div>

<button type="submit" class="btn-submit" id="btnS">PARTICIPAR</button>
<p class="disc">Seus dados estão seguros e não serão compartilhados.</p>
</form>
<?php endif;?>
</div>
<div class="logos-row"><img src="assets/img/logobombeiro.png"><img src="assets/img/logopolicia.png"><img src="assets/img/logo_assego.png"><img src="assets/img/logo_sergio.png" style="height:45px"></div>
</div>
<script>
function proc(f){if(!f||!f.type.startsWith('image/'))return;const r=new FileReader();r.onload=function(ev){const img=new Image();img.onload=function(){const c=document.createElement('canvas');let w=img.width,h=img.height,m=800;if(w>m||h>m){if(w>h){h=Math.round(h*m/w);w=m}else{w=Math.round(w*m/h);h=m}}c.width=w;c.height=h;c.getContext('2d').drawImage(img,0,0,w,h);const b=c.toDataURL('image/jpeg',.8);document.getElementById('fB64').value=b;document.getElementById('pP').innerHTML=`<img src="${b}">`};img.src=ev.target.result};r.readAsDataURL(f)}
document.getElementById('fCam')?.addEventListener('change',function(){proc(this.files[0])});
document.getElementById('fGal')?.addEventListener('change',function(){proc(this.files[0])});
document.getElementById('wa')?.addEventListener('input',function(e){let v=e.target.value.replace(/\D/g,'').substring(0,11);if(v.length>6)v='('+v.substring(0,2)+') '+v.substring(2,7)+'-'+v.substring(7);else if(v.length>2)v='('+v.substring(0,2)+') '+v.substring(2);else if(v.length>0)v='('+v;e.target.value=v});
document.getElementById('cep')?.addEventListener('input',function(e){let v=e.target.value.replace(/\D/g,'');if(v.length>8)v=v.slice(0,8);v=v.replace(/^(\d{5})(\d)/,'$1-$2');e.target.value=v;if(v.replace(/\D/g,'').length===8)fetch(`https://viacep.com.br/ws/${v.replace(/\D/g,'')}/json/`).then(r=>r.json()).then(d=>{if(!d.erro){document.getElementById('endereco').value=d.logradouro+(d.bairro?', '+d.bairro:'');document.getElementById('cidade').value=d.localidade;document.getElementById('estado').value=d.uf}}).catch(()=>{})});
document.getElementById('F')?.addEventListener('submit',function(){const b=document.getElementById('btnS');b.innerHTML='<i class="fas fa-spinner fa-spin"></i> ENVIANDO...';b.disabled=true;b.style.opacity='.7'});
</script>
</body></html>
