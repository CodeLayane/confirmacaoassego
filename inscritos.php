<?php
require_once 'layout.php';

if (!$evento_atual) { header('Location: index.php'); exit(); }
$eid = $evento_atual['id'];
$campos_extras = json_decode($evento_atual['campos_extras'] ?? '[]', true) ?: [];

$action = $_GET['action'] ?? 'list';
$message = ''; $error = ''; $errors = [];

// Buscar inscrito para edição
$inscrito = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM inscritos WHERE id=? AND evento_id=?");
    $stmt->execute([$_GET['id'], $eid]);
    $inscrito = $stmt->fetch();
    if (!$inscrito) { header("Location: index.php"); exit(); }
}

// POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($action == 'add' || $action == 'edit')) {
    $nome = clean_input($_POST['nome'] ?? '');
    $whatsapp = preg_replace('/[^0-9]/', '', $_POST['whatsapp'] ?? '');
    $instagram = clean_input($_POST['instagram'] ?? '');
    $endereco = clean_input($_POST['endereco'] ?? '');
    $cidade = clean_input($_POST['cidade'] ?? '');
    $estado = clean_input($_POST['estado'] ?? '');
    $cep = preg_replace('/[^0-9-]/', '', $_POST['cep'] ?? '');
    $observacoes = clean_input($_POST['observacoes'] ?? '');
    $ativo_ins = isset($_POST['ativo']) ? 1 : 0;
    
    // Campos extras
    $extras = [];
    foreach ($campos_extras as $ce) {
        $val = clean_input($_POST['extra_'.$ce['nome']] ?? '');
        $extras[$ce['nome']] = $val;
        if (($ce['obrigatorio'] ?? false) && empty($val)) {
            $errors[] = $ce['label'] . " é obrigatório.";
        }
    }
    $extras_json = json_encode($extras, JSON_UNESCAPED_UNICODE);
    
    if (empty($nome) || strlen($nome) < 3) $errors[] = "Nome é obrigatório.";
    if (empty($whatsapp) || strlen($whatsapp) < 10) $errors[] = "WhatsApp é obrigatório.";
    
    if (empty($errors)) {
        try {
            if ($action == 'add') {
                $sql = "INSERT INTO inscritos (evento_id,nome,whatsapp,instagram,endereco,cidade,estado,cep,observacoes,campos_extras,aprovado,ativo) VALUES (?,?,?,?,?,?,?,?,?,?,1,?)";
                $pdo->prepare($sql)->execute([$eid,$nome,$whatsapp,$instagram,$endereco,$cidade,$estado,$cep,$observacoes,$extras_json,$ativo_ins]);
                $iid = $pdo->lastInsertId();
            } else {
                $id = $_POST['id'];
                $sql = "UPDATE inscritos SET nome=?,whatsapp=?,instagram=?,endereco=?,cidade=?,estado=?,cep=?,observacoes=?,campos_extras=?,ativo=? WHERE id=? AND evento_id=?";
                $pdo->prepare($sql)->execute([$nome,$whatsapp,$instagram,$endereco,$cidade,$estado,$cep,$observacoes,$extras_json,$ativo_ins,$id,$eid]);
                $iid = $id;
            }
            
            // Foto base64
            $foto_b64 = $_POST['foto_base64'] ?? '';
            if (!empty($foto_b64) && strpos($foto_b64, 'data:image') === 0) {
                $chk = $pdo->prepare("SELECT id FROM fotos WHERE inscrito_id=?"); $chk->execute([$iid]);
                if ($chk->fetch()) {
                    $pdo->prepare("UPDATE fotos SET dados=?,updated_at=NOW() WHERE inscrito_id=?")->execute([$foto_b64, $iid]);
                } else {
                    $pdo->prepare("INSERT INTO fotos (inscrito_id,dados) VALUES (?,?)")->execute([$iid, $foto_b64]);
                }
            }
            if (isset($_POST['remove_photo']) && $_POST['remove_photo']=='1') {
                $pdo->prepare("DELETE FROM fotos WHERE inscrito_id=?")->execute([$iid]);
            }
            
            $msg = $action=='add' ? "Inscrito cadastrado!" : "Inscrito atualizado!";
            header("Location: index.php?message=" . urlencode($msg)); exit();
        } catch (PDOException $e) {
            $errors[] = "Erro: " . $e->getMessage();
        }
    }
}

// Excluir
if ($action == 'delete' && isset($_GET['id'])) {
    $pdo->prepare("DELETE FROM fotos WHERE inscrito_id=?")->execute([$_GET['id']]);
    $pdo->prepare("DELETE FROM materiais WHERE inscrito_id=?")->execute([$_GET['id']]);
    $pdo->prepare("DELETE FROM inscritos WHERE id=? AND evento_id=?")->execute([$_GET['id'], $eid]);
    header("Location: index.php?message=" . urlencode("Inscrito excluído!")); exit();
}

// Foto atual
$foto_atual = null;
if ($inscrito) {
    $stmt = $pdo->prepare("SELECT * FROM fotos WHERE inscrito_id=? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$inscrito['id']]);
    $foto_atual = $stmt->fetch();
}

$extras_vals = $inscrito ? (json_decode($inscrito['campos_extras'] ?? '{}', true) ?: []) : [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $action=='add'?'Novo Inscrito':'Editar Inscrito'; ?> - ASSEGO</title>
    <?php renderCSS(); ?>
</head>
<body>
    <?php renderHeader('index'); ?>
    
    <div class="content-container">
        <div class="form-card">
            <h2 style="color:var(--primary);margin-bottom:24px;padding-bottom:16px;border-bottom:2px solid #dbeafe;">
                <i class="fas <?php echo $action=='add'?'fa-user-plus':'fa-user-edit'; ?>"></i>
                <?php echo $action=='add'?'Novo Inscrito':'Editar Inscrito'; ?>
                <small style="color:var(--gray);font-weight:400;font-size:14px;">— <?php echo htmlspecialchars($evento_atual['nome']); ?></small>
            </h2>
            
            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i>
                <div><strong>Corrija:</strong><ul class="mb-0 mt-1"><?php foreach($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div>
            </div>
            <?php endif; ?>
            
            <form method="POST" id="inscritoForm">
                <?php if ($action=='edit'): ?><input type="hidden" name="id" value="<?php echo $inscrito['id']; ?>"><?php endif; ?>
                
                <!-- Foto -->
                <div style="text-align:center;margin-bottom:24px;">
                    <label class="form-label">Foto</label>
                    <div id="photoPreview" style="width:120px;height:120px;border-radius:50%;background:#e0f2fe;margin:8px auto;display:flex;align-items:center;justify-content:center;overflow:hidden;border:3px solid white;box-shadow:0 4px 6px rgba(0,0,0,0.1);cursor:pointer;" onclick="document.getElementById('fotoInput').click()">
                        <?php if ($foto_atual && !empty($foto_atual['dados'])): ?>
                        <img src="<?php echo $foto_atual['dados']; ?>" style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                        <i class="fas fa-camera" style="font-size:32px;color:#94a3b8;"></i>
                        <?php endif; ?>
                    </div>
                    <input type="file" id="fotoInput" class="d-none" accept="image/*">
                    <input type="hidden" name="foto_base64" id="fotoBase64">
                    <input type="hidden" name="remove_photo" id="removePhotoFlag" value="0">
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-sm btn-secondary" onclick="document.getElementById('fotoInput').click()"><i class="fas fa-image"></i> Selecionar</button>
                        <?php if ($foto_atual): ?>
                        <button type="button" class="btn btn-sm btn-danger" onclick="document.getElementById('removePhotoFlag').value='1';document.getElementById('photoPreview').innerHTML='<i class=\'fas fa-camera\' style=\'font-size:32px;color:#94a3b8;\'></i>';"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Dados Pessoais -->
                <h4 style="color:var(--primary);margin-bottom:16px;"><i class="fas fa-user"></i> Dados Pessoais</h4>
                <div class="row g-3 mb-4">
                    <div class="col-md-8">
                        <label class="form-label">Nome Completo *</label>
                        <input type="text" name="nome" class="form-control" required value="<?php echo $inscrito?htmlspecialchars($inscrito['nome']):(isset($_POST['nome'])?htmlspecialchars($_POST['nome']):''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">WhatsApp *</label>
                        <input type="text" name="whatsapp" id="whatsapp" class="form-control" placeholder="(00) 00000-0000" value="<?php echo $inscrito?htmlspecialchars($inscrito['whatsapp']??''):''; ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Instagram</label>
                        <div class="input-group"><span class="input-group-text">@</span>
                        <input type="text" name="instagram" class="form-control" value="<?php echo $inscrito?htmlspecialchars(ltrim($inscrito['instagram']??'','@')):''; ?>"></div>
                    </div>
                </div>
                
                <!-- Campos Extras do Evento -->
                <?php if (!empty($campos_extras)): ?>
                <h4 style="color:var(--primary);margin-bottom:16px;"><i class="fas fa-list-check"></i> Dados do Evento</h4>
                <div class="row g-3 mb-4">
                    <?php foreach ($campos_extras as $ce): 
                        $val = $extras_vals[$ce['nome']] ?? ($_POST['extra_'.$ce['nome']] ?? '');
                    ?>
                    <div class="col-md-<?php echo ($ce['tipo']=='select')?'4':'6'; ?>">
                        <label class="form-label"><?php echo htmlspecialchars($ce['label']); ?> <?php echo ($ce['obrigatorio']??false)?'*':''; ?></label>
                        <?php if (($ce['tipo']??'text') == 'select' && !empty($ce['opcoes'])): ?>
                        <select name="extra_<?php echo $ce['nome']; ?>" class="form-select" <?php echo ($ce['obrigatorio']??false)?'required':''; ?>>
                            <option value="">Selecione...</option>
                            <?php foreach ($ce['opcoes'] as $op): ?>
                            <option value="<?php echo htmlspecialchars($op); ?>" <?php echo $val==$op?'selected':''; ?>><?php echo htmlspecialchars($op); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <input type="<?php echo $ce['tipo']??'text'; ?>" name="extra_<?php echo $ce['nome']; ?>" class="form-control" value="<?php echo htmlspecialchars($val); ?>" <?php echo ($ce['obrigatorio']??false)?'required':''; ?>>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Endereço -->
                <h4 style="color:var(--primary);margin-bottom:16px;"><i class="fas fa-map-marker-alt"></i> Endereço</h4>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label">CEP</label>
                        <input type="text" name="cep" id="cep" class="form-control" placeholder="00000-000" value="<?php echo $inscrito?htmlspecialchars($inscrito['cep']??''):''; ?>">
                    </div>
                    <div class="col-md-9">
                        <label class="form-label">Endereço</label>
                        <input type="text" name="endereco" id="endereco" class="form-control" value="<?php echo $inscrito?htmlspecialchars($inscrito['endereco']??''):''; ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Cidade</label>
                        <input type="text" name="cidade" id="cidade" class="form-control" value="<?php echo $inscrito?htmlspecialchars($inscrito['cidade']??''):''; ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Estado</label>
                        <select name="estado" id="estado" class="form-select">
                            <option value="">UF</option>
                            <?php $ufs=['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                            $sel = $inscrito ? ($inscrito['estado']??'') : '';
                            foreach($ufs as $u): ?>
                            <option value="<?php echo $u; ?>" <?php echo $sel==$u?'selected':''; ?>><?php echo $u; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Observações -->
                <h4 style="color:var(--primary);margin-bottom:16px;"><i class="fas fa-sticky-note"></i> Observações</h4>
                <textarea name="observacoes" class="form-control mb-4" rows="3"><?php echo $inscrito?htmlspecialchars($inscrito['observacoes']??''):''; ?></textarea>
                
                <!-- Status -->
                <div class="d-flex align-items-center gap-3 mb-4">
                    <input type="checkbox" name="ativo" id="ativo" style="width:48px;height:26px;cursor:pointer;" <?php echo (!$inscrito||($inscrito['ativo']??1))?'checked':''; ?>>
                    <label for="ativo" id="ativoLabel" style="cursor:pointer;font-weight:600;">
                        <?php echo (!$inscrito||($inscrito['ativo']??1))?'<span style="color:#16a34a">Ativo</span>':'<span style="color:#dc2626">Inativo</span>'; ?>
                    </label>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                    <a href="index.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
                </div>
            </form>
        </div>
    </div>
    
    <?php renderScripts(); ?>
    <script>
        // Foto
        document.getElementById('fotoInput')?.addEventListener('change', function(e) {
            const f = e.target.files[0]; if (!f) return;
            const r = new FileReader();
            r.onload = function(ev) {
                const img = new Image();
                img.onload = function() {
                    const c = document.createElement('canvas');
                    let w=img.width,h=img.height,m=800;
                    if(w>m||h>m){if(w>h){h=Math.round(h*m/w);w=m;}else{w=Math.round(w*m/h);h=m;}}
                    c.width=w;c.height=h;c.getContext('2d').drawImage(img,0,0,w,h);
                    const b=c.toDataURL('image/jpeg',0.8);
                    document.getElementById('fotoBase64').value=b;
                    document.getElementById('photoPreview').innerHTML=`<img src="${b}" style="width:100%;height:100%;object-fit:cover;">`;
                    document.getElementById('removePhotoFlag').value='0';
                };
                img.src=ev.target.result;
            };
            r.readAsDataURL(f);
        });
        // WhatsApp mask
        document.getElementById('whatsapp')?.addEventListener('input',function(e){
            let v=e.target.value.replace(/\D/g,'').substring(0,11);
            if(v.length>6)v='('+v.substring(0,2)+') '+v.substring(2,7)+'-'+v.substring(7);
            else if(v.length>2)v='('+v.substring(0,2)+') '+v.substring(2);
            else if(v.length>0)v='('+v;
            e.target.value=v;
        });
        // CEP
        document.getElementById('cep')?.addEventListener('input',function(e){
            let v=e.target.value.replace(/\D/g,'');if(v.length>8)v=v.slice(0,8);
            v=v.replace(/^(\d{5})(\d)/,'$1-$2');e.target.value=v;
            if(v.replace(/\D/g,'').length===8){
                fetch(`https://viacep.com.br/ws/${v.replace(/\D/g,'')}/json/`).then(r=>r.json()).then(d=>{
                    if(!d.erro){
                        document.getElementById('endereco').value=d.logradouro+(d.bairro?', '+d.bairro:'');
                        document.getElementById('cidade').value=d.localidade;
                        document.getElementById('estado').value=d.uf;
                    }
                }).catch(()=>{});
            }
        });
        // Toggle ativo
        document.getElementById('ativo')?.addEventListener('change',function(){
            document.getElementById('ativoLabel').innerHTML=this.checked?'<span style="color:#16a34a">Ativo</span>':'<span style="color:#dc2626">Inativo</span>';
        });
    </script>
</body>
</html>
