<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Verificar se é admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: /login.php');
    exit;
}

$page_title = 'Sistema de E-mails - Admin';
$message = '';
$error = '';

// Processar envio de email
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'send_email') {
        $recipient_type = $_POST['recipient_type'];
        $subject = trim($_POST['subject']);
        $message_body = trim($_POST['message']);
        
        if (empty($subject) || empty($message_body)) {
            $error = 'Assunto e mensagem são obrigatórios.';
        } else {
            try {
                // Buscar destinatários
                if ($recipient_type === 'all') {
                    $stmt = $pdo->query("SELECT email, name FROM users WHERE active = 1");
                } elseif ($recipient_type === 'subscribers') {
                    $stmt = $pdo->query("SELECT email, name FROM users WHERE active = 1 AND subscription_active = 1");
                } else {
                    $stmt = $pdo->query("SELECT email, name FROM users WHERE active = 1 AND subscription_active = 0");
                }
                
                $recipients = $stmt->fetchAll();
                
                // Simular envio (aqui você integraria com um sistema de email real)
                $sent_count = count($recipients);
                
                // Log do envio
                $stmt = $pdo->prepare("INSERT INTO email_logs (subject, message, recipient_count, sent_by, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$subject, $message_body, $sent_count, $_SESSION['user_id']]);
                
                $message = "E-mail enviado para $sent_count destinatários com sucesso!";
                
            } catch (PDOException $e) {
                $error = 'Erro ao enviar e-mail: ' . $e->getMessage();
            }
        }
    }
}

// Buscar logs de email
try {
    $stmt = $pdo->query("
        SELECT el.*, u.name as sender_name 
        FROM email_logs el 
        LEFT JOIN users u ON el.sent_by = u.id 
        ORDER BY el.created_at DESC 
        LIMIT 50
    ");
    $email_logs = $stmt->fetchAll();
} catch (PDOException $e) {
    $email_logs = [];
}

// Estatísticas de usuários
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE active = 1");
    $total_users = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE active = 1 AND subscription_active = 1");
    $subscribers = $stmt->fetchColumn();
    
    $non_subscribers = $total_users - $subscribers;
} catch (PDOException $e) {
    $total_users = 0;
    $subscribers = 0;
    $non_subscribers = 0;
}

include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-envelope"></i> Sistema de E-mails</h1>
            <p>Comunicação com usuários da plataforma</p>
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

    <!-- Estatísticas de Destinatários -->
    <div class="stats-grid">
        <div class="video-card stats-card">
            <div class="stats-content">
                <div class="stats-info">
                    <h3>Total de Usuários</h3>
                    <span class="stats-number"><?php echo number_format($total_users); ?></span>
                </div>
                <div class="stats-icon stats-icon-blue">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>

        <div class="video-card stats-card">
            <div class="stats-content">
                <div class="stats-info">
                    <h3>Assinantes</h3>
                    <span class="stats-number"><?php echo number_format($subscribers); ?></span>
                </div>
                <div class="stats-icon stats-icon-green">
                    <i class="fas fa-star"></i>
                </div>
            </div>
        </div>

        <div class="video-card stats-card">
            <div class="stats-content">
                <div class="stats-info">
                    <h3>Não Assinantes</h3>
                    <span class="stats-number"><?php echo number_format($non_subscribers); ?></span>
                </div>
                <div class="stats-icon stats-icon-red">
                    <i class="fas fa-user-times"></i>
                </div>
            </div>
        </div>

        <div class="video-card stats-card">
            <div class="stats-content">
                <div class="stats-info">
                    <h3>E-mails Enviados</h3>
                    <span class="stats-number"><?php echo count($email_logs); ?></span>
                </div>
                <div class="stats-icon stats-icon-purple">
                    <i class="fas fa-paper-plane"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="video-card">
        <h2><i class="fas fa-paper-plane"></i> Enviar E-mail</h2>
        
        <form method="POST" class="vision-form">
            <input type="hidden" name="action" value="send_email">
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="recipient_type">
                        <i class="fas fa-users"></i> Destinatários *
                    </label>
                    <select id="recipient_type" name="recipient_type" required>
                        <option value="all">Todos os usuários (<?php echo $total_users; ?>)</option>
                        <option value="subscribers">Apenas assinantes (<?php echo $subscribers; ?>)</option>
                        <option value="non_subscribers">Não assinantes (<?php echo $non_subscribers; ?>)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="subject">
                        <i class="fas fa-heading"></i> Assunto *
                    </label>
                    <input type="text" id="subject" name="subject" required>
                </div>

                <div class="form-group form-group-wide">
                    <label for="message">
                        <i class="fas fa-edit"></i> Mensagem *
                    </label>
                    <textarea id="message" name="message" rows="8" required 
                              placeholder="Digite sua mensagem aqui..."></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="cta-btn" onclick="return confirm('Tem certeza que deseja enviar este e-mail?')">
                    <i class="fas fa-paper-plane"></i> Enviar E-mail
                </button>
            </div>
        </form>
    </div>

    <div class="video-card">
        <div class="card-header">
            <h2><i class="fas fa-history"></i> Histórico de Envios</h2>
        </div>

        <?php if (empty($email_logs)): ?>
            <div class="alert-warning">
                <i class="fas fa-info-circle"></i>
                Nenhum e-mail enviado ainda.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-calendar"></i> Data</th>
                            <th><i class="fas fa-heading"></i> Assunto</th>
                            <th><i class="fas fa-users"></i> Destinatários</th>
                            <th><i class="fas fa-user"></i> Enviado por</th>
                            <th><i class="fas fa-eye"></i> Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($email_logs as $log): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                                <td>
                                    <span class="text-primary"><?php echo htmlspecialchars($log['subject']); ?></span>
                                </td>
                                <td><?php echo number_format($log['recipient_count']); ?></td>
                                <td><?php echo htmlspecialchars($log['sender_name'] ?? 'Usuário removido'); ?></td>
                                <td>
                                    <button class="page-btn" onclick="showEmailContent('<?php echo htmlspecialchars($log['message']); ?>')" title="Ver Conteúdo">
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

    <!-- Templates Pré-definidos -->
    <div class="video-card">
        <h2><i class="fas fa-file-alt"></i> Templates Sugeridos</h2>
        
        <div class="quick-actions-grid">
            <div class="quick-action-card" onclick="useTemplate('welcome')" style="cursor: pointer;">
                <div class="quick-action-icon quick-action-icon-blue">
                    <i class="fas fa-hand-wave"></i>
                </div>
                <h3>Boas-vindas</h3>
                <p>Para novos usuários</p>
            </div>

            <div class="quick-action-card" onclick="useTemplate('newsletter')" style="cursor: pointer;">
                <div class="quick-action-icon quick-action-icon-purple">
                    <i class="fas fa-newspaper"></i>
                </div>
                <h3>Newsletter</h3>
                <p>Novidades da plataforma</p>
            </div>

            <div class="quick-action-card" onclick="useTemplate('promotion')" style="cursor: pointer;">
                <div class="quick-action-icon quick-action-icon-green">
                    <i class="fas fa-percentage"></i>
                </div>
                <h3>Promoção</h3>
                <p>Ofertas especiais</p>
            </div>

            <div class="quick-action-card" onclick="useTemplate('reminder')" style="cursor: pointer;">
                <div class="quick-action-icon quick-action-icon-red">
                    <i class="fas fa-bell"></i>
                </div>
                <h3>Lembrete</h3>
                <p>Informações importantes</p>
            </div>
        </div>
    </div>
</div>

<script>
function showEmailContent(content) {
    alert('Conteúdo do E-mail:\n\n' + content);
}

function useTemplate(type) {
    const templates = {
        welcome: {
            subject: 'Bem-vindo(a) à Translators101!',
            message: 'Olá!\n\nSeja bem-vindo(a) à nossa plataforma educacional para profissionais de tradução!\n\nAqui você encontrará palestras exclusivas, glossários especializados e muito conteúdo para aprimorar suas habilidades profissionais.\n\nComece explorando nossa videoteca e não perca nenhuma novidade!\n\nEquipe Translators101'
        },
        newsletter: {
            subject: 'Translators101 - Novidades da Semana',
            message: 'Olá!\n\nConfira as principais novidades desta semana:\n\n• Nova palestra adicionada: [Título]\n• Glossário atualizado: [Área]\n• Certificados disponíveis para download\n\nAcesse nossa plataforma e aproveite todo o conteúdo!\n\nEquipe Translators101'
        },
        promotion: {
            subject: 'Oferta Especial - Translators101',
            message: 'Olá!\n\nTemos uma oferta especial para você!\n\n[Detalhes da promoção]\n\nEsta oferta é válida por tempo limitado. Não perca!\n\nAcesse nossa plataforma e aproveite.\n\nEquipe Translators101'
        },
        reminder: {
            subject: 'Lembrete Importante - Translators101',
            message: 'Olá!\n\nEste é um lembrete importante sobre:\n\n[Conteúdo do lembrete]\n\nPara mais informações, acesse nossa plataforma ou entre em contato conosco.\n\nEquipe Translators101'
        }
    };
    
    if (templates[type]) {
        document.getElementById('subject').value = templates[type].subject;
        document.getElementById('message').value = templates[type].message;
    }
}
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>