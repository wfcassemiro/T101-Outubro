<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Verificar se é admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: /login.php');
    exit;
}

$page_title = 'Painel Administrativo - Translators101';
$page_description = 'Administração da plataforma Translators101';

// Processar formulário de embed
$message = '';
$message_type = '';

if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_embed') {
        try {
            $embed_code = trim($_POST['embed_code']);

            $stmt = $pdo->prepare("SELECT id FROM site_settings WHERE setting_key = 'live_embed_code'");
            $stmt->execute();
            $existing = $stmt->fetch();

            if ($existing) {
                $stmt = $pdo->prepare("UPDATE site_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = 'live_embed_code'");
                $stmt->execute([$embed_code]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('live_embed_code', ?)");
                $stmt->execute([$embed_code]);
            }

            $message = 'Código de embed atualizado com sucesso!';
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = 'Erro ao atualizar código de embed: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Obter dados para os cards
try {
    $stmt = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'live_embed_code'");
    $current_embed = $stmt->fetchColumn() ?: '';

    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_lectures = $pdo->query("SELECT COUNT(*) FROM lectures")->fetchColumn();
    $total_certificates = $pdo->query("SELECT COUNT(*) FROM certificates")->fetchColumn();
    $total_glossaries = $pdo->query("SELECT COUNT(*) FROM glossary_files")->fetchColumn();
    $total_signups = $pdo->query("SELECT COUNT(*) FROM course_signups")->fetchColumn();
    $total_courses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
} catch (PDOException $e) {
    $current_embed = '';
    $total_users = $total_lectures = $total_certificates = $total_glossaries = $total_signups = $total_courses = 0;
    error_log("Erro ao buscar estatísticas: " . $e->getMessage());
}

// Formatação para exibição
$total_users_formatted = number_format($total_users);
$total_lectures_formatted = number_format($total_lectures);
$total_certificates_formatted = number_format($total_certificates);
$total_glossaries_formatted = number_format($total_glossaries);
$total_signups_formatted = number_format($total_signups);
$total_courses_formatted = number_format($total_courses);

include __DIR__ . '/../vision/includes/head.php';
?>
<style>
    /* Layout unificado para a grid de gerenciamento */
    .quick-actions-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr); /* Inicia com 4 colunas */
        gap: 20px;
        margin-top: 20px;
        margin-bottom: 30px;
    }

    .quick-action-card {
        text-decoration: none;
        color: inherit;
        padding: 20px 25px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: transform 0.3s ease, background 0.3s ease, box-shadow 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .quick-action-card:hover {
        transform: translateY(-5px);
        background: rgba(255, 255, 255, 0.1);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
    }

    .card-info {
        display: flex;
        align-items: center;
        gap: 18px;
    }

    .quick-action-icon {
        font-size: 28px;
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        color: #fff;
        flex-shrink: 0; /* Impede que o ícone encolha */
    }

    .quick-action-card h3 {
        margin: 0 0 5px 0;
        font-size: 1.15em;
        font-weight: 700;
        color: #fff;
    }

    .quick-action-card p {
        margin: 0;
        font-size: 0.9em;
        color: rgba(255, 255, 255, 0.7);
        line-height: 1.4;
    }
    
    .card-stat {
        font-size: 2.2em;
        font-weight: 700;
        color: #fff;
        opacity: 0.9;
        margin-left: 15px;
    }

    /* Cores dos ícones */
    .quick-action-icon-blue { background: linear-gradient(135deg, #007AFF, #0056CC); }
    .quick-action-icon-purple { background: linear-gradient(135deg, #AF52DE, #8A2BE2); }
    .quick-action-icon-green { background: linear-gradient(135deg, #34C759, #28A745); }
    .quick-action-icon-red { background: linear-gradient(135deg, #FF3B30, #DC3545); }
    .quick-action-icon-gold { background: linear-gradient(135deg, #f39c12, #e67e22); }
    .quick-action-icon-orange { background: linear-gradient(135deg, #e67e22, #d35400); }

    /* Gerenciamento de Live Stream */
    .embed-container-wrapper { display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-start; }
    .embed-form, .embed-preview { flex: 1; min-width: 300px; background: rgba(255, 255, 255, 0.05); padding: 20px; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.1); }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #fff; }
    .form-control { width: 100%; padding: 12px; border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 8px; background: rgba(255, 255, 255, 0.1); color: #fff; font-family: 'Courier New', monospace; resize: vertical; }
    .form-control:focus { outline: none; border-color: #007AFF; box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.2); }
    .form-help { display: block; margin-top: 5px; color: rgba(255, 255, 255, 0.7); font-size: 0.9em; }
    .form-actions { display: flex; gap: 10px; margin-top: 20px; }
    .btn { padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
    .btn-primary { background: linear-gradient(135deg, #007AFF, #0056CC); color: white; }
    .btn-primary:hover { background: linear-gradient(135deg, #0056CC, #003D99); transform: translateY(-2px); }
    .btn-secondary { background: rgba(255, 255, 255, 0.1); color: white; border: 1px solid rgba(255, 255, 255, 0.2); }
    .btn-secondary:hover { background: rgba(255, 255, 255, 0.2); transform: translateY(-2px); }
    .embed-preview h3 { margin-top: 0; }
    .embed-container { position: relative; width: 100%; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 8px; }
    .embed-container iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none; }
    .success-message { background: linear-gradient(135deg, rgba(52, 199, 89, 0.2), rgba(52, 199, 89, 0.1)); border: 1px solid rgba(52, 199, 89, 0.3); color: #34C759; margin-bottom: 20px; }
    .error-message { background: linear-gradient(135deg, rgba(255, 59, 48, 0.2), rgba(255, 59, 48, 0.1)); border: 1px solid rgba(255, 59, 48, 0.3); color: #FF3B30; margin-bottom: 20px; }
    .success-message p, .error-message p { margin: 0; display: flex; align-items: center; gap: 10px; }
    hr { border: 0; height: 1px; background-color: rgba(255, 255, 255, 0.1); margin: 30px 0; }

    .video-card h2 {
    padding-left: 20px; /* Adiciona um respiro à esquerda do título */
    }
    
    /* Responsividade */
    @media (max-width: 1400px) {
        .quick-actions-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 1024px) {
        .quick-actions-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 768px) {
        .quick-actions-grid { grid-template-columns: 1fr; }
        .card-info { flex-direction: column; align-items: flex-start; }
        .quick-action-card { align-items: flex-start; }
        .card-stat { align-self: flex-end; }
    }
</style>

<?php
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-cogs"></i> Painel Administrativo</h1>
            <p>Gerencie todos os aspectos da plataforma Translators101</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="video-card <?php echo $message_type === 'success' ? 'success-message' : 'error-message'; ?>">
            <p><i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i> <?php echo htmlspecialchars($message); ?></p>
        </div>
    <?php endif; ?>

    <div class="video-card">
        <h2><i class="fas fa-tools"></i> Gerenciamento</h2>

        <div class="quick-actions-grid">
            <a href="usuarios.php" class="quick-action-card">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-blue"><i class="fas fa-users-cog"></i></div>
                    <div>
                        <h3>Usuários</h3>
                        <p>Gerenciar usuários</p>
                    </div>
                </div>
                <span class="card-stat"><?php echo $total_users_formatted; ?></span>
            </a>

            <a href="palestras.php" class="quick-action-card">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-purple"><i class="fas fa-video"></i></div>
                    <div>
                        <h3>Palestras</h3>
                        <p>Adicionar e editar</p>
                    </div>
                </div>
                <span class="card-stat"><?php echo $total_lectures_formatted; ?></span>
            </a>

            <a href="certificados.php" class="quick-action-card">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-green"><i class="fas fa-certificate"></i></div>
                    <div>
                        <h3>Certificados</h3>
                        <p>Gerar e validar</p>
                    </div>
                </div>
                <span class="card-stat"><?php echo $total_certificates_formatted; ?></span>
            </a>

            <a href="glossarios.php" class="quick-action-card">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-red"><i class="fas fa-book"></i></div>
                    <div>
                        <h3>Glossários</h3>
                        <p>Gerenciar arquivos</p>
                    </div>
                </div>
                <span class="card-stat"><?php echo $total_glossaries_formatted; ?></span>
            </a>
            
            <a href="interesse_cursos.php" class="quick-action-card">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-gold"><i class="fas fa-user-graduate"></i></div>
                    <div>
                        <h3>Interessados</h3>
                        <p>Ver lista de leads</p>
                    </div>
                </div>
                <span class="card-stat"><?php echo $total_signups_formatted; ?></span>
            </a>
            
            <a href="gerenciar_cursos.php" class="quick-action-card">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-orange"><i class="fas fa-graduation-cap"></i></div>
                    <div>
                        <h3>Cursos</h3>
                        <p>Adicionar e editar</p>
                    </div>
                </div>
                <span class="card-stat"><?php echo $total_courses_formatted; ?></span>
            </a>
            
            <a href="emails.php" class="quick-action-card">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-blue"><i class="fas fa-envelope"></i></div>
                    <div>
                        <h3>E-mails</h3>
                        <p>Sistema de comunicação</p>
                    </div>
                </div>
            </a>

            <a href="hotmart.php" class="quick-action-card">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-green"><i class="fas fa-shopping-cart"></i></div>
                    <div>
                        <h3>Hotmart</h3>
                        <p>Integração de vendas</p>
                    </div>
                </div>
            </a>

            <a href="logs.php" class="quick-action-card">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-red"><i class="fas fa-list-alt"></i></div>
                    <div>
                        <h3>Logs</h3>
                        <p>Auditoria do sistema</p>
                    </div>
                </div>
            </a>

            <a href="gerenciar_senhas.php" class="quick-action-card">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-purple"><i class="fas fa-key"></i></div>
                    <div>
                        <h3>Senhas</h3>
                        <p>Gerenciar senhas</p>
                    </div>
                </div>
            </a>
        </div>
        
        <hr> 
        <h2><i class="fas fa-broadcast-tower"></i> Gerenciamento de Live Stream</h2>

        <div class="embed-container-wrapper">
            <form method="POST" class="embed-form">
                <input type="hidden" name="action" value="update_embed">
                <div class="form-group">
                    <label for="embed_code"><i class="fas fa-code"></i> Código de embed da live stream</label>
                    <textarea id="embed_code" name="embed_code" rows="8" class="form-control" placeholder="Cole aqui o código de embed..."><?php echo htmlspecialchars($current_embed); ?></textarea>
                    <small class="form-help"><i class="fas fa-info-circle"></i> Cole o código iframe completo fornecido pela plataforma de streaming.</small>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Atualizar Live Stream</button>
                    <button type="button" class="btn btn-secondary" onclick="previewEmbed()"><i class="fas fa-eye"></i> Visualizar</button>
                </div>
            </form>

            <?php if ($current_embed): ?>
                <div class="embed-preview" id="embedPreview">
                    <h3><i class="fas fa-tv"></i> Preview Atual:</h3>
                    <div class="embed-container">
                        <?php echo $current_embed; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function previewEmbed() {
        const embedCode = document.getElementById('embed_code').value.trim();
        let previewDiv = document.getElementById('embedPreview');

        if (!embedCode) {
            alert('Por favor, informe um código de embed primeiro.');
            return;
        }

        if (!previewDiv) {
            previewDiv = document.createElement('div');
            previewDiv.id = 'embedPreview';
            previewDiv.className = 'embed-preview';
            document.querySelector('.embed-container-wrapper').appendChild(previewDiv);
        }

        previewDiv.innerHTML = `
            <h3><i class="fas fa-tv"></i> Preview:</h3>
            <div class="embed-container">
                ${embedCode}
            </div>
        `;
        
        previewDiv.scrollIntoView({ behavior: 'smooth' });
    }
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>