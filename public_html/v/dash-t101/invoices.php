<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';

// Verificar se o usuário está logado
if (!isLoggedIn()) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$page_title = 'Faturas - Dash-T101';
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
    switch ($_POST['action']) {
    case 'add_invoice':
    try {
    $pdo->beginTransaction();
    
    // Gerar número da fatura
    $invoice_number = generateInvoiceNumber($user_id);
    
    // Calcular valores
    $subtotal = floatval($_POST['subtotal'] ?? 0);
    $tax_rate = floatval($_POST['tax_rate'] ?? 0);
    $tax_amount = $subtotal * ($tax_rate / 100);
    $total_amount = $subtotal + $tax_amount;
    
    // Inserir fatura
    $stmt = $pdo->prepare("INSERT INTO dash_invoices (user_id, client_id, invoice_number, issue_date, due_date, subtotal, tax_rate, tax_amount, total_amount, currency, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $result = $stmt->execute([
    $user_id,
    $_POST['client_id'],
    $invoice_number,
    $_POST['invoice_date'],
    $_POST['due_date'],
    $subtotal,
    $tax_rate,
    $tax_amount,
    $total_amount,
    $_POST['currency'] ?? 'BRL',
    $_POST['status'],
    $_POST['notes'] ?? ''
    ]);
    
    if ($result) {
    $invoice_id = $pdo->lastInsertId();
    
    // Inserir itens da fatura se fornecidos
    if (!empty($_POST['items'])) {
    foreach ($_POST['items'] as $item) {
    if (!empty($item['description']) && !empty($item['quantity']) && !empty($item['unit_price'])) {
    $quantity = floatval($item['quantity']);
    $unit_price = floatval($item['unit_price']);
    $total_price = $quantity * $unit_price;
    
    $stmt = $pdo->prepare("INSERT INTO dash_invoice_items (invoice_id, project_id, description, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
    $invoice_id,
    $item['project_id'] ?: null,
    $item['description'],
    $quantity,
    $unit_price,
    $total_price
    ]);
    }
    }
    }
    
    $pdo->commit();
    $message = 'Fatura criada com sucesso! Número: ' . $invoice_number;
    } else {
    $pdo->rollback();
    $error = 'Erro ao criar fatura.';
    }
    } catch (PDOException $e) {
    $pdo->rollback();
    $error = 'Erro: ' . $e->getMessage();
    }
    break;
    
    case 'update_status':
    try {
    $stmt = $pdo->prepare("UPDATE dash_invoices SET status = ?, payment_date = ?, payment_method = ? WHERE id = ? AND user_id = ?");
    $payment_date = ($_POST['status'] == 'paid' && !empty($_POST['payment_date'])) ? $_POST['payment_date'] : null;
    $payment_method = ($_POST['status'] == 'paid' && !empty($_POST['payment_method'])) ? $_POST['payment_method'] : null;
    
    $result = $stmt->execute([
    $_POST['status'],
    $payment_date,
    $payment_method,
    $_POST['invoice_id'],
    $user_id
    ]);
    
    if ($result) {
    $message = 'Status da fatura atualizado com sucesso!';
    } else {
    $error = 'Erro ao atualizar status da fatura.';
    }
    } catch (PDOException $e) {
    $error = 'Erro: ' . $e->getMessage();
    }
    break;
    
    case 'delete_invoice':
    try {
    $stmt = $pdo->prepare("DELETE FROM dash_invoices WHERE id = ? AND user_id = ?");
    $result = $stmt->execute([$_POST['invoice_id'], $user_id]);
    
    if ($result) {
    $message = 'Fatura excluída!';
    } else {
    $error = 'Erro ao excluir fatura.';
    }
    } catch (PDOException $e) {
    $error = 'Erro: ' . $e->getMessage();
    }
    break;

    case 'send_invoice_email':
    try {
    $invoice_id = $_POST['invoice_id'];
    if (sendInvoiceEmail($user_id, $invoice_id)) {
    $message = 'Fatura enviada por e-mail!';
    } else {
    $error = 'Erro ao enviar fatura por e-mail. Verifique o log de erros para mais detalhes.';
    }
    } catch (Exception $e) {
    $error = 'Erro no processo de envio de e-mail: ' . $e->getMessage();
    error_log("Erro ao enviar fatura por e-mail: " . $e->getMessage());
    }
    break;

    case 'generate_invoice_multiple':
    try {
    if (empty($_POST['selected_projects'])) {
    $error = 'Nenhum projeto selecionado para gerar fatura.';
    break;
    }

    $selected_project_ids = $_POST['selected_projects'];
    $placeholders = implode(',', array_fill(0, count($selected_project_ids), '?'));
    $stmt = $pdo->prepare("SELECT id, client_id, total_amount, currency, title, source_language, target_language, service_type, po_number, word_count, rate_per_word, character_count, rate_per_character FROM dash_projects WHERE user_id = ? AND status = 'completed' AND id IN ($placeholders)");
    $params = array_merge([$user_id], $selected_project_ids);
    $stmt->execute($params);
    $projects_to_invoice = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($projects_to_invoice)) {
    $error = 'Nenhum projeto válido encontrado para faturar ou não pertencem a você/não estão concluídos.';
    break;
    }

    $first_client_id = $projects_to_invoice[0]['client_id'];
    $all_same_client = true;
    foreach ($projects_to_invoice as $p) {
    if ($p['client_id'] !== $first_client_id) {
    $all_same_client = false;
    break;
    }
    }

    if (!$all_same_client) {
    $error = 'Não é possível faturar projetos de clientes diferentes na mesma fatura.';
    break;
    }

    $user_settings = getUserSettings($user_id);
    $tax_rate = $user_settings['default_tax_rate'] ?? 0.00;

    $stmt_client = $pdo->prepare("SELECT default_currency FROM dash_clients WHERE id = ? AND user_id = ?");
    $stmt_client->execute([$first_client_id, $user_id]);
    $client_data = $stmt_client->fetch(PDO::FETCH_ASSOC);
    $invoice_currency = $client_data['default_currency'] ?? 'BRL';

    $pdo->beginTransaction();

    $invoice_number = generateInvoiceNumber($user_id);
    $invoice_date = date('Y-m-d');
    $due_date = date('Y-m-d', strtotime('+30 days'));

    $total_subtotal_items = 0;
    $invoice_notes_summary = []; // Coletar notas para o campo 'notes' da fatura principal
    $invoice_items_data = [];

    foreach ($projects_to_invoice as $project) {
    $total_subtotal_items += $project['total_amount'];
    $invoice_notes_summary[] = "- PO: " . ($project['po_number'] ?: 'N/A') . " - " . $project['title'];
    
    $item_description = $project['title'];
    if (!empty($project['po_number'])) {
    $item_description = "PO: " . $project['po_number'] . " - " . $item_description;
    }
    $item_description .= " (" . $dash_config['languages'][$project['source_language']] . " → " . $dash_config['languages'][$project['target_language']] . ", " . $dash_config['service_types'][$project['service_type']] . ")";

    if ($project['word_count'] > 0 && $project['rate_per_word'] > 0) {
    $item_description .= " | Palavras: " . number_format($project['word_count'], 0, ',', '.') . " | Taxa/Palavra: " . formatCurrency($project['rate_per_word'], $project['currency']);
    } elseif ($project['character_count'] > 0 && $project['rate_per_character'] > 0) {
    $item_description .= " | Caracteres: " . number_format($project['character_count'], 0, ',', '.') . " | Taxa/Caractere: " . formatCurrency($project['rate_per_character'], $project['currency']);
    }

    $invoice_items_data[] = [
    'project_id' => $project['id'],
    'description' => $item_description,
    'quantity' => 1.00,
    'unit_price' => $project['total_amount'],
    'total_price' => $project['total_amount']
    ];
    }
    $invoice_notes = "Fatura gerada automaticamente para os projetos: \n" . implode("\n", $invoice_notes_summary);


    $final_tax_amount = $total_subtotal_items * ($tax_rate / 100);
    $final_total_amount = $total_subtotal_items + $final_tax_amount;

    $stmt_invoice = $pdo->prepare("INSERT INTO dash_invoices (user_id, client_id, invoice_number, issue_date, due_date, subtotal, tax_rate, tax_amount, total_amount, currency, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $result_invoice = $stmt_invoice->execute([
    $user_id,
    $first_client_id,
    $invoice_number,
    $invoice_date,
    $due_date,
    $total_subtotal_items,
    $tax_rate,
    $final_tax_amount,
    $final_total_amount,
    $invoice_currency,
    'sent',
    $invoice_notes
    ]);

    if (!$result_invoice) {
    $pdo->rollBack();
    $error = 'Erro ao inserir fatura principal.';
    break;
    }

    $new_invoice_id = $pdo->lastInsertId();

    $stmt_item = $pdo->prepare("INSERT INTO dash_invoice_items (invoice_id, project_id, description, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($invoice_items_data as $item) {
    $result_item = $stmt_item->execute([
    $new_invoice_id,
    $item['project_id'],
    $item['description'],
    $item['quantity'],
    $item['unit_price'],
    $item['total_price']
    ]);
    if (!$result_item) {
    $pdo->rollBack();
    $error = 'Erro ao inserir itens da fatura.';
    break 2;
    }
    }

    $update_project_status_placeholders = implode(',', array_fill(0, count($selected_project_ids), '?'));
    $stmt_update_projects = $pdo->prepare("UPDATE dash_projects SET status = 'completed', completed_date = CURDATE() WHERE id IN ($update_project_status_placeholders) AND user_id = ?");
    $update_params = array_merge($selected_project_ids, [$user_id]);
    $stmt_update_projects->execute($update_params);

    $pdo->commit();
    $message = 'Fatura (' . $invoice_number . ') gerada para os projetos selecionados!';

    } catch (PDOException $e) {
    $pdo->rollBack();
    error_log("PDOException in generate_invoice_multiple: " . $e->getMessage());
    $error = 'Erro de banco de dados ao gerar fatura: ' . $e->getMessage();
    } catch (Exception $e) {
    $pdo->rollBack();
    error_log("Exception in generate_invoice_multiple: " . $e->getMessage());
    $error = 'Erro inesperado ao gerar fatura: ' . $e->getMessage();
    }
    break;
    }
    }
}

// Obter lista de clientes para o dropdown (incluindo a moeda padrão)
$stmt = $pdo->prepare("SELECT id, company, default_currency FROM dash_clients WHERE user_id = ? ORDER BY company ASC");
$stmt->execute([$user_id]);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter lista de projetos concluídos para o dropdown de itens da fatura (agora também para seleção múltipla)
// Inclui campos para exibir na descrição do item da fatura
$stmt = $pdo->prepare("SELECT p.id, p.title AS project_name, p.total_amount, c.company AS company_name, p.currency, p.po_number, p.word_count, p.rate_per_word, p.character_count, p.rate_per_character, p.source_language, p.target_language, p.service_type FROM dash_projects p LEFT JOIN dash_clients c ON p.client_id = c.id WHERE p.user_id = ? AND p.status = 'completed' ORDER BY p.title ASC");
$stmt->execute([$user_id]);
$completed_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Obter lista de faturas
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$where_clause = "WHERE i.user_id = ?";
$params = [$user_id];

if ($search) {
    $where_clause .= " AND (i.invoice_number LIKE ? OR c.company LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status_filter) {
    $where_clause .= " AND i.status = ?";
    $params[] = $status_filter;
}

$stmt = $pdo->prepare("SELECT i.*, i.issue_date AS invoice_date, c.company AS company_name, c.name AS contact_name FROM dash_invoices i LEFT JOIN dash_clients c ON i.client_id = c.id $where_clause ORDER BY i.created_at DESC");
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter configurações do usuário
$user_settings = getUserSettings($user_id);

include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
    <div class="hero-content">
    <h1><i class="fas fa-file-invoice"></i> Gerenciar faturas</h1>
    <p>Crie faturas e controle pagamentos</p>
    <a href="index.php" class="cta-btn">
    <i class="fas fa-arrow-left"></i> Voltar ao Dashboard
    </a>
    </div>
    </div>

    <?php if ($message): ?>
    <div class="alert-success">
    <i class="fas fa-check-circle"></i>
    <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert-error">
    <i class="fas fa-exclamation-triangle"></i>
    <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <div class="video-card">
    <h2><i class="fas fa-plus-circle"></i> Criar fatura</h2>
    
    <form method="POST" class="vision-form" id="invoiceForm">
    <input type="hidden" name="action" value="add_invoice">
    
    <div class="form-grid">
    <div class="form-group">
    <label for="client_id"><i class="fas fa-user"></i> Cliente *</label>
    <select name="client_id" id="client_id" required>
    <option value="">Selecione um cliente</option>
    <?php foreach ($clients as $client): ?>
    <option value="<?php echo $client['id']; ?>">
    <?php echo htmlspecialchars($client['company_name']); ?>
    </option>
    <?php endforeach; ?>
    </select>
    </div>
    
    <div class="form-group">
    <label for="invoice_date"><i class="fas fa-calendar"></i> Data de emissão *</label>
    <input type="date" name="invoice_date" id="invoice_date" required
    value="<?php echo date('Y-m-d'); ?>">
    </div>
    
    <div class="form-group">
    <label for="due_date"><i class="fas fa-calendar-check"></i> Data de vencimento *</label>
    <input type="date" name="due_date" id="due_date" required
    value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
    </div>
    
    <div class="form-group">
    <label for="subtotal"><i class="fas fa-money-bill-wave"></i> Subtotal (R$) *</label>
    <input type="number" name="subtotal" id="subtotal" required min="0" step="0.01"
    onchange="calculateInvoiceTotal()">
    </div>
    
    <div class="form-group">
    <label for="tax_rate"><i class="fas fa-percentage"></i> Imposto (%)</label>
    <input type="number" name="tax_rate" id="tax_rate" min="0" step="0.01"
    value="<?php echo $user_settings['default_tax_rate'] ?? 0; ?>"
    onchange="calculateInvoiceTotal()">
    </div>
    
    <div class="form-group">
    <label for="total_display"><i class="fas fa-calculator"></i> Total (R$)</label>
    <input type="text" id="total_display" readonly>
    </div>
    
    <div class="form-group">
    <label for="status"><i class="fas fa-flag"></i> Status</label>
    <select name="status" id="status">
    <option value="draft">Rascunho</option>
    <option value="sent" selected>Enviada</option>
    <option value="paid">Paga</option>
    </select>
    </div>
    
    <div class="form-group form-group-wide">
    <label for="notes"><i class="fas fa-sticky-note"></i> Observações</label>
    <textarea name="notes" id="notes" rows="3"></textarea>
    </div>
    </div>
    
    <div class="form-section">
    <h3><i class="fas fa-list"></i> Itens da fatura</h3>
    <div id="invoice-items">
    <div class="form-grid invoice-item">
    <div class="form-group">
    <label><i class="fas fa-project-diagram"></i> Projeto (Opcional)</label>
    <select name="items[0][project_id]">
    <option value="">Selecione um projeto</option>
    <?php foreach ($completed_projects as $project): ?>
    <option value="<?php echo $project['id']; ?>" data-amount="<?php echo $project['total_amount']; ?>" data-currency="<?php echo $project['currency']; ?>">
    <?php echo htmlspecialchars($project['project_name'] . ' - ' . $project['company_name']); ?>
    </option>
    <?php endforeach; ?>
    </select>
    </div>
    <div class="form-group">
    <label><i class="fas fa-edit"></i> Descrição *</label>
    <input type="text" name="items[0][description]" required>
    </div>
    <div class="form-group">
    <label><i class="fas fa-sort-numeric-up"></i> Quantidade</label>
    <input type="number" name="items[0][quantity]" min="1" step="0.01" value="1"
    onchange="calculateItemTotal(this)">
    </div>
    <div class="form-group">
    <label><i class="fas fa-tag"></i> Preço unitário (R$)</label>
    <input type="number" name="items[0][unit_price]" min="0" step="0.01"
    onchange="calculateItemTotal(this)">
    </div>
    <div class="form-group">
    <label><i class="fas fa-calculator"></i> Total</label>
    <input type="text" class="item-total" readonly>
    </div>
    </div>
    </div>
    
    <button type="button" onclick="addInvoiceItem()" class="page-btn">
    <i class="fas fa-plus"></i> Adicionar item
    </button>
    </div>
    
    <div class="form-actions">
    <button type="submit" class="cta-btn">
    <i class="fas fa-save"></i> Criar fatura
    </button>
    </div>
    </form>
    </div>

    <div class="video-card">
    <h2><i class="fas fa-layer-group"></i> Gerar fatura para diversos projetos</h2>
    <?php if (empty($completed_projects)): ?>
    <div class="alert-warning">
    <i class="fas fa-info-circle"></i>
    Nenhum projeto concluído disponível para faturar.
    </div>
    <?php else: ?>
    <div class="alert-info">
    <i class="fas fa-exclamation-circle"></i>
    Só é possível gerar fatura múltipla se todos os projetos selecionados pertencerem ao mesmo cliente.
    </div>
    <form method="POST" onsubmit="return confirm('Gerar fatura para os projetos selecionados?')">
    <input type="hidden" name="action" value="generate_invoice_multiple">
    
    <div class="table-responsive">
    <table class="data-table">
    <thead>
    <tr>
    <th><i class="fas fa-check-square"></i></th>
    <th><i class="fas fa-project-diagram"></i> Projeto</th>
    <th><i class="fas fa-user"></i> Cliente</th>
    <th><i class="fas fa-file-alt"></i> PO</th>
    <th><i class="fas fa-money-bill-wave"></i> Valor</th>
    <th><i class="fas fa-tasks"></i> Seleção</th>
    </tr>
    </thead>
    <tbody>
    <?php 
    $current_client = null;
    foreach ($completed_projects as $project): 
    if ($current_client !== $project['company_name']):
    if ($current_client !== null) { echo "</tr>"; }
    $current_client = $project['company_name'];
    ?>
    <tr class="client-row">
    <td colspan="6" style="background-color: rgba(255,255,255,0.1); padding: 10px;">
    <button type="button" class="page-btn" onclick="selectClientProjects('<?php echo htmlspecialchars($current_client); ?>')">
    <i class="fas fa-check-double"></i> Selecionar todos de <?php echo htmlspecialchars($current_client); ?>
    </button>
    </td>
    </tr>
    <?php endif; ?>
    <tr data-client="<?php echo htmlspecialchars($project['company_name']); ?>">
    <td>
    <input type="checkbox" name="selected_projects[]" value="<?php echo $project['id']; ?>">
    </td>
    <td><?php echo htmlspecialchars($project['project_name']); ?></td>
    <td><?php echo htmlspecialchars($project['company_name']); ?></td>
    <td><?php echo htmlspecialchars($project['po_number'] ?? ''); ?></td>
    <td><?php echo formatCurrency($project['total_amount'], $project['currency']); ?></td>
    <td></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
    </div>
    <div class="form-actions">
    <button type="submit" class="cta-btn">
    <i class="fas fa-file-invoice"></i> Gerar fatura para selecionados
    </button>
    </div>
    </form>
    <?php endif; ?>
    </div>


    <div class="video-card">
    <div class="card-header">
    <h2><i class="fas fa-list"></i> Lista de faturas</h2>
    
    <div class="search-filters">
    <form method="GET" class="search-form">
    <div class="search-group">
    <input type="text" name="search" placeholder="Buscar faturas..."
    value="<?php echo htmlspecialchars($search); ?>">
    <select name="status">
    <option value="">Todos os status</option>
    <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Rascunho</option>
    <option value="sent" <?php echo $status_filter == 'sent' ? 'selected' : ''; ?>>Enviada</option>
    <option value="paid" <?php echo $status_filter == 'paid' ? 'selected' : ''; ?>>Paga</option>
    <option value="overdue" <?php echo $status_filter == 'overdue' ? 'selected' : ''; ?>>Vencida</option>
    <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelada</option>
    </select>
    <button type="submit" class="page-btn">
    <i class="fas fa-search"></i>
    </button>
    <?php if ($search || $status_filter): ?>
    <a href="invoices.php" class="page-btn">
    <i class="fas fa-times"></i>
    </a>
    <?php endif; ?>
    </div>
    </form>
    </div>
    </div>

    <?php if (empty($invoices)): ?>
    <div class="alert-warning">
    <i class="fas fa-info-circle"></i>
    <?php echo ($search || $status_filter) ? 'Nenhuma fatura encontrada com os critérios de busca.' : 'Nenhuma fatura criada ainda.'; ?>
    </div>
    <?php else: ?>
    <div class="table-responsive">
    <table class="data-table">
    <thead>
    <tr>
    <th><i class="fas fa-hashtag"></i> Número</th>
    <th><i class="fas fa-user"></i> Cliente</th>
    <th><i class="fas fa-calendar"></i> Data</th>
    <th><i class="fas fa-calendar-check"></i> Vencimento</th>
    <th><i class="fas fa-money-bill-wave"></i> Valor</th>
    <th><i class="fas fa-flag"></i> Status</th>
    <th><i class="fas fa-cogs"></i> Ações</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($invoices as $invoice): ?>
    <?php
    // Verificar se a fatura está vencida
    $is_overdue = ($invoice['status'] == 'sent' && strtotime($invoice['due_date']) < time());
    ?>
    <tr>
    <td>
    <span class="text-primary"><?php echo htmlspecialchars($invoice['invoice_number']); ?></span>
    </td>
    <td><?php echo htmlspecialchars($invoice['company_name']); ?></td>
    <td><?php echo date('d/m/Y', strtotime($invoice['invoice_date'])); ?></td>
    <td>
    <span class="<?php echo $is_overdue ? 'text-danger' : ''; ?>">
    <?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?>
    </span>
    </td>
    <td><?php echo formatCurrency($invoice['total_amount'], $invoice['currency'] ?? 'BRL'); ?></td>
    <td>
    <span class="status-badge status-<?php 
    if ($is_overdue) {
    echo 'overdue';
    } else {
    echo $invoice['status'];
    }
    ?>">
    <?php 
    if ($is_overdue) {
    echo 'Vencida';
    } else {
    $status_labels = [
    'draft' => 'Rascunho',
    'sent' => 'Enviada',
    'paid' => 'Paga',
    'cancelled' => 'Cancelada'
    ];
    echo $status_labels[$invoice['status']] ?? $invoice['status'];
    }
    ?>
    </span>
    </td>
    <td>
    <div class="action-buttons">
    <button onclick="openStatusModal(<?php echo $invoice['id']; ?>, '<?php echo $invoice['status']; ?>')" 
    class="page-btn" title="Editar Status">
    <i class="fas fa-edit"></i>
    </button>
    <a href="view_invoice.php?id=<?php echo $invoice['id']; ?>" target="_blank"
    class="page-btn" title="Visualizar">
    <i class="fas fa-eye"></i>
    </a>
    <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja enviar esta fatura por e-mail?')">
    <input type="hidden" name="action" value="send_invoice_email">
    <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">
    <button type="submit" class="page-btn" title="Enviar por E-mail">
    <i class="fas fa-envelope"></i>
    </button>
    </form>
    <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir esta fatura?')">
    <input type="hidden" name="action" value="delete_invoice">
    <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">
    <button type="submit" class="page-btn btn-danger" title="Excluir">
    <i class="fas fa-trash"></i>
    </button>
    </form>
    </div>
    </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
    </div>
    <?php endif; ?>
    </div>
</div>

<!-- Status Modal -->
<div id="statusModal" class="modal-overlay">
    <div class="modal-content">
    <div class="modal-header">
    <h3><i class="fas fa-edit"></i> Atualizar status da fatura</h3>
    <button type="button" onclick="closeStatusModal()" class="close-btn">
    <i class="fas fa-times"></i>
    </button>
    </div>
    
    <form method="POST" id="statusForm">
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="invoice_id" id="modal_invoice_id">
    
    <div class="form-group">
    <label for="modal_status"><i class="fas fa-flag"></i> Status</label>
    <select name="status" id="modal_status" required onchange="togglePaymentFields()">
    <option value="draft">Rascunho</option>
    <option value="sent">Enviada</option>
    <option value="paid">Paga</option>
    <option value="cancelled">Cancelada</option>
    </select>
    </div>
    
    <div id="payment_fields" style="display: none;">
    <div class="form-group">
    <label for="payment_date"><i class="fas fa-calendar"></i> Data do pagamento</label>
    <input type="date" name="payment_date" id="payment_date">
    </div>
    
    <div class="form-group">
    <label for="payment_method"><i class="fas fa-credit-card"></i> Método de pagamento</label>
    <select name="payment_method" id="payment_method">
    <option value="">Selecione...</option>
    <option value="bank_transfer">Transferência bancária</option>
    <option value="credit_card">Cartão de crédito</option>
    <option value="debit_card">Cartão de débito</option>
    <option value="pix">PIX</option>
    <option value="check">Cheque</option>
    <option value="cash">Dinheiro</option>
    <option value="other">Outro</option>
    </select>
    </div>
    </div>
    
    <div class="form-actions">
    <button type="submit" class="cta-btn">
    <i class="fas fa-save"></i> Atualizar status
    </button>
    <button type="button" onclick="closeStatusModal()" class="page-btn">
    <i class="fas fa-times"></i> Cancelar
    </button>
    </div>
    </form>
    </div>
</div>

<script>
let itemCounter = 1;

function calculateInvoiceTotal() {
    const subtotal = parseFloat(document.getElementById('subtotal').value) || 0;
    const taxRate = parseFloat(document.getElementById('tax_rate').value) || 0;
    const taxAmount = subtotal * (taxRate / 100);
    const total = subtotal + taxAmount;
    
    document.getElementById('total_display').value = 'R$ ' + total.toFixed(2).replace('.', ',');
}

function calculateItemTotal(element) {
    const row = element.closest('.invoice-item');
    const quantity = parseFloat(row.querySelector('input[name*="[quantity]"]').value) || 0;
    const unitPrice = parseFloat(row.querySelector('input[name*="[unit_price]"]').value) || 0;
    const total = quantity * unitPrice;
    
    row.querySelector('.item-total').value = 'R$ ' + total.toFixed(2).replace('.', ',');
    updateSubtotal();
}

function updateSubtotal() {
    let subtotal = 0;
    document.querySelectorAll('.invoice-item').forEach(function(row) {
    const quantity = parseFloat(row.querySelector('input[name*="[quantity]"]').value) || 0;
    const unitPrice = parseFloat(row.querySelector('input[name*="[unit_price]"]').value) || 0;
    subtotal += quantity * unitPrice;
    });
    
    document.getElementById('subtotal').value = subtotal.toFixed(2);
    calculateInvoiceTotal();
}

function addInvoiceItem() {
    const container = document.getElementById('invoice-items');
    const newItem = container.querySelector('.invoice-item').cloneNode(true);
    
    // Update form field names
    const inputs = newItem.querySelectorAll('input, select');
    inputs.forEach(function(input) {
    if (input.name) {
    input.name = input.name.replace(/\[\d+\]/, '[' + itemCounter + ']');
    }
    if (input.type !== 'hidden') {
    input.value = input.type === 'number' && input.name.includes('quantity') ? '1' : '';
    }
    });
    
    container.appendChild(newItem);
    itemCounter++;
}

function openStatusModal(invoiceId, currentStatus) {
    document.getElementById('modal_invoice_id').value = invoiceId;
    document.getElementById('modal_status').value = currentStatus;
    document.getElementById('statusModal').style.display = 'flex';
    togglePaymentFields();
}

function closeStatusModal() {
    document.getElementById('statusModal').style.display = 'none';
}

function togglePaymentFields() {
    const status = document.getElementById('modal_status').value;
    const paymentFields = document.getElementById('payment_fields');
    
    if (status === 'paid') {
    paymentFields.style.display = 'block';
    document.getElementById('payment_date').value = new Date().toISOString().split('T')[0];
    } else {
    paymentFields.style.display = 'none';
    }
}

// Close modal when clicking outside
document.getElementById('statusModal').addEventListener('click', function(e) {
    if (e.target === this) {
    closeStatusModal();
    }
});

// Calculate total on page load
document.addEventListener('DOMContentLoaded', function() {
    calculateInvoiceTotal();
});

// Function to select all projects from a specific client
function selectClientProjects(clientName) {
    const rows = document.querySelectorAll('tr[data-client="'+clientName+'"] input[type="checkbox"]');
    rows.forEach(cb => cb.checked = true);
}
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>