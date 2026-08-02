<?php

namespace Victi\MyGameLibrary\Models;

use PDO;

class ProfileComment {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Adiciona um comentário (ou uma resposta, se $parentId for informado)
     * no perfil de $profileUserId. Só permite 1 nível de resposta: se o
     * comentário-pai já for uma resposta, a resposta nova é "achatada"
     * para responder ao comentário de topo original.
     *
     * @return int|false ID do comentário criado, ou false em caso de erro.
     */
    public function add($profileUserId, $authorId, string $content, $parentId = null) {
        $content = trim($content);
        if ($content === '') {
            return false;
        }

        if ($parentId !== null) {
            $parentId = $this->resolveTopLevelParent((int) $parentId, (int) $profileUserId);
            if ($parentId === null) {
                return false;
            }
        }

        $stmt = $this->conn->prepare("
            INSERT INTO profile_comments (profile_user_id, author_id, parent_id, content)
            VALUES (?, ?, ?, ?)
        ");

        if (!$stmt->execute([$profileUserId, $authorId, $parentId, $content])) {
            return false;
        }

        return (int) $this->conn->lastInsertId();
    }

    /**
     * Garante que o parent_id informado aponta para um comentário de topo
     * (não removido) do mesmo perfil. Se apontar para uma resposta,
     * reaproveita o pai dela (achatando em 1 nível). Retorna null se o
     * comentário-pai não existir/for de outro perfil.
     */
    private function resolveTopLevelParent(int $parentId, int $profileUserId): ?int {
        $stmt = $this->conn->prepare("
            SELECT id, parent_id FROM profile_comments
            WHERE id = ? AND profile_user_id = ? AND removed_at IS NULL
        ");
        $stmt->execute([$parentId, $profileUserId]);
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$parent) {
            return null;
        }

        return $parent['parent_id'] !== null ? (int) $parent['parent_id'] : (int) $parent['id'];
    }

    /**
     * Busca os comentários de topo de um perfil (mais recentes primeiro),
     * cada um já com a lista de respostas (mais antigas primeiro) embutida
     * em 'replies'. Comentários removidos aparecem como "placeholder" só
     * quando têm respostas ainda visíveis (pra não quebrar a thread).
     */
    public function getByProfile($profileUserId): array {
        $topLevel = $this->fetchComments($profileUserId, null, 'DESC');

        foreach ($topLevel as &$comment) {
            $comment['replies'] = $this->fetchComments($profileUserId, (int) $comment['id'], 'ASC');
        }
        unset($comment);

        // Remove comentários de topo apagados que não têm nenhuma resposta visível.
        return array_values(array_filter($topLevel, function ($comment) {
            return $comment['removed_at'] === null || !empty($comment['replies']);
        }));
    }

    private function fetchComments($profileUserId, $parentId, string $order): array {
        $sql = "
            SELECT pc.id, pc.author_id, pc.parent_id, pc.content, pc.created_at,
                   pc.removed_by, pc.removed_at,
                   u.username, u.display_name, u.avatar
            FROM profile_comments pc
            INNER JOIN users u ON u.id = pc.author_id
            WHERE pc.profile_user_id = ?
        ";
        $params = [$profileUserId];

        if ($parentId === null) {
            $sql .= " AND pc.parent_id IS NULL";
        } else {
            $sql .= " AND pc.parent_id = ?";
            $params[] = $parentId;
        }

        $sql .= " ORDER BY pc.created_at {$order}";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find($commentId) {
        $stmt = $this->conn->prepare("SELECT * FROM profile_comments WHERE id = ?");
        $stmt->execute([$commentId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Marca o comentário como removido (soft delete) se $requestingUserId
     * for o autor ou o dono do perfil. Retorna false se não encontrado ou
     * sem permissão.
     */
    public function delete($commentId, $requestingUserId): bool {
        $comment = $this->find($commentId);
        if (!$comment) {
            return false;
        }

        $requestingUserId = (int) $requestingUserId;
        $isAuthor = $requestingUserId === (int) $comment['author_id'];
        $isProfileOwner = $requestingUserId === (int) $comment['profile_user_id'];

        if (!$isAuthor && !$isProfileOwner) {
            return false;
        }

        $removedBy = $isAuthor ? 'author' : 'profile_owner';

        $stmt = $this->conn->prepare("
            UPDATE profile_comments
            SET content = '', removed_by = ?, removed_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$removedBy, $commentId]);
    }
}
