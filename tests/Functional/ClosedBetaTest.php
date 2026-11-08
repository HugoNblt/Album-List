<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ClosedBetaTest extends AppWebTestCase
{
    protected function setUp(): void
    {
        // Inscriptions fermées, comme dans la release de bêta privée
        $_SERVER['APP_REGISTRATION_ENABLED'] = $_ENV['APP_REGISTRATION_ENABLED'] = '0';
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $_SERVER['APP_REGISTRATION_ENABLED'] = $_ENV['APP_REGISTRATION_ENABLED'] = '1';
        parent::tearDown();
    }

    public function testRegistrationIsClosed(): void
    {
        $this->client->request('GET', '/register');
        self::assertResponseStatusCodeSame(403);
        self::assertSelectorTextContains('h1', 'Bêta privée');

        $this->client->request('POST', '/register', ['registration_form' => [
            'username' => 'intrus',
            'email' => 'intrus@example.test',
            'plainPassword' => 'MotDePasse!42',
        ]]);
        self::assertResponseStatusCodeSame(403);
        self::assertNull($this->em()->getRepository(User::class)->findOneBy(['username' => 'intrus']));
    }

    public function testRegistrationLinksAreHidden(): void
    {
        $this->client->request('GET', '/');
        self::assertSelectorNotExists('a[href="/register"]');

        $this->client->request('GET', '/login');
        self::assertSelectorNotExists('a[href="/register"]');
        self::assertSelectorTextContains('.register-footer-link', 'sur invitation');
    }

    public function testAdminCanCreateTesterAccounts(): void
    {
        $tester = $this->commandTester();

        $tester->execute(['username' => 'user1', 'email' => 'User1@Example.test']);
        $tester->assertCommandIsSuccessful();
        self::assertMatchesRegularExpression('/Mot de passe généré/', $tester->getDisplay());
        preg_match('/^Mot de passe : (\S{16})$/m', $tester->getDisplay(), $m);
        self::assertNotEmpty($m[1] ?? null, 'le mot de passe généré est affiché');

        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => 'user1@example.test']);
        self::assertNotNull($user);

        // Le testeur peut se connecter avec le mot de passe généré
        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', ['_username' => 'user1@example.test', '_password' => $m[1]]);
        $this->client->request('GET', '/profile');
        self::assertResponseRedirects('/user/user1');
    }

    public function testCommandRejectsInvalidOrDuplicateAccounts(): void
    {
        $this->createUser('user2');
        $tester = $this->commandTester();

        self::assertSame(Command::INVALID, $tester->execute(['username' => 'user2', 'email' => 'autre@example.test']));
        self::assertStringContainsString('déjà utilisé', $tester->getDisplay());

        self::assertSame(Command::INVALID, $tester->execute(['username' => 'Hélène', 'email' => 'helene@example.test']));
        self::assertSame(Command::INVALID, $tester->execute(['username' => 'user3', 'email' => 'user3@example.test', '--password' => 'court']));
    }

    private function commandTester(): CommandTester
    {
        $application = new Application(self::$kernel);

        return new CommandTester($application->find('app:user:create'));
    }
}
