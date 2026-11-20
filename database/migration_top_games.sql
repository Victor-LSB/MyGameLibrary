-- ============================================================
-- Migração: Top 5 jogos do perfil
-- Rode este script UMA VEZ no seu banco de dados existente.
-- ============================================================

CREATE TABLE IF NOT EXISTS user_top_games (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    game_id INT NOT NULL,
    position TINYINT UNSIGNED NOT NULL,            -- 1 a 5
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_position (user_id, position),  -- um jogo por rank
    UNIQUE KEY uniq_user_game (user_id, game_id),       -- o mesmo jogo não entra 2x
    CONSTRAINT chk_top_position CHECK (position BETWEEN 1 AND 5),
    CONSTRAINT fk_top_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_top_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
