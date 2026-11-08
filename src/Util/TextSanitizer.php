<?php

namespace App\Util;

/**
 * Nettoie les textes saisis par les utilisateurs avant enregistrement.
 */
final class TextSanitizer
{
    /**
     * Caractères invisibles détournables : contrôles (hors tabulation et retour à la ligne),
     * espaces de largeur nulle, marques et surcharges de direction du texte (RTL), BOM.
     */
    private const INVISIBLE = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{2066}-\x{2069}\x{FEFF}]/u';

    /** Texte sur une ligne (pseudo, e-mail) : invisibles retirés, espaces aux extrémités supprimés. */
    public static function line(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return trim(self::stripInvisible(str_replace(["\r", "\n", "\t"], ' ', $value)));
    }

    /**
     * Texte multiligne (critique, bio) : retours à la ligne unifiés, 2 lignes vides au maximum à la suite,
     * espaces en fin de ligne et aux extrémités supprimés.
     */
    public static function multiline(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = self::stripInvisible(str_replace(["\r\n", "\r"], "\n", $value));
        $value = preg_replace('/[ \t]+\n/', "\n", $value);
        $value = preg_replace("/\n{3,}/", "\n\n", $value);

        return trim($value);
    }

    private static function stripInvisible(string $value): string
    {
        return preg_replace(self::INVISIBLE, '', $value) ?? $value;
    }
}
