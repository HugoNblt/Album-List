<?php

namespace App\Tests\Functional;

use App\Entity\User;

class SecurityTest extends AppWebTestCase
{
    private function register(string $username, string $email, string $password): void
    {
        $this->client->request('GET', '/register');
        $this->client->submitForm("S'inscrire", [
            'registration_form[username]' => $username,
            'registration_form[email]' => $email,
            'registration_form[plainPassword]' => $password,
        ]);
    }

    public function testRegistrationCreatesAccountAndLogsIn(): void
    {
        $this->register('  bob  ', 'Bob@Example.test', 'MotDePasse!42');

        self::assertResponseRedirects();
        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => 'bob@example.test']);
        self::assertNotNull($user, 'e-mail enregistré en minuscules');
        self::assertSame('bob', $user->getUsername(), 'espaces retirés du pseudo');

        $this->client->request('GET', '/profile');
        self::assertResponseRedirects('/user/bob');
    }

    public function testRegistrationRejectsInvalidData(): void
    {
        $this->register('Hélène', 'helene@example.test', 'court');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.form-error', 'lettres sans accent');
        self::assertSelectorTextContains('body', 'au moins 8 caractères');
        self::assertNull($this->em()->getRepository(User::class)->findOneBy(['email' => 'helene@example.test']));
    }

    public function testRegistrationIsLimitedPerIp(): void
    {

        foreach (['user1', 'user2', 'user3'] as $name) {
            $this->register($name, $name . '@example.test', 'MotDePasse!42');
            self::assertResponseRedirects();
            $this->client->getCookieJar()->clear(); // nouvelle session, comme un nouveau visiteur sur la même IP
        }

        $this->register('user4', 'user4@example.test', 'MotDePasse!42');
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.form-alert', "Trop d'inscriptions");
    }

    public function testLoginWithWrongPasswordFails(): void
    {
        $this->createUser('carol');

        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', ['_username' => 'carol@example.test', '_password' => 'mauvais']);
        $this->client->followRedirect();

        self::assertSelectorExists('.form-alert');
        $this->client->request('GET', '/profile');
        self::assertResponseRedirects('/login');
    }

    public function testLoginAndLogout(): void
    {
        $this->createUser('dave');

        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', ['_username' => 'dave@example.test', '_password' => 'MotDePasse!42']);
        $this->client->request('GET', '/profile');
        self::assertResponseRedirects('/user/dave');

        $crawler = $this->client->request('GET', '/');
        $this->client->click($crawler->selectLink('Déconnexion')->link());
        $this->client->request('GET', '/profile');
        self::assertResponseRedirects('/login');
    }

    public function testLogoutWithoutTokenIsRefused(): void
    {
        $this->client->loginUser($this->createUser('erin'));

        $this->client->request('GET', '/logout');
        self::assertResponseStatusCodeSame(403);
    }
}
