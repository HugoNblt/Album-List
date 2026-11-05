<?php

namespace App\Tests\Unit;

use App\Util\SearchQuery;
use PHPUnit\Framework\TestCase;

class SearchQueryTest extends TestCase
{
    public function testNormalizeTrimsAndTruncates(): void
    {
        self::assertSame('sleep token', SearchQuery::normalize('  sleep token  '));
        self::assertSame(100, mb_strlen(SearchQuery::normalize(str_repeat('é', 150))));
        self::assertSame('', SearchQuery::normalize(null));
    }

    public function testContainsEscapesWildcards(): void
    {
        self::assertSame('%iowa%', SearchQuery::contains('iowa'));
        self::assertSame('%100\%%', SearchQuery::contains('100%'));
        self::assertSame('%a\_b%', SearchQuery::contains('a_b'));
        self::assertSame('%a\\\\b%', SearchQuery::contains('a\\b'));
    }
}
