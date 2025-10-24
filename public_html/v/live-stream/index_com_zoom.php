<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/zoom_functions.php';

// Funções de Acesso
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}
if (!function_exists('isAdmin')) {
    function isAdmin() {
        return (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
    }
}
if (!function_exists('hasVideotecaAccess')) {
    function hasVideotecaAccess() {
        return isLoggedIn();
    }
}

// Verificar acesso
if (!isLoggedIn() || !hasVideotecaAccess()) {
    header("Location: /planos.php");
    exit;
}

$page_title = 'Live Stream - Translators101';
$page_description = 'Assista às palestras ao vivo da Translators101';

// Buscar embed code do banco (FALLBACK)
$live_embed_code = '';
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'live_embed_code'");
    $stmt->execute();
    $result = $stmt->fetch();
    if ($result) {
        $live_embed_code = $result['setting_value'];
    }
} catch (PDOException $e) {
    $live_embed_code = '';
}

// NOVA LÓGICA: Verificar se há reunião do Zoom programada para transmitir
$zoomMeetingToShow = null;
$showZoomLive = false;

// Verificar configuração manual do admin (tabela: zoom_meetings, campo: show_live)
try {
    $stmt = $pdo->prepare("
        SELECT * FROM zoom_meetings
        WHERE is_active = 1
        AND show_live = 1
        ORDER BY start_time DESC
        LIMIT 1
    ");
    $stmt->execute();
    $zoomMeetingToShow = $stmt->fetch();
    
    if ($zoomMeetingToShow) {
        $showZoomLive = true;
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar reunião Zoom para exibir: " . $e->getMessage());
}

// Determinar o que exibir
if ($showZoomLive && $zoomMeetingToShow) {
    $is_live_active = true;
    $meeting_type = 'zoom';
} else if (!empty(trim($live_embed_code))) {
    $is_live_active = true;
    $meeting_type = 'embed';
} else {
    $is_live_active = false;
    $meeting_type = 'none';
}

$current_user_is_admin = isAdmin();

// Buscar PRÓXIMAS palestras/anúncios
$upcomingLectures = [];
try {
    $stmt = $pdo->query("
        SELECT id, title, speaker, description, image_path, announcement_date, lecture_time
        FROM upcoming_announcements
        WHERE is_active = 1
        AND announcement_date >= CURDATE()
        ORDER BY announcement_date ASC, display_order ASC
        LIMIT 3
    ");
    $upcomingLectures = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $upcomingLectures = [];
}

// NOVA: Buscar próximas reuniões do Zoom com formato S##E## (APENAS FUTURAS)
$upcomingZoomMeetings = [];
try {
    // Query compatível com MySQL e MariaDB usando LIKE
    $stmt = $pdo->query("
        SELECT * FROM zoom_meetings
        WHERE is_active = 1
        AND start_time >= NOW()
        AND (topic LIKE 'S__E__%' OR topic LIKE 'S__E__-%')
        ORDER BY start_time ASC
        LIMIT 6
    ");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filtro adicional em PHP para garantir formato exato S##E##
    if (!empty($results)) {
        foreach ($results as $meeting) {
            // Verificar se o título começa com S[dígito][dígito]E[dígito][dígito]
            if (preg_match('/^S[0-9]{2}E[0-9]{2}/', $meeting['topic'])) {
                $upcomingZoomMeetings[] = $meeting;
            }
        }
    }
} catch (Exception $e) {
    error_log("Erro ao buscar reuniões Zoom futuras: " . $e->getMessage());
    $upcomingZoomMeetings = [];
}

include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<style>
/* === ESTILOS APPLE VISION + ZOOM === */

:root {
    --brand-purple: #8e44ad;
    --brand-purple-dark: #5e3370;
    --brand-purple-light: #a569bd;
    --accent-gold: #f39c12;
    --accent-green: #27ae60;
    --accent-red: #e74c3c;
    --text-primary: #ffffff;
    --text-secondary: #f0f0f0;
    --text-muted: #d4d4d4;
    --glass-bg: rgba(255, 255, 255, 0.05);
    --glass-border: rgba(255, 255, 255, 0.15);
    --glass-hover-bg: rgba(142, 68, 173, 0.1);
    --glass-player-bg: rgba(0,0,0,0.1);
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #18181c;
    color: var(--text-primary);
    margin: 0;
    padding: 0;
    line-height: 1.6;
    overflow-x: hidden;
}

.main-content {
    padding: 20px;
    max-width: 1400px;
    margin: 0 auto;
    box-sizing: border-box;
}

/* === HERO === */
.glass-hero {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 40px 30px;
    margin-bottom: 40px;
    backdrop-filter: blur(15px);
    text-align: center;
    position: relative;
    overflow: hidden;
}

.glass-hero::before {
    content: '';
    position: absolute;
    top: -1px;
    left: -1px;
    right: -1px;
    bottom: -1px;
    background: inherit;
    border-radius: 20px;
    box-shadow: inset 0 0 0 1px var(--glass-border);
    z-index: -1;
}

.hero-content h1 {
    font-size: 2.8rem;
    margin-bottom: 15px;
    color: var(--text-primary);
    text-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.hero-content p {
    font-size: 1.2rem;
    color: var(--text-secondary);
    margin-bottom: 25px;
}

/* === BUTTONS === */
.cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 28px;
    font-size: 1.1rem;
    font-weight: bold;
    border-radius: 30px;
    background: var(--brand-purple);
    color: #fff;
    text-decoration: none;
    box-shadow: 0 6px 18px rgba(142, 68, 173, 0.6);
    transition: transform 0.3s ease, box-shadow 0.3s ease, background 0.3s ease;
    border: none;
    cursor: pointer;
}

.cta-btn:hover {
    background: var(--brand-purple-dark);
    box-shadow: 0 8px 22px rgba(142, 68, 173, 0.7);
    transform: translateY(-2px);
}

/* === CARDS === */
.video-card {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    backdrop-filter: blur(10px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.3);
    display: flex;
    flex-direction: column;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 20px 15px;
    border-bottom: 1px solid var(--glass-border);
    background: var(--glass-hover-bg);
}

.card-header h2 {
    margin: 0;
    color: var(--text-primary);
    font-size: 1.3rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* === LIVE CONTAINER === */
.live-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
    margin-bottom: 40px;
    align-items: stretch;
}

.player-section {
    min-height: 0;
    display: flex;
    flex-direction: column;
}

.player-card {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.live-player {
    flex: 1;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.player-container {
    flex: 1;
    min-height: 400px;
    background: #000;
    border-radius: 12px;
    overflow: hidden;
    position: relative;
}

.player-container iframe {
    width: 100%;
    height: 100%;
    border: none;
}

.player-controls {
    display: flex;
    gap: 12px;
    padding: 16px;
    background: var(--glass-player-bg);
    border-top: 1px solid var(--glass-border);
}

.control-btn {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    color: var(--text-secondary);
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.control-btn:hover {
    background: var(--brand-purple);
    color: #fff;
    transform: translateY(-2px);
}

.control-btn.small {
    padding: 6px 10px;
    font-size: 0.8rem;
}

/* === ZOOM ESPECÍFICO === */
.zoom-live-info {
    padding: 20px;
    background: linear-gradient(135deg, rgba(45, 140, 240, 0.15), rgba(94, 53, 177, 0.15));
    border-radius: 12px;
    margin-bottom: 16px;
    border: 1px solid rgba(45, 140, 240, 0.3);
}

.zoom-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #2D8CFF, #5E35B1);
    color: white;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 700;
    margin-bottom: 12px;
    box-shadow: 0 4px 12px rgba(45, 140, 240, 0.4);
}

.zoom-live-info h3 {
    color: var(--text-primary);
    font-size: 1.4rem;
    margin: 8px 0 12px;
    font-weight: 700;
}

.zoom-details {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    margin: 12px 0;
}

.zoom-detail-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-secondary);
    font-size: 0.95rem;
}

.zoom-detail-item i {
    color: var(--accent-gold);
}

.zoom-agenda {
    color: var(--text-muted);
    font-size: 0.95rem;
    line-height: 1.5;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid rgba(255,255,255,0.1);
}

.zoom-join-btn {
    background: linear-gradient(135deg, #2D8CFF, #5E35B1) !important;
    color: white !important;
}

.zoom-join-btn:hover {
    background: linear-gradient(135deg, #1a7ae8, #4a2c8e) !important;
    box-shadow: 0 6px 20px rgba(45, 140, 240, 0.4);
}

/* === CHAT === */
.chat-section {
    min-height: 0;
    display: flex;
    flex-direction: column;
}

.chat-card {
    flex: 1;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.chat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    border-bottom: 1px solid var(--glass-border);
    background: var(--glass-hover-bg);
}

.chat-header h3 {
    margin: 0;
    color: #fff;
    font-size: 1.1rem;
}

.chat-controls {
    display: flex;
    gap: 8px;
}

.chat-messages {
    flex: 1;
    padding: 16px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 12px;
    scrollbar-width: thin;
    scrollbar-color: var(--brand-purple-light) transparent;
}

.chat-message {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 12px;
    backdrop-filter: blur(10px);
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    0% { opacity: 0; transform: translateY(20px); }
    50% { opacity: 0.5; }
    100% { opacity: 1; transform: translateY(0); }
}

.message-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
}

.username {
    color: var(--brand-purple);
    font-weight: 600;
    font-size: 0.9rem;
}

.timestamp {
    color: #999;
    font-size: 0.8rem;
}

.message-content {
    color: var(--text-secondary);
    font-size: 0.9rem;
    line-height: 1.4;
    word-wrap: break-word;
}

.message-actions {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid var(--glass-border);
}

.btn-overlay {
    background: rgba(52, 152, 219, 0.2);
    border: 1px solid rgba(52, 152, 219, 0.3);
    color: #3498db;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-overlay:hover {
    background: rgba(52, 152, 219, 0.3);
    transform: translateY(-1px);
}

.system-message {
    background: rgba(46, 204, 113, 0.2);
    border: 1px solid rgba(46, 204, 113, 0.3);
    color: #2ecc71;
    padding: 12px;
    border-radius: 8px;
    text-align: center;
    font-size: 0.9rem;
}

.chat-offline-message {
    background: rgba(149, 165, 166, 0.2);
    border: 1px solid rgba(149, 165, 166, 0.3);
    color: #95a5a6;
    padding: 12px;
    border-radius: 8px;
    text-align: center;
    font-size: 0.9rem;
}

.chat-input-container {
    padding: 16px;
    border-top: 1px solid var(--glass-border);
    background: var(--glass-player-bg);
    margin-top: auto;
}

.chat-input-group {
    display: flex;
    gap: 12px;
    align-items: center;
}

.chat-input-group input {
    flex: 1;
    padding: 12px;
    border-radius: 20px;
    border: 1px solid var(--glass-border);
    background: rgba(255,255,255,0.06);
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.chat-input-group input:focus {
    outline: none;
    border-color: var(--brand-purple);
    box-shadow: 0 0 0 2px rgba(142, 68, 173, 0.2);
}

.send-btn {
    background: var(--brand-purple);
    border: none;
    color: #fff;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    font-size: 1rem;
}

.send-btn:hover {
    background: var(--brand-purple-dark);
    transform: scale(1.1);
}

/* === STATUS === */
.live-status {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
    margin-top: 20px;
}

.live-active {
    background: rgba(46, 204, 113, 0.2);
    border: 1px solid rgba(46, 204, 113, 0.3);
    color: #2ecc71;
}

.live-offline {
    background: rgba(149, 165, 166, 0.2);
    border: 1px solid rgba(149, 165, 166, 0.3);
    color: #95a5a6;
}

.pulse {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

/* === OFFLINE === */
.offline-player {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #2c2c2e, #1c1c1e);
    border-radius: 12px;
    min-height: 400px;
}

.offline-content {
    text-align: center;
    padding: 40px;
}

.offline-content i {
    font-size: 4rem;
    color: #666;
    margin-bottom: 20px;
}

.offline-content h3 {
    color: #fff;
    margin-bottom: 16px;
    font-size: 1.5rem;
}

.offline-content p {
    color: #ccc;
    margin-bottom: 12px;
    line-height: 1.5;
}

.social-links {
    display: flex;
    gap: 16px;
    justify-content: center;
    margin-top: 24px;
}

.social-link {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.social-link:hover {
    background: var(--brand-purple);
    color: #fff;
    transform: translateY(-2px);
}

/* === OVERLAY PREVIEW === */
.overlay-preview {
    margin-top: 24px;
}

.overlay-content {
    min-height: 100px;
    background: rgba(0,0,0,0.3);
    border-radius: 8px;
    padding: 20px;
    border: 2px dashed var(--glass-border);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    color: var(--text-muted);
}

.overlay-content .empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
}

.overlay-content .empty-state i {
    font-size: 2rem;
    color: var(--brand-purple-light);
}

/* === SCHEDULE === */
.schedule-card h2 {
    margin: 0 20px 10px 20px;
}

.schedule-instruction {
    text-align: center;
    color: var(--accent-gold);
    margin-top: 15px;
    margin-bottom: 30px;
    font-size: 1.15rem;
    font-weight: 600;
    line-height: 1.5;
}

.instruction-highlight {
    font-size: 1.25rem;
}

.lectures-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    margin: 30px 0;
}

.lecture-card {
    border: 2px solid transparent;
    border-radius: 20px;
    padding: 20px;
    background: rgba(255,255,255,0.08);
    transition: box-shadow 0.28s ease, border-color 0.28s ease, transform 0.28s ease;
    cursor: default;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.lecture-card:hover {
    border-color: var(--brand-purple);
    box-shadow: 0 10px 20px rgba(142, 68, 173, 0.3);
}

/* === ZOOM MEETINGS CARDS === */
.zoom-schedule {
    margin-top: 40px;
}

.zoom-meeting-card {
    border: 2px solid rgba(45, 140, 240, 0.3);
    background: rgba(45, 140, 240, 0.08);
    position: relative;
    overflow: visible;
}

.zoom-meeting-card:hover {
    border-color: #2D8CFF;
    box-shadow: 0 12px 30px rgba(45, 140, 240, 0.4);
    transform: translateY(-4px);
}

.zoom-episode-badge {
    position: absolute;
    top: -12px;
    right: 20px;
    z-index: 10;
}

.episode-tag {
    display: inline-block;
    background: linear-gradient(135deg, #2D8CFF, #5E35B1);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 800;
    letter-spacing: 1px;
    box-shadow: 0 6px 16px rgba(45, 140, 240, 0.5);
}

.zoom-meeting-info {
    display: flex;
    gap: 12px;
    margin-top: 12px;
    flex-wrap: wrap;
}

.info-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.info-badge i {
    color: var(--accent-gold);
}

.btn-zoom-join {
    width: 100%;
    background: linear-gradient(135deg, #2D8CFF, #5E35B1);
    color: white;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 14px 24px;
    font-size: 1rem;
    box-shadow: 0 6px 18px rgba(45, 140, 240, 0.4);
}

.btn-zoom-join:hover {
    background: linear-gradient(135deg, #1a7ae8, #4a2c8e);
    box-shadow: 0 8px 24px rgba(45, 140, 240, 0.5);
    transform: translateY(-2px);
}

/* === LECTURE CARDS === */
.lecture-image-container {
    position: relative;
    width: 100%;
    padding-bottom: 56.25%;
    overflow: hidden;
    border-radius: 12px;
    margin-bottom: 16px;
}

.lecture-image {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.lecture-info {
    flex-grow: 1;
}

.lecture-datetime {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.lecture-date {
    background: var(--brand-purple);
    color: white;
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 0.9rem;
    font-weight: 700;
    display: inline-block;
}

.lecture-time {
    background: var(--accent-gold);
    color: white;
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 0.9rem;
    font-weight: 700;
    display: inline-block;
}

.lecture-title {
    font-size: 1.2rem;
    color: var(--text-primary);
    margin: 8px 0 10px;
    font-weight: 700;
    line-height: 1.25rem;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    max-height: calc(1.25rem * 3);
    min-height: calc(1.25rem * 3);
}

.lecture-speaker {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-size: 1.15rem;
    color: var(--brand-purple-dark);
    margin-bottom: 10px;
}

.lecture-speaker span {
    color: var(--accent-gold) !important;
    font-weight: 700;
    line-height: 1.3rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    min-height: calc(1.3rem * 2);
    max-height: calc(1.3rem * 2);
}

.lecture-speaker i {
    color: var(--accent-gold);
    margin-top: 2px;
}

.lecture-summary {
    color: var(--text-secondary);
    font-size: 0.95rem;
    line-height: 1.4rem;
    display: -webkit-box;
    -webkit-line-clamp: 5;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    max-height: calc(1.4rem * 5);
    margin: 0;
}

.schedule-actions-bottom {
    margin-top: 20px;
    text-align: center;
}

.agenda-divider {
    border: none;
    height: 1px;
    background-color: rgba(255, 255, 255, 0.15);
    margin: 20px 0 15px;
}

.agenda-title {
    text-align: center;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin: 0 0 15px 0;
}

.agenda-buttons-container {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.btn-agenda {
    flex: 1;
    padding: 10px 15px;
    font-size: 0.9rem;
    justify-content: center;
    text-decoration: none;
}

.btn-google-cal {
    background: linear-gradient(135deg, #4285F4, #357ae8);
    box-shadow: 0 4px 15px rgba(66, 133, 244, 0.4);
}

.btn-apple-cal {
    background: linear-gradient(135deg, #f0f0f0, #e0e0e0);
    color: #333;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.btn-agenda:not(.clicked):hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.3);
}

.btn-google-cal:not(.clicked):hover {
    background: linear-gradient(135deg, #357ae8, #2a62bc);
    box-shadow: 0 8px 20px rgba(53, 122, 184, 0.5);
}

.btn-apple-cal:not(.clicked):hover {
    background: linear-gradient(135deg, #e0e0e0, #d0d0d0);
    color: #222;
    box-shadow: 0 8px 20px rgba(0,0,0,0.3);
}

.btn-agenda.clicked {
    background: linear-gradient(135deg, #2ecc71, #27ae60);
    color: white;
    box-shadow: 0 4px 15px rgba(39, 174, 96, 0.5);
    cursor: default;
    transform: none !important;
}

/* === RESPONSIVE === */
@media (max-width: 1200px) {
    .lectures-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .live-container {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .lectures-grid {
        grid-template-columns: 1fr;
    }
    
    .glass-hero h1 {
        font-size: 2.2rem;
    }
    
    .glass-hero p {
        font-size: 1.1rem;
    }
    
    .cta-btn {
        padding: 12px 24px;
        font-size: 1rem;
    }
    
    .zoom-details {
        flex-direction: column;
        gap: 8px;
    }
    
    .zoom-episode-badge {
        top: -10px;
        right: 10px;
    }
    
    .episode-tag {
        font-size: 0.8rem;
        padding: 6px 12px;
    }
}
</style>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-broadcast-tower"></i> Live Stream Translators101</h1>
            <p>Participe e interaja</p>
            <?php if ($is_live_active): ?>
                <div class="live-status live-active">
                    <i class="fas fa-circle pulse"></i> AO VIVO
                </div>
            <?php else: ?>
                <div class="live-status live-offline">
                    <i class="fas fa-circle"></i> OFFLINE
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="live-container">
        <div class="player-section">
            <div class="video-card player-card">
                <?php if ($is_live_active): ?>
                    <?php if ($meeting_type === 'zoom'): ?>
                        <!-- EXIBIR REUNIÃO DO ZOOM -->
                        <div class="zoom-live-info">
                            <div class="zoom-badge">
                                <i class="fab fa-zoom"></i> Zoom Meeting
                            </div>
                            <h3><?php echo htmlspecialchars($zoomMeetingToShow['topic']); ?></h3>
                            <div class="zoom-details">
                                <div class="zoom-detail-item">
                                    <i class="fas fa-clock"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($zoomMeetingToShow['start_time'])); ?>
                                </div>
                                <div class="zoom-detail-item">
                                    <i class="fas fa-hourglass-half"></i>
                                    <?php echo $zoomMeetingToShow['duration']; ?> minutos
                                </div>
                            </div>
                            <?php if (!empty($zoomMeetingToShow['agenda'])): ?>
                                <p class="zoom-agenda"><?php echo htmlspecialchars($zoomMeetingToShow['agenda']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="live-player zoom-player">
                            <div class="player-container">
                                <iframe 
                                    src="<?php echo htmlspecialchars($zoomMeetingToShow['join_url']); ?>" 
                                    allow="microphone; camera; fullscreen"
                                    allowfullscreen
                                    style="width: 100%; height: 100%; border: none;">
                                </iframe>
                            </div>
                            <div class="player-controls">
                                <a href="<?php echo htmlspecialchars($zoomMeetingToShow['join_url']); ?>" 
                                   target="_blank" 
                                   class="control-btn zoom-join-btn">
                                    <i class="fas fa-external-link-alt"></i> Abrir no Zoom
                                </a>
                                <button class="control-btn" onclick="toggleFullscreen()">
                                    <i class="fas fa-expand"></i> Tela Cheia
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- EXIBIR EMBED PADRÃO -->
                        <div class="live-player">
                            <div class="player-container">
                                <?php echo $live_embed_code; ?>
                            </div>
                            <div class="player-controls">
                                <button class="control-btn" onclick="toggleFullscreen()">
                                    <i class="fas fa-expand"></i> Tela Cheia
                                </button>
                                <button class="control-btn" onclick="togglePictureInPicture()">
                                    <i class="fas fa-external-link-alt"></i> PiP
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- OFFLINE -->
                    <div class="offline-player">
                        <div class="offline-content">
                            <i class="fas fa-video-slash"></i>
                            <h3>Transmissão Offline</h3>
                            <p>No momento não há transmissões ao vivo.</p>
                            <p>Confira as próximas reuniões agendadas abaixo!</p>
                            <div class="social-links">
                                <a href="#" class="social-link"><i class="fab fa-instagram"></i> Instagram</a>
                                <a href="#" class="social-link"><i class="fab fa-youtube"></i> YouTube</a>
                                <a href="#" class="social-link"><i class="fab fa-linkedin"></i> LinkedIn</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="chat-section">
            <div class="video-card chat-card">
                <div class="chat-header">
                    <h3><i class="fas fa-comments"></i> Chat da Live</h3>
                    <div class="chat-controls">
                        <button class="control-btn small" onclick="toggleChat()" title="Minimizar Chat">
                            <i class="fas fa-minus"></i>
                        </button>
                        <?php if ($current_user_is_admin): ?>
                            <button class="control-btn small" onclick="clearChat()" title="Limpar Chat">
                                <i class="fas fa-broom"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="chat-messages" id="chatMessages">
                    <div class="system-message">
                        <i class="fas fa-info-circle"></i>
                        Bem-vindo ao chat da live! Seja respeitoso com outros participantes.
                    </div>
                    <div id="chatMessagesContainer"></div>
                </div>
                
                <?php if ($is_live_active): ?>
                    <div class="chat-input-container">
                        <form id="chatForm" method="post" action="chat-save.php">
                            <div class="chat-input-group">
                                <input type="text" id="chatInput" name="message" placeholder="Digite sua mensagem..." maxlength="500" autocomplete="off" required>
                                <button type="submit" class="send-btn">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="chat-input-container">
                        <div class="chat-offline-message">
                            <i class="fas fa-info-circle"></i>
                            O chat estará disponível quando a transmissão estiver ao vivo.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (isAdmin()): ?>
        <div class="video-card overlay-preview">
            <div class="card-header">
                <h2><i class="fas fa-tv"></i> Pré-visualização do Overlay</h2>
                <button type="button" class="cta-btn" onclick="clearOverlay()" style="padding: 8px 16px; font-size: 0.9rem;">
                    <i class="fas fa-trash"></i> Remover da Tela
                </button>
            </div>
            <div id="overlayMessage" class="overlay-content">
                <div class="empty-state">
                    <i class="fas fa-tv"></i>
                    <p>Nenhuma mensagem selecionada para o overlay</p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- NOVA SEÇÃO: Próximas Reuniões Zoom (S##E##) - APENAS FUTURAS -->
    <?php if (!empty($upcomingZoomMeetings)): ?>
        <div class="video-card schedule-card zoom-schedule">
            <h2><i class="fab fa-zoom"></i> Próximos Episódios no Zoom</h2>
            <p class="schedule-instruction instruction-highlight">Reuniões futuras agendadas com formato S##E## (Season/Episode)</p>
            
            <div class="lectures-grid" id="zoomMeetingsContainer">
                <?php foreach ($upcomingZoomMeetings as $meeting): ?>
                    <div class="lecture-card zoom-meeting-card">
                        <div class="zoom-episode-badge">
                            <?php
                            // Extrair S##E## do título
                            preg_match('/^(S[0-9]{2}E[0-9]{2})/', $meeting['topic'], $matches);
                            $episode = $matches[1] ?? 'EPISÓDIO';
                            ?>
                            <span class="episode-tag"><?php echo htmlspecialchars($episode); ?></span>
                        </div>
                        <div class="lecture-info">
                            <div class="lecture-datetime">
                                <div class="lecture-date">
                                    <?php echo date('d/m/Y', strtotime($meeting['start_time'])); ?>
                                </div>
                                <div class="lecture-time">
                                    <?php echo date('H:i', strtotime($meeting['start_time'])); ?>h
                                </div>
                            </div>
                            <h4 class="lecture-title"><?php echo htmlspecialchars($meeting['topic']); ?></h4>
                            <div class="lecture-speaker">
                                <i class="fas fa-video"></i>
                                <span>Reunião Zoom</span>
                            </div>
                            <?php if (!empty($meeting['agenda'])): ?>
                                <p class="lecture-summary">
                                    <?php echo htmlspecialchars($meeting['agenda']); ?>
                                </p>
                            <?php endif; ?>
                            <div class="zoom-meeting-info">
                                <div class="info-badge">
                                    <i class="fas fa-clock"></i> <?php echo $meeting['duration']; ?> min
                                </div>
                                <?php if (!empty($meeting['password'])): ?>
                                    <div class="info-badge">
                                        <i class="fas fa-lock"></i> Protegida
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="schedule-actions-bottom">
                            <hr class="agenda-divider">
                            <a href="<?php echo htmlspecialchars($meeting['join_url']); ?>" 
                               target="_blank" 
                               class="cta-btn btn-zoom-join" 
                               data-testid="zoom-join-<?php echo $meeting['meeting_id']; ?>">
                                <i class="fab fa-zoom"></i> Entrar na Reunião
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Agenda T101 Original (upcoming_announcements) -->
    <?php if (!empty($upcomingLectures)): ?>
        <div class="video-card schedule-card">
            <h2><i class="fas fa-calendar-alt"></i> Agenda T101</h2>
            <p class="schedule-instruction instruction-highlight">Baixe o arquivo de convite e clique nele para incluir um lembrete em sua agenda.</p>
            
            <div class="lectures-grid" id="lecturesContainer">
                <?php 
                date_default_timezone_set('America/Sao_Paulo');
                foreach ($upcomingLectures as $lecture): 
                    $announcementDate = $lecture['announcement_date'] ?? '';
                    $lectureTime = $lecture['lecture_time'] ?? '19:00:00';
                    $defaultDuration = 90;

                    try {
                        $dateTimeStart = new DateTime($announcementDate . ' ' . $lectureTime);
                    } catch (Exception $e) {
                        $dateTimeStart = new DateTime($announcementDate . ' 19:00:00');
                    }
                    
                    $dateTimeEnd = clone $dateTimeStart;
                    $dateTimeEnd->modify("+{$defaultDuration} minutes");

                    $formattedDate = $dateTimeStart->format('d \d\e F, Y');
                    $formattedTime = $dateTimeStart->format('H:i');
                    $monthNames = [
                        'January' => 'Janeiro', 'February' => 'Fevereiro', 'March' => 'Março',
                        'April' => 'Abril', 'May' => 'Maio', 'June' => 'Junho',
                        'July' => 'Julho', 'August' => 'Agosto', 'September' => 'Setembro',
                        'October' => 'Outubro', 'November' => 'Novembro', 'December' => 'Dezembro'
                    ];
                    $formattedDate = str_replace(array_keys($monthNames), array_values($monthNames), $formattedDate);

                    $eventData = [
                        'title' => $lecture['title'] ?? 'Palestra T101',
                        'description' => $lecture['description'] ?? 'Sem descrição.',
                        'speaker' => $lecture['speaker'] ?? 'Palestrante',
                        'start' => $dateTimeStart->format('Ymd\THis'), 
                        'end' => $dateTimeEnd->format('Ymd\THis'),
                        'start_utc' => $dateTimeStart->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z'),
                        'end_utc' => $dateTimeEnd->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z'),
                        'reminder' => 30,
                    ];
                ?>
                <div class="lecture-card">
                    <div class="lecture-image-container">
                        <img src="<?php echo htmlspecialchars($lecture['image_path'] ?? '/images/palestra-placeholder.jpg'); ?>" alt="Palestra" class="lecture-image">
                    </div>
                    <div class="lecture-info">
                        <div class="lecture-datetime">
                            <div class="lecture-date"><?php echo htmlspecialchars($formattedDate); ?></div>
                            <div class="lecture-time"><?php echo htmlspecialchars($formattedTime); ?>h</div>
                        </div>
                        <h4 class="lecture-title"><?php echo htmlspecialchars($lecture['title'] ?? 'Título a Definir'); ?></h4>
                        <div class="lecture-speaker">
                            <i class="fas fa-user"></i>
                            <span><?php echo htmlspecialchars($lecture['speaker'] ?? 'Palestrante'); ?></span>
                        </div>
                        <p class="lecture-summary">
                            <?php echo htmlspecialchars($lecture['description'] ?? 'Breve descrição...'); ?>
                        </p>
                    </div>
                    <div class="schedule-actions-bottom">
                        <hr class="agenda-divider">
                        <h4 class="agenda-title">Incluir na minha agenda</h4>
                        <div class="agenda-buttons-container">
                            <a href="#" 
                               class="cta-btn btn-agenda btn-google-cal" 
                               data-event='<?php echo htmlspecialchars(json_encode($eventData), ENT_QUOTES, 'UTF-8'); ?>'
                               onclick="generateGoogleCalendarLink(event)">
                                <i class="fab fa-google"></i> Google
                            </a>
                            <a href="#" 
                               class="cta-btn btn-agenda btn-apple-cal" 
                               data-event='<?php echo htmlspecialchars(json_encode($eventData), ENT_QUOTES, 'UTF-8'); ?>'
                               onclick="generateIcs(event)">
                                <i class="fab fa-apple"></i> Apple
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// === JAVASCRIPT COMPLETO ===
const CURRENT_USER_IS_ADMIN = <?php echo isAdmin() ? 'true' : 'false'; ?>;
const CURRENT_USER_NAME = '<?php echo addslashes($_SESSION['user_name'] ?? $_SESSION['nome'] ?? 'Você'); ?>';

let chatMessages = [];

document.addEventListener('DOMContentLoaded', function() {
    initChatLogic();
});

function initChatLogic() {
    const chatForm = document.getElementById('chatForm');
    
    if (chatForm) {
        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const input = document.getElementById('chatInput');
            const msg = input.value.trim();

            if (!msg) return;

            input.disabled = true;
            const sendBtn = chatForm.querySelector('.send-btn');
            const originalContent = sendBtn.innerHTML;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            const endpoint = 'chat-save.php';
            const formData = new FormData(chatForm);
            
            fetch(endpoint, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    const newMessage = {
                        id: Date.now(),
                        user_name: CURRENT_USER_NAME,
                        message: msg,
                        created_at: new Date().toISOString(),
                        isOwn: true,
                        isAdmin: CURRENT_USER_IS_ADMIN
                    };
                    
                    chatMessages.push(newMessage);
                    addMessageToChat(newMessage);
                    input.value = '';
                } else {
                    console.error('Erro ao enviar mensagem:', response.statusText);
                    alert('Erro ao enviar mensagem. Tente novamente.');
                }
            })
            .catch(error => {
                console.error('Erro de rede:', error);
                alert('Erro de conexão. Verifique sua rede.');
            })
            .finally(() => {
                input.disabled = false;
                sendBtn.innerHTML = originalContent;
                scrollChatToBottom();
            });
        });
    }
    
    loadRealMessagesFromApi();
}

function loadRealMessagesFromApi() {
    addInitialMessages();
}

function addInitialMessages() {
    const container = document.getElementById('chatMessagesContainer');
    if (container.children.length === 0) {
        const initialMessages = [
            { id: 1, user_name: 'Sistema', message: 'Chat da live iniciado!', created_at: new Date(Date.now() - 30000).toISOString(), isSystem: true },
            { id: 2, user_name: 'Admin', message: 'Bem-vindos à transmissão ao vivo!', created_at: new Date(Date.now() - 120000).toISOString(), isAdmin: true }
        ];
        
        initialMessages.forEach(msg => {
            chatMessages.push(msg);
            addMessageToChat(msg);
        });
        scrollChatToBottom();
    }
}

function addMessageToChat(msg) {
    const container = document.getElementById('chatMessagesContainer');
    const messageElement = document.createElement('div');
    messageElement.className = 'chat-message';
    
    const time = new Date(msg.created_at).toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
    
    let overlayButton = '';
    if (CURRENT_USER_IS_ADMIN && !msg.isSystem) {
        overlayButton = `
        <div class="message-actions">
        <button class="btn-overlay" data-id="${msg.id}">
        <i class="fas fa-tv"></i> Exibir na Tela
        </button>
        </div>
        `;
    }
    
    messageElement.innerHTML = `
    <div class="message-header">
    <strong class="username">${escapeHtml(msg.user_name)}</strong>
    <small class="timestamp">[${time}]</small>
    </div>
    <div class="message-content">
    ${escapeHtml(msg.message)}
    </div>
    ${overlayButton}
    `;
    
    container.appendChild(messageElement);
    
    if (overlayButton) {
        addOverlayButtonEvents();
    }
}

function addOverlayButtonEvents() {
    document.querySelectorAll('.btn-overlay:not([data-bound])').forEach(btn => {
        btn.setAttribute('data-bound', 'true');
        btn.addEventListener('click', function() {
            const messageText = this.closest('.chat-message').querySelector('.message-content').textContent;
            const username = this.closest('.chat-message').querySelector('.username').textContent;
            
            const overlayContent = document.getElementById('overlayMessage');
            if (overlayContent) {
                overlayContent.innerHTML = `
                <div class="overlay-message" style="background: rgba(142, 68, 173, 0.2); border: 1px solid rgba(142, 68, 173, 0.3); padding: 16px; border-radius: 8px; color: #fff;">
                <strong style="color: var(--brand-purple);">${username}:</strong> ${messageText}
                </div>
                `;
            }
            
            this.innerHTML = '<i class="fas fa-check"></i> Enviado!';
            this.style.background = 'rgba(46, 204, 113, 0.3)';
            this.style.color = '#2ecc71';
            
            setTimeout(() => {
                this.innerHTML = '<i class="fas fa-tv"></i> Exibir na Tela';
                this.style.background = 'rgba(52, 152, 219, 0.2)';
                this.style.color = '#3498db';
            }, 2000);
        });
    });
}

function scrollChatToBottom() {
    const chatContainer = document.getElementById('chatMessages');
    setTimeout(() => {
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }, 50);
}

function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function toggleChat() {
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages.style.display === 'none' || chatMessages.style.display === '') {
        chatMessages.style.display = 'flex';
    } else {
        chatMessages.style.display = 'none';
    }
}

function clearChat() {
    if (confirm('Limpar todas as mensagens do chat? Esta ação não pode ser desfeita.')) {
        chatMessages = [];
        document.getElementById('chatMessagesContainer').innerHTML = '';
    }
}

function clearOverlay() {
    const overlayContent = document.getElementById('overlayMessage');
    if (overlayContent) {
        overlayContent.innerHTML = `
        <div class="empty-state">
        <i class="fas fa-tv"></i>
        <p>Nenhuma mensagem selecionada para o overlay</p>
        </div>
        `;
    }
}

function toggleFullscreen() {
    const playerContainer = document.querySelector('.player-container');
    if (playerContainer && playerContainer.requestFullscreen) {
        playerContainer.requestFullscreen();
    } else {
        alert('Seu navegador não suporta tela cheia neste elemento ou não há player ativo.');
    }
}

function togglePictureInPicture() {
    const videoElement = document.querySelector('.player-container iframe');
    if (videoElement && videoElement.requestPictureInPicture) {
        videoElement.requestPictureInPicture()
            .catch(error => {
                console.error('Erro ao tentar PiP:', error);
                alert('Não foi possível ativar o Picture-in-Picture.');
            });
    } else {
        alert('Seu navegador não suporta Picture-in-Picture para este elemento.');
    }
}

function escapeIcs(value) {
    if (!value) return '';
    return value
        .replace(/\\/g, '\\\\')
        .replace(/,/g, '\\,')
        .replace(/;/g, '\\;')
        .replace(/\n/g, '\\n')
        .replace(/\r/g, '');
}

function showFeedback(btn, isGoogle) {
    btn.innerHTML = `<i class="fas fa-check"></i> ${isGoogle ? 'Aberto' : 'Baixado'}`;
    btn.classList.add('clicked');
    btn.onclick = function(event) { event.preventDefault(); };
    btn.style.cursor = 'default';
}

function generateGoogleCalendarLink(e) {
    e.preventDefault();
    const btn = e.currentTarget;
    const eventData = JSON.parse(btn.getAttribute('data-event'));

    const baseUrl = 'https://www.google.com/calendar/render?action=TEMPLATE';
    const params = new URLSearchParams({
        'text': `${eventData.title} com ${eventData.speaker}`,
        'dates': `${eventData.start_utc.replace(/\.000Z$/, 'Z')}/${eventData.end_utc.replace(/\.000Z$/, 'Z')}`,
        'details': `${eventData.description}\n\nPalestrante: ${eventData.speaker}\n\nAssista em: https://translators101.com/v/live-stream`,
        'location': 'Translators101 - Online'
    });
    
    window.open(baseUrl + '&' + params.toString(), '_blank');
    showFeedback(btn, true);
}

function generateIcs(e) {
    e.preventDefault();
    const btn = e.currentTarget;
    const eventData = JSON.parse(btn.getAttribute('data-event'));

    const title = eventData.title || "Evento Translators101";
    const description = eventData.description || "Palestra Exclusiva da Translators101";
    const speaker = eventData.speaker || "Palestrante";
    const start = eventData.start;
    const end = eventData.end;
    const reminder = eventData.reminder;

    const escapedTitle = escapeIcs(title);
    const escapedDescription = escapeIcs(description);
    const escapedSpeaker = escapeIcs(speaker);

    const icsContent = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Translators101//Live Reminder//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        `DTSTART;TZID=America/Sao_Paulo:${start}`,
        `DTEND;TZID=America/Sao_Paulo:${end}`,
        `SUMMARY;CHARSET=UTF-8:${escapedTitle} com ${escapedSpeaker}`,
        `DESCRIPTION;CHARSET=UTF-8:${escapedDescription}\\n\\nPalestrante: ${escapedSpeaker}\\n\\nAssista em: https://translators101.com/v/live-stream`,
        `LOCATION;CHARSET=UTF-8:Translators101 - Online`,
        `UID:${Date.now()}-${Math.random().toString(36).substring(2, 9)}@translators101.com.br`,
        `DTSTAMP:${new Date().toISOString().replace(/[-:]|\\.\\d{3}/g, '')}Z`,
        'BEGIN:VALARM',
        'ACTION:DISPLAY',
        `DESCRIPTION;CHARSET=UTF-8:Lembrete: ${escapedTitle}`,
        `TRIGGER:-PT${reminder}M`,
        'END:VALARM',
        'END:VEVENT',
        'END:VCALENDAR'
    ].join('\r\n');
    
    const safeTitle = title.replace(/[\\/:\\*?"<>|]/g, '_').substring(0, 40).trim();
    const filename = `Lembrete_${safeTitle}.ics`;
    
    const blob = new Blob([icsContent], { type: 'text/calendar;charset=utf-8' });

    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showFeedback(btn, false);
}
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>