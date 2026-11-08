<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Tests\Unit\AvatarUploaderTest;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ProfileTest extends AppWebTestCase
{
    public function testProfileShowsStatsWithoutCountingRevisions(): void
    {
        $user = $this->createUser();
        $iowa = $this->createAlbum('album0000000000000001', 'Iowa', 'Slipknot');
        $this->createReview($user, $iowa, 7);
        $this->createReview($user, $iowa, 9); // révision : ne compte pas comme un nouvel album
        $this->createReview($user, $this->createAlbum('album0000000000000002', 'Slipknot', 'Slipknot'));
        $this->createReview($user, $this->createAlbum('album0000000000000003', 'Ultra', 'Vatican'));

        $crawler = $this->client->request('GET', '/user/alice');

        self::assertResponseIsSuccessful();
        self::assertSame(['03', '02'], $crawler->filter('.profile-stats dd')->each(fn ($n) => $n->text()));
        self::assertSelectorCount(3, '.disc-row', 'une ligne par album, dernière révision uniquement');
    }

    public function testUnknownProfileReturns404(): void
    {
        $this->client->request('GET', '/user/personne');
        self::assertResponseStatusCodeSame(404);
    }

    public function testEditProfileRequiresLogin(): void
    {
        $this->client->request('GET', '/profile/edit');
        self::assertResponseRedirects('/login');
    }

    public function testChangingUsernameKeepsSessionAndUploadsCleanAvatar(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $photo = sys_get_temp_dir() . '/profil-gps.jpg';
        AvatarUploaderTest::jpegWithGpsExif($photo);

        $this->client->request('GET', '/profile/edit');
        $this->client->submitForm('Enregistrer', [
            'profile_form[username]' => 'alice2',
            'profile_form[bio]' => "  Fan de métal.  \n\n\n\nEt de post-rock. ",
            'profile_form[avatarFile]' => new UploadedFile($photo, 'photo.jpg', 'image/jpeg', null, true),
        ]);

        self::assertResponseRedirects('/user/alice2');
        $this->client->request('GET', '/profile');
        self::assertResponseRedirects('/user/alice2', null, 'toujours connecté après le changement de pseudo');

        $saved = $this->reload($user);
        self::assertInstanceOf(User::class, $saved);
        self::assertSame("Fan de métal.\n\nEt de post-rock.", $saved->getBio());
        self::assertStringEndsWith('.webp', (string) $saved->getAvatar());

        $avatar = static::getContainer()->getParameter('app.avatar_dir') . '/' . $saved->getAvatar();
        self::assertStringNotContainsString('Exif', (string) file_get_contents($avatar));
        unlink($avatar);
    }

    public function testUsernameAlreadyTakenIsRejected(): void
    {
        $this->createUser('pris');
        $this->client->loginUser($this->createUser('alice'));

        $this->client->request('GET', '/profile/edit');
        $this->client->submitForm('Enregistrer', ['profile_form[username]' => 'pris']);

        self::assertResponseStatusCodeSame(422); // formulaire invalide
        self::assertSelectorTextContains('.form-error', 'déjà utilisé');
        $this->client->request('GET', '/profile');
        self::assertResponseRedirects('/user/alice', null, 'la session reste sur le pseudo d\'origine');
    }
}
