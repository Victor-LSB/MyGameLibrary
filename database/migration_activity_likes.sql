-- ============================================================
-- Migração: curtidas nas atividades do feed
-- Rode este script UMA VEZ no seu banco de dados existente.
-- ============================================================

CREATE TABLE IF NOT EXISTS activity_likes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    activity_id BIGINT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_activity_user (activity_id, user_id),
    INDEX idx_activity (activity_id),
    CONSTRAINT fk_activity_like_activity FOREIGN KEY (activity_id) REFERENCES user_activity(id) ON DELETE CASCADE,
    CONSTRAINT fk_activity_like_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
