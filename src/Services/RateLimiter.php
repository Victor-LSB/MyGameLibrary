<?php

namespace Victi\MyGameLibrary\Services;

use PDO;

/**
 * Rate limiter simples baseado em banco de dados (tabela rate_limit_hits).
 *
 * Uso típico:
 *   $limiter = new RateLimiter($db);
 *   $key = $limiter->key('login', $_SERVER['REMOTE_ADDR'], $email);
 *
 *   if ($limiter->tooManyAttempts($key, 5, 15)) {
 *       // bloquear e avisar o usuário
 *   }
 *   ...
 *   $limiter->hit($key); // registra a tentativa (chamar após uma tentativa falha, por ex.)
 */
class RateLimiter {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Monta uma chave estável para o bucket (ex: "login:127.0.0.1:user@mail.com").
     */
    public function key(string $action, ...$parts): string {
        $normalized = array_map(function ($p) {
            return mb_strtolower(trim((string) $p));
        }, $parts);

        return $action . ':' . implode(':', $normalized);
    }

    /**
     * Quantas tentativas essa chave teve dentro da janela (em minutos).
     */
    public function attempts(string $bucketKey, int $decayMinutes): int {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total
            FROM rate_limit_hits
            WHERE bucket_key = ?
              AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$bucketKey, $decayMinutes]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['total'] ?? 0);
    }

    /**
     * True se a chave já bateu o limite de tentativas na janela informada.
     */
    public function tooManyAttempts(string $bucketKey, int $maxAttempts, int $decayMinutes): bool {
        return $this->attempts($bucketKey, $decayMinutes) >= $maxAttempts;
    }

    /**
     * Registra uma nova tentativa para a chave.
     */
    public function hit(string $bucketKey): void {
        $stmt = $this->db->prepare("INSERT INTO rate_limit_hits (bucket_key, created_at) VALUES (?, NOW())");
        $stmt->execute([$bucketKey]);

        // Limpeza oportunista (1% das requisições) para a tabela não crescer para sempre
        if (random_int(1, 100) === 1) {
            $this->db->prepare("DELETE FROM rate_limit_hits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)")->execute();
        }
    }

    /**
     * Quantos minutos faltam até a tentativa mais antiga da janela expirar.
     * Útil para mostrar "tente novamente em X minutos" ao usuário.
     */
    public function minutesUntilReset(string $bucketKey, int $decayMinutes): int {
        $stmt = $this->db->prepare("
            SELECT MIN(created_at) as oldest
            FROM rate_limit_hits
            WHERE bucket_key = ?
              AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$bucketKey, $decayMinutes]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($row['oldest'])) {
            return 0;
        }

        $oldest = new \DateTime($row['oldest']);
        $resetAt = $oldest->add(new \DateInterval('PT' . $decayMinutes . 'M'));
        $now = new \DateTime();

        $diff = $now->diff($resetAt);
        $minutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;

        return max(1, $minutes);
    }
}
