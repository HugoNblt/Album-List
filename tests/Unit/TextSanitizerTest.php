<?php

namespace App\Tests\Unit;

use App\Util\TextSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TextSanitizerTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function lineProvider(): iterable
    {
        yield 'espaces aux extrémités' => ['  user0  ', 'user0'];
        yield 'espace de largeur nulle' => ["ad\u{200B}min", 'admin'];
        yield 'inversion de texte RTL' => ["abc\u{202E}def", 'abcdef'];
        yield 'BOM et caractère de contrôle' => ["\u{FEFF} Bonjour\x07 ", 'Bonjour'];
        yield 'retours à la ligne aplatis' => ["a\nb\tc", 'a b c'];
    }

    #[DataProvider('lineProvider')]
    public function testLine(string $input, string $expected): void
    {
        self::assertSame($expected, TextSanitizer::line($input));
    }

    public function testMultilineNormalizesLineBreaks(): void
    {
        self::assertSame("Ligne 1\n\nLigne 2", TextSanitizer::multiline("  Ligne 1   \r\n\r\n\r\n\r\nLigne 2\t \n  "));
    }

    public function testMultilineKeepsSingleBlankLine(): void
    {
        self::assertSame("Paragraphe 1\n\nParagraphe 2", TextSanitizer::multiline("Paragraphe 1\n\nParagraphe 2"));
    }

    public function testNullStaysNull(): void
    {
        self::assertNull(TextSanitizer::line(null));
        self::assertNull(TextSanitizer::multiline(null));
    }
}
