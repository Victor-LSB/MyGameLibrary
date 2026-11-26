-- ============================================================
-- Migração: status "Quero jogar" (lista de desejos)
-- Rode este script UMA VEZ no seu banco de dados existente.
-- Troca a coluna de status para texto, para aceitar o novo valor (se ela era um ENUM
-- com só os 4 status antigos, "Quero jogar" seria recusado). Os dados não mudam.
-- ============================================================

ALTER TABLE user_games MODIFY status VARCHAR(20) NULL DEFAULT 'Backlog';
