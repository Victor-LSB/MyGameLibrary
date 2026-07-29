-- ============================================================
-- Migração: melhorias sociais + verificação de email + rate limit
-- Rode este script UMA VEZ no seu banco de dados existente.
-- ============================================================

-- 1) Verificação de e-mail -------------------------------------------------
ALTER TABLE users
    ADD COLUMN email_verified_at DATETIME NULL DEFAULT NULL AFTER email,
    ADD COLUMN verification_token VARCHAR(100) NULL DEFAULT NULL AFTER email_verified_at,
    ADD COLUMN verification_token_expires_at DATETIME NULL DEFAULT NULL AFTER verification_token;

-- IMPORTANTE: isso marca todos os usuários JÁ EXISTENTES como verificados,
-- para não travar quem já usa o site. Só quem se registrar A PARTIR de agora
-- vai precisar confirmar o e-mail. Rode isso só na primeira vez.
UPDATE users SET email_verified_at = NOW() WHERE email_verified_at IS NULL;

-- 2) Rate limiting (genérico, usado por login/registo/forgot/follow) -------
CREATE TABLE IF NOT EXISTS rate_limit_hits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bucket_key VARCHAR(191) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bucket_created (bucket_key, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Feed de atividade dos seguidos -----------------------------------------
CREATE TABLE IF NOT EXISTS user_activity (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    game_id INT NULL,
    type ENUM('game_added', 'status_changed', 'review_posted') NOT NULL,
    extra VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_created (user_id, created_at),
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_activity_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
