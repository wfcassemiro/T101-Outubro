<?php
/**
 * Funções para gerenciamento de reuniões do Zoom - VERSÃO CORRIGIDA
 * 
 * ESCOPOS NECESSÁRIOS:
 * - meeting:write:meeting:admin
 * - meeting:read:meeting:admin
 * - meeting:update:meeting:admin
 * - meeting:delete:meeting:admin
 * - user:read:user:admin
 */

require_once 'zoom_config_fixed.php';
require_once 'zoom_auth_fixed.php';

/**
 * Obter informações do usuário Zoom
 */
function getZoomUser() {
    writeToZoomLog("Obtendo informações do usuário Zoom...");
    
    $result = zoomApiRequest('/users/me');
    
    if ($result['success'] && !empty($result['data'])) {
        writeToZoomLog("✓ Usuário obtido: " . ($result['data']['email'] ?? 'N/A'));
        return $result['data'];
    }
    
    // Fallback: listar usuários
    writeToZoomLog("Tentando fallback /users...");
    $listResult = zoomApiRequest('/users?status=active&page_size=1');
    
    if ($listResult['success'] && !empty($listResult['data']['users'])) {
        writeToZoomLog("✓ Usuário obtido via /users");
        return $listResult['data']['users'][0];
    }
    
    writeToZoomLog("✗ Falha ao obter usuário");
    return null;
}

/**
 * Criar uma nova reunião no Zoom
 */
function createZoomMeeting($topic, $startTime, $duration, $agenda = '', $timezone = 'America/Sao_Paulo') {
    writeToZoomLog("\n=== CRIAR REUNIÃO ===");
    writeToZoomLog("Tópico: {$topic}");
    writeToZoomLog("Início: {$startTime}");
    writeToZoomLog("Duração: {$duration} minutos");
    
    $user = getZoomUser();
    
    if (!$user) {
        return [
            'success' => false,
            'error' => 'Não foi possível obter informações do usuário Zoom. Verifique os escopos da aplicação.'
        ];
    }
    
    $userId = $user['id'] ?? 'me';
    
    // Formatar data/hora para ISO 8601
    try {
        $dateTime = new DateTime($startTime, new DateTimeZone($timezone));
        $startTimeFormatted = $dateTime->format('Y-m-d\TH:i:s');
    } catch (Exception $e) {
        writeToZoomLog("✗ Erro ao formatar data: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Data/hora inválida. Use o formato: YYYY-MM-DD HH:MM:SS'
        ];
    }
    
    $meetingData = [
        'topic' => $topic,
        'type' => 2, // Reunião agendada
        'start_time' => $startTimeFormatted,
        'duration' => (int)$duration,
        'timezone' => $timezone,
        'agenda' => $agenda,
        'settings' => [
            'host_video' => true,
            'participant_video' => true,
            'join_before_host' => false,
            'mute_upon_entry' => true,
            'watermark' => false,
            'use_pmi' => false,
            'approval_type' => 0,
            'audio' => 'both',
            'auto_recording' => 'none',
            'waiting_room' => false,
            'allow_multiple_devices' => true
        ]
    ];
    
    $result = zoomApiRequest('/users/' . $userId . '/meetings', 'POST', $meetingData);
    
    if ($result['success']) {
        $meeting = $result['data'];
        writeToZoomLog("✓ Reunião criada com sucesso! ID: " . $meeting['id']);
        
        // Salvar no banco de dados
        if (saveMeetingToDatabase($meeting)) {
            writeToZoomLog("✓ Reunião salva no banco de dados");
        } else {
            writeToZoomLog("✗ Erro ao salvar reunião no banco de dados");
        }
        
        return [
            'success' => true,
            'meeting' => $meeting
        ];
    }
    
    writeToZoomLog("✗ Erro ao criar reunião: " . $result['error']);
    return $result;
}

/**
 * Obter informações de uma reunião existente
 */
function getZoomMeeting($meetingId) {
    writeToZoomLog("\n=== OBTER REUNIÃO ===");
    writeToZoomLog("Meeting ID: {$meetingId}");
    
    $result = zoomApiRequest('/meetings/' . $meetingId);
    
    if ($result['success']) {
        writeToZoomLog("✓ Reunião obtida com sucesso");
        
        // Salvar/atualizar no banco de dados
        saveMeetingToDatabase($result['data']);
        
        return [
            'success' => true,
            'meeting' => $result['data']
        ];
    }
    
    writeToZoomLog("✗ Erro ao obter reunião: " . $result['error']);
    return $result;
}

/**
 * Listar todas as reuniões do usuário
 */
function listZoomMeetings($type = 'scheduled') {
    writeToZoomLog("\n=== LISTAR REUNIÕES ===");
    writeToZoomLog("Tipo: {$type}");
    
    $user = getZoomUser();
    
    if (!$user) {
        return [
            'success' => false,
            'error' => 'Não foi possível obter informações do usuário'
        ];
    }
    
    $userId = $user['id'] ?? 'me';
    $result = zoomApiRequest('/users/' . $userId . '/meetings?type=' . $type);
    
    if ($result['success']) {
        writeToZoomLog("✓ " . count($result['data']['meetings'] ?? []) . " reuniões encontradas");
    }
    
    return $result;
}

/**
 * Deletar uma reunião do Zoom
 */
function deleteZoomMeeting($meetingId) {
    writeToZoomLog("\n=== DELETAR REUNIÃO ===");
    writeToZoomLog("Meeting ID: {$meetingId}");
    
    $result = zoomApiRequest('/meetings/' . $meetingId, 'DELETE');
    
    if ($result['success']) {
        writeToZoomLog("✓ Reunião deletada com sucesso");
        
        // Marcar como inativa no banco de dados
        deleteMeetingFromDatabase($meetingId);
        
        return [
            'success' => true,
            'message' => 'Reunião deletada com sucesso'
        ];
    }
    
    writeToZoomLog("✗ Erro ao deletar reunião: " . $result['error']);
    return $result;
}

/**
 * Atualizar uma reunião existente
 */
function updateZoomMeeting($meetingId, $data) {
    writeToZoomLog("\n=== ATUALIZAR REUNIÃO ===");
    writeToZoomLog("Meeting ID: {$meetingId}");
    
    $result = zoomApiRequest('/meetings/' . $meetingId, 'PATCH', $data);
    
    if ($result['success']) {
        writeToZoomLog("✓ Reunião atualizada com sucesso");
        
        // Atualizar no banco de dados
        $meeting = getZoomMeeting($meetingId);
        if ($meeting['success']) {
            saveMeetingToDatabase($meeting['meeting']);
        }
        
        return [
            'success' => true,
            'message' => 'Reunião atualizada com sucesso'
        ];
    }
    
    writeToZoomLog("✗ Erro ao atualizar reunião: " . $result['error']);
    return $result;
}

/**
 * Salvar reunião no banco de dados local
 */
function saveMeetingToDatabase($meeting) {
    try {
        $pdo = getDbConnection();
        
        $sql = "INSERT INTO zoom_meetings 
            (meeting_id, topic, start_time, duration, timezone, join_url, start_url, password, agenda, host_id, status) 
            VALUES 
            (:meeting_id, :topic, :start_time, :duration, :timezone, :join_url, :start_url, :password, :agenda, :host_id, :status)
            ON DUPLICATE KEY UPDATE
            topic = VALUES(topic),
            start_time = VALUES(start_time),
            duration = VALUES(duration),
            timezone = VALUES(timezone),
            join_url = VALUES(join_url),
            start_url = VALUES(start_url),
            password = VALUES(password),
            agenda = VALUES(agenda),
            status = VALUES(status),
            is_active = 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':meeting_id' => $meeting['id'],
            ':topic' => $meeting['topic'],
            ':start_time' => date('Y-m-d H:i:s', strtotime($meeting['start_time'])),
            ':duration' => $meeting['duration'],
            ':timezone' => $meeting['timezone'] ?? 'America/Sao_Paulo',
            ':join_url' => $meeting['join_url'],
            ':start_url' => $meeting['start_url'] ?? '',
            ':password' => $meeting['password'] ?? '',
            ':agenda' => $meeting['agenda'] ?? '',
            ':host_id' => $meeting['host_id'] ?? '',
            ':status' => $meeting['status'] ?? 'scheduled'
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao salvar reunião no BD: " . $e->getMessage());
        return false;
    }
}

/**
 * Deletar reunião do banco de dados local
 */
function deleteMeetingFromDatabase($meetingId) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("UPDATE zoom_meetings SET is_active = 0 WHERE meeting_id = :meeting_id");
        $stmt->execute([':meeting_id' => $meetingId]);
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao deletar reunião do BD: " . $e->getMessage());
        return false;
    }
}

/**
 * Obter reuniões ativas do banco de dados
 */
function getActiveMeetingsFromDatabase($limit = 50) {
    try {
        $pdo = getDbConnection();
        
        $sql = "SELECT * FROM zoom_meetings 
            WHERE is_active = 1 
            ORDER BY 
            CASE WHEN start_time >= NOW() THEN 0 ELSE 1 END, 
            start_time DESC 
            LIMIT :limit";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao buscar reuniões do BD: " . $e->getMessage());
        return [];
    }
}

/**
 * Obter reunião atual (acontecendo agora)
 */
function getCurrentMeeting() {
    try {
        $pdo = getDbConnection();
        
        $stmt = $pdo->prepare("
            SELECT * FROM zoom_meetings 
            WHERE is_active = 1 
            AND show_live = 1
            AND start_time <= NOW() 
            AND DATE_ADD(start_time, INTERVAL duration MINUTE) >= NOW()
            ORDER BY start_time DESC 
            LIMIT 1
        ");
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao buscar reunião atual: " . $e->getMessage());
        return null;
    }
}

/**
 * Marcar reunião para exibição no live stream
 */
function toggleMeetingLiveDisplay($meetingId, $showLive) {
    try {
        $pdo = getDbConnection();
        
        // Desmarcar todas as outras
        if ($showLive) {
            $pdo->exec("UPDATE zoom_meetings SET show_live = 0");
        }
        
        // Marcar/desmarcar a selecionada
        $stmt = $pdo->prepare("UPDATE zoom_meetings SET show_live = :show_live WHERE meeting_id = :meeting_id");
        $stmt->execute([
            ':show_live' => $showLive ? 1 : 0,
            ':meeting_id' => $meetingId
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao atualizar show_live: " . $e->getMessage());
        return false;
    }
}

/**
 * Extrair Meeting ID de um link do Zoom
 */
function extractMeetingIdFromUrl($url) {
    // Padrões: https://zoom.us/j/1234567890 ou apenas o ID
    if (preg_match('/\/j\/(\d+)/', $url, $matches)) {
        return $matches[1];
    }
    
    if (is_numeric(trim($url))) {
        return trim($url);
    }
    
    return null;
}

/**
 * Adicionar reunião existente pelo ID ou URL
 */
function addExistingMeeting($meetingIdOrUrl) {
    $meetingId = extractMeetingIdFromUrl($meetingIdOrUrl);
    
    if (!$meetingId) {
        return [
            'success' => false,
            'error' => 'ID ou URL da reunião inválido'
        ];
    }
    
    return getZoomMeeting($meetingId);
}

/**
 * Sincronizar reuniões do Zoom com o banco de dados
 */
function syncZoomMeetings() {
    writeToZoomLog("\n=== SINCRONIZAR REUNIÕES ===");
    
    $result = listZoomMeetings('scheduled');
    
    if (!$result['success']) {
        return $result;
    }
    
    $meetings = $result['data']['meetings'] ?? [];
    $syncCount = 0;
    
    foreach ($meetings as $meeting) {
        if (saveMeetingToDatabase($meeting)) {
            $syncCount++;
        }
    }
    
    writeToZoomLog("✓ {$syncCount} reuniões sincronizadas");
    
    return [
        'success' => true,
        'synced' => $syncCount,
        'total' => count($meetings)
    ];
}
?>