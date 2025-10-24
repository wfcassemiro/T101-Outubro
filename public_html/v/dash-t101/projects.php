<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';

// Verificar se o usuário está logado
if (!isLoggedIn()) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$page_title = 'Projetos - Dash-T101';
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Processar adição de novo serviço
$new_service_added = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_service_type']) && !empty($_POST['new_service_type'])) {
    $new_service = trim($_POST['new_service_type']);
    if (!empty($new_service)) {
        if (!isset($_SESSION['custom_services'])) {
            $_SESSION['custom_services'] = [];
        }
        $normalized_new_service = strtolower($new_service);
        $normalized_custom_services = array_map('strtolower', $_SESSION['custom_services']);

        if (!in_array($normalized_new_service, $normalized_custom_services)) {
            $_SESSION['custom_services'][] = $new_service;
            $new_service_added = $new_service;
            $message = "Novo serviço adicionado.";
        } else {
            $new_service_added = $new_service;
            $message = "Serviço '$new_service' já existe na lista.";
        }
    }
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_client':
                try {
                    $company_name = trim($_POST['company_name']);
                    $default_currency = $_POST['default_currency'];

                    if (!empty($company_name)) {
                        $stmt = $pdo->prepare("INSERT INTO dash_clients (user_id, company, default_currency) VALUES (?, ?, ?)");
                        $stmt->execute([$user_id, $company_name, $default_currency]);
                        
                        $_SESSION['last_added_client_id'] = $pdo->lastInsertId();
                        $_SESSION['temp_message'] = "Cliente adicionado.";
                    } else {
                        $_SESSION['temp_error'] = "O nome da empresa não pode estar vazio.";
                    }
                } catch (PDOException $e) {
                    $_SESSION['temp_error'] = 'Erro ao adicionar cliente: ' . $e->getMessage();
                }
                header("Location: projects_b.php");
                exit;
                break;

            case 'add_project':
                try {
                    $unit_type = $_POST['unit_type'] ?? 'palavra';
                    $quantity = floatval($_POST['quantity'] ?? 0);
                    $rate_per_unit = floatval($_POST['rate_per_unit'] ?? 0);
                    $daily_target = floatval($_POST['daily_target'] ?? 0);
                    $lauda_size = intval($_POST['lauda_size'] ?? 0);
                    $lauda_unit = $_POST['lauda_unit'] ?? 'palavras';

                    $negotiated_amount = floatval(str_replace(',', '.', $_POST['negotiated_value'] ?? '0'));
                    $calculated_amount = $quantity * $rate_per_unit;
                    $total_amount = ($negotiated_amount > 0) ? $negotiated_amount : $calculated_amount;

                    $stmt = $pdo->prepare("INSERT INTO dash_projects (user_id, client_id, title, po_number, description, source_language, target_language, service_type, word_count, rate_per_word, total_amount, currency, status, priority, start_date, deadline, payment_date, notes, daily_word_target, unit_type, lauda_size, lauda_unit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $result = $stmt->execute([
                        $user_id, $_POST['client_id'], $_POST['project_name'], $_POST['po_number'] ?? null, $_POST['description'] ?? '', $_POST['source_lang'], $_POST['target_lang'], $_POST['service_type'], $quantity, $rate_per_unit, $total_amount, $_POST['currency'], $_POST['status'], $_POST['priority'], $_POST['start_date'] ?: null, $_POST['due_date'] ?: null, $_POST['payment_date'] ?: null, $_POST['notes'] ?? '', $daily_target, $unit_type, $lauda_size, $lauda_unit
                    ]);

                    if ($result) { $message = 'Projeto adicionado.'; } else { $error = 'Erro ao adicionar projeto.'; }
                } catch (PDOException $e) { $error = 'Erro: ' . $e->getMessage(); }
                break;

            case 'edit_project':
                try {
                    $unit_type = $_POST['unit_type'] ?? 'palavra';
                    $quantity = floatval($_POST['quantity'] ?? 0);
                    $rate_per_unit = floatval($_POST['rate_per_unit'] ?? 0);
                    $daily_target = floatval($_POST['daily_target'] ?? 0);
                    $lauda_size = intval($_POST['lauda_size'] ?? 0);
                    $lauda_unit = $_POST['lauda_unit'] ?? 'palavras';

                    $negotiated_amount = floatval(str_replace(',', '.', $_POST['negotiated_value'] ?? '0'));
                    $calculated_amount = $quantity * $rate_per_unit;
                    $total_amount = ($negotiated_amount > 0) ? $negotiated_amount : $calculated_amount;

                    $stmt = $pdo->prepare("UPDATE dash_projects SET client_id = ?, title = ?, po_number = ?, description = ?, source_language = ?, target_language = ?, service_type = ?, word_count = ?, rate_per_word = ?, total_amount = ?, currency = ?, status = ?, priority = ?, start_date = ?, deadline = ?, payment_date = ?, notes = ?, daily_word_target = ?, unit_type = ?, lauda_size = ?, lauda_unit = ? WHERE id = ? AND user_id = ?");
                    $result = $stmt->execute([
                        $_POST['client_id'], $_POST['project_name'], $_POST['po_number'] ?? null, $_POST['description'] ?? '', $_POST['source_lang'], $_POST['target_lang'], $_POST['service_type'], $quantity, $rate_per_unit, $total_amount, $_POST['currency'], $_POST['status'], $_POST['priority'], $_POST['start_date'] ?: null, $_POST['due_date'] ?: null, $_POST['payment_date'] ?: null, $_POST['notes'] ?? '', $daily_target, $unit_type, $lauda_size, $lauda_unit, $_POST['project_id'], $user_id
                    ]);

                    if ($result) { $message = 'Projeto atualizado.'; } else { $error = 'Erro ao atualizar projeto.'; }
                } catch (PDOException $e) { $error = 'Erro: ' . $e->getMessage(); }
                break;

            case 'delete_project':
                try {
                    $stmt = $pdo->prepare("DELETE FROM dash_projects WHERE id = ? AND user_id = ?");
                    $result = $stmt->execute([$_POST['project_id'], $user_id]);
                    if ($result) { $message = 'Projeto excluído.'; } else { $error = 'Erro ao excluir projeto.'; }
                } catch (PDOException $e) { $error = 'Erro: ' . $e->getMessage(); }
                break;

            case 'complete_project':
                try {
                    $stmt = $pdo->prepare("UPDATE dash_projects SET status = 'completed', completed_date = CURDATE() WHERE id = ? AND user_id = ?");
                    $result = $stmt->execute([$_POST['project_id'], $user_id]);
                    if ($result) { $message = 'Projeto marcado como concluído.'; } else { $error = 'Erro ao marcar projeto como concluído.'; }
                } catch (PDOException $e) { $error = 'Erro: ' . $e->getMessage(); }
                break;

            case 'generate_invoice':
                try {
                    $invoice_number = generateInvoiceFromProject($user_id, $_POST['project_id']);
                    if ($invoice_number) { $message = 'Fatura gerada! Número: ' . $invoice_number; } else { $error = 'Erro ao gerar fatura a partir do projeto.'; }
                } catch (PDOException $e) { $error = 'Erro: ' . $e->getMessage(); }
                break;
        }
    }
}

if(isset($_SESSION['temp_message'])) {
    $message = $_SESSION['temp_message'];
    unset($_SESSION['temp_message']);
}
if(isset($_SESSION['temp_error'])) {
    $error = $_SESSION['temp_error'];
    unset($_SESSION['temp_error']);
}


// Obter lista de clientes
$stmt = $pdo->prepare("SELECT id, company AS company_name, default_currency FROM dash_clients WHERE user_id = ? ORDER BY company ASC");
$stmt->execute([$user_id]);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter lista de projetos
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$where_clause = "WHERE p.user_id = ?";
$params = [$user_id];

if ($search) {
    $where_clause .= " AND (p.title LIKE ? OR c.company LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}
if ($status_filter) {
    $where_clause .= " AND p.status = ?";
    $params[] = $status_filter;
}
$stmt = $pdo->prepare("SELECT p.*, p.title AS project_name, p.description AS project_description, p.po_number, p.daily_word_target, c.company AS company_name, c.name AS contact_name FROM dash_projects p LEFT JOIN dash_clients c ON p.client_id = c.id $where_clause ORDER BY p.created_at DESC");
$stmt->execute($params);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter projeto para edição se solicitado
$edit_project = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT p.*, p.title AS project_name, p.description AS project_description, p.po_number, p.daily_word_target, c.company AS company_name, c.name AS contact_name, p.unit_type, p.lauda_size, p.lauda_unit, p.payment_date FROM dash_projects p LEFT JOIN dash_clients c ON p.client_id = c.id WHERE p.id = ? AND p.user_id = ?");
    $stmt->execute([$_GET['edit'], $user_id]);
    $edit_project = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Linha do tempo
$display_range_days = 31;
$today_display_offset_days = 5;

date_default_timezone_set('America/Sao_Paulo');
$today_ts = strtotime(date('Y-m-d'));

$min_display_date = strtotime("-$today_display_offset_days days", $today_ts);
$max_display_date = strtotime("+" . ($display_range_days - $today_display_offset_days - 1) . " days", $today_ts);
$today_pos_on_global_line_percent = ($today_display_offset_days / $display_range_days) * 100;
$total_visible_duration_seconds = $max_display_date - $min_display_date;

// Filtra os projetos para incluir apenas aqueles com datas válidas para a linha do tempo
$timeline_projects = array_filter($projects, function($p) use ($min_display_date, $max_display_date) {
    if (empty($p['start_date']) || empty($p['deadline'])) { return false; }
    $start_ts = strtotime($p['start_date']);
    $deadline_ts = strtotime($p['deadline']);
    return $deadline_ts >= $min_display_date && $start_ts <= $max_display_date;
});

$page_title = "Projetos - Dash-T101";
$page_description = "Gerencie seus projetos de tradução e acompanhe o progresso.";
include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    <?php if ($message): ?><div class="alert-success"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert-error"><i class="fas fa-exclamation-triangle"></i><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="video-card">
        <h2><i class="fas fa-plus-circle"></i> Adicionar novo projeto</h2>
        <form method="POST" class="vision-form-refined" action="projects_b.php">
            <input type="hidden" name="action" value="<?php echo $edit_project ? 'edit_project' : 'add_project'; ?>">
            <?php if ($edit_project): ?><input type="hidden" name="project_id" value="<?php echo $edit_project['id']; ?>"><?php endif; ?>
            <div class="form-row">
                <div class="form-group form-group-full">
                    <label for="project_name"><i class="fas fa-folder-plus"></i> Nome do projeto *</label>
                    <input type="text" id="project_name" name="project_name" required value="<?php echo htmlspecialchars($edit_project['project_name'] ?? ''); ?>" class="vision-input">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="client_id"><i class="fas fa-user"></i> Cliente *</label>
                    <div class="client-container">
                        <select id="client_id" name="client_id" required class="vision-select">
                            <option value="">Selecione um cliente</option>
                            <?php
                            $last_added_client_id = $_SESSION['last_added_client_id'] ?? null;
                            if ($last_added_client_id) {
                                unset($_SESSION['last_added_client_id']);
                            }
                            foreach ($clients as $client): 
                                $isSelected = ($edit_project && $edit_project['client_id'] == $client['id']) || ($last_added_client_id == $client['id']);
                            ?>
                                <option value="<?php echo $client['id']; ?>" data-currency="<?php echo htmlspecialchars($client['default_currency']); ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client['company_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="add-service-btn" onclick="showAddClientModal()"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="source_lang"><i class="fas fa-language"></i> Idioma de origem *</label>
                    <select id="source_lang" name="source_lang" required class="vision-select">
                        <option value="">Selecione</option>
                        <?php $languages = ['pt' => 'Português', 'en' => 'Inglês', 'es' => 'Espanhol', 'fr' => 'Francês', 'de' => 'Alemão', 'it' => 'Italiano', 'ja' => 'Japonês', 'ko' => 'Coreano', 'zh' => 'Chinês', 'ru' => 'Russo'];
                        foreach ($languages as $code => $name): ?>
                            <option value="<?php echo $code; ?>" <?php echo ($edit_project && $edit_project['source_language'] == $code) ? 'selected' : ''; ?>><?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="target_lang"><i class="fas fa-language"></i> Idioma de destino *</label>
                    <select id="target_lang" name="target_lang" required class="vision-select">
                        <option value="">Selecione</option>
                        <?php foreach ($languages as $code => $name): ?>
                            <option value="<?php echo $code; ?>" <?php echo ($edit_project && $edit_project['target_language'] == $code) ? 'selected' : ''; ?>><?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="service_type"><i class="fas fa-cogs"></i> Tipo de serviço *</label>
                    <div class="service-type-container">
                        <select id="service_type" name="service_type" required class="vision-select">
                            <option value="traducao" <?php echo ($edit_project && $edit_project['service_type'] == 'traducao') ? 'selected' : ''; ?>>Tradução</option>
                            <option value="revisao" <?php echo ($edit_project && $edit_project['service_type'] == 'revisao') ? 'selected' : ''; ?>>Revisão</option>
                            <option value="localizacao" <?php echo ($edit_project && $edit_project['service_type'] == 'localizacao') ? 'selected' : ''; ?>>Localização</option>
                            <option value="interpretacao" <?php echo ($edit_project && $edit_project['service_type'] == 'interpretacao') ? 'selected' : ''; ?>>Interpretação</option>
                            <option value="transcricao" <?php echo ($edit_project && $edit_project['service_type'] == 'transcricao') ? 'selected' : ''; ?>>Transcrição</option>
                            <?php if (isset($_SESSION['custom_services'])): foreach ($_SESSION['custom_services'] as $service): ?>
                                <option value="<?php echo htmlspecialchars($service); ?>" <?php if ($edit_project && $edit_project['service_type'] == $service) { echo 'selected'; } elseif ($new_service_added && $new_service_added == $service) { echo 'selected'; } ?>>
                                    <?php echo htmlspecialchars($service); ?>
                                </option>
                            <?php endforeach; endif; ?>
                        </select>
                        <button type="button" class="add-service-btn" onclick="showAddServiceModal()"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="po_number"><i class="fas fa-hashtag"></i> Número da PO</label>
                    <input type="text" id="po_number" name="po_number" value="<?php echo htmlspecialchars($edit_project['po_number'] ?? ''); ?>" class="vision-input">
                </div>
                <div class="form-group">
                    <label for="unit_type"><i class="fas fa-ruler"></i> Unidade de medida *</label>
                    <select id="unit_type" name="unit_type" required class="vision-select" onchange="handleUnitChange()">
                        <option value="palavra" <?php echo ($edit_project && $edit_project['unit_type'] == 'palavra') ? 'selected' : ''; ?>>Palavra</option>
                        <option value="caracteres" <?php echo ($edit_project && $edit_project['unit_type'] == 'caracteres') ? 'selected' : ''; ?>>Caracteres</option>
                        <option value="hora" <?php echo ($edit_project && $edit_project['unit_type'] == 'hora') ? 'selected' : ''; ?>>Hora</option>
                        <option value="minuto" <?php echo ($edit_project && $edit_project['unit_type'] == 'minuto') ? 'selected' : ''; ?>>Minuto</option>
                        <option value="lauda" <?php echo ($edit_project && $edit_project['unit_type'] == 'lauda') ? 'selected' : ''; ?>>Lauda</option>
                        <option value="diaria" <?php echo ($edit_project && $edit_project['unit_type'] == 'diaria') ? 'selected' : ''; ?>>Diária</option>
                    </select>
                </div>
            </div>
            <div class="form-row" id="lauda-config-row" style="display: none;">
                <div class="form-group">
                    <label for="lauda_size"><i class="fas fa-text-width"></i> Tamanho da lauda *</label>
                    <input type="number" id="lauda_size" name="lauda_size" value="<?php echo $edit_project['lauda_size'] ?? '1000'; ?>" class="vision-input" min="1">
                </div>
                <div class="form-group">
                    <label for="lauda_unit"><i class="fas fa-ruler-horizontal"></i> Medida da lauda *</label>
                    <select id="lauda_unit" name="lauda_unit" class="vision-select">
                        <option value="palavras" <?php echo ($edit_project && $edit_project['lauda_unit'] == 'palavras') ? 'selected' : ''; ?>>Palavras</option>
                        <option value="caracteres" <?php echo ($edit_project && $edit_project['lauda_unit'] == 'caracteres') ? 'selected' : ''; ?>>Caracteres</option>
                    </select>
                </div>
                <div class="form-group"></div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="quantity" id="quantity_label"><i class="fas fa-sort-numeric-up"></i> Quantidade *</label>
                    <input type="number" id="quantity" name="quantity" step="0.01" value="<?php echo $edit_project['word_count'] ?? ''; ?>" oninput="calculateTotal()" class="vision-input">
                </div>
                <div class="form-group">
                    <label for="rate_per_unit" id="rate_label"><i class="fas fa-dollar-sign"></i> Valor por unidade</label>
                    <input type="number" id="rate_per_unit" name="rate_per_unit" step="0.0001" value="<?php echo $edit_project['rate_per_word'] ?? ''; ?>" oninput="calculateTotal()" class="vision-input">
                </div>
                <div class="form-group">
                    <label for="currency"><i class="fas fa-money-bill-wave"></i> Moeda</label>
                    <select id="currency" name="currency" class="vision-select">
                        <option value="BRL" <?php echo ($edit_project && $edit_project['currency'] == 'BRL') ? 'selected' : ''; ?>>BRL</option>
                        <option value="USD" <?php echo ($edit_project && $edit_project['currency'] == 'USD') ? 'selected' : ''; ?>>USD</option>
                        <option value="EUR" <?php echo ($edit_project && $edit_project['currency'] == 'EUR') ? 'selected' : ''; ?>>EUR</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-calculator"></i> Total calculado</label>
                    <input type="text" id="total_calculated" readonly value="R$ 0,00" class="vision-input vision-input-readonly">
                </div>
                <div class="form-group">
                    <label for="negotiated_value"><i class="fas fa-hand-holding-usd"></i> Valor negociado</label>
                    <input type="number" id="negotiated_value" name="negotiated_value" step="0.01" value="<?php echo $edit_project['total_amount'] ?? ''; ?>" class="vision-input">
                </div>
                <div class="form-group">
                    <label for="daily_target" id="daily_target_label"><i class="fas fa-chart-line"></i> Meta por dia</label>
                    <input type="number" id="daily_target" name="daily_target" step="0.01" value="<?php echo $edit_project['daily_word_target'] ?? ''; ?>" class="vision-input">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="status"><i class="fas fa-tasks"></i> Status</label>
                    <select id="status" name="status" class="vision-select">
                        <option value="pending" <?php echo ($edit_project && $edit_project['status'] == 'pending') ? 'selected' : ''; ?>>Pendente</option>
                        <option value="in_progress" <?php echo ($edit_project && $edit_project['status'] == 'in_progress') ? 'selected' : ''; ?>>Em andamento</option>
                        <option value="completed" <?php echo ($edit_project && $edit_project['status'] == 'completed') ? 'selected' : ''; ?>>Concluído</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="priority"><i class="fas fa-flag"></i> Prioridade</label>
                    <select id="priority" name="priority" class="vision-select">
                        <option value="low" <?php echo ($edit_project && $edit_project['priority'] == 'low') ? 'selected' : ''; ?>>Baixa</option>
                        <option value="medium" <?php echo ($edit_project && $edit_project['priority'] == 'medium') ? 'selected' : ''; ?>>Média</option>
                        <option value="high" <?php echo ($edit_project && $edit_project['priority'] == 'high') ? 'selected' : ''; ?>>Alta</option>
                    </select>
                </div>
                 <div class="form-group"></div> 
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="start_date"><i class="fas fa-calendar"></i> Data de início</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo $edit_project['start_date'] ?? ''; ?>" class="vision-input">
                </div>
                <div class="form-group">
                    <label for="due_date"><i class="fas fa-calendar-alt"></i> Data de entrega</label>
                    <input type="date" id="due_date" name="due_date" value="<?php echo $edit_project['deadline'] ?? ''; ?>" class="vision-input">
                </div>
                <div class="form-group">
                    <label for="payment_date"><i class="fas fa-calendar-check"></i> Data de pagamento</label>
                    <input type="date" id="payment_date" name="payment_date" value="<?php echo $edit_project['payment_date'] ?? ''; ?>" class="vision-input">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group form-group-full">
                    <label for="description"><i class="fas fa-align-left"></i> Descrição do projeto</label>
                    <textarea id="description" name="description" rows="4" class="vision-textarea"><?php echo htmlspecialchars($edit_project['project_description'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="vision-btn vision-btn-primary"><i class="fas fa-save"></i> <?php echo $edit_project ? 'Atualizar projeto' : 'Salvar projeto'; ?></button>
                <?php if ($edit_project): ?><a href="projects_b.php" class="vision-btn vision-btn-secondary"><i class="fas fa-times"></i> Cancelar</a><?php endif; ?>
            </div>
        </form>
    </div>

    <div class="video-card">
        <div class="card-header-with-action">
            <h2><i class="fas fa-chart-line"></i> Linha do tempo dos projetos</h2>
            <button id="btn-open-report-modal" class="report-btn-highlight" type="button">
                <i class="fas fa-file-alt"></i> <span>Gerar relatório</span>
                <span class="glow"></span>
            </button>
        </div>
        <div class="timeline-container">
            <div class="timeline-header">
                <div class="timeline-label"><span class="timeline-title">Projetos</span></div>
                <div class="timeline-bar">
                    <div class="timeline-date-labels-container">
                        <span class="timeline-date-label date-start"><?php echo date('d/m/Y', $min_display_date); ?></span>
                        <span class="timeline-date-label date-today" style="left: <?php echo $today_pos_on_global_line_percent; ?>%;">HOJE (<?php echo date('d/m/Y', $today_ts); ?>)</span>
                        <span class="timeline-date-label date-end"><?php echo date('d/m/Y', $max_display_date); ?></span>
                    </div>
                    <div class="timeline-today-line" style="left: <?php echo $today_pos_on_global_line_percent; ?>%;"></div>
                </div>
            </div>
            
            <?php
            if (empty($timeline_projects)) {
                echo '<div class="timeline-fallback">
                        <img src="https://placehold.co/800x200/2a2a3e/a0a0b0?text=Sem+projetos+no+horizonte...%5CnAproveite+a+calmaria!&font=lato" alt="Nenhum projeto agendado no período">
                        <p>Nenhum projeto com data de início e fim foi encontrado no período visível.</p>
                      </div>';
            } else {
                function interpolateColor($ratio) {
                    $r = (int)(34 + ($ratio * (239 - 34))); $g = (int)(197 - ($ratio * (197 - 68))); $b = (int)(94 - ($ratio * (94 - 68)));
                    return "rgb($r,$g,$b)";
                }

                foreach ($timeline_projects as $project) {
                    $start_ts = strtotime($project['start_date']);
                    $deadline_ts = strtotime($project['deadline']);
                    $visible_start_ts = max($start_ts, $min_display_date);
                    $visible_end_ts = min($deadline_ts, $max_display_date);
                    
                    $project_start_percent = (($visible_start_ts - $min_display_date) / $total_visible_duration_seconds) * 100;
                    $project_width_percent = (($visible_end_ts - $visible_start_ts) / $total_visible_duration_seconds) * 100;

                    $bar_html = '';
                    $label_text = '';

                    if ($project['status'] === 'completed' && !empty($project['completed_date'])) {
                        $completion_ts = strtotime($project['completed_date']);
                        $visible_completion_ts = max($visible_start_ts, min($completion_ts, $visible_end_ts));
                        
                        $total_visible_duration = $visible_end_ts - $visible_start_ts;
                        $green_duration = $visible_completion_ts - $visible_start_ts;
                        
                        $green_fill_percent = ($total_visible_duration > 0) ? ($green_duration / $total_visible_duration) * 100 : 0;
                        $purple_fill_percent = 100 - $green_fill_percent;
                        
                        if($green_fill_percent > 0) {
                             $bar_html .= '<div class="timeline-progress" style="width: ' . $green_fill_percent . '%; background: #22c55e;"></div>';
                        }
                        if($purple_fill_percent > 0) {
                             $bar_html .= '<div class="timeline-progress timeline-progress-completed" style="width: ' . $purple_fill_percent . '%;"></div>';
                        }
                        $label_text = '✔ ' . date('d/m', $completion_ts);

                    } else {
                        $green_fill_percent = 0;
                        $gray_fill_percent = 0;
                        $total_visible_duration = ($visible_end_ts - $visible_start_ts);
                        
                        if ($total_visible_duration > 0) {
                            $elapsed_visible_duration = $today_ts - $visible_start_ts;
                            $green_fill_percent = max(0, min(100, ($elapsed_visible_duration / $total_visible_duration) * 100));
                        } elseif ($today_ts >= $visible_end_ts) {
                            $green_fill_percent = 100;
                        }
                        $gray_fill_percent = 100 - $green_fill_percent;
                        
                        $total_project_duration_for_color = ($deadline_ts - $start_ts) > 0 ? ($deadline_ts - $start_ts) : 1;
                        $progress_ratio_for_color = max(0, min(1, ($today_ts - $start_ts) / $total_project_duration_for_color));
                        $color_gradient_css = "linear-gradient(to right, #22c55e, " . interpolateColor($progress_ratio_for_color) . ")";
                        
                        if($green_fill_percent > 0) {
                             $bar_html .= '<div class="timeline-progress" style="width: ' . $green_fill_percent . '%; background-image:' . $color_gradient_css . ';"></div>';
                        }
                        if($gray_fill_percent > 0) {
                             $bar_html .= '<div class="timeline-progress timeline-progress-gray" style="width: ' . $gray_fill_percent . '%;"></div>';
                        }
                        $label_text = date('d/m', $deadline_ts);
                    }

                    echo '<div class="timeline-project">
                            <div class="timeline-project-info">
                                <span class="project-name">' . htmlspecialchars($project['project_name']) . '</span>
                                <span class="project-client"> - ' . htmlspecialchars($project['company_name']) . '</span>
                            </div>
                            <div class="timeline-bar-wrapper">
                                <div class="timeline-project-bar" style="left: ' . $project_start_percent . '%; width: ' . $project_width_percent . '%;">
                                    ' . $bar_html . '
                                    <span class="timeline-deadline-label">' . $label_text . '</span>
                                </div>
                            </div>
                          </div>';
                }
            }
            ?>
        </div>
    </div>
    
    <section id="report-output" class="report-output" hidden>
        <div id="report-content" class="report-content"></div>
        <div id="report-pagination" class="report-pagination-controls"></div>
    </section>

    <div class="video-card">
        <h2><i class="fas fa-chart-bar"></i> Estimativa de produtividade</h2>
        <?php
        $pending_projects_for_estimation = array_filter($projects, function($p) use ($today_ts) {
            $is_active_status = ($p['status'] !== 'completed' && $p['status'] !== 'cancelled');
            $has_future_deadline = $p['deadline'] && (strtotime($p['deadline']) >= $today_ts);
            $has_quantity = ($p['word_count'] > 0); 
            return $is_active_status && $has_future_deadline && $has_quantity;
        });

        if (empty($pending_projects_for_estimation)): ?>
            <div class="alert-warning"><i class="fas fa-info-circle"></i>Nenhum projeto pendente com prazo e quantidade para estimativa.</div>
        <?php else: ?>
            <div class="vision-table-container">
                <table class="vision-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-project-diagram"></i> Projeto</th><th><i class="fas fa-calendar-check"></i> Prazo</th><th><i class="fas fa-sort-numeric-up"></i> Quantidade</th><th><i class="fas fa-clock"></i> Dias restantes</th><th><i class="fas fa-target"></i> Meta diária</th><th><i class="fas fa-chart-line"></i> Sugestão diária</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_projects_for_estimation as $project):
                            $deadline_ts = strtotime($project['deadline']);
                            $days_remaining = max(1, floor(($deadline_ts - $today_ts) / (60 * 60 * 24)) + 1);
                            $quantity_total = $project['word_count'];
                            $suggested_daily_quantity = ceil($quantity_total / $days_remaining);
                            $unit_display = $project['unit_type'] ?? 'palavra';
                        ?>
                            <tr>
                                <td><span class="text-primary"><?php echo htmlspecialchars($project['project_name']); ?></span></td>
                                <td><?php echo date('d/m/Y', $deadline_ts); ?></td>
                                <td><?php echo number_format($quantity_total, 0, ',', '.'); ?> <?php echo $unit_display; ?>(s)</td>
                                <td><?php echo $days_remaining; ?></td>
                                <td><?php echo $project['daily_word_target'] > 0 ? number_format($project['daily_word_target'], 0, ',', '.') : '-'; ?></td>
                                <td>
                                    <?php
                                    $daily_target_set = $project['daily_word_target'];
                                    if ($daily_target_set > 0) {
                                        if ($daily_target_set < $suggested_daily_quantity) {
                                            echo '<span class="text-warning">' . number_format($suggested_daily_quantity, 0, ',', '.') . ' (Acima da meta!)</span>';
                                        } else {
                                            echo '<span class="text-success">' . number_format($suggested_daily_quantity, 0, ',', '.') . ' (Meta atingida!)</span>';
                                        }
                                    } else {
                                        echo number_format($suggested_daily_quantity, 0, ',', '.');
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="video-card">
        <div class="card-header-refined">
            <h2><i class="fas fa-list"></i> Lista de projetos</h2>
            <div class="search-filters-refined">
                <form method="GET" class="search-form-refined" action="projects_b.php">
                    <div class="search-group-refined">
                        <input type="text" name="search" placeholder="Buscar projetos..." value="<?php echo htmlspecialchars($search); ?>" class="vision-search">
                        <select name="status" class="vision-select">
                            <option value="">Todos os status</option>
                            <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pendente</option>
                            <option value="in_progress" <?php echo ($status_filter == 'in_progress') ? 'selected' : ''; ?>>Em andamento</option>
                            <option value="completed" <?php echo ($status_filter == 'completed') ? 'selected' : ''; ?>>Concluído</option>
                            <option value="cancelled" <?php echo ($status_filter == 'cancelled') ? 'selected' : ''; ?>>Cancelado</option>
                        </select>
                        <button type="submit" class="vision-btn vision-btn-primary"><i class="fas fa-search"></i></button>
                        <?php if ($search || $status_filter): ?><a href="projects_b.php" class="vision-btn vision-btn-secondary"><i class="fas fa-times"></i></a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <?php if (empty($projects)): ?>
            <div class="alert-warning"><i class="fas fa-info-circle"></i><?php echo ($search || $status_filter) ? 'Nenhum projeto encontrado com os critérios de busca.' : 'Nenhum projeto cadastrado ainda.'; ?></div>
        <?php else: ?>
            <div class="vision-table-container">
                <table class="vision-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-project-diagram"></i> Projeto</th><th><i class="fas fa-user"></i> Cliente</th><th><i class="fas fa-language"></i> Idiomas</th><th><i class="fas fa-flag"></i> Status</th><th><i class="fas fa-money-bill-wave"></i> Valor</th><th><i class="fas fa-calendar-check"></i> Entrega</th><th><i class="fas fa-wallet"></i> Pagamento</th><th><i class="fas fa-cogs"></i> Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $project): ?>
                            <tr>
                                <td>
                                    <div class="project-info-refined">
                                        <span class="project-name-refined"><?php echo htmlspecialchars($project['project_name']); ?></span>
                                        <span class="project-type-refined"><?php echo ucfirst($project['service_type']); ?></span>
                                        <?php if ($project['po_number']): ?><span class="po-number-refined">PO: <?php echo htmlspecialchars($project['po_number']); ?></span><?php endif; ?>
                                    </div>
                                </td>
                                <td class="client-name-refined"><?php echo htmlspecialchars($project['company_name']); ?></td>
                                <td class="language-pair-refined"><?php echo strtoupper($project['source_language']); ?> → <?php echo strtoupper($project['target_language']); ?></td>
                                <td>
                                    <span class="status-badge-refined status-<?php echo $project['status']; ?>"><?php $status_labels = ['pending' => 'Pendente', 'in_progress' => 'Em andamento', 'completed' => 'Concluído', 'cancelled' => 'Cancelado', 'on_hold' => 'Pausado']; echo $status_labels[$project['status']] ?? $project['status']; ?></span>
                                </td>
                                <td class="value-cell-refined"><?php echo formatCurrency($project['total_amount'], $project['currency'] ?? 'BRL'); ?></td>
                                <td class="deadline-cell-refined"><?php echo $project['deadline'] ? date('d/m/Y', strtotime($project['deadline'])) : '<span class="text-muted">-</span>'; ?></td>
                                <td class="payment-cell-refined"><?php echo $project['payment_date'] ? '<span class="payment-date-badge">' . date('d/m/Y', strtotime($project['payment_date'])) . '</span>' : '<span class="text-muted">Pendente</span>'; ?></td>
                                <td>
                                    <div class="action-buttons-refined">
                                        <a href="?edit=<?php echo $project['id']; ?>" class="action-btn-refined action-btn-edit" title="Editar"><i class="fas fa-edit"></i></a>
                                        <?php if ($project['status'] != 'completed'): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja marcar como concluído?')" action="projects_b.php">
                                                <input type="hidden" name="action" value="complete_project"><input type="hidden" name="project_id" value="<?php echo $project['id']; ?>"><button type="submit" class="action-btn-refined action-btn-complete" title="Concluir"><i class="fas fa-check-circle"></i></button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja gerar uma fatura?')" action="projects_b.php">
                                            <input type="hidden" name="action" value="generate_invoice"><input type="hidden" name="project_id" value="<?php echo $project['id']; ?>"><button type="submit" class="action-btn-refined action-btn-invoice" title="Gerar fatura"><i class="fas fa-file-invoice"></i></button>
                                        </form>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir?')" action="projects_b.php">
                                            <input type="hidden" name="action" value="delete_project"><input type="hidden" name="project_id" value="<?php echo $project['id']; ?>"><button type="submit" class="action-btn-refined action-btn-delete" title="Excluir"><i class="fas fa-trash"></i></button>
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

<div id="addClientModal" class="vision-modal">
    <div class="vision-modal-content">
        <div class="vision-modal-header">
            <h3><i class="fas fa-user-plus"></i> Adicionar Novo Cliente</h3>
            <button type="button" class="vision-modal-close" onclick="hideAddClientModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" class="vision-modal-form" action="projects_b.php">
            <input type="hidden" name="action" value="add_client">
            <div class="form-group">
                <label for="company_name">Nome da empresa:</label>
                <input type="text" id="company_name" name="company_name" required class="vision-input" placeholder="Ex: Acme Inc.">
            </div>
            <div class="form-group">
                <label for="default_currency">Moeda padrão:</label>
                <select id="default_currency" name="default_currency" class="vision-select">
                    <option value="BRL">BRL</option>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                </select>
            </div>
            <div class="vision-modal-actions">
                <button type="submit" class="vision-btn vision-btn-primary">
                    <i class="fas fa-plus"></i> Adicionar
                </button>
                <button type="button" class="vision-btn vision-btn-secondary" onclick="hideAddClientModal()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<div id="addServiceModal" class="vision-modal">
    <div class="vision-modal-content">
        <div class="vision-modal-header">
            <h3><i class="fas fa-plus-circle"></i> Adicionar Novo Serviço</h3>
            <button type="button" class="vision-modal-close" onclick="hideAddServiceModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" class="vision-modal-form" id="addServiceForm" action="projects_b.php">
            <input type="hidden" name="action" value="add_service_type">
            <div class="form-group">
                <label for="new_service_type">Nome do serviço:</label>
                <input type="text" id="new_service_type" name="new_service_type" required class="vision-input" placeholder="Ex: Legendagem, Dublagem...">
            </div>
            <div class="vision-modal-actions">
                <button type="submit" class="vision-btn vision-btn-primary">
                    <i class="fas fa-plus"></i> Adicionar
                </button>
                <button type="button" class="vision-btn vision-btn-secondary" onclick="hideAddServiceModal()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<div id="report-modal" class="report-modal" aria-hidden="true">
    <div class="report-modal-backdrop" data-close-modal></div>
    <div class="report-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="reportModalTitle">
        <div class="report-modal-header">
            <h3 id="reportModalTitle"><i class="fas fa-file-alt"></i> Gerar relatório de projetos</h3>
            <button class="modal-close" type="button" aria-label="Fechar" data-close-modal>&times;</button>
        </div>
        <form id="report-filters-form" class="report-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="start_date_report">Data inicial</label>
                    <input type="date" id="start_date_report" name="start_date" required>
                </div>
                <div class="form-group">
                    <label for="end_date_report">Data final</label>
                    <input type="date" id="end_date_report" name="end_date" required>
                </div>
            </div>
             <div class="form-row">
                <div class="form-group">
                    <label for="status_report">Status do Projeto</label>
                    <select id="status_report" name="status">
                        <option value="all">Todos</option>
                        <option value="pending">Pendente</option>
                        <option value="in_progress">Em andamento</option>
                        <option value="completed">Concluído</option>
                        <option value="cancelled">Cancelado</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="client_id_report">Cliente</label>
                    <select id="client_id_report" name="client_id">
                        <option value="">Todos</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                 <div class="form-group">
                    <label for="currency_report">Moeda</label>
                    <select id="currency_report" name="currency">
                        <option value="">Todas</option>
                        <option value="BRL">BRL</option>
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="min_value">Valor Mínimo</label>
                    <input type="number" id="min_value" name="min_value" step="0.01" placeholder="Ex: 100.50">
                </div>
                 <div class="form-group">
                    <label for="max_value">Valor Máximo</label>
                    <input type="number" id="max_value" name="max_value" step="0.01" placeholder="Ex: 5000.00">
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" data-close-modal>Cancelar</button>
                <button type="button" id="btn-generate-report" class="btn-primary"><i class="fas fa-magnifying-glass-chart"></i> Gerar relatório</button>
            </div>
        </form>
    </div>
</div>


<style>
.main-content .video-card { margin-bottom: 20px; }
.timeline-container { padding: 30px; }
.timeline-header { display: flex; align-items: center; margin-bottom: 20px; }
.timeline-label { width: 200px; flex-shrink: 0; padding-right: 20px; }
.timeline-title { font-weight: 600; color: var(--text-secondary); }
.timeline-bar { flex-grow: 1; height: 30px; background: rgba(255, 255, 255, 0.05); border-radius: 15px; position: relative; }
.timeline-date-labels-container { position: absolute; top: -25px; left: 0; width: 100%; height: 20px; }
.timeline-date-label { position: absolute; background: var(--brand-purple-dark); color: #e0e0e0; padding: 2px 8px; border-radius: 8px; font-size: 0.7rem; font-weight: 600; white-space: nowrap; }
.timeline-date-label.date-start { left: 0; }
.timeline-date-label.date-today { background: var(--brand-purple); color: white; transform: translateX(-50%); z-index: 3; }
.timeline-date-label.date-end { right: 0; }
.timeline-today-line { position: absolute; top: -5px; bottom: -5px; width: 2px; background: var(--brand-purple); z-index: 2; }
.timeline-project { display: flex; align-items: center; margin-bottom: 12px; height: 30px; }
.timeline-project-info { width: 200px; flex-shrink: 0; padding-right: 20px; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.project-name { font-weight: 600; }
.project-client { color: var(--text-muted); }
.timeline-bar-wrapper { flex-grow: 1; height: 100%; position: relative; }
.timeline-project-bar { position: absolute; top: 0; height: 100%; display: flex; background: rgba(255, 255, 255, 0.2); border-radius: 15px; overflow: hidden; }
.timeline-progress { height: 100%; }
.timeline-progress-completed { background-color: #22c55e; }
.timeline-progress-gray { background-color: rgba(255, 255, 255, 0.2); }
.timeline-deadline-label { display: inline-flex; align-items: center; gap: 4px; position: absolute; right: 10px; top: 50%; transform: translateY(-50%); font-size: 0.8rem; font-weight: 600; color: white; background-color: rgba(0,0,0,0.4); padding: 2px 6px; border-radius: 5px; z-index: 3; }
.timeline-fallback { text-align: center; padding: 20px; }
.timeline-fallback img { max-width: 100%; border-radius: 12px; margin-bottom: 15px; opacity: 0.8; }
.timeline-fallback p { color: var(--text-muted); font-style: italic; }

.client-container,
.service-type-container { display: flex; gap: 8px; align-items: stretch; }
.client-container .vision-select,
.service-type-container .vision-select { flex: 1; }

.card-header-with-action { display: flex; justify-content: space-between; align-items: center; padding: 25px 30px 0; }
.card-header-with-action h2 { margin: 0 !important; }
.header-button-group { display: flex; gap: 10px; }
.btn-small { padding: 8px 16px; font-size: 0.85rem; }

.video-card > h2 { margin-left: 30px; padding-top: 25px; }
.vision-form-refined { padding: 0 30px 30px; }
.form-row { display: grid; gap: 20px; margin-bottom: 25px; grid-template-columns: repeat(3, 1fr); }
.form-row:has(.form-group-full) { grid-template-columns: 1fr; }
.form-group { display: flex; flex-direction: column; }
.form-group-full { grid-column: 1 / -1; }
.form-group label { margin-bottom: 8px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 8px; font-size: 0.9rem; }
.vision-input, .vision-select, .vision-textarea { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; padding: 12px 16px; color: var(--text-primary); font-size: 0.95rem; transition: all 0.3s ease; outline: none; }
.vision-input:focus, .vision-select:focus, .vision-textarea:focus { border-color: var(--brand-purple); box-shadow: 0 0 0 3px rgba(142, 68, 173, 0.2); background: rgba(255, 255, 255, 0.08); }
.vision-input-readonly { background: rgba(255, 255, 255, 0.02); border-color: rgba(255, 255, 255, 0.05); cursor: not-allowed; opacity: 0.7; }
.vision-textarea { resize: vertical; min-height: 100px; font-family: inherit; }
.add-service-btn { background: var(--brand-purple); border: 1px solid var(--brand-purple); border-radius: 16px; width: 44px; color: white; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; }
.add-service-btn:hover { background: var(--brand-purple-dark); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(142, 68, 173, 0.4); }
.form-actions { display: flex; gap: 15px; justify-content: flex-start; margin-top: 30px; padding: 0 30px; }
.vision-btn { background: var(--brand-purple); color: white; border: 1px solid var(--brand-purple); border-radius: 20px; padding: 12px 24px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: all 0.3s ease; font-size: 0.95rem; box-shadow: 0 4px 12px rgba(142, 68, 173, 0.3); }
.vision-btn:hover { background: var(--brand-purple-dark); transform: translateY(-1px); box-shadow: 0 6px 16px rgba(142, 68, 173, 0.4); }
.vision-btn-primary { background: var(--brand-purple); border-color: var(--brand-purple); }
.vision-btn-secondary { background: rgba(255, 255, 255, 0.1); color: var(--text-primary); border-color: rgba(255, 255, 255, 0.2); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); }
.vision-btn-secondary:hover { background: rgba(255, 255, 255, 0.15); border-color: rgba(255, 255, 255, 0.3); }
.card-header-refined { display: flex; justify-content: space-between; align-items: center; padding: 25px 30px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.06); }
.card-header-refined h2 { margin: 0 !important; font-size: 1.3rem; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 12px; }
.search-filters-refined, .search-form-refined, .search-group-refined { display: flex; align-items: center; }
.search-group-refined { gap: 12px; }
.vision-search { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 20px; padding: 10px 16px; color: var(--text-primary); width: 250px; transition: all 0.3s ease; outline: none; }
.vision-search:focus { border-color: var(--brand-purple); box-shadow: 0 0 0 3px rgba(142, 68, 173, 0.2); background: rgba(255, 255, 255, 0.08); }
.vision-search::placeholder { color: var(--text-muted); }
.vision-table-container { margin: 0; overflow-x: auto; }
.vision-table { width: 100%; border-collapse: collapse; background: transparent; }
.vision-table th { background: rgba(255, 255, 255, 0.05); border-bottom: 1px solid rgba(255, 255, 255, 0.1); padding: 18px 20px; font-weight: 600; font-size: 0.9rem; color: var(--text-secondary); text-align: left; }
.vision-table td { padding: 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.04); font-size: 0.95rem; }
.vision-table tr:hover { background: rgba(255, 255, 255, 0.03); }
.vision-table tr:last-child td { border-bottom: none; }
.project-info-refined { display: flex; flex-direction: column; gap: 4px; }
.project-name-refined { font-weight: 600; color: var(--text-primary); font-size: 1rem; }
.project-type-refined { font-size: 0.8rem; color: var(--brand-purple); text-transform: uppercase; font-weight: 500; }
.po-number-refined { font-size: 0.8rem; color: var(--text-muted); font-family: monospace; }
.client-name-refined { color: var(--text-secondary); font-weight: 500; }
.language-pair-refined { color: var(--text-primary); font-weight: 600; font-family: monospace; }
.value-cell-refined { font-weight: 600; color: var(--accent-green); font-size: 1rem; }
.deadline-cell-refined { color: var(--text-primary); font-weight: 500; }
.payment-cell-refined { font-weight: 500; }
.payment-date-badge { background: rgba(34, 197, 94, 0.2); color: #22c55e; padding: 4px 10px; border-radius: 12px; font-size: 0.85rem; font-weight: 600; }
.action-buttons-refined { display: flex; gap: 8px; align-items: center; }
.action-btn-refined { width: 36px; height: 36px; border-radius: 18px; border: 1px solid rgba(255, 255, 255, 0.1); color: var(--text-secondary); display: flex; align-items: center; justify-content: center; text-decoration: none; cursor: pointer; transition: all 0.3s ease; font-size: 0.9rem; background: rgba(255, 255, 255, 0.05); }
.action-btn-refined:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); }
.action-btn-edit:hover { background: var(--brand-purple); color: white; border-color: var(--brand-purple); }
.action-btn-complete:hover { background: var(--accent-green); color: white; border-color: var(--accent-green); }
.action-btn-invoice:hover { background: #3b82f6; color: white; border-color: #3b82f6; }
.action-btn-delete:hover { background: #ef4444; color: white; border-color: #ef4444; }
.vision-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(10px); z-index: 1000; align-items: center; justify-content: center; }
.vision-modal.active { display: flex; }
.vision-modal-content { background: rgba(30, 30, 30, 0.95); backdrop-filter: blur(30px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 24px; width: 90%; max-width: 500px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5); }
.vision-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 25px 30px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
.vision-modal-header h3 { margin: 0; color: var(--text-primary); font-size: 1.2rem; display: flex; align-items: center; gap: 8px; }
.vision-modal-close { background: none; border: none; color: var(--text-secondary); font-size: 1.2rem; cursor: pointer; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 16px; transition: all 0.3s ease; }
.vision-modal-close:hover { background: rgba(255, 255, 255, 0.1); color: var(--text-primary); }
.vision-modal-form { padding: 30px; }
.vision-modal-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px; }

/* --- CSS DO MODAL E BOTÃO DE RELATÓRIO --- */
.report-btn-highlight { margin-left: auto; display: inline-flex; align-items: center; gap: 10px; padding: 10px 18px; border-radius: 30px; border: 0; cursor: pointer; font-weight: 700; color: #fff; background: linear-gradient(135deg, #ff3d00, #ff9100); box-shadow: 0 8px 24px rgba(255, 61, 0, 0.35); position: relative; overflow: hidden; }
.report-btn-highlight .glow { position: absolute; inset: -2px; background: radial-gradient(120px 40px at var(--mx, 50%) -20%, rgba(255,255,255,0.45), transparent 50%); mix-blend-mode: soft-light; pointer-events: none; transition: opacity .3s ease; opacity: 0; }
.report-btn-highlight:hover .glow { opacity: 1; }
.report-btn-highlight:hover { transform: translateY(-1px); box-shadow: 0 12px 28px rgba(255, 61, 0, 0.45);}
.report-btn-highlight i { font-size: 1rem; }
.report-modal { position: fixed; inset: 0; display: none; z-index: 999; }
.report-modal.active { display: block; }
.report-modal-backdrop { position: absolute; inset: 0; background: rgba(10,10,20,0.6); backdrop-filter: blur(6px); opacity: 0; transition: opacity .25s ease; }
.report-modal.active .report-modal-backdrop { opacity: 1; }
.report-modal-dialog { position: relative; width: min(720px, 92vw); margin: 8vh auto; background: linear-gradient(160deg, rgba(35, 0, 60, 0.9), rgba(10, 10, 25, 0.9)); border: 1px solid rgba(180, 120, 255, 0.25); border-radius: 20px; box-shadow: 0 24px 60px rgba(0,0,0,0.5); transform: translateY(12px) scale(.98); opacity: 0; transition: transform .25s ease, opacity .25s ease; overflow: hidden; }
.report-modal.active .report-modal-dialog { transform: translateY(0) scale(1); opacity: 1; }
.report-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; background: linear-gradient(135deg, rgba(160, 80, 255, .2), rgba(80, 0, 160, .15)); border-bottom: 1px solid rgba(255,255,255,0.08); }
.modal-close { background: transparent; border: 0; color: #fff; font-size: 1.6rem; cursor: pointer; opacity: .8; }
.modal-close:hover { opacity: 1; }
.report-form { padding: 20px 22px; }
.report-form .form-row { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
.modal-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 10px; padding: 12px 22px 22px; }
.btn-secondary { background: transparent; border: 1px solid rgba(255,255,255,0.2); color: #fff; padding: 10px 16px; border-radius: 10px; cursor: pointer; }
.btn-secondary:hover { background: rgba(255,255,255,0.06); }
.btn-primary { background: linear-gradient(135deg, #7c4dff, #b388ff); color: #0b0318; font-weight: 800; border: 0; padding: 10px 16px; border-radius: 30px; cursor: pointer; }
.btn-primary i { margin-right: 8px; }
.report-output { margin-top: 24px; }
.report-content { background: linear-gradient(160deg, #1b0033, #0e001a); border: 1px solid rgba(180, 120, 255, 0.25); border-radius: 16px; overflow: hidden; }
.report-header { padding: 24px; background: linear-gradient(135deg, rgba(123, 31, 162, 0.6), rgba(103, 58, 183, 0.6)); color: #fff; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; }
.report-header-content { display: flex; justify-content: space-between; align-items: center; width: 100%; flex-wrap: wrap; gap: 20px; }
.report-download-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
.btn-download { background: linear-gradient(135deg, #7c4dff, #b388ff); color: white; border: none; padding: 12px 20px; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(124, 77, 255, 0.3); }
.btn-download:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(124, 77, 255, 0.4); }
.btn-download i { font-size: 1.1rem; }
.btn-download-pdf { background: linear-gradient(135deg, #ef5350, #e53935); }
.btn-download-pdf:hover { box-shadow: 0 6px 16px rgba(239, 83, 80, 0.4); }
.btn-download-csv { background: linear-gradient(135deg, #66bb6a, #43a047); }
.btn-download-csv:hover { box-shadow: 0 6px 16px rgba(102, 187, 106, 0.4); }
.download-notification { position: fixed; top: 20px; right: 20px; background: linear-gradient(135deg, rgba(124, 77, 255, 0.95), rgba(179, 136, 255, 0.95)); backdrop-filter: blur(10px); border: 1px solid rgba(124, 77, 255, 0.5); border-radius: 12px; padding: 18px 24px; display: flex; align-items: center; gap: 12px; color: white; font-weight: 600; font-size: 0.95rem; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3); z-index: 10000; opacity: 0; transform: translateX(400px); transition: all 0.4s ease; }
.download-notification.show { opacity: 1; transform: translateX(0); }
.download-notification i { font-size: 1.5rem; }
.report-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; padding: 16px 24px; }
.kpi { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); border-radius: 12px; padding: 14px; color: #fff; text-align: center; }
.kpi h4 { margin: 0 0 8px 0; font-size: .9rem; opacity: .9; text-transform: uppercase; }
.kpi .value { font-size: 1.4rem; font-weight: 800; }
.report-table { width: 100%; border-collapse: collapse; color: #fff; }
.report-table th, .report-table td { padding: 12px 14px; border-bottom: 1px solid rgba(255,255,255,0.1); }
.report-table th { text-align: left; font-size: .85rem; opacity: .8; text-transform: uppercase; }
.badge { padding: 6px 10px; border-radius: 999px; font-size: .75rem; font-weight: 700; display: inline-block; }
.badge.in_progress { background: rgba(0, 123, 255, .2); color: #79b8ff; border: 1px solid rgba(0, 123, 255, .4); }
.badge.completed { background: rgba(40, 167, 69, .2); color: #66ffa6; border: 1px solid rgba(40, 167, 69, .4); }
.badge.pending { background: rgba(255, 193, 7, .2); color: #ffd666; border: 1px solid rgba(255, 193, 7, .4); }
.badge.cancelled { background: rgba(108, 117, 125, 0.2); color: #6c757d; border: 1px solid rgba(108, 117, 125, 0.4); }
.report-pagination-controls { text-align: center; padding: 20px; }
.report-pagination-controls .page-btn { background: var(--brand-purple); color: white; padding: 8px 16px; border-radius: 10px; text-decoration: none; margin: 0 5px; font-size: 0.9rem; font-weight: 600; transition: all 0.3s ease; border: 1px solid var(--brand-purple); }
.report-pagination-controls .page-btn:hover { background: var(--brand-purple-dark); }
.report-pagination-controls .page-btn.disabled { background: #333; border-color: #444; cursor: not-allowed; opacity: 0.6; }
.report-pagination-controls .page-info { display: inline-block; margin: 0 15px; color: var(--text-secondary); }

@media (max-width: 1200px) { .report-grid { grid-template-columns: 1fr; } }
@media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } .report-grid { grid-template-columns: 1fr; } }
</style>

<script>
(function(){
    // --- Funções para todos os modais ---
    window.showAddClientModal = function() {
        document.getElementById('addClientModal').classList.add('active');
        document.getElementById('company_name').focus();
    }
    window.hideAddClientModal = function() {
        document.getElementById('addClientModal').classList.remove('active');
    }
    window.showAddServiceModal = function(){
        document.getElementById("addServiceModal").classList.add("active");
        document.getElementById("new_service_type").focus();
    }
    window.hideAddServiceModal = function(){
        document.getElementById("addServiceModal").classList.remove("active");
        document.getElementById("new_service_type").value = "";
    }

    // --- LÓGICA DO FORMULÁRIO PRINCIPAL ---
    const quantityInput=document.getElementById("quantity"),ratePerUnitInput=document.getElementById("rate_per_unit"),totalCalculatedInput=document.getElementById("total_calculated"),negotiatedValueInput=document.getElementById("negotiated_value"),currencySelect=document.getElementById("currency"),clientIdSelect=document.getElementById("client_id"),unitTypeSelect=document.getElementById("unit_type"),serviceTypeSelect=document.getElementById("service_type"),laudaConfigRow=document.getElementById("lauda-config-row"),quantityLabel=document.getElementById("quantity_label"),rateLabel=document.getElementById("rate_label"),dailyTargetLabel=document.getElementById("daily_target_label"),labelTexts={palavra:{quantity:"Contagem de palavras",rate:"Valor por palavra",daily:"Meta palavras/dia"},caracteres:{quantity:"Quantidade de caracteres",rate:"Valor por caractere",daily:"Meta caracteres/dia"},hora:{quantity:"Quantidade de horas",rate:"Valor por hora",daily:"Meta horas/dia"},minuto:{quantity:"Quantidade de minutos",rate:"Valor por minuto",daily:"Meta minutos/dia"},lauda:{quantity:"Quantidade de laudas",rate:"Valor por lauda",daily:"Meta laudas/dia"},diaria:{quantity:"Quantidade de diárias",rate:"Valor por diária",daily:"Meta diárias/dia"}};
    window.handleUnitChange=function(){const e=unitTypeSelect.value,t=labelTexts[e];t&&(quantityLabel.innerHTML=`<i class="fas fa-sort-numeric-up"></i> ${t.quantity} *`,rateLabel.innerHTML=`<i class="fas fa-dollar-sign"></i> ${t.rate}`,dailyTargetLabel.innerHTML=`<i class="fas fa-chart-line"></i> ${t.daily}`),"lauda"===e?laudaConfigRow.style.display="grid":laudaConfigRow.style.display="none",calculateTotal()};
    window.calculateTotal=function(){const e=parseFloat(quantityInput.value)||0,t=parseFloat(ratePerUnitInput.value)||0;totalCalculatedInput.value=formatCurrencyForDisplay(e*t,currencySelect.value)};
    function formatCurrencyForDisplay(e,t){return isNaN(e)&&(e=0),"BRL"===t?"R$ "+e.toFixed(2).replace(".",","):"USD"===t?"$"+e.toFixed(2):"EUR"===t?"\u20ac"+e.toFixed(2).replace(".",","):e.toFixed(2)}
    function handleRateInput(){calculateTotal(),""===negotiatedValueInput.value&&(currencySelect.value=currencySelect.value)}
    function updateCurrencyForNegotiated(){""!==negotiatedValueInput.value?(quantityInput.disabled=!0,ratePerUnitInput.disabled=!0):(quantityInput.disabled=!1,ratePerUnitInput.disabled=!1,calculateTotal(),currencySelect.value=clientIdSelect.options[clientIdSelect.selectedIndex].dataset.currency||currencySelect.value)}
    clientIdSelect.addEventListener("change",function(){const e=this.options[this.selectedIndex].dataset.currency;e&&(currencySelect.value=e),handleRateInput()});
    negotiatedValueInput.addEventListener("input",updateCurrencyForNegotiated);
    
    // Automação para serviço de Interpretação
    serviceTypeSelect.addEventListener('change', function() {
        if (this.value === 'interpretacao') {
            unitTypeSelect.value = 'diaria';
            handleUnitChange(); // Atualiza os labels
        }
    });

    // --- LÓGICA DO MODAL DE RELATÓRIO ---
    const openBtn = document.getElementById('btn-open-report-modal');
    const modal = document.getElementById('report-modal');
    const form = document.getElementById('report-filters-form');
    const reportSection = document.getElementById('report-output');
    const reportContent = document.getElementById('report-content');
    const reportPagination = document.getElementById('report-pagination');
    const btnGenerate = document.getElementById('btn-generate-report');

    function firstDayOfCurrentMonth() { const d = new Date(); return new Date(d.getFullYear(), d.getMonth(), 1); }
    function toInputDate(d) { return d.toISOString().split('T')[0]; }

    function openModal() { modal.classList.add('active'); modal.setAttribute('aria-hidden', 'false'); loadClientFilter(); }
    function closeModal() { modal.classList.remove('active'); modal.setAttribute('aria-hidden', 'true'); }

    document.getElementById('start_date_report').value = toInputDate(firstDayOfCurrentMonth());
    document.getElementById('end_date_report').value = toInputDate(new Date());

    openBtn.addEventListener('mousemove', (e) => { const rect = openBtn.getBoundingClientRect(); const x = ((e.clientX - rect.left) / rect.width) * 100; openBtn.style.setProperty('--mx', x + '%'); });
    openBtn.addEventListener('click', openModal);
    modal.addEventListener('click', (e) => { if (e.target.hasAttribute('data-close-modal')) closeModal(); });
    
    async function loadClientFilter() {
        const clientSelect = document.getElementById('client_id_report');
        if (clientSelect.options.length > 1) return;
        try {
            const res = await fetch('index.php?api=get_clients');
            const data = await res.json();
            if (data.clients) {
                data.clients.forEach(client => {
                    const option = new Option(client.company, client.id);
                    clientSelect.add(option);
                });
            }
        } catch (err) { console.error('Falha ao carregar clientes:', err); }
    }

    function validateDates(s, e) { return s && e && new Date(s) <= new Date(e); }
    function currencyBRL(v) { try { return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0); } catch { return v; } }
    function escapeHtml(str) { const map = {'&': '&amp;','<': '&lt;','>': '&gt;','"': '&quot;',"'": '&#039;'}; return String(str).replace(/[&<>"']/g, s => map[s]); }

    function getFormURLParams() { const formData = new FormData(form); const params = new URLSearchParams(); for (const [key, value] of formData.entries()) { if (value) { params.append(key, value); } } return params; }

    btnGenerate.addEventListener('click', () => generateReport(1));

    async function generateReport(page = 1) {
        const startDateValue = document.getElementById('start_date_report').value;
        const endDateValue = document.getElementById('end_date_report').value;

        if (!validateDates(startDateValue, endDateValue)) { alert('A data inicial não pode ser maior que a data final.'); return; }
        btnGenerate.disabled = true;
        btnGenerate.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gerando...';
        try {
            const params = getFormURLParams();
            params.append('page', page);

            // --- SALVAMENTO AUTOMÁTICO EM SEGUNDO PLANO ---
            const saveParams = new URLSearchParams(params);
            saveParams.append('save_only', 'true');
            fetch(`generate_pdf_report.php?${saveParams.toString()}`); // Fire-and-forget
            // --- FIM DO SALVAMENTO AUTOMÁTICO ---

            const res = await fetch(`index.php?api=projects_report&${params.toString()}`);
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Erro no servidor');
            renderReport(data);
            attachDownloadButtons();
            renderPagination(data.pagination);
            reportSection.hidden = false;
            closeModal();
        } catch (err) {
            alert('Falha ao gerar relatório: ' + err.message);
        } finally {
            btnGenerate.disabled = false;
            btnGenerate.innerHTML = '<i class="fas fa-magnifying-glass-chart"></i> Gerar relatório';
        }
    }

    function renderReport(data) {
        const { projects, kpis } = data;
        const statusReport = document.getElementById('status_report').value;
        const statusMap = { 'all': 'Todos', 'in_progress': 'Em andamento', 'completed': 'Concluídos', 'pending': 'Pendentes', 'cancelled': 'Cancelados' };
        reportContent.innerHTML = `
            <div class="report-header"><div class="report-header-content"><div><h2>Relatório de Projetos</h2><p>Período: ${document.getElementById('start_date_report').value} a ${document.getElementById('end_date_report').value} • Status: ${statusMap[statusReport]}</p></div><div class="report-download-buttons"><button type="button" id="btn-download-pdf" class="btn-download btn-download-pdf"><i class="fas fa-file-pdf"></i> Baixar PDF</button><button type="button" id="btn-download-csv" class="btn-download btn-download-csv"><i class="fas fa-file-csv"></i> Baixar CSV</button></div></div></div>
            <div class="report-grid"><div class="kpi"><h4>Total de Projetos</h4><div class="value">${kpis.total_projects}</div></div><div class="kpi"><h4>Receita Total</h4><div class="value">${currencyBRL(kpis.total_revenue)}</div></div><div class="kpi"><h4>Status</h4><div class="value">${kpis.in_progress_count} em andamento • ${kpis.completed_count} concluídos</div></div></div>
            <div class="report-table-wrapper"><table class="report-table"><thead><tr><th>Projeto</th><th>Cliente</th><th>Status</th><th>Valor</th><th>Data</th></tr></thead><tbody>
                  ${projects.length === 0 ? '<tr><td colspan="5" style="text-align:center;padding:20px;">Nenhum projeto encontrado.</td></tr>' : projects.map(p => `
                    <tr><td>${escapeHtml(p.project_name||'-')}</td><td>${escapeHtml(p.company_name||'-')}</td><td><span class="badge ${p.status}">${p.status.replace('_',' ')}</span></td><td>${currencyBRL(p.total_amount)} (${p.currency})</td><td>${new Date(p.created_at).toLocaleDateString('pt-BR')}</td></tr>`).join('')}
            </tbody></table></div>`;
    }

    function attachDownloadButtons() {
        const btnPDF = document.getElementById('btn-download-pdf');
        const btnCSV = document.getElementById('btn-download-csv');
        if (btnPDF) btnPDF.addEventListener('click', () => handleDownload('pdf'));
        if (btnCSV) btnCSV.addEventListener('click', () => handleDownload('csv'));
    }

    function handleDownload(format) {
        const startDateValue = document.getElementById('start_date_report').value;
        const endDateValue = document.getElementById('end_date_report').value;
        if (!validateDates(startDateValue, endDateValue)) { alert('A data inicial não pode ser maior que a data final.'); return; }
        const params = getFormURLParams();
        const downloadUrl = `generate_${format}_report.php?${params.toString()}`;
        window.open(downloadUrl, '_blank');
        showNotification(`Relatório ${format.toUpperCase()} está sendo gerado...`);
    }

    function showNotification(message) {
        const notification = document.createElement('div');
        notification.className = 'download-notification';
        notification.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
        document.body.appendChild(notification);
        setTimeout(() => notification.classList.add('show'), 100);
        setTimeout(() => { notification.classList.remove('show'); setTimeout(() => notification.remove(), 400); }, 3000);
    }

    function renderPagination({ current_page, total_pages, total_items }) {
        if (total_pages <= 1) { reportPagination.innerHTML = ''; return; }
        let html = (current_page > 1) ? `<a href="#" class="page-btn" data-page="${current_page - 1}">&laquo; Anterior</a>` : `<span class="page-btn disabled">&laquo; Anterior</span>`;
        html += `<span class="page-info">Página ${current_page} de ${total_pages}</span>`;
        html += (current_page < total_pages) ? `<a href="#" class="page-btn" data-page="${current_page + 1}">Próxima &raquo;</a>` : `<span class="page-btn disabled">Próxima &raquo;</span>`;
        reportPagination.innerHTML = html;
    }

    reportPagination.addEventListener('click', (e) => { e.preventDefault(); const target = e.target.closest('.page-btn'); if (target && !target.classList.contains('disabled')) { const page = parseInt(target.dataset.page, 10); generateReport(page); } });

    // --- EVENTOS GERAIS DA PÁGINA ---
    document.addEventListener("DOMContentLoaded",function(){
        handleUnitChange();
        calculateTotal();
        if(negotiatedValueInput.value!==""){ quantityInput.disabled=!0; ratePerUnitInput.disabled=!0; } else { handleRateInput(); }
        if(clientIdSelect.value){ const selectedOption = clientIdSelect.options[clientIdSelect.selectedIndex]; if(selectedOption && selectedOption.dataset.currency){ currencySelect.value = selectedOption.dataset.currency; } }
        <?php if($new_service_added):?>setTimeout(function(){const e=document.getElementById("service_type");e.style.boxShadow="0 0 0 3px rgba(34, 197, 94, 0.3)";e.style.borderColor="#22c55e";setTimeout(function(){e.style.boxShadow="",e.style.borderColor=""},2e3)},500);<?php endif;?>
        document.getElementById("addClientModal").addEventListener("click",function(e){if(e.target===this){hideAddClientModal()}});
        document.getElementById("addServiceModal").addEventListener("click",function(e){if(e.target===this){hideAddServiceModal()}});
        document.addEventListener("keydown",function(e){if(e.key==="Escape"){hideAddClientModal();hideAddServiceModal();closeModal();}});
        document.getElementById("addServiceForm").addEventListener("submit",function(e){
            const serviceInput = document.getElementById("new_service_type"), serviceName = serviceInput.value.trim();
            if(!serviceName){ e.preventDefault(); alert("Por favor, insira o nome do serviço."); serviceInput.focus(); return false; }
            const options = document.getElementById("service_type").options;
            for(let i=0; i<options.length; i++){
                if(options[i].text.toLowerCase() === serviceName.toLowerCase()){ e.preventDefault(); alert("Este serviço já existe na lista!"); serviceInput.focus(); return false; }
            }
        });
    });
})();
</script>

<?php
include __DIR__ . '/../vision/includes/footer.php';
?>