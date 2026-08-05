-- ============================================================
-- Migração: comentários nas análises (reviews) de jogos
-- Rode este script UMA VEZ no seu banco de dados existente.
-- ============================================================

CREATE TABLE IF NOT EXISTS review_comments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    target_user_id INT NOT NULL,
    game_id INT NOT NULL,
    author_id INT NOT NULL,
    parent_id BIGINT UNSIGNED DEFAULT NULL,
    content TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    removed_by VARCHAR(20) DEFAULT NULL,
    removed_at DATETIME DEFAULT NULL,
    INDEX idx_target_game (target_user_id, game_id),
    INDEX idx_parent (parent_id),
    CONSTRAINT fk_review_comment_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_review_comment_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    CONSTRAINT fk_review_comment_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_review_comment_parent FOREIGN KEY (parent_id) REFERENCES review_comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
