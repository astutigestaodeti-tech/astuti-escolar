<?php

namespace App\Support;

final class EmailNormalizer
{
    /** Domínio das contas com nome de usuário curto (por exemplo, "admin"). */
    public const LOCAL_DOMAIN = 'usuarios.astuti.invalid';

    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    /** Aceita o e-mail completo ou só o nome de usuário: "admin" vira "admin@usuarios.astuti.invalid". */
    public static function fromLogin(string $login): string
    {
        $login = self::normalize($login);

        return str_contains($login, '@') ? $login : $login.'@'.self::LOCAL_DOMAIN;
    }
}
