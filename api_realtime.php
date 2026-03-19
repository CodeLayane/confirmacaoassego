<?php
if(session_status()===PHP_SESSION_NONE) session_start();
require_once 'config.php'; checkLogin();
header('Content-Type: application/json'); header('Cache-Control: no-cache,no-store');
$pdo=getConnection(); $action=$_GET['action']??'stats'; $eid=(int)($_GET['evento_id']??($_SESSION['evento_atual']??0));
if(!$eid){echo json_encode(['error'=>'no_event']);exit();}
try{
switch($action){

    case 'stats':
        $s=$pdo->prepare("SELECT COUNT(*) FROM participantes WHERE aprovado=1 AND evento_id=?");$s->execute([$eid]);$total=(int)$s->fetchColumn();
        $s=$pdo->prepare("SELECT COUNT(*) FROM participantes WHERE aprovado=0 AND evento_id=?");$s->execute([$eid]);$pendentes=(int)$s->fetchColumn();
        $s=$pdo->prepare("SELECT COUNT(*) FROM participantes WHERE aprovado=1 AND evento_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)");$s->execute([$eid]);$novos=(int)$s->fetchColumn();
        echo json_encode(['total'=>$total,'pendentes'=>$pendentes,'novos'=>$novos]);
        break;

    case 'badge':
        $s=$pdo->prepare("SELECT COUNT(*) FROM participantes WHERE aprovado=0 AND evento_id=?");$s->execute([$eid]);
        echo json_encode(['pendentes'=>(int)$s->fetchColumn()]);
        break;

    // Lista paginada com busca (para atualizar tabela em tempo real)
    case 'lista':
        $search=$_GET['search']??'';$page=max(1,(int)($_GET['page']??1));$per_page=(int)($_GET['per_page']??10);$offset=($page-1)*$per_page;
        $where="WHERE p.aprovado=1 AND p.evento_id=?";$params=[$eid];
        if($search){$where.=" AND (p.nome LIKE ? OR p.whatsapp LIKE ? OR p.instagram LIKE ? OR p.campos_extras LIKE ?)";$l="%$search%";$params=array_merge($params,[$l,$l,$l,$l]);}
        $s=$pdo->prepare("SELECT COUNT(*) FROM participantes p $where");$s->execute($params);$total_rows=(int)$s->fetchColumn();
        $s=$pdo->prepare("SELECT p.id,p.nome,p.whatsapp,p.instagram,p.campos_extras,p.ativo,p.created_at FROM participantes p $where ORDER BY p.nome LIMIT $per_page OFFSET $offset");$s->execute($params);$rows=$s->fetchAll();
        echo json_encode(['rows'=>$rows,'total'=>$total_rows,'page'=>$page,'per_page'=>$per_page,'total_pages'=>ceil($total_rows/$per_page)]);
        break;

    // Pendentes (para atualizar solicitações em tempo real)
    case 'pendentes':
        $s=$pdo->prepare("SELECT p.id,p.nome,p.whatsapp,p.instagram,p.cidade,p.estado,p.campos_extras,p.created_at,(SELECT dados FROM fotos WHERE participante_id=p.id LIMIT 1) as foto FROM participantes p WHERE p.evento_id=? AND p.aprovado=0 ORDER BY p.created_at DESC");
        $s->execute([$eid]);$rows=$s->fetchAll();
        echo json_encode(['total'=>count($rows),'rows'=>$rows]);
        break;

    // Aprovar via AJAX
    case 'aprovar':
        $pid=(int)($_GET['id']??0);
        $pdo->prepare("UPDATE participantes SET aprovado=1 WHERE id=? AND evento_id=?")->execute([$pid,$eid]);
        echo json_encode(['ok'=>true]);
        break;

    // Rejeitar via AJAX
    case 'rejeitar':
        $pid=(int)($_GET['id']??0);
        $pdo->prepare("DELETE FROM fotos WHERE participante_id=?")->execute([$pid]);
        $pdo->prepare("DELETE FROM participantes WHERE id=? AND evento_id=? AND aprovado=0")->execute([$pid,$eid]);
        echo json_encode(['ok'=>true]);
        break;

    // Aprovar todos
    case 'aprovar_todos':
        $pdo->prepare("UPDATE participantes SET aprovado=1 WHERE evento_id=? AND aprovado=0")->execute([$eid]);
        echo json_encode(['ok'=>true]);
        break;

    // Stats para relatórios
    case 'relatorio':
        $s=$pdo->prepare("SELECT COUNT(*) FROM participantes WHERE aprovado=1 AND evento_id=?");$s->execute([$eid]);$total=(int)$s->fetchColumn();
        $s=$pdo->prepare("SELECT COUNT(*) FROM participantes WHERE aprovado=0 AND evento_id=?");$s->execute([$eid]);$pendentes=(int)$s->fetchColumn();
        $cidades=$pdo->prepare("SELECT cidade,COUNT(*) as t FROM participantes WHERE aprovado=1 AND evento_id=? AND cidade!='' GROUP BY cidade ORDER BY t DESC LIMIT 10");$cidades->execute([$eid]);
        echo json_encode(['total'=>$total,'pendentes'=>$pendentes,'cidades'=>$cidades->fetchAll()]);
        break;

    default:
        echo json_encode(['error'=>'invalid_action']);
}
}catch(Exception $e){http_response_code(500);echo json_encode(['error'=>$e->getMessage()]);}
?>
