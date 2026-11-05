<?php

namespace App\Tests\Functional;

class LikeTest extends AppWebTestCase
{
    public function testAnonymousCannotLike(): void
    {
        $review = $this->createReview($this->createUser(), $this->createAlbum());

        $this->client->request('POST', '/review/' . $review->getId() . '/like');
        self::assertResponseStatusCodeSame(401);
    }

    public function testLikeRequiresCsrfToken(): void
    {
        $review = $this->createReview($this->createUser(), $this->createAlbum());
        $this->client->loginUser($this->createUser('fan'));

        $this->client->request('POST', '/review/' . $review->getId() . '/like');
        self::assertResponseStatusCodeSame(403);
    }

    public function testLikeAndUnlike(): void
    {
        $review = $this->createReview($this->createUser(), $this->createAlbum());
        $this->client->loginUser($this->createUser('fan'));

        $crawler = $this->client->request('GET', '/');
        $token = $crawler->filter('meta[name="csrf-like"]')->attr('content');
        $like = fn () => $this->client->request('POST', '/review/' . $review->getId() . '/like', [], [], ['HTTP_X_CSRF_TOKEN' => $token]);

        $like();
        self::assertSame(['isLiked' => true, 'count' => 1], $this->json());

        $like();
        self::assertSame(['isLiked' => false, 'count' => 0], $this->json());
    }
}
