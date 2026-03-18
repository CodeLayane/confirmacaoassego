<?php
// export_pdf_advanced.php - Versão avançada de PDF usando TCPDF
// NOTA: Este arquivo requer a biblioteca TCPDF instalada

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
checkLogin();

// Verificar se TCPDF está disponível
if (!file_exists('tcpdf/tcpdf.php')) {
    // Se não tiver TCPDF, redirecionar para versão HTML
    header('Location: export.php?format=pdf&' . $_SERVER['QUERY_STRING']);
    exit;
}

require_once('tcpdf/tcpdf.php');

$pdo = getConnection();

// Obter filtros
$cargo_filter = $_GET['cargo'] ?? '';
$situacao_filter = $_GET['situacao'] ?? '';
$cidade_filter = $_GET['cidade'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';
$funcao_filter = $_GET['funcao'] ?? '';

// Verificar colunas
$checkColumns = $pdo->query("SHOW COLUMNS FROM membros");
$columns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);
$hasFuncao = in_array('funcao', $columns);
$hasMatricula = in_array('matricula', $columns);
$hasDataFiliacao = in_array('data_filiacao', $columns);

// Buscar dados (mesma query do export.php)
$sql = "SELECT m.*, c.nome as cargo_nome 
        FROM membros m 
        LEFT JOIN cargos c ON m.cargo_id = c.id 
        WHERE 1=1";
$params = [];

if ($cargo_filter) {
    $sql .= " AND m.cargo_id = ?";
    $params[] = $cargo_filter;
}

if ($situacao_filter) {
    $sql .= " AND m.situacao_cadastral = ?";
    $params[] = $situacao_filter;
}

if ($cidade_filter) {
    $sql .= " AND m.cidade = ?";
    $params[] = $cidade_filter;
}

if ($hasFuncao && $funcao_filter) {
    $sql .= " AND m.funcao = ?";
    $params[] = $funcao_filter;
}

if ($data_inicio && $data_fim) {
    if ($hasDataFiliacao) {
        $sql .= " AND (m.data_filiacao BETWEEN ? AND ? OR (m.data_filiacao IS NULL AND DATE(m.created_at) BETWEEN ? AND ?))";
    } else {
        $sql .= " AND DATE(m.created_at) BETWEEN ? AND ?";
    }
    $params[] = $data_inicio;
    $params[] = $data_fim;
    if ($hasDataFiliacao) {
        $params[] = $data_inicio;
        $params[] = $data_fim;
    }
}

$sql .= " ORDER BY m.nome";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$membros = $stmt->fetchAll();

// Criar PDF
class MYPDF extends TCPDF {
    
    // Cabeçalho
    public function Header() {
        // Logo ou título
        $this->SetFont('helvetica', 'B', 16);
        $this->SetTextColor(30, 64, 175);
        $this->Cell(0, 10, 'RemiLeal - Prof. Ritmos', 0, 1, 'C');
        
        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 5, 'Relatório de Alunos - ' . date('d/m/Y'), 0, 1, 'C');
        
        // Linha
        $this->SetLineWidth(0.5);
        $this->SetDrawColor(30, 64, 175);
        $this->Line(10, 25, $this->getPageWidth() - 10, 25);
        $this->Ln(10);
    }
    
    // Rodapé
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// Criar nova instância PDF
$pdf = new MYPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);

// Configurações do documento
$pdf->SetCreator('RemiLeal Sistema');
$pdf->SetAuthor('RemiLeal');
$pdf->SetTitle('Relatório de Alunos');
$pdf->SetSubject('Lista de Alunos RemiLeal');

// Configurações de página
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->SetMargins(10, 30, 10);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 20);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Adicionar página
$pdf->AddPage();

// Fonte para conteúdo
$pdf->SetFont('helvetica', '', 9);

// Resumo
$pdf->SetFillColor(240, 249, 255);
$pdf->SetTextColor(0, 0, 0);

// Estatísticas
$total_filiados = 0;
$total_desfiliados = 0;
foreach ($membros as $m) {
    if (in_array($m['situacao_cadastral'], ['Ativo', 'Filiado'])) {
        $total_filiados++;
    } else {
        $total_desfiliados++;
    }
}

// Box de resumo
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'Resumo do Relatório', 0, 1, 'L', true);
$pdf->SetFont('helvetica', '', 10);

$resumo_html = '<table cellpadding="5">
    <tr>
        <td width="200"><b>Total de Registros:</b></td>
        <td>' . count($membros) . '</td>
    </tr>
    <tr>
        <td><b>Alunos Ativos:</b></td>
        <td style="color: #059669;">' . $total_filiados . '</td>
    </tr>
    <tr>
        <td><b>Alunos Inativos:</b></td>
        <td style="color: #dc2626;">' . $total_desfiliados . '</td>
    </tr>';

if ($cargo_filter || $situacao_filter || $cidade_filter || ($data_inicio && $data_fim)) {
    $filtros = [];
    if ($cargo_filter) $filtros[] = "Cargo/Setor específico";
    if ($situacao_filter) $filtros[] = "Situação: " . $situacao_filter;
    if ($cidade_filter) $filtros[] = "Cidade: " . $cidade_filter;
    if ($data_inicio && $data_fim) $filtros[] = "Período: " . date('d/m/Y', strtotime($data_inicio)) . " a " . date('d/m/Y', strtotime($data_fim));
    
    $resumo_html .= '<tr>
        <td><b>Filtros Aplicados:</b></td>
        <td>' . implode(", ", $filtros) . '</td>
    </tr>';
}

$resumo_html .= '</table>';

$pdf->writeHTML($resumo_html, true, false, true, false, '');
$pdf->Ln(5);

// Tabela de membros
$pdf->SetFont('helvetica', '', 8);

// Cabeçalho da tabela
$html = '<table border="1" cellpadding="4">
    <thead>
        <tr style="background-color: #1e40af; color: white; font-weight: bold;">
            <th width="30">#</th>';

if ($hasMatricula) {
    $html .= '<th width="60">Matrícula</th>';
}

$html .= '<th width="180">Nome</th>
            <th width="100">CPF</th>
            <th width="100">Telefone</th>
            <th width="150">Email</th>
            <th width="120">Cargo/Setor</th>';

if ($hasFuncao) {
    $html .= '<th width="100">Função</th>';
}

$html .= '<th width="100">Cidade/UF</th>
            <th width="80">Filiação</th>
            <th width="60">Status</th>
        </tr>
    </thead>
    <tbody>';

// Dados
$i = 1;
foreach ($membros as $membro) {
    $situacao = in_array($membro['situacao_cadastral'], ['Ativo', 'Filiado']) ? 'Filiado' : 'Desfiliado';
    $data_filiacao = $hasDataFiliacao && $membro['data_filiacao'] 
        ? date('d/m/Y', strtotime($membro['data_filiacao'])) 
        : date('d/m/Y', strtotime($membro['created_at']));
    
    $cor_situacao = $situacao == 'Filiado' ? '#059669' : '#dc2626';
    
    $html .= '<tr>
        <td>' . $i++ . '</td>';
    
    if ($hasMatricula) {
        $html .= '<td>' . htmlspecialchars($membro['matricula'] ?? str_pad($membro['id'], 5, '0', STR_PAD_LEFT)) . '</td>';
    }
    
    $html .= '<td><b>' . htmlspecialchars($membro['nome']) . '</b></td>
        <td>' . formatCPF($membro['cpf']) . '</td>
        <td>' . formatPhone($membro['telefone']) . '</td>
        <td style="font-size: 7pt;">' . htmlspecialchars($membro['email']) . '</td>
        <td>' . htmlspecialchars($membro['cargo_nome'] ?? '-') . '</td>';
    
    if ($hasFuncao) {
        $html .= '<td>' . htmlspecialchars($membro['funcao'] ?? '-') . '</td>';
    }
    
    $cidade_uf = htmlspecialchars($membro['cidade'] ?? '-');
    if ($membro['estado']) $cidade_uf .= '/' . $membro['estado'];
    
    $html .= '<td>' . $cidade_uf . '</td>
        <td>' . $data_filiacao . '</td>
        <td style="color: ' . $cor_situacao . '; font-weight: bold;">' . $situacao . '</td>
    </tr>';
}

$html .= '</tbody></table>';

// Escrever tabela
$pdf->writeHTML($html, true, false, true, false, '');

// Saída do PDF
$pdf->Output('alunos_remileal_' . date('Y-m-d') . '.pdf', 'D');
?>