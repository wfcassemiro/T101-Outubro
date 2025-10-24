<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['subscriber','admin'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

try {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) {
    header('Location: logout.php');
    exit;
    }
} catch (Exception $e) {
    $error = 'Erro ao carregar dados do perfil.';
}

$certificates_stats = [];
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_certificates, SUM(duration_hours) as total_hours FROM certificates WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $certificates_stats = $stmt->fetch();
} catch (Exception $e) {
    $certificates_stats = ['total_certificates' => 0, 'total_hours' => 0];
}

$user_watchlist = [];
try {
    $query = "SELECT w.id as watchlist_id,w.added_at,l.id as lecture_id,l.title,l.speaker,l.duration_minutes
    FROM user_watchlist w
    JOIN lectures l ON w.lecture_id = l.id
    WHERE w.user_id = ?
    ORDER BY w.added_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $user_watchlist = $stmt->fetchAll();
} catch (Exception $e) {
    $user_watchlist = [];
}

$user_certificates = [];
try {
    $stmt = $pdo->prepare("SELECT c.*, l.title as lecture_title, l.speaker as speaker_name, l.duration_minutes
    FROM certificates c
    LEFT JOIN lectures l ON c.lecture_id = l.id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC");
    $stmt->execute([$user_id]);
    $user_certificates = $stmt->fetchAll();
} catch (Exception $e) {}

// helper → corta o título no SEGUNDO "—"
function cleanTitle($title) {
    $parts = explode('—', $title);
    if (count($parts) > 2) {
    array_pop($parts); // remove a última parte (geralmente palestrante)
    return trim(implode('—', $parts));
    }
    return trim($title);
}

$page_title = 'Meu Perfil - Translators101';
$page_description = 'Gerencie suas informações pessoais e configurações de conta';

include __DIR__ . '/vision/includes/head.php';
include __DIR__ . '/vision/includes/header.php';
include __DIR__ . '/vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
    <div class="hero-content">
    <h1><i class="fas fa-user-circle"></i> Meu Perfil</h1>
    <p>Gerencie suas informações pessoais e configurações de conta</p>
    </div>
    </div>

    <?php if ($message): ?><div class="alert-success"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert-error"><i class="fas fa-exclamation-triangle"></i><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="profile-nav-section">
    <div class="quick-actions-grid">
    <a href="videoteca.php" class="quick-action-card"><i class="fas fa-play-circle"></i><h3>Videoteca</h3><p>Acesse suas aulas</p></a>
    <a href="glossarios.php" class="quick-action-card"><i class="fas fa-book"></i><h3>Glossários</h3><p>Consulte termos</p></a>
    <a href="dash-t101/index.php" class="quick-action-card"><i class="fas fa-chart-line"></i><h3>Dashboard</h3><p>Seu progresso</p></a>
    </div>
    </div>

    <div class="video-card profile-card">
    <div class="card-header"><h2><i class="fas fa-list-ul"></i> Minha Lista</h2></div>
    <?php if (!empty($user_watchlist)): ?>
    <div class="list-header"><span>Palestra</span><span>Palestrante</span><span>Duração</span><span>Ações</span></div>
    <?php foreach ($user_watchlist as $item): ?>
    <div class="list-row" data-lecture-id="<?php echo $item['lecture_id']; ?>">
    <span><?php echo htmlspecialchars(cleanTitle($item['title'])); ?></span>
    <span><?php echo htmlspecialchars($item['speaker']); ?></span>
    <span><?php echo $item['duration_minutes']; ?> min</span>
    <span class="col-actions">
    <a href="/palestra.php?id=<?php echo $item['lecture_id']; ?>" class="cta-btn btn-small">Assistir</a>
    <button class="cta-btn btn-small btn-remove" onclick="removeFromWatchlist('<?php echo $item['lecture_id']; ?>', this)">Remover</button>
    </span>
    </div>
    <?php endforeach; ?>
    <?php else: ?><p class="text-light">Sua lista está vazia.</p><?php endif; ?>
    </div>

    <div class="video-card profile-card">
    <div class="card-header"><h2><i class="fas fa-list-alt"></i> Palestras Assistidas</h2></div>
    
    <div class="stats-report-summary">
    <span>Certificados: <?php echo $certificates_stats['total_certificates'] ?? 0; ?></span>
    <span>Total de horas: <?php echo number_format($certificates_stats['total_hours'] ?? 0,1); ?></span>
    <div class="button-wrapper-inline">
    <button onclick="generateReport()" class="cta-btn btn-small btn-green-report" id="btnGenerateReport">Baixar relatório</button>
    </div>
    </div>
    <?php if (!empty($user_certificates)): ?>
    <div class="list-header"><span>Palestra</span><span>Palestrante</span><span>Duração</span><span>Ações</span></div>
    <?php foreach ($user_certificates as $cert): ?>
    <div class="list-row">
    <span><?php echo htmlspecialchars(cleanTitle($cert['lecture_title'])); ?></span>
    <span><?php echo htmlspecialchars($cert['speaker_name']); ?></span>
    <span><?php echo number_format($cert['duration_hours'] ?? 0, 1); ?> h</span>
    <span class="col-actions">
    <a href="view_certificate_files.php?id=<?php echo $cert['id']; ?>" class="cta-btn btn-small">Visualizar</a>
    <a href="download_certificate_files.php?id=<?php echo $cert['id']; ?>" class="cta-btn btn-small">Baixar</a>
    </span>
    </div>
    <?php endforeach; ?>
    <?php else: ?><p class="text-light">Nenhuma palestra concluída.</p><?php endif; ?>
    </div>

    <div class="profile-row">
    <div class="video-card profile-card flex-1">
    <div class="card-header"><h2><i class="fas fa-id-card"></i> Informações Pessoais</h2></div>
    <form method="POST" class="vision-form profile-form">
    <div class="form-fields">
    <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
    </div>
    <button type="submit" class="cta-btn">Salvar</button>
    </form>
    </div>
    <div class="video-card profile-card flex-1">
    <div class="card-header"><h2><i class="fas fa-lock"></i> Alterar Senha</h2></div>
    <form method="POST" class="vision-form profile-form">
    <input type="hidden" name="name" value="<?php echo htmlspecialchars($user['name']); ?>">
    <input type="hidden" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
    <input type="password" name="current_password" placeholder="Senha atual">
    <input type="password" name="new_password" placeholder="Nova senha">
    <input type="password" name="confirm_password" placeholder="Confirmar senha">
    <button type="submit" class="cta-btn">Alterar</button>
    </form>
    </div>
    </div>

</div>

<!-- Pop-up de notificação -->
<div id="downloadNotification" class="download-notification">
    <i class="fas fa-check-circle"></i>
    <span>Relatório baixado com sucesso! Verifique sua pasta de Downloads.</span>
</div>

<style>
.profile-card, .quick-action-card { margin-bottom: 25px; }
.card-header h2, .card-header h3 { padding: 15px 0 15px 18px; }

.profile-card:hover, .quick-action-card:hover {
  transform: none !important;
  border-color: #a855f7 !important;
  box-shadow: 0 0 15px rgba(168,85,247,0.6) !important;
}

.list-header, .list-row {
  display: grid;
  grid-template-columns: 3fr 2fr 1fr 1fr;
  gap:10px; padding:8px 12px; align-items:center;
}
.list-header { font-weight:600; border-bottom:2px solid rgba(255,255,255,0.2); }
.list-row { border-bottom:1px solid rgba(255,255,255,0.1); transition: opacity 0.3s ease; }
.col-actions { display:flex; gap:6px; }

.profile-row { display:flex; gap:20px; margin-bottom:25px; }
.flex-1 { flex:1; }

/* Botões padrão */
.cta-btn {
  border: none;
  background: var(--brand-purple);
  border-radius: 6px;
  color: #fff !important;
  cursor: pointer;
  padding: 8px 16px;
  font-weight: 600;
  transition: all 0.3s ease;
  display:inline-flex; align-items:center; justify-content:center;
}
.cta-btn:hover {
  box-shadow: 0 0 8px rgba(139,92,246,0.6);
}
.btn-small { font-size:0.8rem; padding:5px 12px; }

/* Ajuste do cta-btn para bordas arredondadas */
.cta-btn, .btn-small {
    border-radius: 25px;
}

/* Estilização para o botão "Remover" */
.btn-remove {
    background-color: #ff0000 !important;
    border: 1px solid #ff0000; 
    color: white !important; 
}

.btn-remove:hover {
    background-color: #cc0000 !important;
    border-color: #cc0000;
}

/* Estilo verde para o botão "Baixar Relatório" */
.btn-green-report {
    background-color: #28a745 !important;
    border: 1px solid #28a745 !important;
    color: white !important;
    padding: 10px 20px;
}

.btn-green-report:hover {
    background-color: #218838 !important;
    border-color: #218838 !important;
}

.btn-green-report:disabled {
    background-color: #6c757d !important;
    border-color: #6c757d !important;
    cursor: not-allowed;
    opacity: 0.6;
}

/* Estilização do resumo de estatísticas */
.stats-report-summary {
    display: flex;
    gap: 50px !important;
    justify-content: center !important;
    align-items: center !important; 
    padding: 15px 30px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    font-weight: 600;
    font-size: 1.25rem !important;
    flex-wrap: wrap; 
}

.stats-report-summary span {
    font-size: 1.75rem !important;
    color: #ffff !important;
}

.button-wrapper-inline {
    flex-shrink: 0;
}

.btn-green-report {
    background-color: #28a745 !important; 
    border: 1px solid #28a745 !important; 
    color: white !important;
    font-size: 1rem !important;
    padding: 12px 24px !important; 
}

/* Pop-up de notificação de download */
.download-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: linear-gradient(135deg, rgba(40, 167, 69, 0.95), rgba(34, 139, 58, 0.95));
    backdrop-filter: blur(10px);
    border: 1px solid rgba(40, 167, 69, 0.5);
    border-radius: 12px;
    padding: 18px 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    color: white;
    font-weight: 600;
    font-size: 0.95rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3), 0 0 20px rgba(40, 167, 69, 0.4);
    z-index: 10000;
    opacity: 0;
    transform: translateX(400px);
    transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    pointer-events: none;
}

.download-notification.show {
    opacity: 1;
    transform: translateX(0);
    pointer-events: auto;
}

.download-notification i {
    font-size: 1.5rem;
    color: #fff;
}

.security-info { display:flex; gap:15px; margin-top:10px; font-size:0.85rem; }

/* Responsivo */
@media (max-width: 768px) {
  .profile-row { flex-direction: column; }
  .list-header { display:none; }
  .list-row { grid-template-columns: 1fr; padding:12px; }
  .list-row span { display:block; margin-bottom:6px; }
  .list-row span:nth-child(1):before { content: "Palestra: "; font-weight:600; color:#a855f7; }
  .list-row span:nth-child(2):before { content: "Palestrante: "; font-weight:600; color:#a855f7; }
  .list-row span:nth-child(3):before { content: "Duração: "; font-weight:600; color:#a855f7; }
  .list-row span:nth-child(4):before { content: "Ações: "; font-weight:600; color:#a855f7; }
  .col-actions { margin-top:5px; }
  
  .stats-report-summary {
    flex-direction: column;
    align-items: flex-start;
  }
  .stats-report-summary span {
    margin-bottom: 5px;
  }
  .button-wrapper-inline {
    margin-top: 10px;
  }
  
  .download-notification {
    top: 10px;
    right: 10px;
    left: 10px;
    font-size: 0.85rem;
    padding: 14px 18px;
  }
}
</style>

<script>
// Função para mostrar notificação de download
function showDownloadNotification() {
    const notification = document.getElementById('downloadNotification');
    notification.classList.add('show');
    
    // Remover após 5 segundos
    setTimeout(() => {
        notification.classList.remove('show');
    }, 5000);
}

// Função para gerar relatório
function generateReport() {
    const btn = document.getElementById('btnGenerateReport');
    const originalText = btn.innerHTML;
    
    // Desabilitar botão e mostrar loading
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gerando...';
    
    // Fazer requisição POST para generate_report.php
    fetch('generate_report.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'generate_report'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Redirecionar para download
            window.location.href = data.download_url;
            
            // Mostrar notificação após 1 segundo (tempo para iniciar o download)
            setTimeout(() => {
                showDownloadNotification();
            }, 1000);
            
            // Restaurar botão após 2 segundos
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }, 2000);
        } else {
            alert('Erro ao gerar relatório: ' + (data.message || 'Erro desconhecido'));
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao gerar relatório. Tente novamente.');
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

// Função para remover da watchlist (SEM CONFIRMAÇÃO)
function removeFromWatchlist(lectureId, button) {
    const row = button.closest('.list-row');
    
    // Animação de fade out
    row.style.opacity = '0.5';
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    fetch('watchlist_cleanup.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'remove_watched',
            lecture_id: lectureId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remover a linha da tabela com animação
            row.style.opacity = '0';
            setTimeout(() => {
                row.remove();
                
                // Verificar se a lista ficou vazia
                const remainingRows = document.querySelectorAll('.list-row[data-lecture-id]');
                if (remainingRows.length === 0) {
                    location.reload(); // Recarregar para mostrar "Sua lista está vazia"
                }
            }, 300);
        } else {
            alert('Erro ao remover: ' + (data.message || 'Erro desconhecido'));
            row.style.opacity = '1';
            button.disabled = false;
            button.innerHTML = 'Remover';
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao remover da lista. Tente novamente.');
        row.style.opacity = '1';
        button.disabled = false;
        button.innerHTML = 'Remover';
    });
}
</script>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>