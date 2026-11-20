-- ============================================================
-- Limpa atividades repetidas do feed (análises salvas várias vezes etc.)
-- Rode UMA VEZ no banco existente. O código novo já evita novas duplicatas.
-- Mantém a ocorrência mais antiga de cada uma.
-- ============================================================

-- Análise e "adicionou": uma por usuário + jogo
DELETE ua FROM user_activity ua
INNER JOIN user_activity keep
    ON keep.user_id = ua.user_id
   AND keep.game_id <=> ua.game_id
   AND keep.type = ua.type
   AND keep.id < ua.id
WHERE ua.type IN ('review_posted', 'game_added');

-- Mesmo status, no mesmo jogo, no mesmo dia
DELETE ua FROM user_activity ua
INNER JOIN user_activity keep
    ON keep.user_id = ua.user_id
   AND keep.game_id <=> ua.game_id
   AND keep.type = 'status_changed'
   AND keep.extra <=> ua.extra
   AND DATE(keep.created_at) = DATE(ua.created_at)
   AND keep.id < ua.id
WHERE ua.type = 'status_changed';
