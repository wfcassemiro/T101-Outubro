<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';

if (!isLoggedIn()) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$page_title = 'Visualizar Fatura - Dash-T101';
$user_id = $_SESSION['user_id'];
$invoice_id = $_GET['id'] ?? null;

if (!$invoice_id) {
    header('Location: invoices.php');
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT i.*, 
               c.company AS company_name,
               c.name AS contact_name,
               c.email AS client_email,
               c.vat_number AS client_vat_number,
               c.address_line1 AS client_address_line1,
               c.address_line2 AS client_address_line2,
               c.address_line3 AS client_address_line3,
               c.phone AS client_phone
        FROM dash_invoices i 
        LEFT JOIN dash_clients c ON i.client_id = c.id 
        WHERE i.id = ? AND i.user_id = ?
    ");
    $stmt->execute([$invoice_id, $user_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        header('Location: invoices.php?error=Fatura não encontrada');
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM dash_invoice_items WHERE invoice_id = ? ORDER BY id ASC");
    $stmt->execute([$invoice_id]);
    $invoice_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    header('Location: invoices.php?error=Erro ao carregar fatura: ' . urlencode($e->getMessage()));
    exit;
}

include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<main class="main-content">

    <!-- Hero -->
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-file-invoice"></i> Fatura #<?php echo htmlspecialchars($invoice['invoice_number']); ?></h1>
            <p>Detalhes completos da fatura</p>
            <span class="status-badge status-<?php echo htmlspecialchars($invoice['status']); ?>">
                <?php
                    $labels = [
                        'draft' => 'Rascunho',
                        'sent' => 'Enviada',
                        'paid' => 'Paga',
                        'overdue' => 'Vencida',
                        'cancelled' => 'Cancelada'
                    ];
                    echo $labels[$invoice['status']] ?? $invoice['status'];
                ?>
            </span>
        </div>
    </div>

    <div class="page-grid">
        <!-- Coluna principal -->
        <div class="page-main">
            
            <!-- Cliente -->
            <div class="video-card">
                <h2><i class="fas fa-building"></i> Cliente</h2>
                <p><strong>Empresa:</strong> <?php echo htmlspecialchars($invoice['company_name']); ?></p>
                <?php if ($invoice['contact_name']): ?>
                    <p><strong>Contato:</strong> <?php echo htmlspecialchars($invoice['contact_name']); ?></p>
                <?php endif; ?>
                <?php if ($invoice['client_email']): ?>
                    <p><strong>Email:</strong> 
                        <a href="mailto:<?php echo htmlspecialchars($invoice['client_email']); ?>">
                            <?php echo htmlspecialchars($invoice['client_email']); ?>
                        </a>
                    </p>
                <?php endif; ?>
                <?php if ($invoice['client_vat_number']): ?>
                    <p><strong>CNPJ/CPF:</strong> <?php echo htmlspecialchars($invoice['client_vat_number']); ?></p>
                <?php endif; ?>
                <?php if ($invoice['client_address_line1']): ?>
                    <p><strong>Endereço:</strong><br>
                        <?php echo htmlspecialchars($invoice['client_address_line1']); ?><br>
                        <?php echo htmlspecialchars($invoice['client_address_line2']); ?><br>
                        <?php echo htmlspecialchars($invoice['client_address_line3']); ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Itens -->
            <div class="video-card">
                <h2><i class="fas fa-list"></i> Itens da Fatura</h2>
                <?php if (empty($invoice_items)): ?>
                    <div class="alert-warning"><i class="fas fa-info-circle"></i> Nenhum item nesta fatura.</div>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Descrição</th>
                            <th>Qtd</th>
                            <th>Valor Unit.</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoice_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['description']); ?></td>
                            <td style="text-align:center"><?php echo number_format($item['quantity'], 2, ',', '.'); ?></td>
                            <td style="text-align:right"><?php echo formatCurrency($item['unit_price'], $invoice['currency']); ?></td>
                            <td style="text-align:right"><strong><?php echo formatCurrency($item['total_price'], $invoice['currency']); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <aside class="page-sidebar">

            <!-- Detalhes -->
            <div class="video-card">
                <h2><i class="fas fa-info-circle"></i> Detalhes</h2>
                <p><strong>Número:</strong> <?php echo htmlspecialchars($invoice['invoice_number']); ?></p>
                <p><strong>Emissão:</strong> <?php echo date('d/m/Y', strtotime($invoice['issue_date'])); ?></p>
                <p><strong>Vencimento:</strong> <?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?></p>
                <p><strong>Status:</strong> <?php echo $labels[$invoice['status']] ?? $invoice['status']; ?></p>
                <p><strong>Moeda:</strong> <?php echo htmlspecialchars($invoice['currency']); ?></p>
                <p><strong>Subtotal:</strong> <?php echo formatCurrency($invoice['subtotal'], $invoice['currency']); ?></p>
                <p><strong>Impostos:</strong> <?php echo formatCurrency($invoice['tax_amount'], $invoice['currency']); ?></p>
                <p><strong>Total:</strong> <span style="color:var(--brand-purple);font-weight:bold;"><?php echo formatCurrency($invoice['total_amount'], $invoice['currency']); ?></span></p>
                <?php if ($invoice['notes']): ?>
                    <p><strong>Notas:</strong><br><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
                <?php endif; ?>
            </div>

            <!-- Ações -->
            <div class="video-card">
                <h2><i class="fas fa-cogs"></i> Ações</h2>
                <div style="display:flex; justify-content:center; gap:10px; flex-wrap:wrap;">
                    <a href="invoices.php?edit=<?php echo $invoice['id']; ?>" class="page-btn" style="min-width:100px;">
                        <i class="fas fa-edit"></i> Editar
                    </a>
                    
                    <a href="invoice_print.php?id=<?php echo $invoice['id']; ?>" 
                       class="page-btn" style="min-width:100px;" target="_blank">
                        <i class="fas fa-file-invoice"></i> Versão para Impressão
                    </a>
                   
                    
                    <a href="invoices.php" class="page-btn" style="min-width:100px;">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                </div>
            </div>

        </aside>
    </div>
</main>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>