<?php
require_once 'layout.php';
if(!$evento_atual){/* no event */}
$campos_extras=[];
if($evento_atual&&!empty($evento_atual['campos_extras']))$campos_extras=json_decode($evento_atual['campos_extras'],true)?:[];
$slug_ev=$evento_atual['slug']??null;
$base=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?"https":"http")."://".$_SERVER['HTTP_HOST'].dirname($_SERVER['SCRIPT_NAME']).'/';
$link_cadastro=$base."inscricao.php?evento=".($slug_ev?:($evento_atual['id']??''));
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>ASSEGO Eventos</title><?php renderCSS();?>
<style>.rt-updating{position:relative}.rt-updating::after{content:'';position:absolute;top:8px;right:8px;width:6px;height:6px;border-radius:50%;background:#10b981;animation:rtPulse 1.5s infinite}
.row-new{animation:rowFlash .8s ease}.@keyframes rowFlash{0%{background:#dbeafe}100%{background:transparent}}</style>
</head><body>
<?php renderHeader('index');?>

<?php if(!$evento_atual):?>
<div class="no-evento-alert"><i class="fas fa-calendar-times" style="font-size:48px;color:#dbeafe;display:block;margin-bottom:16px"></i><h3 style="color:var(--primary)">Nenhum evento</h3>
<p style="color:var(--gray)"><?=isAdmin()?'<a href="eventos.php?action=add" class="btn btn-primary mt-3"><i class="fas fa-plus-circle"></i> Criar Evento</a>':'Solicite acesso ao administrador.'?></p></div>
<?php else:?>

<!-- Stats (atualizadas em tempo real) -->
<div class="stats-container">
    <div class="stat-card rt-updating"><div class="stat-header"><div><div class="stat-value" id="rt-total">0</div><div class="stat-label">Cadastrados</div></div><div class="stat-icon"><i class="fas fa-users"></i></div></div></div>
    <div class="stat-card"><div class="stat-header"><div><div class="stat-value" id="rt-novos">0</div><div class="stat-label">Novos (30 dias)</div></div><div class="stat-icon" style="background:linear-gradient(135deg,#0284c7,#0ea5e9)"><i class="fas fa-user-plus"></i></div></div></div>
    <div class="stat-card"><div class="stat-header"><div><div class="stat-value" id="rt-pendentes">0</div><div class="stat-label">Pendentes</div></div><div class="stat-icon" style="background:linear-gradient(135deg,#d97706,#f59e0b)"><i class="fas fa-user-clock"></i></div></div></div>
</div>

<!-- Filtros -->
<div style="background:white;padding:20px 24px;margin:0 24px 20px;border-radius:16px;box-shadow:var(--shadow-md);border:1px solid #dbeafe">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px">
        <h3 style="color:var(--primary);font-size:18px;font-weight:600;margin:0"><i class="fas fa-bolt" style="color:#f59e0b"></i> <?=htmlspecialchars($evento_atual['nome'])?> <small style="color:var(--gray);font-weight:400;font-size:12px">— tempo real</small></h3>
        <div class="d-flex gap-2 flex-wrap">
            <a href="participantes.php?action=add" class="btn btn-success btn-sm"><i class="fas fa-plus-circle"></i> Novo</a>
            <button class="btn btn-sm" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;border:none" data-bs-toggle="modal" data-bs-target="#modalLink"><i class="fas fa-share-alt"></i> Link</button>
            <a href="export.php?format=excel" class="btn btn-info btn-sm"><i class="fas fa-download"></i> Excel</a>
        </div>
    </div>
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:end">
        <div style="flex:1;min-width:200px"><input type="text" id="rtSearch" class="form-control" placeholder="Buscar nome, WhatsApp, CPF... (busca em tempo real)" autocomplete="off"></div>
        <select id="rtPerPage" class="form-select" style="width:auto"><option value="10">10</option><option value="25" selected>25</option><option value="50">50</option><option value="100">100</option></select>
    </div>
</div>

<!-- Tabela (atualizada em tempo real) -->
<div class="table-container" id="rtTableContainer">
    <div class="table-header">
        <h3 class="table-title">Cadastrados</h3>
        <span style="color:var(--gray)" id="rtTotalRows">carregando...</span>
    </div>
    <div style="overflow-x:auto">
        <table class="table"><thead><tr>
            <th>Nome</th><th>WhatsApp</th><th>Instagram</th>
            <?php foreach(array_slice($campos_extras,0,2) as $ce):?><th><?=htmlspecialchars($ce['label'])?></th><?php endforeach;?>
            <th class="text-center">Ações</th>
        </tr></thead>
        <tbody id="rtTableBody">
            <tr><td colspan="<?=4+count(array_slice($campos_extras,0,2))?>" style="text-align:center;padding:40px;color:var(--gray)"><i class="fas fa-spinner fa-spin"></i> Carregando...</td></tr>
        </tbody></table>
    </div>
    <div class="pagination-container" id="rtPagination"></div>
</div>

<!-- Modal Link -->
<div class="modal fade" id="modalLink" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="border-radius:16px;border:none">
<div class="modal-header" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;border-radius:16px 16px 0 0"><h5 class="modal-title"><i class="fas fa-link"></i> Link de Cadastro</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<div class="modal-body p-4 text-center"><p class="text-muted small">Envie para as pessoas se cadastrarem</p>
<div class="input-group mb-3"><input type="text" class="form-control bg-light" id="linkInput" value="<?=$link_cadastro?>" readonly><button class="btn btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('linkInput').value).then(()=>Swal.fire({icon:'success',title:'Copiado!',timer:1500,showConfirmButton:false}))"><i class="fas fa-copy"></i></button></div></div></div></div></div>

<?php endif;?>

<?php renderScripts();?>
<script>
<?php if($evento_atual):?>
const CAMPOS_EXTRAS = <?=json_encode(array_slice($campos_extras,0,2))?>;
let currentPage = 1;
let currentSearch = '';

// Buscar lista via AJAX
async function loadTable(page, search){
    page = page || currentPage;
    search = search !== undefined ? search : currentSearch;
    const perPage = document.getElementById('rtPerPage').value;
    const eid = ASSEGO_RT.getEventoId();
    
    try{
        const r = await fetch(`api_realtime.php?action=lista&evento_id=${eid}&page=${page}&per_page=${perPage}&search=${encodeURIComponent(search)}`,{cache:'no-store'});
        const d = await r.json();
        
        currentPage = d.page;
        currentSearch = search;
        
        // Atualizar contador
        document.getElementById('rtTotalRows').textContent = d.total + ' registros';
        
        // Renderizar linhas
        const tbody = document.getElementById('rtTableBody');
        if(d.rows.length === 0){
            tbody.innerHTML = `<tr><td colspan="${4+CAMPOS_EXTRAS.length}" style="text-align:center;padding:40px;color:var(--gray)"><i class="fas fa-users-slash" style="font-size:32px;color:#dbeafe;margin-bottom:8px;display:block"></i>Nenhum cadastrado encontrado</td></tr>`;
        } else {
            tbody.innerHTML = d.rows.map(p => {
                const extras = JSON.parse(p.campos_extras || '{}');
                const insta = p.instagram ? '@'+p.instagram.replace(/^@/,'') : '-';
                let extraCols = '';
                CAMPOS_EXTRAS.forEach(ce => {
                    extraCols += `<td>${extras[ce.nome] || '-'}</td>`;
                });
                return `<tr>
                    <td class="fw-semibold">${(p.nome||'').toUpperCase()}</td>
                    <td>${p.whatsapp||'-'}</td>
                    <td>${insta}</td>
                    ${extraCols}
                    <td><div class="action-buttons">
                        <a href="participante_view.php?id=${p.id}" class="btn-action view"><i class="fas fa-eye"></i></a>
                        <a href="participantes.php?action=edit&id=${p.id}" class="btn-action edit"><i class="fas fa-edit"></i></a>
                        <button onclick="delPart(${p.id})" class="btn-action delete"><i class="fas fa-trash"></i></button>
                    </div></td></tr>`;
            }).join('');
        }
        
        // Paginação
        const pag = document.getElementById('rtPagination');
        if(d.total_pages > 1){
            let html = `<div style="color:var(--gray);font-size:14px">Pág ${d.page} de ${d.total_pages} (${d.total})</div><nav><ul class="pagination">`;
            for(let i=Math.max(1,d.page-2);i<=Math.min(d.total_pages,d.page+2);i++){
                html += `<li class="page-item ${i===d.page?'active':''}"><a class="page-link" href="#" onclick="loadTable(${i});return false">${i}</a></li>`;
            }
            html += '</ul></nav>';
            pag.innerHTML = html;
            pag.style.display = 'flex';
        } else { pag.style.display = 'none'; }
        
    }catch(e){ console.error('loadTable error',e); }
}

// Deletar participante via AJAX
function delPart(id){
    Swal.fire({title:'Excluir?',icon:'warning',showCancelButton:true,confirmButtonColor:'#dc2626',confirmButtonText:'Excluir'}).then(r=>{
        if(r.isConfirmed){
            fetch(`participantes.php?action=delete&id=${id}&ajax=1`).then(()=>{
                ASSEGO_RT.toast('Participante excluído','🗑️');
                loadTable();
                ASSEGO_RT.refresh();
            });
        }
    });
}

// Busca em tempo real (com debounce)
let searchTimer;
document.getElementById('rtSearch')?.addEventListener('input', function(){
    clearTimeout(searchTimer);
    searchTimer = setTimeout(()=>loadTable(1, this.value), 400);
});
document.getElementById('rtPerPage')?.addEventListener('change', ()=>loadTable(1));

// Carregar tabela inicial
loadTable(1, '');

// Registrar callback no motor RT para recarregar a tabela quando houver mudanças
let lastKnownTotal = -1;
ASSEGO_RT.onUpdate(function(data){
    if(lastKnownTotal >= 0 && data.total !== lastKnownTotal){
        loadTable(); // Recarregar tabela automaticamente
    }
    lastKnownTotal = data.total;
});
<?php endif;?>
</script>
</body></html>
