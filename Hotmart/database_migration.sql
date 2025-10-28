-- Migration para criar tabela de progresso de aulas dos assinantes Hotmart
-- Execute este script no banco de dados MySQL

USE u335416710_t101_db;

-- Tabela para armazenar o progresso de cada usuário em cada palestra
CREATE TABLE IF NOT EXISTS hotmart_user_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    lecture_id VARCHAR(36) NOT NULL,
    hotmart_user_id VARCHAR(255) NOT NULL COMMENT 'UUID ou subscriber_code da Hotmart',
    hotmart_lesson_id VARCHAR(255) NULL COMMENT 'ID da lesson na Hotmart',
    progress_percent INT DEFAULT 0 COMMENT 'Percentual de progresso (0-100)',
    is_completed BOOLEAN DEFAULT FALSE COMMENT 'Se a palestra foi completada',
    watch_time_seconds INT DEFAULT 0 COMMENT 'Tempo assistido em segundos',
    last_position_seconds INT DEFAULT 0 COMMENT 'Última posição de visualização',
    started_at TIMESTAMP NULL COMMENT 'Quando começou a assistir',
    completed_at TIMESTAMP NULL COMMENT 'Quando completou',
    last_synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    raw_data JSON NULL COMMENT 'Dados brutos da API Hotmart',
    UNIQUE KEY unique_user_lecture (user_id, lecture_id),
    KEY idx_hotmart_user_id (hotmart_user_id),
    KEY idx_user_id (user_id),
    KEY idx_lecture_id (lecture_id),
    KEY idx_completed (is_completed),
    KEY idx_last_synced (last_synced_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lecture_id) REFERENCES lectures(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índices adicionais para performance
CREATE INDEX IF NOT EXISTS idx_user_progress_sync ON hotmart_user_progress(user_id, last_synced_at);
CREATE INDEX IF NOT EXISTS idx_hotmart_progress_lookup ON hotmart_user_progress(hotmart_user_id, lecture_id);

-- Tabela para mapear IDs de palestras entre o sistema local e a Hotmart
CREATE TABLE IF NOT EXISTS hotmart_lecture_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lecture_id VARCHAR(36) NOT NULL,
    hotmart_module_id VARCHAR(255) NULL,
    hotmart_lesson_id VARCHAR(255) NULL,
    hotmart_page_id VARCHAR(255) NULL,
    lecture_title VARCHAR(500) NOT NULL,
    sync_enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_lecture (lecture_id),
    KEY idx_hotmart_lesson (hotmart_lesson_id),
    FOREIGN KEY (lecture_id) REFERENCES lectures(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adicionar campos na tabela users se não existirem
ALTER TABLE users 
    ADD COLUMN IF NOT EXISTS hotmart_ucode VARCHAR(255) NULL COMMENT 'UUID único do usuário na Hotmart',
    ADD COLUMN IF NOT EXISTS last_progress_sync TIMESTAMP NULL COMMENT 'Última sincronização de progresso';

CREATE INDEX IF NOT EXISTS idx_users_hotmart_ucode ON users(hotmart_ucode);

-- Atualizar tabela hotmart_sync_logs para incluir tipo de sincronização
ALTER TABLE hotmart_sync_logs 
    MODIFY COLUMN sync_type ENUM('MANUAL', 'WEBHOOK', 'SCHEDULED', 'PROGRESS') NOT NULL;

COMMIT;
