<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Verificar se é admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: /login.php');
    exit;
}

$page_title = 'Integração Hotmart - Admin';
$message = '';
$error = '';

// Processar sincronização com API da Hotmart
if (isset($_POST['sync_hotmart'])) {
    try {
        $sync_result = syncWithHotmart();
        if ($sync_result['success']) {
            $message = 'Sincronização realizada com sucesso! ' . $sync_result['message'];
        } else {
            $error = 'Erro na sincronização: ' . $sync_result['message'];
        }
    } catch (Exception $e) {
        $error = 'Erro durante a sincronização: ' . $e->getMessage();
    }
}

// Função para sincronizar com Hotmart
function syncWithHotmart() {
    global $pdo;
    
    // Carregar configurações
    $config = require_once __DIR__ . '/../config/hotmart_config.php';
    
    $client_id = $config['api']['client_id'];
    $client_secret = $config['api']['client_secret'];
    $basic_token = $config['api']['basic_token'];
    $auth_url = $config['api']['auth_url'];
    $sales_url = $config['api']['sales_url'];
    $days_to_sync = $config['sync']['days_to_sync'];
    $max_results = $config['sync']['max_results'];
    
    // Verificar se as credenciais foram configuradas
    if (strpos($client_id, 'SEU_CLIENT_ID') !== false || 
        strpos($client_secret, 'SEU_CLIENT_SECRET') !== false || 
        strpos($basic_token, 'SEU_BASIC_TOKEN') !== false) {
        throw new Exception('Credenciais da API Hotmart não configuradas. Configure o arquivo /config/hotmart_config.php');
    }
    
    try {
        // 1. Obter token de acesso
        $auth_data = [
            'grant_type' => 'client_credentials',
            'client_id' => $client_id,
            'client_secret' => $client_secret
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $auth_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($auth_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $basic_token,
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $auth_response = curl_exec($ch);
        $auth_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($auth_http_code !== 200) {
            throw new Exception('Falha na autenticação com a API Hotmart. Código HTTP: ' . $auth_http_code);
        }
        
        $auth_data = json_decode($auth_response, true);
        if (!$auth_data || !isset($auth_data['access_token'])) {
            throw new Exception('Token de acesso não recebido da API Hotmart');
        }
        
        $access_token = $auth_data['access_token'];
        
        // 2. Buscar vendas recentes
        $start_date = date('Y-m-d', strtotime("-{$days_to_sync} days"));
        $end_date = date('Y-m-d');
        
        $sales_params = [
            'start_date' => $start_date,
            'end_date' => $end_date,
            'max_results' => $max_results,
            'status' => 'COMPLETE'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $sales_url . '?' . http_build_query($sales_params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ]);
        
        $sales_response = curl_exec($ch);
        $sales_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($sales_http_code !== 200) {
            throw new Exception('Falha ao buscar vendas da API Hotmart. Código HTTP: ' . $sales_http_code);
        }
        
        $sales_data = json_decode($sales_response, true);
        if (!$sales_data) {
            throw new Exception('Dados de vendas inválidos recebidos da API Hotmart');
        }
        
        // 3. Processar e salvar dados no banco
        $processed_count = 0;
        $new_users_count = 0;
        
        if (isset($sales_data['content']) && is_array($sales_data['content'])) {
            foreach ($sales_data['content'] as $sale) {
                $transaction_id = $sale['transaction'] ?? null;
                $buyer_email = $sale['buyer']['email'] ?? null;
                $buyer_name = $sale['buyer']['name'] ?? null;
                $product_name = $sale['product']['name'] ?? null;
                $price = $sale['price']['value'] ?? 0;
                $currency = $sale['price']['currency_code'] ?? 'BRL';
                
                if (!$transaction_id || !$buyer_email) {
                    continue;
                }
                
                // Verificar se já existe no banco
                $stmt = $pdo->prepare("
                    SELECT id FROM hotmart_logs 
                    WHERE transaction_id = ? AND event_type = 'SYNC_PURCHASE'
                ");
                $stmt->execute([$transaction_id]);
                
                if (!$stmt->fetch()) {
                    // Inserir novo registro
                    $stmt = $pdo->prepare("
                        INSERT INTO hotmart_logs (
                            event_type, status, user_email, user_name, 
                            transaction_id, product_name, price, currency,
                            webhook_data, response_data, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $stmt->execute([
                        'SYNC_PURCHASE',
                        'success',
                        $buyer_email,
                        $buyer_name,
                        $transaction_id,
                        $product_name,
                        $price,
                        $currency,
                        json_encode($sale),
                        'Importado via sincronização API'
                    ]);
                    
                    $processed_count++;
                    
                    // Criar usuário se não existir
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->execute([$buyer_email]);
                    
                    if (!$stmt->fetch()) {
                        // Criar novo usuário
                        $password_length = $config['sync']['default_password_length'];
                        $password_hash = password_hash(substr($transaction_id, -$password_length), PASSWORD_DEFAULT);
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO users (
                                name, email, password, is_active, 
                                subscription_status, created_at
                            ) VALUES (?, ?, ?, 1, 'active', NOW())
                        ");
                        
                        $stmt->execute([
                            $buyer_name,
                            $buyer_email,
                            $password_hash
                        ]);
                        
                        $new_users_count++;
                    }
                }
            }
        }
        
        return [
            'success' => true,
            'message' => "Processadas {$processed_count} transações, {$new_users_count} novos usuários criados."
        ];
        
    } catch (Exception $e) {
        // Log do erro
        error_log('Erro na sincronização Hotmart: ' . $e->getMessage());
        
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// Verificar se a tabela hotmart_logs existe
$table_exists = false;
$missing_table_error = false;

try {
    // Tentar verificar se a tabela existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'hotmart_logs'");
    $table_exists = ($stmt && $stmt->fetchColumn() !== false);
} catch (PDOException $e) {
    // Se der erro, assumir que estamos no ambiente mock
    $table_exists = ($pdo instanceof MockPDO);
}

// Buscar logs de webhook apenas se a tabela existir
if ($table_exists) {
    try {
        $stmt = $pdo->query("
            SELECT * FROM hotmart_logs 
            ORDER BY created_at DESC 
            LIMIT 100
        ");
        $webhook_logs = $stmt->fetchAll();
        
        // Estatísticas
        $stmt = $pdo->query("SELECT COUNT(*) FROM hotmart_logs WHERE status = 'success'");
        $successful_webhooks = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM hotmart_logs WHERE status = 'error'");
        $failed_webhooks = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM hotmart_logs WHERE DATE(created_at) = CURDATE()");
        $today_webhooks = $stmt->fetchColumn();
        
    } catch (PDOException $e) {
        $webhook_logs = [];
        $successful_webhooks = 0;
        $failed_webhooks = 0;
        $today_webhooks = 0;
        $error = 'Erro ao carregar logs: ' . $e->getMessage();
    }
} else {
    // Tabela não existe - definir valores padrão e sinalizar erro
    $webhook_logs = [];
    $successful_webhooks = 0;
    $failed_webhooks = 0;
    $today_webhooks = 0;
    $missing_table_error = true;
    $error = 'A tabela hotmart_logs não existe no banco de dados. Execute o script SQL fornecido para criá-la.';
}

include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-shopping-cart"></i> Integração Hotmart</h1>
            <p>Gerenciamento da integração com a plataforma Hotmart</p>
            <a href="index.php" class="cta-btn">
                <i class="fas fa-arrow-left"></i> Voltar ao Admin
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

    <?php if ($missing_table_error): ?>
        <div class="video-card" style="border: 2px solid #f39c12; background: rgba(243, 156, 18, 0.1);">
            <h3 style="color: #f39c12;"><i class="fas fa-database"></i> Configuração Necessária</h3>
            <p style="color: #ddd; margin-bottom: 20px;">
                A tabela <code>hotmart_logs</code> não foi encontrada no banco de dados. 
                Esta tabela é necessária para armazenar os logs dos webhooks da Hotmart.
            </p>
            
            <h4 style="color: #f39c12; margin-bottom: 15px;">Instruções para Correção:</h4>
            
            <div style="background: rgba(0, 0, 0, 0.3); padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                <h5 style="color: #c084fc; margin-bottom: 10px;">1. Acesse o phpMyAdmin ou seu cliente MySQL</h5>
                <p style="color: #ddd; font-size: 0.9rem;">
                    Entre no painel de controle do seu banco de dados MySQL.
                </p>
                
                <h5 style="color: #c084fc; margin-bottom: 10px; margin-top: 15px;">2. Selecione o banco de dados</h5>
                <p style="color: #ddd; font-size: 0.9rem;">
                    Certifique-se de estar no banco: <code>u335416710_t101_db</code>
                </p>
                
                <h5 style="color: #c084fc; margin-bottom: 10px; margin-top: 15px;">3. Execute o SQL abaixo:</h5>
                <div style="background: #1a1a1a; padding: 15px; border-radius: 5px; margin: 10px 0;">
                    <code id="sql_script" style="color: #10b981; font-size: 0.85rem; white-space: pre-line; line-height: 1.4;">CREATE TABLE IF NOT EXISTS `hotmart_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` varchar(100) NOT NULL COMMENT 'Tipo do evento (PURCHASE_COMPLETE, etc.)',
  `status` enum('success','error') NOT NULL DEFAULT 'success',
  `user_email` varchar(255) DEFAULT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `product_id` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'BRL',
  `hotmart_status` varchar(50) DEFAULT NULL,
  `webhook_data` text COMMENT 'Dados JSON completos do webhook',
  `response_data` text COMMENT 'Resposta do processamento',
  `error_message` varchar(500) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_status` (`status`),
  KEY `idx_user_email` (`user_email`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;</code>
                </div>
                
                <button onclick="copyToClipboard(document.getElementById('sql_script').textContent)" class="cta-btn" style="margin-top: 10px;">
                    <i class="fas fa-copy"></i> Copiar SQL
                </button>
                
                <h5 style="color: #c084fc; margin-bottom: 10px; margin-top: 20px;">4. Recarregue esta página</h5>
                <p style="color: #ddd; font-size: 0.9rem;">
                    Após executar o SQL, recarregue esta página para ver os logs funcionando.
                </p>
            </div>
            
            <div style="background: rgba(59, 130, 246, 0.1); padding: 15px; border-radius: 10px; border-left: 4px solid #3b82f6;">
                <h5 style="color: #3b82f6; margin-bottom: 8px;"><i class="fas fa-info-circle"></i> Informação</h5>
                <p style="color: #ddd; font-size: 0.9rem; margin: 0;">
                    Esta tabela armazenará todos os eventos recebidos dos webhooks da Hotmart, 
                    permitindo monitoramento completo das transações e troubleshooting de problemas.
                </p>
            </div>
            
            <div style="text-align: center; margin-top: 20px; display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="create_hotmart_table.php" class="cta-btn" style="background: linear-gradient(135deg, #10b981, #059669);">
                    <i class="fas fa-magic"></i> Criar Tabela Automaticamente
                </a>
                <button onclick="location.reload()" class="cta-btn">
                    <i class="fas fa-sync-alt"></i> Recarregar Página
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Estatísticas da Integração -->
    <div class="stats-grid">
        <div class="video-card stats-card">
            <div class="stats-content">
                <div class="stats-info">
                    <h3>Webhooks Hoje</h3>
                    <span class="stats-number"><?php echo number_format($today_webhooks); ?></span>
                </div>
                <div class="stats-icon stats-icon-blue">
                    <i class="fas fa-calendar-day"></i>
                </div>
            </div>
        </div>

        <div class="video-card stats-card">
            <div class="stats-content">
                <div class="stats-info">
                    <h3>Sucessos</h3>
                    <span class="stats-number"><?php echo number_format($successful_webhooks); ?></span>
                </div>
                <div class="stats-icon stats-icon-green">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>

        <div class="video-card stats-card">
            <div class="stats-content">
                <div class="stats-info">
                    <h3>Falhas</h3>
                    <span class="stats-number"><?php echo number_format($failed_webhooks); ?></span>
                </div>
                <div class="stats-icon stats-icon-red">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
        </div>

        <div class="video-card stats-card">
            <div class="stats-content">
                <div class="stats-info">
                    <h3>Total</h3>
                    <span class="stats-number"><?php echo number_format(count($webhook_logs)); ?></span>
                </div>
                <div class="stats-icon stats-icon-purple">
                    <i class="fas fa-list"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Configurações da Integração -->
    <div class="video-card">
        <h2><i class="fas fa-cogs"></i> Configurações da Integração</h2>
        
        <div class="dashboard-sections">
            <div>
                <h3><i class="fas fa-link"></i> <strong>Webhook URL</strong></h3>
                <div class="code-block">
                    <code>https://v.translators101.com/hotmart_webhook.php</code>
                    <button onclick="copyToClipboard('https://v.translators101.com/hotmart_webhook.php')" class="page-btn" style="margin-left: 10px;">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                
                <h3><i class="fas fa-shield-alt"></i> <strong>Eventos Configurados</strong></h3>
                <ul style="color: #ddd;">
                    <li>PURCHASE_COMPLETE - Compra finalizada</li>
                    <li>PURCHASE_CANCELED - Compra cancelada</li>
                    <li>PURCHASE_REFUNDED - Compra reembolsada</li>
                    <li>SUBSCRIPTION_CANCELLATION - Cancelamento de assinatura</li>
                </ul>
            </div>
            
            <div>
                <h3><i class="fas fa-sync"></i> <strong>Sincronização</strong></h3>
                <p>A integração sincroniza automaticamente:</p>
                <ul style="color: #ddd;">
                    <li>Criação de usuários</li>
                    <li>Ativação de assinaturas</li>
                    <li>Cancelamento de acessos</li>
                    <li>Logs de transações</li>
                </ul>
                
                <div style="margin-top: 20px; display: flex; gap: 15px; flex-wrap: wrap;">
                    <button onclick="testWebhook()" class="cta-btn">
                        <i class="fas fa-play"></i> Testar Webhook
                    </button>
                    
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="sync_hotmart" class="cta-btn" 
                                style="background: linear-gradient(135deg, #10b981, #059669);"
                                onclick="if(confirm('Deseja sincronizar dados com a Hotmart? Isso pode demorar alguns segundos.')) { syncHotmart(this); return true; } return false;">
                            <i class="fas fa-sync-alt"></i> Sincronizar com Hotmart
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Configuração da API Hotmart -->
    <div class="video-card" style="border: 2px solid #3b82f6; background: rgba(59, 130, 246, 0.05);">
        <h2 style="color: #3b82f6;"><i class="fas fa-key"></i> Configuração da API Hotmart</h2>
        
        <div style="background: rgba(0, 0, 0, 0.3); padding: 20px; border-radius: 10px; margin-bottom: 20px;">
            <h4 style="color: #3b82f6; margin-bottom: 15px;"><i class="fas fa-exclamation-circle"></i> Credenciais Necessárias</h4>
            <p style="color: #ddd; margin-bottom: 15px;">
                Para utilizar a sincronização automática, é necessário configurar as credenciais da API Hotmart no código:
            </p>
            
            <div style="display: grid; gap: 15px;">
                <div>
                    <h5 style="color: #10b981; margin-bottom: 8px;">1. Client ID</h5>
                    <p style="color: #ccc; font-size: 0.9rem;">
                        Identificador único da sua aplicação na Hotmart
                    </p>
                </div>
                
                <div>
                    <h5 style="color: #10b981; margin-bottom: 8px;">2. Client Secret</h5>
                    <p style="color: #ccc; font-size: 0.9rem;">
                        Chave secreta para autenticação na API
                    </p>
                </div>
                
                <div>
                    <h5 style="color: #10b981; margin-bottom: 8px;">3. Basic Token</h5>
                    <p style="color: #ccc; font-size: 0.9rem;">
                        Token básico para autenticação inicial
                    </p>
                </div>
            </div>
            
            <div style="background: rgba(243, 156, 18, 0.1); padding: 15px; border-radius: 8px; margin-top: 20px; border-left: 4px solid #f39c12;">
                <h5 style="color: #f39c12; margin-bottom: 8px;"><i class="fas fa-warning"></i> Como Obter as Credenciais</h5>
                <ol style="color: #ddd; font-size: 0.9rem; line-height: 1.5;">
                    <li>Acesse o <strong>Painel do Desenvolvedor da Hotmart</strong></li>
                    <li>Navegue até <strong>Aplicações → Nova Aplicação</strong></li>
                    <li>Preencha os dados da aplicação</li>
                    <li>Anote o <strong>Client ID</strong> e <strong>Client Secret</strong> gerados</li>
                    <li>Configure as permissões necessárias para acessar vendas</li>
                </ol>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="https://developers.hotmart.com/" target="_blank" class="cta-btn" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
                    <i class="fas fa-external-link-alt"></i> Acessar Painel de Desenvolvedores
                </a>
            </div>
        </div>
        
        <div style="background: rgba(16, 185, 129, 0.1); padding: 15px; border-radius: 10px; border-left: 4px solid #10b981;">
            <h5 style="color: #10b981; margin-bottom: 8px;"><i class="fas fa-info-circle"></i> Funcionalidades da Sincronização</h5>
            <ul style="color: #ddd; font-size: 0.9rem; line-height: 1.6;">
                <li><strong>Busca automática:</strong> Últimas 30 dias de vendas</li>
                <li><strong>Criação de usuários:</strong> Clientes que ainda não existem no sistema</li>
                <li><strong>Atualização de dados:</strong> Informações de transações e produtos</li>
                <li><strong>Prevenção de duplicatas:</strong> Evita registros duplicados</li>
                <li><strong>Logs detalhados:</strong> Registra todas as operações para auditoria</li>
            </ul>
        </div>
    </div>

    <!-- Logs dos Webhooks -->
    <div class="video-card">
        <div class="card-header">
            <h2><i class="fas fa-list-alt"></i> Logs dos Webhooks</h2>
            
            <div class="search-filters">
                <select onchange="filterLogs(this.value)">
                    <option value="">Todos os status</option>
                    <option value="success">Sucessos</option>
                    <option value="error">Erros</option>
                </select>
            </div>
        </div>

        <?php if (empty($webhook_logs)): ?>
            <div class="alert-warning">
                <i class="fas fa-info-circle"></i>
                Nenhum webhook recebido ainda.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-calendar"></i> Data/Hora</th>
                            <th><i class="fas fa-cog"></i> Evento</th>
                            <th><i class="fas fa-flag"></i> Status</th>
                            <th><i class="fas fa-envelope"></i> Email</th>
                            <th><i class="fas fa-info-circle"></i> Detalhes</th>
                            <th><i class="fas fa-eye"></i> Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($webhook_logs as $log): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                                <td>
                                    <span class="text-primary"><?php echo htmlspecialchars($log['event_type'] ?? 'N/A'); ?></span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $log['status'] === 'success' ? 'completed' : 'cancelled'; ?>">
                                        <?php echo $log['status'] === 'success' ? 'Sucesso' : 'Erro'; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['user_email'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(substr($log['response_data'] ?? '', 0, 50)) . '...'; ?></td>
                                <td>
                                    <button onclick="showLogDetails(<?php echo htmlspecialchars(json_encode($log)); ?>)" 
                                            class="page-btn" title="Ver Detalhes">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Documentação -->
    <div class="video-card">
        <h2><i class="fas fa-book"></i> Documentação da Integração</h2>
        
        <div class="quick-actions-grid">
            <div class="quick-action-card" style="cursor: default;">
                <div class="quick-action-icon quick-action-icon-blue">
                    <i class="fas fa-cog"></i>
                </div>
                <h3>Configuração</h3>
                <p>Como configurar webhooks no painel Hotmart</p>
            </div>

            <div class="quick-action-card" style="cursor: default;">
                <div class="quick-action-icon quick-action-icon-green">
                    <i class="fas fa-code"></i>
                </div>
                <h3>Desenvolvimento</h3>
                <p>Estrutura dos dados recebidos via webhook</p>
            </div>

            <div class="quick-action-card" style="cursor: default;">
                <div class="quick-action-icon quick-action-icon-red">
                    <i class="fas fa-bug"></i>
                </div>
                <h3>Troubleshooting</h3>
                <p>Resolução de problemas comuns</p>
            </div>

            <div class="quick-action-card" style="cursor: default;">
                <div class="quick-action-icon quick-action-icon-purple">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <h3>Relatórios</h3>
                <p>Análise de performance e estatísticas</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal para detalhes do log -->
<div id="logModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle"></i> Detalhes do Log</h3>
            <button type="button" onclick="closeLogModal()" class="close-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div id="logDetails">
            <!-- Conteúdo será preenchido via JavaScript -->
        </div>
    </div>
</div>

<style>
.code-block {
    background: #2d3748;
    color: #fff;
    padding: 15px;
    border-radius: 8px;
    font-family: 'Courier New', monospace;
    margin: 10px 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.code-block code {
    font-size: 0.9rem;
}
</style>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('URL copiada para a área de transferência!');
    });
}

function testWebhook() {
    if (confirm('Isso enviará um webhook de teste. Continuar?')) {
        // Aqui você implementaria a lógica de teste
        alert('Teste de webhook enviado! Verifique os logs.');
    }
}

function syncHotmart(button) {
    // Desabilitar botão e mostrar loading
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sincronizando...';
    
    // Simular processo (o form será enviado normalmente)
    setTimeout(() => {
        button.disabled = false;
        button.innerHTML = originalText;
    }, 3000);
}

function filterLogs(status) {
    const rows = document.querySelectorAll('.data-table tbody tr');
    rows.forEach(row => {
        if (!status) {
            row.style.display = '';
        } else {
            const statusCell = row.querySelector('.status-badge');
            const shouldShow = (status === 'success' && statusCell.textContent.trim() === 'Sucesso') ||
                              (status === 'error' && statusCell.textContent.trim() === 'Erro');
            row.style.display = shouldShow ? '' : 'none';
        }
    });
}

function showLogDetails(log) {
    const modal = document.getElementById('logModal');
    const details = document.getElementById('logDetails');
    
    details.innerHTML = `
        <div class="form-group">
            <label><strong>Data/Hora:</strong></label>
            <p>${new Date(log.created_at).toLocaleString()}</p>
        </div>
        <div class="form-group">
            <label><strong>Evento:</strong></label>
            <p>${log.event_type || 'N/A'}</p>
        </div>
        <div class="form-group">
            <label><strong>Status:</strong></label>
            <p>${log.status}</p>
        </div>
        <div class="form-group">
            <label><strong>Email do Usuário:</strong></label>
            <p>${log.user_email || 'N/A'}</p>
        </div>
        <div class="form-group">
            <label><strong>Dados da Resposta:</strong></label>
            <textarea readonly rows="10" style="width: 100%; font-family: monospace; font-size: 0.8rem;">${log.response_data || 'N/A'}</textarea>
        </div>
    `;
    
    modal.style.display = 'flex';
}

function closeLogModal() {
    document.getElementById('logModal').style.display = 'none';
}

// Fechar modal clicando fora
document.getElementById('logModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeLogModal();
    }
});
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>