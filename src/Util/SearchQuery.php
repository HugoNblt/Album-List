<?php

namespace App\Util;

/**
 * Normalisation des recherches saisies par les utilisateurs.
 */
final class SearchQuery
{
    public const MAX_LENGTH = 100;

    /** Supprime les espaces superflus et tronque à 100 caractères. */
    public static function normalize(?string $query): string
    {
        return mb_substr(trim((string) $query), 0, self::MAX_LENGTH);
    }

    /** Motif LIKE « contient » : les jokers % et _ saisis par l'utilisateur sont pris littéralement. */
    public static function contains(string $query): string
    {
        return '%' . addcslashes($query, '%_\\') . '%';
    }
}
