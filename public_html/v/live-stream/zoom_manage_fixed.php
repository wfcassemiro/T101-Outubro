<?php
/**
 * Painel de Gerenciamento de Reuniões do Zoom - VERSÃO CORRIGIDA
 * Apenas administradores podem acessar
 */

session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/zoom_functions_fixed.php';

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

// Verificar acesso
if (!isLoggedIn()) {
    header('Location: /auth/login.php');
    exit;
}

if (!isAdmin()) {
    die('<h1>Acesso Negado</h1><p>Apenas administradores podem acessar esta página.</p>');
}

// Criar tabela se não existir
createZoomMeetingsTable();

$message = '';
$messageType = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'test_auth':
            $result = testZoomAuth();
            if ($result['success']) {
                $message = '✓ Autenticação funcionando! Usuário: ' . ($result['user']['email'] ?? 'N/A');
                $messageType = 'success';
            } else {
                $message = '✗ Erro na autenticação: ' . $result['message'];
                $messageType = 'error';
            }
            break;
            
        case 'create':
            $result = createZoomMeeting(
                $_POST['topic'],
                $_POST['start_time'],
                $_POST['duration'],
                $_POST['agenda'] ?? '',
                $_POST['timezone'] ?? 'America/Sao_Paulo'
            );
            
            if ($result['success']) {
                $message = '✓ Reunião criada com sucesso! ID: ' . $result['meeting']['id'];
                $messageType = 'success';
            } else {
                $message = '✗ Erro ao criar reunião: ' . $result['error'];
                $messageType = 'error';
            }
            break;
            
        case 'add_existing':
            $result = addExistingMeeting($_POST['meeting_id_or_url']);
            
            if ($result['success']) {
                $message = '✓ Reunião adicionada com sucesso!';
                $messageType = 'success';
            } else {
                $message = '✗ Erro ao adicionar reunião: ' . $result['error'];
                $messageType = 'error';
            }
            break;
            
        case 'delete':
            $result = deleteZoomMeeting($_POST['meeting_id']);
            
            if ($result['success']) {
                $message = '✓ Reunião deletada com sucesso!';
                $messageType = 'success';
            } else {
                $message = '✗ Erro ao deletar reunião: ' . $result['error'];
                $messageType = 'error';
            }
            break;
            
        case 'sync':
            $result = syncZoomMeetings();
            
            if ($result['success']) {
                $message = "✓ {$result['synced']} reuniões sincronizadas!";
                $messageType = 'success';
            } else {
                $message = '✗ Erro ao sincronizar: ' . $result['error'];
                $messageType = 'error';
            }
            break;
            
        case 'toggle_live':
            $meetingId = $_POST['meeting_id'];
            $showLive = $_POST['show_live'] == '1' ? 1 : 0;
            
            if (toggleMeetingLiveDisplay($meetingId, $showLive)) {
                $message = $showLive ? '✓ Reunião marcada para exibição no live!' : '✓ Reunião desmarcada';
                $messageType = 'success';
            } else {
                $message = '✗ Erro ao atualizar reunião';
                $messageType = 'error';
            }
            break;
    }
}

// Buscar reuniões ativas
$meetings = getActiveMeetingsFromDatabase(50);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Reuniões Zoom</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
        
        .header h1 {
            color: #2d3748;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #718096;
        }
        
        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
        
        .card h2 {
            color: #2d3748;
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
            margin-bottom: 8px;
            color: #2d3748;
            font-weight: 600;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #718096;
        }
        
        .btn-danger {
            background: #e53e3e;
        }
        
        .btn-success {
            background: #38a169;
        }
        
        .btn-small {
            padding: 8px 16px;
            font-size: 12px;
        }
        
        .meetings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .meeting-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            position: relative;
        }
        
        .meeting-card.live {
            border: 3px solid #48bb78;
            box-shadow: 0 0 20px rgba(72, 187, 120, 0.5);
        }
        
        .meeting-card h3 {
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .meeting-info {
            font-size: 14px;
            margin: 8px 0;
            opacity: 0.95;
        }
        
        .meeting-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .live-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #48bb78;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .live-badge::before {
            content: '';
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 50%;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        @media (max-width: 768px) {
            .two-columns {
                grid-template-columns: 1fr;
            }
            
            .meetings-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .log-link {
            display: inline-block;
            margin-top: 10px;
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }
        
        .log-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-video"></i> Gerenciar Reuniões Zoom</h1>
            <p>Crie e gerencie reuniões do Zoom para exibição no live stream</p>
            <a href="index.php" style="color: #667eea; text-decoration: none; margin-top: 10px; display: inline-block;">
                <i class="fas fa-arrow-left"></i> Voltar para Live Stream
            </a>
        </div>
        
        <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <!-- Teste de Autenticação -->
        <div class="card">
            <h2><i class="fas fa-plug"></i> Testar Conexão com Zoom</h2>
            <p style="color: #718096; margin-bottom: 15px;">
                Clique no botão abaixo para testar se as credenciais do Zoom estão funcionando corretamente.
            </p>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="test_auth">
                <button type="submit" class="btn">
                    <i class="fas fa-bolt"></i> Testar Autenticação
                </button>
            </form>
            <a href="zoom_debug_log.txt" target="_blank" class="log-link">
                <i class="fas fa-file-alt"></i> Ver Log de Debug
            </a>
        </div>
        
        <div class="two-columns">
            <!-- Criar Nova Reunião -->
            <div class="card">
                <h2><i class="fas fa-plus-circle"></i> Criar Nova Reunião</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-group">
                        <label for="topic">Título da Reunião *</label>
                        <input type="text" id="topic" name="topic" required>
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
                        <label for="agenda">Descrição/Agenda</label>
                        <textarea id="agenda" name="agenda"></textarea>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-calendar-plus"></i> Criar Reunião
                    </button>
                </form>
            </div>
            
            <!-- Adicionar Reunião Existente -->
            <div class="card">
                <h2><i class="fas fa-link"></i> Adicionar Reunião Existente</h2>
                <p style="color: #718096; margin-bottom: 20px;">
                    Cole o ID ou URL de uma reunião já criada no Zoom para adicioná-la ao sistema.
                </p>
                <form method="POST">
                    <input type="hidden" name="action" value="add_existing">
                    
                    <div class="form-group">
                        <label for="meeting_id_or_url">ID ou URL da Reunião *</label>
                        <input type="text" id="meeting_id_or_url" name="meeting_id_or_url" 
                               placeholder="Ex: 1234567890 ou https://zoom.us/j/1234567890" required>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-plus"></i> Adicionar Reunião
                    </button>
                </form>
                
                <hr style="margin: 30px 0; border: none; border-top: 1px solid #e2e8f0;">
                
                <!-- Sincronizar Reuniões -->
                <h2 style="font-size: 18px; margin-bottom: 15px;">
                    <i class="fas fa-sync"></i> Sincronizar Reuniões
                </h2>
                <p style="color: #718096; margin-bottom: 15px; font-size: 14px;">
                    Importar todas as reuniões agendadas da sua conta Zoom.
                </p>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="sync">
                    <button type="submit" class="btn btn-secondary">
                        <i class="fas fa-sync-alt"></i> Sincronizar Agora
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Lista de Reuniões -->
        <div class="card">
            <h2><i class="fas fa-list"></i> Reuniões Ativas (<?php echo count($meetings); ?>)</h2>
            
            <?php if (empty($meetings)): ?>
                <p style="color: #718096; text-align: center; padding: 40px;">
                    Nenhuma reunião encontrada. Crie uma nova reunião ou sincronize com o Zoom.
                </p>
            <?php else: ?>
                <div class="meetings-grid">
                    <?php foreach ($meetings as $meeting): ?>
                        <div class="meeting-card <?php echo $meeting['show_live'] ? 'live' : ''; ?>">
                            <?php if ($meeting['show_live']): ?>
                                <div class="live-badge">
                                    <span>AO VIVO</span>
                                </div>
                            <?php endif; ?>
                            
                            <h3><?php echo htmlspecialchars($meeting['topic']); ?></h3>
                            
                            <div class="meeting-info">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('d/m/Y', strtotime($meeting['start_time'])); ?>
                            </div>
                            
                            <div class="meeting-info">
                                <i class="fas fa-clock"></i>
                                <?php echo date('H:i', strtotime($meeting['start_time'])); ?> 
                                (<?php echo $meeting['duration']; ?> min)
                            </div>
                            
                            <div class="meeting-info">
                                <i class="fas fa-hashtag"></i>
                                ID: <?php echo $meeting['meeting_id']; ?>
                            </div>
                            
                            <?php if ($meeting['agenda']): ?>
                                <div class="meeting-info" style="margin-top: 10px; opacity: 0.9;">
                                    <?php echo htmlspecialchars($meeting['agenda']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="meeting-actions">
                                <a href="<?php echo htmlspecialchars($meeting['join_url']); ?>" 
                                   target="_blank" 
                                   class="btn btn-success btn-small">
                                    <i class="fas fa-video"></i> Entrar
                                </a>
                                
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_live">
                                    <input type="hidden" name="meeting_id" value="<?php echo $meeting['meeting_id']; ?>">
                                    <input type="hidden" name="show_live" value="<?php echo $meeting['show_live'] ? '0' : '1'; ?>">
                                    <button type="submit" class="btn btn-secondary btn-small">
                                        <i class="fas fa-<?php echo $meeting['show_live'] ? 'eye-slash' : 'eye'; ?>"></i>
                                        <?php echo $meeting['show_live'] ? 'Ocultar' : 'Exibir'; ?>
                                    </button>
                                </form>
                                
                                <form method="POST" style="display: inline;" 
                                      onsubmit="return confirm('Tem certeza que deseja deletar esta reunião?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="meeting_id" value="<?php echo $meeting['meeting_id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-small">
                                        <i class="fas fa-trash"></i> Deletar
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
