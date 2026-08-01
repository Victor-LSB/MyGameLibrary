<?php
namespace Victi\MyGameLibrary\Services;

/**
 * Regras de força de senha usadas em todo lugar em que uma senha é
 * criada ou trocada (registo, "esqueci minha senha" e "alterar senha").
 *
 * Mantida num único lugar para as regras nunca ficarem dessincronizadas
 * entre as páginas.
 */
class PasswordPolicy {
    const MIN_LENGTH = 8;

    /**
     * Retorna a primeira regra violada pela senha, ou null se ela
     * cumprir todos os requisitos.
     */
    public static function validate(string $password): ?string {
        if (strlen($password) < self::MIN_LENGTH) {
            return 'A senha deve ter no mínimo ' . self::MIN_LENGTH . ' caracteres.';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return 'A senha deve ter pelo menos 1 letra maiúscula.';
        }

        if (!preg_match('/[a-z]/', $password)) {
            return 'A senha deve ter pelo menos 1 letra minúscula.';
        }

        if (!preg_match('/[0-9]/', $password)) {
            return 'A senha deve ter pelo menos 1 número.';
        }

        return null;
    }
}
