<?php
/**
 * Painel de Gerenciamento de Reuniões do Zoom
 * Apenas administradores podem acessar
 */

session_start();

// Conectar ao banco de dados usando o sistema existente
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/zoom_functions.php';

/**
 * Verificar se o usuário está logado e é admin
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Verificar no banco de dados se o usuário é admin
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        return $user && $user['role'] === 'admin';
    } catch (PDOException $e) {
        error_log("Erro ao verificar role do usuário: " . $e->getMessage());
        return false;
    }
}

// Verificar se está logado e é admin
if (!isLoggedIn()) {
    // Redirecionar para a página de login do sistema
    header('Location: /auth/login.php');
    exit;
}

if (!isAdmin()) {
    // Usuário logado mas não é admin
    die('<h1>Acesso Negado</h1><p>Apenas administradores podem acessar esta página.</p><a href="/">Voltar</a>');
}

// Criar tabela se não existir
createZoomMeetingsTable();

$message = '';
$messageType = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $result = createZoomMeeting(
                $_POST['topic'],
                $_POST['start_time'],
                $_POST['duration'],
                $_POST['agenda'] ?? '',
                $_POST['timezone'] ?? 'America/Sao_Paulo'
            );
            
            if ($result['success']) {
                $message = 'Reunião criada com sucesso! ID: ' . $result['meeting']['id'];
                $messageType = 'success';
            } else {
                $message = 'Erro ao criar reunião: ' . $result['error'];
                $messageType = 'error';
            }
            break;
            
        case 'add_existing':
            $result = addExistingMeeting($_POST['meeting_id_or_url']);
            
            if ($result['success']) {
                $message = 'Reunião adicionada com sucesso!';
                $messageType = 'success';
            } else {
                $message = 'Erro ao adicionar reunião: ' . $result['error'];
                $messageType = 'error';
            }
            break;
            
        case 'delete':
            $result = deleteZoomMeeting($_POST['meeting_id']);
            
            if ($result['success']) {
                $message = 'Reunião deletada com sucesso!';
                $messageType = 'success';
            } else {
                $message = 'Erro ao deletar reunião: ' . $result['error'];
                $messageType = 'error';
            }
            break;
            
        case 'sync':
            $result = getZoomMeeting($_POST['meeting_id']);
            
            if ($result['success']) {
                $message = 'Reunião sincronizada com sucesso!';
                $messageType = 'success';
            } else {
                $message = 'Erro ao sincronizar reunião: ' . $result['error'];
                $messageType = 'error';
            }
            break;
            
        case 'set_live':
            // Desativar todas as outras
            $pdo->exec("UPDATE zoom_meetings SET show_live = 0");
            
            // Ativar apenas esta
            $stmt = $pdo->prepare("UPDATE zoom_meetings SET show_live = 1 WHERE meeting_id = ?");
            $stmt->execute([$_POST['meeting_id']]);
            
            $message = 'Reunião configurada para exibição ao vivo!';
            $messageType = 'success';
            break;
            
        case 'remove_live':
            $stmt = $pdo->prepare("UPDATE zoom_meetings SET show_live = 0 WHERE meeting_id = ?");
            $stmt->execute([$_POST['meeting_id']]);
            
            $message = 'Reunião removida da exibição ao vivo!';
            $messageType = 'success';
            break;
    }
}

// Buscar reuniões ativas
$meetings = getActiveMeetingsFromDatabase(50);

// Incluir head do sistema
$page_title = "Gerenciar Reuniões Zoom";
include __DIR__ . '/../vision/includes/head.php';
?>

<!-- CSS Adicional para esta página -->
<style>
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
    }
    
    .zoom-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .zoom-header {
        background: rgba(255, 255, 255, 0.95);
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }
    
    .zoom-header h1 {
        color: #2d3748;
        font-size: 32px;
        margin-bottom: 10px;
    }
    
    .zoom-header p {
        color: #718096;
        font-size: 16px;
    }
    
    .message {
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-weight: 500;
    }
    
    .message.success {
        background: #c6f6d5;
        color: #22543d;
        border-left: 4px solid #38a169;
    }
    
    .message.error {
        background: #fed7d7;
        color: #742a2a;
        border-left: 4px solid #e53e3e;
    }
    
    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 30px;
        margin-bottom: 30px;
    }
    
    .card {
        background: rgba(255, 255, 255, 0.95);
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }
    
    .card h2 {
        color: #2d3748;
        font-size: 24px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        color: #4a5568;
        font-weight: 600;
        margin-bottom: 8px;
        font-size: 14px;
    }
    
    .form-group input,
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s ease;
        font-family: inherit;
    }
    
    .form-group input:focus,
    .form-group textarea:focus,
    .form-group select:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .form-group textarea {
        resize: vertical;
        min-height: 80px;
    }
    
    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-block;
        text-align: center;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }
    
    .btn-secondary {
        background: #4299e1;
        color: white;
    }
    
    .btn-secondary:hover {
        background: #3182ce;
        transform: translateY(-2px);
    }
    
    .btn-danger {
        background: #f56565;
        color: white;
        padding: 8px 16px;
        font-size: 13px;
    }
    
    .btn-danger:hover {
        background: #e53e3e;
    }
    
    .btn-info {
        background: #48bb78;
        color: white;
        padding: 8px 16px;
        font-size: 13px;
    }
    
    .btn-info:hover {
        background: #38a169;
    }
    
    .meetings-list {
        background: rgba(255, 255, 255, 0.95);
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }
    
    .meetings-list h2 {
        color: #2d3748;
        font-size: 24px;
        margin-bottom: 20px;
    }
    
    .meeting-item {
        background: #f7fafc;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 15px;
        border-left: 4px solid #667eea;
    }
    
    .meeting-item h3 {
        color: #2d3748;
        font-size: 18px;
        margin-bottom: 10px;
    }
    
    .meeting-info {
        color: #718096;
        font-size: 14px;
        margin-bottom: 5px;
    }
    
    .meeting-info strong {
        color: #4a5568;
    }
    
    .meeting-actions {
        margin-top: 15px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .meeting-link {
        display: inline-block;
        color: #4299e1;
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
        padding: 5px 10px;
        background: #ebf8ff;
        border-radius: 5px;
        transition: all 0.3s ease;
    }
    
    .meeting-link:hover {
        background: #bee3f8;
        transform: translateY(-1px);
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #a0aec0;
    }
    
    .info-box {
        background: #ebf8ff;
        border-left: 4px solid #4299e1;
        padding: 15px;
        margin-top: 20px;
        border-radius: 5px;
    }
    
    .info-box h3 {
        color: #2c5282;
        font-size: 16px;
        margin-bottom: 10px;
    }
    
    .info-box ul {
        color: #2d3748;
        font-size: 14px;
        line-height: 1.8;
        padding-left: 20px;
    }
    
    @media (max-width: 768px) {
        .grid {
            grid-template-columns: 1fr;
        }
        
        .meeting-actions {
            flex-direction: column;
        }
    }
</style>

<div class="zoom-container">
    <div class="zoom-header">
        <h1>🎥 Gerenciamento de Reuniões Zoom</h1>
        <p>Crie novas reuniões ou adicione reuniões existentes para exibir no site</p>
    </div>
    
    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="grid">
        <!-- Criar Nova Reunião -->
        <div class="card">
            <h2>📝 Criar Nova Reunião</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label for="topic">Título da Reunião *</label>
                    <input type="text" id="topic" name="topic" required placeholder="Ex: Palestra sobre Marketing Digital">
                </div>
                
                <div class="form-group">
                    <label for="start_time">Data e Hora de Início *</label>
                    <input type="datetime-local" id="start_time" name="start_time" required>
                </div>
                
                <div class="form-group">
                    <label for="duration">Duração (minutos) *</label>
                    <input type="number" id="duration" name="duration" value="60" min="15" max="480" required>
                </div>
                
                <div class="form-group">
                    <label for="timezone">Fuso Horário</label>
                    <select id="timezone" name="timezone">
                        <option value="America/Sao_Paulo" selected>Brasília (UTC-3)</option>
                        <option value="America/New_York">Nova York (UTC-5)</option>
                        <option value="Europe/London">Londres (UTC+0)</option>
                        <option value="Europe/Paris">Paris (UTC+1)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="agenda">Descrição/Agenda</label>
                    <textarea id="agenda" name="agenda" placeholder="Descreva o conteúdo da reunião..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">✨ Criar Reunião</button>
            </form>
        </div>
        
        <!-- Adicionar Reunião Existente -->
        <div class="card">
            <h2>➕ Adicionar Reunião Existente</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_existing">
                
                <div class="form-group">
                    <label for="meeting_id_or_url">ID ou Link da Reunião *</label>
                    <input type="text" id="meeting_id_or_url" name="meeting_id_or_url" required 
                           placeholder="Ex: 1234567890 ou https://zoom.us/j/1234567890">
                </div>
                
                <p style="color: #718096; font-size: 14px; margin-bottom: 20px;">
                    Cole o ID numérico da reunião ou o link completo do Zoom
                </p>
                
                <button type="submit" class="btn btn-secondary">🔗 Adicionar Reunião</button>
            </form>
            
            <div class="info-box">
                <h3>💡 Como encontrar o ID da reunião?</h3>
                <ul>
                    <li>Acesse seu painel do Zoom</li>
                    <li>Vá em "Reuniões" → "Próximas"</li>
                    <li>Copie o ID ou link da reunião</li>
                    <li>Cole aqui para adicionar ao site</li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Lista de Reuniões -->
    <div class="meetings-list">
        <h2>📋 Reuniões Agendadas (<?php echo count($meetings); ?>)</h2>
        
        <?php if (empty($meetings)): ?>
            <div class="empty-state">
                <div style="font-size: 64px; margin-bottom: 20px;">📅</div>
                <p>Nenhuma reunião agendada</p>
                <p style="font-size: 14px; margin-top: 10px;">Crie uma nova reunião ou adicione uma existente</p>
            </div>
        <?php else: ?>
            <?php foreach ($meetings as $meeting): ?>
                <div class="meeting-item">
                    <h3><?php echo htmlspecialchars($meeting['topic']); ?></h3>
                    
                    <div class="meeting-info">
                        <strong>ID:</strong> <?php echo htmlspecialchars($meeting['meeting_id']); ?>
                    </div>
                    
                    <div class="meeting-info">
                        <strong>Data/Hora:</strong> 
                        <?php echo date('d/m/Y \à\s H:i', strtotime($meeting['start_time'])); ?>
                    </div>
                    
                    <div class="meeting-info">
                        <strong>Duração:</strong> <?php echo $meeting['duration']; ?> minutos
                    </div>
                    
                    <?php if (!empty($meeting['agenda'])): ?>
                        <div class="meeting-info">
                            <strong>Agenda:</strong> <?php echo htmlspecialchars($meeting['agenda']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="meeting-info">
                        <strong>Status:</strong> <?php echo ucfirst($meeting['status']); ?>
                    </div>
                    
                    <div class="meeting-actions">
                        <a href="<?php echo htmlspecialchars($meeting['join_url']); ?>" 
                           target="_blank" 
                           class="meeting-link">
                            🔗 Link de Participante
                        </a>
                        
                        <?php if (!empty($meeting['start_url'])): ?>
                            <a href="<?php echo htmlspecialchars($meeting['start_url']); ?>" 
                               target="_blank" 
                               class="meeting-link">
                                🎬 Link de Host
                            </a>
                        <?php endif; ?>
                        
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="sync">
                            <input type="hidden" name="meeting_id" value="<?php echo $meeting['meeting_id']; ?>">
                            <button type="submit" class="btn btn-info">🔄 Sincronizar</button>
                        </form>
                        
                        <?php if (isset($meeting['show_live']) && $meeting['show_live'] == 1): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="remove_live">
                                <input type="hidden" name="meeting_id" value="<?php echo $meeting['meeting_id']; ?>">
                                <button type="submit" class="btn btn-success" style="background: #27ae60;">
                                    🔴 AO VIVO (Remover)
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="set_live">
                                <input type="hidden" name="meeting_id" value="<?php echo $meeting['meeting_id']; ?>">
                                <button type="submit" class="btn btn-info" style="background: #3498db;">
                                    📺 Colocar no Ar
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <form method="POST" style="display: inline;" 
                              onsubmit="return confirm('Deseja realmente deletar esta reunião?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="meeting_id" value="<?php echo $meeting['meeting_id']; ?>">
                            <button type="submit" class="btn btn-danger">🗑️ Deletar</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
// Definir data/hora mínima para agora
document.addEventListener('DOMContentLoaded', function() {
    const startTimeInput = document.getElementById('start_time');
    if (startTimeInput) {
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        startTimeInput.min = now.toISOString().slice(0, 16);
    }
});
</script>

<?php
// Incluir footer do sistema (se existir)
if (file_exists(__DIR__ . '/../vision/includes/footer.php')) {
    include __DIR__ . '/../vision/includes/footer.php';
}
?>