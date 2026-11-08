<?php

namespace App\Tests\Unit;

use App\Entity\Review;
use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EntityValidationTest extends KernelTestCase
{
    private function validator(): ValidatorInterface
    {
        return static::getContainer()->get(ValidatorInterface::class);
    }

    /** @return iterable<string, array{?int, bool}> */
    public static function ratingProvider(): iterable
    {
        yield 'note absente' => [null, false];
        yield 'zéro' => [0, false];
        yield 'minimum' => [1, true];
        yield 'maximum' => [10, true];
        yield 'au-delà' => [11, false];
    }

    #[DataProvider('ratingProvider')]
    public function testRating(?int $rating, bool $valid): void
    {
        $review = (new Review())->setRating($rating)->setContent('Texte');

        self::assertSame($valid, 0 === count($this->validator()->validateProperty($review, 'rating')));
    }

    public function testContentLength(): void
    {
        $ok = (new Review())->setContent(str_repeat('a', 3000));
        $tooLong = (new Review())->setContent(str_repeat('a', 3001));
        $blank = (new Review())->setContent("  \n  ");

        self::assertCount(0, $this->validator()->validateProperty($ok, 'content'));
        self::assertCount(1, $this->validator()->validateProperty($tooLong, 'content'));
        self::assertCount(1, $this->validator()->validateProperty($blank, 'content'));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function usernameProvider(): iterable
    {
        yield 'simple' => ['alice', true];
        yield 'caractères autorisés' => ['a.b_c-1', true];
        yield 'trop court' => ['ab', false];
        yield 'trop long' => [str_repeat('a', 21), false];
        yield 'accent' => ['Hélène', false];
        yield 'espace' => ['a b', false];
        yield 'slash' => ['a/b', false];
        yield 'lettre cyrillique' => ["\u{0430}dmin", false];
    }

    #[DataProvider('usernameProvider')]
    public function testUsername(string $username, bool $valid): void
    {
        $user = (new User())->setUsername($username);

        self::assertSame($valid, 0 === count($this->validator()->validateProperty($user, 'username')));
    }
}
