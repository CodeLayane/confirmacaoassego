<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config.php';
checkLogin();
$pdo = getConnection();

$format        = $_GET['format'] ?? 'excel';
$cidade_filter = $_GET['cidade'] ?? '';
$status_filter = $_GET['status'] ?? '';
$data_inicio   = $_GET['data_inicio'] ?? '';
$data_fim      = $_GET['data_fim'] ?? '';

$sql = "SELECT m.id, m.nome, m.whatsapp, m.instagram, m.endereco, m.cidade, m.estado, m.cep, m.observacoes, m.ativo, m.created_at
        FROM membros m WHERE m.aprovado = 1";
$params = [];
if ($cidade_filter)           { $sql .= " AND m.cidade = ?";    $params[] = $cidade_filter; }
if ($status_filter === 'ativo')   $sql .= " AND (m.ativo IS NULL OR m.ativo = 1)";
if ($status_filter === 'inativo') $sql .= " AND m.ativo = 0";
if ($data_inicio && $data_fim) {
    $sql .= " AND DATE(m.created_at) BETWEEN ? AND ?";
    $params[] = $data_inicio; $params[] = $data_fim;
}
$sql .= " ORDER BY m.nome";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$membros = $stmt->fetchAll();

$total_ativos = $total_inativos = 0;
foreach ($membros as $m) { if ($m['ativo'] ?? 1) $total_ativos++; else $total_inativos++; }

if ($format == 'excel') {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"alunos_remileal_" . date('Y-m-d') . ".xls\"");
    header("Pragma: no-cache"); header("Expires: 0");
    echo "\xEF\xBB\xBF";
    echo "<table border='1'>";
    echo "<tr style='background:#1e40af;color:white;font-weight:bold;'>";
    echo "<th>N</th><th>Nome</th><th>WhatsApp</th><th>Instagram</th><th>Endereco</th><th>Cidade</th><th>Estado</th><th>CEP</th><th>Status</th><th>Cadastro</th><th>Observacoes</th>";
    echo "</tr>";
    $i = 1;
    foreach ($membros as $m) {
        $status = ($m['ativo'] ?? 1) ? 'Ativo' : 'Inativo';
        $insta  = !empty($m['instagram']) ? '@' . ltrim($m['instagram'], '@') : '-';
        $bg     = ($m['ativo'] ?? 1) ? '' : "style='background:#fff1f2;'";
        echo "<tr $bg>";
        echo "<td>".($i++)."</td>";
        echo "<td>".htmlspecialchars($m['nome'])."</td>";
        echo "<td>".htmlspecialchars($m['whatsapp'] ?? '-')."</td>";
        echo "<td>".htmlspecialchars($insta)."</td>";
        echo "<td>".htmlspecialchars($m['endereco'] ?? '-')."</td>";
        echo "<td>".htmlspecialchars($m['cidade'] ?? '-')."</td>";
        echo "<td>".htmlspecialchars($m['estado'] ?? '-')."</td>";
        echo "<td>".htmlspecialchars($m['cep'] ?? '-')."</td>";
        echo "<td>".$status."</td>";
        echo "<td>".date('d/m/Y', strtotime($m['created_at']))."</td>";
        echo "<td>".htmlspecialchars($m['observacoes'] ?? '')."</td>";
        echo "</tr>";
    }
    echo "</table><br><table border='1'>";
    echo "<tr style='background:#e0f2fe;'><td colspan='2'><strong>RESUMO</strong></td></tr>";
    echo "<tr><td><strong>Total:</strong></td><td>".count($membros)."</td></tr>";
    echo "<tr><td><strong>Ativos:</strong></td><td>$total_ativos</td></tr>";
    echo "<tr><td><strong>Inativos:</strong></td><td>$total_inativos</td></tr>";
    echo "<tr><td><strong>Gerado em:</strong></td><td>".date('d/m/Y H:i:s')."</td></tr>";
    echo "</table>";

} elseif ($format == 'pdf') { ?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">
<title>Relatorio de Alunos - RemiLeal</title>
<style>
@page{size:A4 landscape;margin:1cm}
body{font-family:Arial,sans-serif;font-size:9pt;color:#333;margin:0;padding:0}
.header{text-align:center;margin-bottom:16px;padding-bottom:10px;border-bottom:2px solid #1e40af}
.header h1{color:#1e40af;font-size:18pt;margin:0 0 4px}
.header p{color:#666;margin:0;font-size:9pt}
.summary{background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:10px 16px;margin-bottom:16px;display:flex;gap:30px;flex-wrap:wrap}
.summary p{margin:0}
table{width:100%;border-collapse:collapse;margin-bottom:20px}
th{background:#1e40af;color:white;padding:6px 8px;text-align:left;font-size:8pt}
td{padding:5px 8px;border-bottom:1px solid #e5e7eb;font-size:8pt}
tr:nth-child(even){background:#f8faff}
.badge-ativo{background:#dcfce7;color:#16a34a;padding:2px 7px;border-radius:10px;font-weight:700;font-size:7.5pt}
.badge-inativo{background:#fee2e2;color:#dc2626;padding:2px 7px;border-radius:10px;font-weight:700;font-size:7.5pt}
.footer{text-align:center;font-size:8pt;color:#999;margin-top:20px;border-top:1px solid #e5e7eb;padding-top:8px}
.no-print{margin:16px;text-align:center}
.btn{background:#1e40af;color:white;padding:8px 18px;text-decoration:none;border-radius:5px;display:inline-block;margin:4px;font-family:Arial;font-size:10pt;border:none;cursor:pointer}
@media print{.no-print{display:none}body{margin:0}tr{page-break-inside:avoid}}
</style></head><body>
<div class="no-print">
    <button onclick="window.print()" class="btn">Imprimir / Salvar PDF</button>
    <a href="relatorios.php" class="btn" style="background:#6b7280;">Voltar</a>
    <p style="margin-top:8px;color:#666;font-size:10pt;">Para salvar como PDF: clique em Imprimir e selecione "Salvar como PDF"</p>
</div>
<div class="header">
    <h1>RemiLeal - Prof. Ritmos</h1>
    <p>Relatorio de Alunos - Gerado em <?php echo date('d/m/Y H:i'); ?></p>
</div>
<div class="summary">
    <p><strong>Total:</strong> <?php echo count($membros); ?> alunos</p>
    <p><strong>Ativos:</strong> <?php echo $total_ativos; ?></p>
    <p><strong>Inativos:</strong> <?php echo $total_inativos; ?></p>
    <?php if ($cidade_filter): ?><p><strong>Cidade:</strong> <?php echo htmlspecialchars($cidade_filter); ?></p><?php endif; ?>
    <?php if ($data_inicio && $data_fim): ?><p><strong>Periodo:</strong> <?php echo date('d/m/Y',strtotime($data_inicio)); ?> a <?php echo date('d/m/Y',strtotime($data_fim)); ?></p><?php endif; ?>
</div>
<table>
    <thead><tr>
        <th width="3%">#</th>
        <th width="22%">Nome</th>
        <th width="13%">WhatsApp</th>
        <th width="13%">Instagram</th>
        <th width="20%">Endereco</th>
        <th width="10%">Cidade/UF</th>
        <th width="8%">Cadastro</th>
        <th width="9%">Status</th>
    </tr></thead>
    <tbody>
    <?php $i=1; foreach($membros as $m):
        $status   = ($m['ativo'] ?? 1) ? 'Ativo' : 'Inativo';
        $badgeCls = ($m['ativo'] ?? 1) ? 'badge-ativo' : 'badge-inativo';
        $insta    = !empty($m['instagram']) ? '@'.ltrim($m['instagram'],'@') : '-';
        $cidade_uf = trim(($m['cidade']??'').(($m['estado']??'') ? '/'.$m['estado'] : '')) ?: '-';
    ?>
        <tr>
            <td><?php echo $i++; ?></td>
            <td><strong><?php echo htmlspecialchars($m['nome']); ?></strong></td>
            <td><?php echo htmlspecialchars($m['whatsapp']??'-'); ?></td>
            <td><?php echo htmlspecialchars($insta); ?></td>
            <td><?php echo htmlspecialchars($m['endereco']??'-'); ?></td>
            <td><?php echo htmlspecialchars($cidade_uf); ?></td>
            <td><?php echo date('d/m/Y',strtotime($m['created_at'])); ?></td>
            <td><span class="<?php echo $badgeCls; ?>"><?php echo $status; ?></span></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<div class="footer">
    <p>RemiLeal - Prof. Ritmos | Gerado em <?php echo date('d/m/Y H:i:s'); ?></p>
</div>
<?php if(isset($_GET['autoprint']) && $_GET['autoprint']=='1'): ?>
<script>window.onload=function(){window.print();}</script>
<?php endif; ?>
</body></html>
<?php } ?>
