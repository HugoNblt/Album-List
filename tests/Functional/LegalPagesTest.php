<?php

namespace App\Tests\Functional;

class LegalPagesTest extends AppWebTestCase
{
    public function testLegalPagesAreLinkedFromFooter(): void
    {
        $crawler = $this->client->request('GET', '/');

        $this->client->click($crawler->filter('.site-footer')->selectLink('Mentions légales')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mentions');
        self::assertSelectorTextContains('.legal-page', 'hugo.noju9@gmail.com');

        $crawler = $this->client->request('GET', '/');
        $this->client->click($crawler->filter('.site-footer')->selectLink('Confidentialité')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.legal-page', '14 jours');
        self::assertSelectorTextContains('.legal-page', 'Google Fonts');

        $crawler = $this->client->request('GET', '/');
        $this->client->click($crawler->filter('.site-footer')->selectLink('CGU')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Conditions générales');
        self::assertSelectorTextContains('.legal-page__updated', '10/11/2026');
    }

    public function testRegistrationFormLinksToTermsAndPrivacy(): void
    {
        $this->client->request('GET', '/register');

        self::assertSelectorExists('.checkbox-field a[href="/cgu"]');
        self::assertSelectorExists('.form-hint a[href="/confidentialite"]');
    }
}
