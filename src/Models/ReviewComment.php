<?php

namespace Victi\MyGameLibrary\Models;

use PDO;

class ReviewComment {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Adiciona um comentário (ou uma resposta, se $parentId for informado)
     * na análise que $targetUserId escreveu para o jogo $gameId. Só permite
     * 1 nível de resposta: se o comentário-pai já for uma resposta, a
     * resposta nova é "achatada" para responder ao comentário de topo original.
     *
     * @return int|false ID do comentário criado, ou false em caso de erro.
     */
    public function add($targetUserId, $gameId, $authorId, string $content, $parentId = null) {
        $content = trim($content);
        if ($content === '') {
            return false;
        }

        if ($parentId !== null) {
            $parentId = $this->resolveTopLevelParent((int) $parentId, (int) $targetUserId, (int) $gameId);
            if ($parentId === null) {
                return false;
            }
        }

        $stmt = $this->conn->prepare("
            INSERT INTO review_comments (target_user_id, game_id, author_id, parent_id, content)
            VALUES (?, ?, ?, ?, ?)
        ");

        if (!$stmt->execute([$targetUserId, $gameId, $authorId, $parentId, $content])) {
            return false;
        }

        return (int) $this->conn->lastInsertId();
    }

    /**
     * Garante que o parent_id informado aponta para um comentário de topo
     * (não removido) da mesma análise. Se apontar para uma resposta,
     * reaproveita o pai dela (achatando em 1 nível). Retorna null se o
     * comentário-pai não existir/for de outra análise.
     */
    private function resolveTopLevelParent(int $parentId, int $targetUserId, int $gameId): ?int {
        $stmt = $this->conn->prepare("
            SELECT id, parent_id FROM review_comments
            WHERE id = ? AND target_user_id = ? AND game_id = ? AND removed_at IS NULL
        ");
        $stmt->execute([$parentId, $targetUserId, $gameId]);
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$parent) {
            return null;
        }

        return $parent['parent_id'] !== null ? (int) $parent['parent_id'] : (int) $parent['id'];
    }

    /**
     * Busca os comentários de topo da análise (mais recentes primeiro),
     * cada um já com a lista de respostas (mais antigas primeiro) embutida
     * em 'replies'. Comentários removidos aparecem como "placeholder" só
     * quando têm respostas ainda visíveis (pra não quebrar a thread).
     */
    public function getByReview($targetUserId, $gameId): array {
        $topLevel = $this->fetchComments($targetUserId, $gameId, null, 'DESC');

        foreach ($topLevel as &$comment) {
            $comment['replies'] = $this->fetchComments($targetUserId, $gameId, (int) $comment['id'], 'ASC');
        }
        unset($comment);

        // Remove comentários de topo apagados que não têm nenhuma resposta visível.
        return array_values(array_filter($topLevel, function ($comment) {
            return $comment['removed_at'] === null || !empty($comment['replies']);
        }));
    }

    private function fetchComments($targetUserId, $gameId, $parentId, string $order): array {
        $sql = "
            SELECT rc.id, rc.author_id, rc.parent_id, rc.content, rc.created_at,
                   rc.removed_by, rc.removed_at,
                   u.username, u.display_name, u.avatar
            FROM review_comments rc
            INNER JOIN users u ON u.id = rc.author_id
            WHERE rc.target_user_id = ? AND rc.game_id = ?
        ";
        $params = [$targetUserId, $gameId];

        if ($parentId === null) {
            $sql .= " AND rc.parent_id IS NULL";
        } else {
            $sql .= " AND rc.parent_id = ?";
            $params[] = $parentId;
        }

        $sql .= " ORDER BY rc.created_at {$order}";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find($commentId) {
        $stmt = $this->conn->prepare("SELECT * FROM review_comments WHERE id = ?");
        $stmt->execute([$commentId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Marca o comentário como removido (soft delete) se $requestingUserId
     * for o autor ou o dono da análise. Retorna false se não encontrado ou
     * sem permissão.
     */
    public function delete($commentId, $requestingUserId): bool {
        $comment = $this->find($commentId);
        if (!$comment) {
            return false;
        }

        $requestingUserId = (int) $requestingUserId;
        $isAuthor = $requestingUserId === (int) $comment['author_id'];
        $isReviewOwner = $requestingUserId === (int) $comment['target_user_id'];

        if (!$isAuthor && !$isReviewOwner) {
            return false;
        }

        $removedBy = $isAuthor ? 'author' : 'review_owner';

        $stmt = $this->conn->prepare("
            UPDATE review_comments
            SET content = '', removed_by = ?, removed_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$removedBy, $commentId]);
    }
}
