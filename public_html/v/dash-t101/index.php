<?php
session_start();

// Ajuste os caminhos conforme a localização deste arquivo.
// Considerando que este index.php está em v/vision/:
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';
require_once __DIR__ . '/../config/dash_functions.php';

// =========================================================================
// INÍCIO DOS ENDPOINTS DA API
// =========================================================================

/**
 * Endpoint para buscar a lista de clientes do usuário.
 */
if (isset($_GET['api']) && $_GET['api'] === 'get_clients') {
    header('Content-Type: application/json; charset=utf-8');
    if (!function_exists('isLoggedIn') || !isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'not_authenticated']);
        exit;
    }

    try {
        global $pdo;
        $stmt = $pdo->prepare("SELECT id, company FROM dash_clients WHERE user_id = :uid ORDER BY company ASC");
        $stmt->execute([':uid' => $_SESSION['user_id']]);
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['clients' => $clients]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => 'server_error', 'message' => $e->getMessage()]);
    }
    exit;
}

/**
 * Função auxiliar para construir a query de relatório e seus parâmetros.
 */
function build_report_query_and_params($filters) {
    // Query base alinhada ao schema (dash_projects/dash_clients)
    $sql = "
        FROM dash_projects p
        LEFT JOIN dash_clients c ON c.id = p.client_id
        WHERE p.user_id = :uid
          AND DATE(p.created_at) BETWEEN :start AND :end
    ";

    $params = [
        ':uid'   => $_SESSION['user_id'] ?? 0,
        ':start' => $filters['start_date'],
        ':end'   => $filters['end_date']
    ];

    if (!empty($filters['status']) && $filters['status'] !== 'all') {
        $sql .= " AND p.status = :status";
        $params[':status'] = $filters['status'];
    }
    if (!empty($filters['client_id'])) {
        $sql .= " AND p.client_id = :client_id";
        $params[':client_id'] = $filters['client_id'];
    }
    if (!empty($filters['currency'])) {
        $sql .= " AND p.currency = :currency";
        $params[':currency'] = $filters['currency'];
    }
    if (!empty($filters['min_value'])) {
        $sql .= " AND p.total_amount >= :min_value";
        $params[':min_value'] = $filters['min_value'];
    }
    if (!empty($filters['max_value'])) {
        $sql .= " AND p.total_amount <= :max_value";
        $params[':max_value'] = $filters['max_value'];
    }

    return ['sql' => $sql, 'params' => $params];
}

/**
 * Endpoint JSON para o relatório paginado na tela.
 */
if (isset($_GET['api']) && $_GET['api'] === 'projects_report') {
    header('Content-Type: application/json; charset=utf-8');

    if (!function_exists('isLoggedIn') || !isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'not_authenticated']);
        exit;
    }

    $filters = [
        'start_date' => $_GET['start_date'] ?? null,
        'end_date'   => $_GET['end_date'] ?? null,
        'status'     => $_GET['status'] ?? 'all',
        'client_id'  => $_GET['client_id'] ?? null,
        'currency'   => $_GET['currency'] ?? null,
        'min_value'  => $_GET['min_value'] ?? null,
        'max_value'  => $_GET['max_value'] ?? null,
    ];

    if (!$filters['start_date'] || !$filters['end_date']) {
        http_response_code(400);
        echo json_encode(['error' => 'missing_parameters']);
        exit;
    }

    try {
        global $pdo;

        $query_parts = build_report_query_and_params($filters);
        $sql_from_where = $query_parts['sql'];
        $params = $query_parts['params'];

        // KPIs (Agregação no backend)
        $kpi_sql = "SELECT
                        COUNT(p.id) as total_projects,
                        SUM(p.total_amount) as total_revenue,
                        SUM(CASE WHEN p.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
                        SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) as completed_count
                    " . $sql_from_where;
        $stmt = $pdo->prepare($kpi_sql);
        $stmt->execute($params);
        $kpis = $stmt->fetch(PDO::FETCH_ASSOC);

        // Paginação
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $per_page = 20; // Itens por página
        $offset = ($page - 1) * $per_page;
        $total_count = (int)$kpis['total_projects'];
        $total_pages = ceil($total_count / $per_page);

        // Busca dos projetos (paginado)
        $projects_sql = "SELECT
                            p.id, p.title AS project_name, p.status, p.total_amount, p.created_at, p.currency,
                            c.company AS company_name
                        " . $sql_from_where . " ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset";

        $projects_params = array_merge($params, [':limit' => $per_page, ':offset' => $offset]);

        $stmt = $pdo->prepare($projects_sql);
        foreach ($projects_params as $key => &$val) {
             $stmt->bindParam($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        echo json_encode([
            'projects' => $projects,
            'kpis' => [
                'total_projects'   => (int)($kpis['total_projects'] ?? 0),
                'total_revenue'    => (float)($kpis['total_revenue'] ?? 0),
                'in_progress_count' => (int)($kpis['in_progress_count'] ?? 0),
                'completed_count'  => (int)($kpis['completed_count'] ?? 0),
            ],
            'pagination' => [
                'current_page' => $page,
                'total_pages'  => $total_pages,
                'total_items'  => $total_count
            ]
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {
        http_response_code(500);
        error_log('projects_report API error: ' . $e->getMessage());
        echo json_encode(['error' => 'server_error', 'message' => $e->getMessage()]);
    }
    exit;
}

// =========================================================================
// FIM DOS ENDPOINTS DA API
// =========================================================================

// Verificar se o usuário está logado (fluxo normal de página)
if (!isLoggedIn()) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$page_title = 'Dash-T101';
$page_description = 'Painel de controle de projetos e negócios';
$user_id = $_SESSION['user_id'];

// Obter estatísticas do dashboard
$stats = getDashboardStats($user_id);
$recent_projects = getRecentProjects($user_id, 5);
$recent_invoices = getRecentInvoices($user_id, 5);

// Obter configurações do usuário
$user_settings = getUserSettings($user_id);

// Includes visuais
include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-chart-line"></i> Dash-T101</h1>
            <p>Seu painel de controle de projetos e negócios.</p>
            <p>Gerencie suas operações com eficiência.</p>
        </div>
    </div>

    <div class="stats-grid">
        <div class="video-card stats-card">
            <div class="stats-content">
                <div class="stats-info">
                    <h3>Total de clientes</h3>
                    <span class="stats-number"><?php echo number_format($stats['total_clients']); ?></span>
                </div>
                <div class="stats-icon stats-icon-blue">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>

        <div class="video-card stats-card">
            <div class="stats-content">
                <div class="stats-info">
                    <h3>Projetos ativos</h3>
                    <span class="stats-number"><?php echo number_format($stats['active_projects']); ?></span>
                    <span class="stats-subtitle">de <?php echo number_format($stats['total_projects']); ?> total</span>
                </div>
                <div class="stats-icon stats-icon-purple">
                    <i class="fas fa-project-diagram"></i>
                </div>
            </div>
        </div>

        <div class="video-card stats-card">
            <div class="stats-content">
                <div class="stats-info">
                    <h3>Receita total</h3>
                    <span class="stats-number">
                        <?php echo formatCurrency($stats['total_revenue'], $user_settings['default_currency']); ?>
                    </span>
                </div>
                <div class="stats-icon stats-icon-green">
                    <i class="fas fa-dollar-sign"></i>
                </div>
            </div>
        </div>

        <div class="video-card stats-card">
            <div class="stats-content">
                <div class="stats-info">
                    <h3>Faturas pendentes</h3>
                    <span class="stats-number"><?php echo number_format($stats['pending_invoices']); ?></span>
                    <span class="stats-subtitle">
                        <?php echo formatCurrency($stats['pending_revenue'] ?? 0, $user_settings['default_currency']); ?>
                    </span>
                </div>
                <div class="stats-icon stats-icon-red">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="video-card">
        <div class="card-header-refined quick-actions-header">
            <h2><i class="fas fa-bolt"></i> Ações rápidas</h2>
            <button id="btn-open-report-modal" class="report-btn-highlight" type="button">
                <i class="fas fa-file-alt"></i> <span>Gerar Relatório</span>
                <span class="glow"></span>
            </button>
        </div>

        <div class="workflow-actions">
            <div class="workflow-step">
                <a href="clients.php" class="quick-action-card-refined">
                    <div class="quick-action-icon quick-action-icon-blue">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h3>Novo cliente</h3>
                    <p>Adicionar um novo cliente</p>
                </a>
            </div>

            <div class="workflow-arrow">
                <i class="fas fa-arrow-right"></i>
            </div>

            <div class="workflow-step">
                <a href="projects.php" class="quick-action-card-refined">
                    <div class="quick-action-icon quick-action-icon-purple">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <h3>Novo projeto</h3>
                    <p>Criar um projeto de tradução</p>
                </a>
            </div>

            <div class="workflow-arrow">
                <i class="fas fa-arrow-right"></i>
            </div>

            <div class="workflow-step">
                <a href="invoices.php" class="quick-action-card-refined">
                    <div class="quick-action-icon quick-action-icon-green">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <h3>Nova fatura</h3>
                    <p>Gerar uma nova fatura</p>
                </a>
            </div>
        </div>
    </div>

    <div id="report-modal" class="report-modal" aria-hidden="true">
        <div class="report-modal-backdrop" data-close-modal></div>
        <div class="report-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="reportModalTitle">
            <div class="report-modal-header">
                <h3 id="reportModalTitle"><i class="fas fa-file-alt"></i> Gerar Relatório de Projetos</h3>
                <button class="modal-close" type="button" aria-label="Fechar" data-close-modal>&times;</button>
            </div>
            <form id="report-filters-form" class="report-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date">Data inicial</label>
                        <input type="date" id="start_date" name="start_date" required>
                    </div>
                    <div class="form-group">
                        <label for="end_date">Data final</label>
                        <input type="date" id="end_date" name="end_date" required>
                    </div>
                </div>
                 <div class="form-row">
                    <div class="form-group">
                        <label for="status">Status do Projeto</label>
                        <select id="status" name="status">
                            <option value="all">Todos</option>
                            <option value="pending">Pendente</option>
                            <option value="in_progress">Em andamento</option>
                            <option value="completed">Concluído</option>
                            <option value="cancelled">Cancelado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="client_id">Cliente</label>
                        <select id="client_id" name="client_id">
                            <option value="">Todos</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                     <div class="form-group">
                        <label for="currency">Moeda</label>
                        <select id="currency" name="currency">
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
                    <button type="button" id="btn-generate-report" class="btn-primary"><i class="fas fa-magnifying-glass-chart"></i> Gerar Relatório</button>
                </div>
            </form>
        </div>
    </div>

    <section id="report-output" class="report-output" hidden>
        <div id="report-content" class="report-content"></div>
        <div id="report-pagination" class="report-pagination-controls" style="text-align: center; padding: 20px;"></div>
    </section>

    <div class="dashboard-sections-refined">
        <div class="video-card apple-vision-card">
            <div class="card-header-refined">
                <h2><i class="fas fa-history"></i> Projetos recentes</h2>
                <a href="projects.php" class="page-btn-refined">Ver tudo</a>
            </div>

            <?php if (empty($recent_projects)): ?>
            <div class="empty-state-refined">
                 <div class="empty-icon"><i class="fas fa-folder-open"></i></div>
                 <h3>Nenhum projeto ainda</h3>
                 <p>Comece criando seu primeiro projeto</p>
                 <a href="projects.php" class="cta-btn-refined"><i class="fas fa-plus"></i> Criar Projeto</a>
            </div>
            <?php else: ?>
            <div class="table-refined">
                <div class="table-header">
                    <div class="table-cell">Projeto</div>
                    <div class="table-cell">Status</div>
                    <div class="table-cell">Valor</div>
                    <div class="table-cell">Ações</div>
                </div>
                <?php foreach ($recent_projects as $project): ?>
                <div class="table-row">
                    <div class="table-cell" data-label="Projeto">
                        <div class="project-info-refined">
                            <span class="project-name"><?php echo htmlspecialchars($project['project_name']); ?></span>
                            <span class="project-client"><?php echo htmlspecialchars($project['company_name'] ?? 'Cliente não informado'); ?></span>
                        </div>
                    </div>
                    <div class="table-cell" data-label="Status">
                        <span class="status-badge-refined status-<?php echo $project['status']; ?>">
                        <?php
                            $status_labels = [
                                'pending' => 'Pendente', 'in_progress' => 'Em Andamento', 'completed' => 'Concluído', 'cancelled' => 'Cancelado'
                            ];
                            echo $status_labels[$project['status']] ?? $project['status'];
                        ?>
                        </span>
                    </div>
                    <div class="table-cell value-cell" data-label="Valor">
                        <?php echo formatCurrency($project['total_amount'] ?? 0, $user_settings['default_currency']); ?>
                    </div>
                    <div class="table-cell" data-label="Ações">
                        <a href="projects.php?edit=<?php echo $project['id']; ?>" class="action-btn" title="Editar"><i class="fas fa-edit"></i></a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="video-card apple-vision-card">
            <div class="card-header-refined">
                <h2><i class="fas fa-file-invoice-dollar"></i> Faturas recentes</h2>
                <a href="invoices.php" class="page-btn-refined">Ver tudo</a>
            </div>

            <?php if (empty($recent_invoices)): ?>
            <div class="empty-state-refined">
                <div class="empty-icon"><i class="fas fa-file-invoice"></i></div>
                <h3>Nenhuma fatura ainda</h3>
                <p>Crie sua primeira fatura</p>
                <a href="invoices.php" class="cta-btn-refined"><i class="fas fa-plus"></i> Criar fatura</a>
            </div>
            <?php else: ?>
            <div class="table-refined invoices-table">
                 <div class="table-header">
                    <div class="table-cell">Número</div>
                    <div class="table-cell">Cliente</div>
                    <div class="table-cell">Valor</div>
                    <div class="table-cell">Status</div>
                    <div class="table-cell">Ações</div>
                </div>
                <?php foreach ($recent_invoices as $invoice): ?>
                <div class="table-row invoice-row">
                    <div class="table-cell" data-label="Número">
                        <span class="invoice-number">#<?php echo htmlspecialchars($invoice['invoice_number']); ?></span>
                    </div>
                    <div class="table-cell client-cell" data-label="Cliente">
                        <?php echo htmlspecialchars($invoice['company_name'] ?? 'N/A'); ?>
                    </div>
                    <div class="table-cell value-cell" data-label="Valor">
                        <?php echo formatCurrency($invoice['total_amount'] ?? 0, $invoice['currency'] ?? 'BRL'); ?>
                    </div>
                    <div class="table-cell" data-label="Status">
                        <span class="status-badge-refined status-<?php echo $invoice['status']; ?>">
                        <?php
                            $status_labels = [ 'draft' => 'Rascunho', 'sent' => 'Enviada', 'paid' => 'Paga', 'overdue' => 'Vencida' ];
                            echo $status_labels[$invoice['status']] ?? $invoice['status'];
                        ?>
                        </span>
                    </div>
                    <div class="table-cell" data-label="Ações">
                        <a href="view_invoice.php?id=<?php echo $invoice['id'] ?? ''; ?>" class="action-btn" title="Visualizar"><i class="fas fa-eye"></i></a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.workflow-actions { display: flex; align-items: center; justify-content: center; gap: 30px; padding: 30px 20px; flex-wrap: wrap; }
.workflow-step { flex: 1; max-width: 280px; min-width: 240px; }
.workflow-arrow { color: var(--brand-purple); font-size: 2rem; opacity: 0.7; margin: 0 10px; animation: pulse 2s infinite; }
.workflow-arrow i { filter: drop-shadow(0 2px 8px rgba(142, 68, 173, 0.4)); }
.quick-action-card-refined { display: block; text-decoration: none; color: inherit; background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 20px; padding: 30px 20px; text-align: center; transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1); position: relative; overflow: hidden; }
.quick-action-card-refined::before { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), transparent); opacity: 0; transition: opacity 0.3s ease; border-radius: 20px; }
.quick-action-card-refined:hover { transform: translateY(-8px); border-color: var(--brand-purple); box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3), 0 0 20px rgba(142, 68, 173, 0.4); }
.quick-action-card-refined:hover::before { opacity: 1; }
.quick-action-card-refined .quick-action-icon { width: 60px; height: 60px; margin: 0 auto 20px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; transition: all 0.3s ease; }
.quick-action-card-refined:hover .quick-action-icon { transform: scale(1.1); }
.quick-action-card-refined h3 { margin: 0 0 10px 0; font-size: 1.2rem; font-weight: 600; color: var(--text-primary); }
.quick-action-card-refined p { margin: 0; font-size: 0.9rem; color: var(--text-secondary); line-height: 1.4; }
.apple-vision-card { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(30px); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 24px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.1); overflow: hidden; }
.dashboard-sections-refined { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 30px; }
.card-header-refined { display: flex; justify-content: space-between; align-items: center; padding: 25px 30px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.06); background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.02)); }
.card-header-refined h2 { margin: 0; font-size: 1.3rem; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 12px; }
.page-btn-refined { background: var(--brand-purple); color: white; padding: 10px 20px; border-radius: 20px; text-decoration: none; font-size: 0.9rem; font-weight: 600; transition: all 0.3s ease; border: 1px solid var(--brand-purple); box-shadow: 0 4px 12px rgba(142, 68, 173, 0.3); }
.page-btn-refined:hover { background: var(--brand-purple-dark); border-color: var(--brand-purple-dark); transform: translateY(-2px); box-shadow: 0 6px 16px rgba(142, 68, 173, 0.5); }
.table-refined { margin: 0; }
.table-header { display: grid; grid-template-columns: 2fr 1fr 1fr 80px; background: rgba(255, 255, 255, 0.05); border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
.invoices-table .table-header { grid-template-columns: 1.2fr 1.5fr 1fr 1fr 80px; }
.invoices-table .table-row { grid-template-columns: 1.2fr 1.5fr 1fr 1fr 80px; }
.table-header .table-cell { padding: 18px 20px; font-weight: 600; font-size: 0.9rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }
.table-row { display: grid; grid-template-columns: 2fr 1fr 1fr 80px; border-bottom: 1px solid rgba(255, 255, 255, 0.04); transition: all 0.3s ease; }
.table-row:hover { background: rgba(255, 255, 255, 0.03); }
.table-row:last-child { border-bottom: none; }
.table-cell { padding: 20px; display: flex; align-items: center; font-size: 0.95rem; }
.project-info-refined { display: flex; flex-direction: column; gap: 6px; }
.project-name { font-weight: 600; color: var(--text-primary); font-size: 1rem; }
.project-client { font-size: 0.85rem; color: var(--text-muted); }
.status-badge-refined { padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; display: inline-flex; align-items: center; gap: 6px; }
.status-badge-refined::before { content: ''; width: 6px; height: 6px; border-radius: 50%; }
.status-pending { background: rgba(255, 193, 7, 0.2); color: #ffc107; border: 1px solid rgba(255, 193, 7, 0.4); }
.status-pending::before { background: #ffc107; }
.status-in_progress { background: rgba(0, 123, 255, 0.2); color: #007bff; border: 1px solid rgba(0, 123, 255, 0.4); }
.status-in_progress::before { background: #007bff; }
.status-completed { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid rgba(40, 167, 69, 0.4); }
.status-completed::before { background: #28a745; }
.status-cancelled { background: rgba(108, 117, 125, 0.2); color: #6c757d; border: 1px solid rgba(108, 117, 125, 0.4); }
.status-cancelled::before { background: #6c757d; }
.status-draft { background: rgba(108, 117, 125, 0.2); color: #6c757d; border: 1px solid rgba(108, 117, 125, 0.4); }
.status-draft::before { background: #6c757d; }
.status-sent { background: #28a745; color: var(--text-secondary) !important; border: 1px solid rgba(0, 123, 255, 0.4); }
.status-sent::before { background: #007bff; }
.status-paid { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid rgba(40, 167, 69, 0.4); }
.status-paid::before { background: #28a745; }
.status-overdue { background: rgba(220, 53, 69, 0.2); color: #dc3545; border: 1px solid rgba(220, 53, 69, 0.4); }
.status-overdue::before { background: #dc3545; }
.stats-info h3 { color: #fff !important; font-size: 1.9rem; margin-bottom: 8px; font-weight: 500; }
.stats-content { display: flex; align-items: flex-start; justify-content: space-between; height: 100%; }
.stats-info { display: flex; flex-direction: column; align-items: center; text-align: center; justify-content: flex-start; flex: 1; }
.value-cell { font-weight: 600; color: var(--accent-green); font-size: 1rem; }
.invoice-number { font-weight: 600; color: var(--text-secondary) !important; font-family: 'Monaco', 'Menlo', monospace; }
.client-cell { color: var(--text-secondary); }
.action-btn { width: 36px; height: 36px; border-radius: 18px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); color: var(--text-secondary); display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.3s ease; font-size: 0.9rem; }
.action-btn:hover { background: var(--brand-purple); color: white; border-color: var(--brand-purple); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(142, 68, 173, 0.4); }
.empty-state-refined { text-align: center; padding: 60px 30px; }
.empty-icon { width: 80px; height: 80px; margin: 0 auto 24px; border-radius: 20px; background: rgba(255, 255, 255, 0.05); display: flex; align-items: center; justify-content: center; font-size: 2rem; color: var(--text-muted); }
.empty-state-refined h3 { margin: 0 0 12px 0; color: var(--text-primary); font-size: 1.2rem; }
.empty-state-refined p { margin: 0 0 24px 0; color: var(--text-secondary); font-size: 0.95rem; }
.cta-btn-refined { background: var(--brand-purple); color: white; padding: 12px 24px; border-radius: 20px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(142, 68, 173, 0.3); }
.cta-btn-refined:hover { background: var(--brand-purple-dark); transform: translateY(-1px); box-shadow: 0 8px 20px rgba(142, 68, 173, 0.4); }
.quick-actions-header { position: relative; }
.report-btn-highlight { margin-left: auto; display: inline-flex; align-items: center; gap: 10px; padding: 10px 18px; border-radius: 14px; border: 0; cursor: pointer; font-weight: 700; color: #fff; background: linear-gradient(135deg, #ff3d00, #ff9100); box-shadow: 0 8px 24px rgba(255, 61, 0, 0.35); position: relative; overflow: hidden; }
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
.report-modal-header h3 { margin: 0; color: #fff; font-weight: 700; display: flex; align-items: center; gap: 10px; }
.modal-close { background: transparent; border: 0; color: #fff; font-size: 1.6rem; cursor: pointer; opacity: .8; }
.modal-close:hover { opacity: 1; }
.report-form { padding: 20px 22px; }
.form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 14px; }
.form-group { display: flex; flex-direction: column; gap: 8px; }
.form-group label { color: #eee; font-size: .9rem; }
.form-group input, .form-group select { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); color: #fff; padding: 10px 12px; border-radius: 10px; outline: none; }
.form-group input:focus, .form-group select:focus { border-color: #a07bff; box-shadow: 0 0 0 3px rgba(160,123,255,.2); }
.modal-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 10px; padding: 12px 22px 22px; }
.btn-secondary { background: transparent; border: 1px solid rgba(255,255,255,0.2); color: #fff; padding: 10px 16px; border-radius: 10px; cursor: pointer; }
.btn-secondary:hover { background: rgba(255,255,255,0.06); }
.btn-primary { background: linear-gradient(135deg, #7c4dff, #b388ff); color: #0b0318; font-weight: 800; border: 0; padding: 10px 16px; border-radius: 10px; cursor: pointer; }
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

.report-pagination-controls .page-btn {
    background: var(--brand-purple); color: white; padding: 8px 16px; border-radius: 10px; text-decoration: none;
    margin: 0 5px; font-size: 0.9rem; font-weight: 600; transition: all 0.3s ease; border: 1px solid var(--brand-purple);
}
.report-pagination-controls .page-btn:hover { background: var(--brand-purple-dark); }
.report-pagination-controls .page-btn.disabled { background: #333; border-color: #444; cursor: not-allowed; opacity: 0.6; }
.report-pagination-controls .page-info { display: inline-block; margin: 0 15px; color: var(--text-secondary); }

@media (max-width: 1200px) {
    .dashboard-sections-refined { grid-template-columns: 1fr; gap: 20px; }
    .workflow-actions { flex-direction: column; gap: 20px; }
    .workflow-arrow { transform: rotate(90deg); }
    .report-header-content { flex-direction: column; align-items: flex-start; }
    .report-download-buttons { width: 100%; }
    .btn-download { flex: 1; justify-content: center; }
}
@media (max-width: 768px) {
    .form-row { grid-template-columns: 1fr; }
    .report-grid { grid-template-columns: 1fr; }
    .table-header { display: none; }
    .table-row { grid-template-columns: 1fr; gap: 0; padding: 10px 0; }
    .table-cell { padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; }
    .table-cell::before { content: attr(data-label); font-weight: 600; color: var(--text-muted); }
    .invoices-table .table-row, .table-refined .table-row { display: block; }
}
@media (max-width: 480px) {
    .card-header-refined { flex-direction: column; gap: 15px; align-items: flex-start; }
    .workflow-actions { padding: 20px 10px; }
    .modal-actions { justify-content: center; }
}
.video-card > h2 { margin-left: 30px; }

.stats-grid + .video-card {
    margin-top: 20px;
}

/* ======================================================= */
/* CSS PARA ALINHAR E AUMENTAR FONTES DOS CARDS         */
/* ======================================================= */

/* 1. Melhora o espaçamento interno geral do card */
.stats-card .stats-content {
    padding: 25px;
}

/* 2. Alinha os textos (título, número, subtítulo) à esquerda */
.stats-card .stats-info {
    align-items: flex-start; /* Alinha os itens de texto à esquerda */
    text-align: left;
    gap: 5px; /* Define um pequeno espaço entre os textos */
}

/* 3. Ajusta e aumenta o título do card */
.stats-card .stats-info h3 {
    font-size: 1.2rem; /* Fonte maior para o título */
    font-weight: 600;
    color: var(--text-secondary);
    margin: 0;
}

/* 4. Aumenta o NÚMERO principal para dar destaque */
.stats-card .stats-number {
    font-size: 2.5rem; /* Fonte bem maior para o número */
    font-weight: 700;
    color: #ffffff;
    line-height: 1.2;
}

/* 5. Aumenta a fonte do subtítulo */
.stats-card .stats-subtitle {
    font-size: 1rem; /* Fonte maior para o texto secundário */
    color: var(--text-muted);
}

/* 6. Aumenta o ícone para balancear com as fontes */
.stats-card .stats-icon i {
    font-size: 2.2rem; /* Ícone maior */
    opacity: 0.8;
}

</style>

<script>
(function(){
    // Elementos do DOM
    const openBtn = document.getElementById('btn-open-report-modal');
    const modal = document.getElementById('report-modal');
    const form = document.getElementById('report-filters-form');
    const reportSection = document.getElementById('report-output');
    const reportContent = document.getElementById('report-content');
    const reportPagination = document.getElementById('report-pagination');

    // Botões de ação
    const btnGenerate = document.getElementById('btn-generate-report');

    // Funções utilitárias de data
    function firstDayOfCurrentMonth() {
        const d = new Date();
        return new Date(d.getFullYear(), d.getMonth(), 1);
    }
    function toInputDate(d) {
        return d.toISOString().split('T')[0];
    }

    // Funções do Modal
    function openModal() {
        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');
        loadClientFilter();
    }
    function closeModal() {
        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
    }

    // Preencher datas padrão
    document.getElementById('start_date').value = toInputDate(firstDayOfCurrentMonth());
    document.getElementById('end_date').value = toInputDate(new Date());

    // Efeito de brilho no botão
    openBtn.addEventListener('mousemove', (e) => {
        const rect = openBtn.getBoundingClientRect();
        const x = ((e.clientX - rect.left) / rect.width) * 100;
        openBtn.style.setProperty('--mx', x + '%');
    });

    // Event Listeners do Modal
    openBtn.addEventListener('click', openModal);
    modal.addEventListener('click', (e) => { if (e.target.hasAttribute('data-close-modal')) closeModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });

    // Carregar filtro de clientes
    async function loadClientFilter() {
        const clientSelect = document.getElementById('client_id');
        if (clientSelect.options.length > 1) return; // Já carregado

        try {
            const res = await fetch('?api=get_clients');
            const data = await res.json();
            if (data.clients) {
                data.clients.forEach(client => {
                    const option = new Option(client.company, client.id);
                    clientSelect.add(option);
                });
            }
        } catch (err) {
            console.error('Falha ao carregar clientes:', err);
        }
    }

    // Funções utilitárias
    function validateDates(s, e) { return s && e && new Date(s) <= new Date(e); }
    function currencyBRL(v) {
        try { return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0); } catch { return v; }
    }
    function escapeHtml(str) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(str).replace(/[&<>"']/g, s => map[s]);
    }

    // Constrói os parâmetros da URL a partir do formulário
    function getFormURLParams() {
        const formData = new FormData(form);
        const params = new URLSearchParams();
        for (const [key, value] of formData.entries()) {
            if (value) { // Adiciona apenas se houver valor
                params.append(key, value);
            }
        }
        return params;
    }

    // Lógica para GERAR RELATÓRIO NA TELA
    btnGenerate.addEventListener('click', () => generateReport(1));

    async function generateReport(page = 1) {
        if (!validateDates(form.start_date.value, form.end_date.value)) {
            alert('A data inicial não pode ser maior que a data final.');
            return;
        }

        btnGenerate.disabled = true;
        btnGenerate.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gerando...';

        try {
            const params = getFormURLParams();
            params.append('page', page);

            const res = await fetch(`?api=projects_report&${params.toString()}`);
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
            btnGenerate.innerHTML = '<i class="fas fa-magnifying-glass-chart"></i> Gerar Relatório';
        }
    }

    function renderReport(data) {
        const { projects, kpis } = data;
        const statusMap = { 'all': 'Todos', 'in_progress': 'Em andamento', 'completed': 'Concluídos', 'pending': 'Pendentes', 'cancelled': 'Cancelados' };

        reportContent.innerHTML = `
            <div class="report-header">
              <div class="report-header-content">
                <div>
                  <h2>Relatório de Projetos</h2>
                  <p>Período: ${form.start_date.value} a ${form.end_date.value} • Status: ${statusMap[form.status.value]}</p>
                </div>
                <div class="report-download-buttons">
                  <button type="button" id="btn-download-pdf" class="btn-download btn-download-pdf">
                    <i class="fas fa-file-pdf"></i> Baixar PDF
                  </button>
                  <button type="button" id="btn-download-csv" class="btn-download btn-download-csv">
                    <i class="fas fa-file-csv"></i> Baixar CSV
                  </button>
                </div>
              </div>
            </div>
            <div class="report-grid">
              <div class="kpi"><h4>Total de Projetos</h4><div class="value">${kpis.total_projects}</div></div>
              <div class="kpi"><h4>Receita Total</h4><div class="value">${currencyBRL(kpis.total_revenue)}</div></div>
              <div class="kpi"><h4>Status</h4><div class="value">${kpis.in_progress_count} em andamento • ${kpis.completed_count} concluídos</div></div>
            </div>
            <div class="report-table-wrapper">
              <table class="report-table">
                <thead>
                  <tr><th>Projeto</th><th>Cliente</th><th>Status</th><th>Valor</th><th>Data</th></tr>
                </thead>
                <tbody>
                  ${projects.length === 0
                    ? '<tr><td colspan="5" style="text-align:center;padding:20px;">Nenhum projeto encontrado para os filtros selecionados.</td></tr>'
                    : projects.map(p => `
                    <tr>
                      <td>${escapeHtml(p.project_name || '-')}</td>
                      <td>${escapeHtml(p.company_name || '-')}</td>
                      <td><span class="badge ${p.status}">${p.status.replace('_', ' ')}</span></td>
                      <td>${currencyBRL(p.total_amount)} (${p.currency})</td>
                      <td>${new Date(p.created_at).toLocaleDateString('pt-BR')}</td>
                    </tr>`).join('')}
                </tbody>
              </table>
            </div>
        `;
    }

    function attachDownloadButtons() {
        const btnPDF = document.getElementById('btn-download-pdf');
        const btnCSV = document.getElementById('btn-download-csv');
        
        if (btnPDF) {
            btnPDF.addEventListener('click', () => handleDownload('pdf'));
        }
        if (btnCSV) {
            btnCSV.addEventListener('click', () => handleDownload('csv'));
        }
    }

    function handleDownload(format) {
        if (!validateDates(form.start_date.value, form.end_date.value)) {
            alert('A data inicial não pode ser maior que a data final.');
            return;
        }

        const params = getFormURLParams();
        
        let downloadUrl;
        if (format === 'pdf') {
            downloadUrl = `../dash-t101/generate_pdf_report.php?${params.toString()}`;
        } else {
            downloadUrl = `../dash-t101/generate_csv_report.php?${params.toString()}`;
        }

        // Abrir em nova aba para download
        window.open(downloadUrl, '_blank');
        
        // Mostrar notificação
        showNotification(`Relatório ${format.toUpperCase()} está sendo gerado...`);
    }

    function showNotification(message) {
        const notification = document.createElement('div');
        notification.className = 'download-notification';
        notification.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
        document.body.appendChild(notification);
        
        setTimeout(() => notification.classList.add('show'), 100);
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 400);
        }, 3000);
    }

    function renderPagination({ current_page, total_pages, total_items }) {
        if (total_pages <= 1) {
            reportPagination.innerHTML = '';
            return;
        }

        let html = '';
        if (current_page > 1) {
            html += `<a href="#" class="page-btn" data-page="${current_page - 1}">&laquo; Anterior</a>`;
        } else {
            html += `<span class="page-btn disabled">&laquo; Anterior</span>`;
        }

        html += `<span class="page-info">Página ${current_page} de ${total_pages}</span>`;

        if (current_page < total_pages) {
            html += `<a href="#" class="page-btn" data-page="${current_page + 1}">Próxima &raquo;</a>`;
        } else {
            html += `<span class="page-btn disabled">Próxima &raquo;</span>`;
        }

        reportPagination.innerHTML = html;
    }

    reportPagination.addEventListener('click', (e) => {
        e.preventDefault();
        const target = e.target.closest('.page-btn');
        if (target && !target.classList.contains('disabled')) {
            const page = parseInt(target.dataset.page, 10);
            generateReport(page);
        }
    });

})();
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>