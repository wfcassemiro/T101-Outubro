<?php
/**
 * Configuração de Integração com Zoom
 * Credenciais Server-to-Server OAuth
 */

// Credenciais do Zoom
define('ZOOM_ACCOUNT_ID', 'KiJeWwARQbGPJ1uhAWf-dw');
define('ZOOM_CLIENT_ID', 'F0STeVn6RCqnh90twpeWQQ');
define('ZOOM_CLIENT_SECRET', '6J0ihEGf6JjjyixZ4pkV5u4DmVR9nuJ5');
define('ZOOM_SECRET_TOKEN', '_bavMn5PQeKbw0ZMnfnoMw');


// URLs da API do Zoom
define('ZOOM_API_BASE_URL', 'https://api.zoom.us/v2');
define('ZOOM_OAUTH_TOKEN_URL', 'https://zoom.us/oauth/token');

// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'u335416710_t101_db');
define('DB_USER', 'u335416710_t101');
define('DB_PASS', 'Pa392ap!');

// Timezone
date_default_timezone_set('America/Sao_Paulo');

/**
 * Conexão com o banco de dados
 * Usa a conexão PDO existente do sistema
 */
function getDbConnection() {
    // Usar a conexão global do sistema se disponível
    global $pdo;
    
    if (isset($pdo) && $pdo instanceof PDO) {
        return $pdo;
    }
    
    // Caso contrário, criar nova conexão
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Erro de conexão com BD: " . $e->getMessage());
        die("Erro ao conectar com o banco de dados");
    }
}

/**
 * Criar tabela de reuniões do Zoom (executar apenas uma vez)
 */
function createZoomMeetingsTable() {
    $pdo = getDbConnection();
    
    $sql = "CREATE TABLE IF NOT EXISTS zoom_meetings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        meeting_id BIGINT NOT NULL UNIQUE,
        topic VARCHAR(255) NOT NULL,
        start_time DATETIME NOT NULL,
        duration INT NOT NULL,
        timezone VARCHAR(100) DEFAULT 'America/Sao_Paulo',
        join_url TEXT NOT NULL,
        start_url TEXT,
        password VARCHAR(50),
        agenda TEXT,
        host_id VARCHAR(100),
        status VARCHAR(50) DEFAULT 'scheduled',
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_start_time (start_time),
        INDEX idx_status (status),
        INDEX idx_is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    try {
        $pdo->exec($sql);
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao criar tabela zoom_meetings: " . $e->getMessage());
        return false;
    }
}

?>