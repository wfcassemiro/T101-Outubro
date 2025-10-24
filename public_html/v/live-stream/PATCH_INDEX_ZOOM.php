<?php
/**
 * PATCH PARA INTEGRAÇÃO DO ZOOM NO INDEX.PHP
 * 
 * INSTRUÇÕES:
 * 1. Adicione este código logo após a linha:
 *    require_once __DIR__ . '/../../config/database.php';
 * 
 * 2. No seu index.php atual (linha ~46-49), você tem:
 *    $is_live_active = !empty(trim($live_embed_code));
 * 
 * 3. Substitua essas linhas pelo código deste arquivo
 */

// ===============================================
// INÍCIO DO CÓDIGO PARA ADICIONAR NO INDEX.PHP
// ===============================================

// Incluir funções do Zoom
require_once __DIR__ . '/zoom_functions_fixed.php';

// Verificar se há reunião do Zoom marcada para exibição
$currentZoomMeeting = getCurrentMeeting();
$meeting_type = 'embed'; // Valor padrão

// Se houver reunião do Zoom ativa, usar ela ao invés do embed padrão
if ($currentZoomMeeting) {
    $is_live_active = true;
    $meeting_type = 'zoom';
    writeToZoomLog("Reunião Zoom ativa detectada: " . $currentZoomMeeting['topic']);
} else {
    // Buscar embed code do banco de dados (código já existente)
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
    
    // Live está ativa se temos embed code
    $is_live_active = !empty(trim($live_embed_code));
    $meeting_type = 'embed';
}

// Buscar próximas reuniões do Zoom para exibir na agenda
$upcomingZoomMeetings = getActiveMeetingsFromDatabase(5);

// ===============================================
// FIM DO CÓDIGO PARA ADICIONAR NO INDEX.PHP
// ===============================================
?>
