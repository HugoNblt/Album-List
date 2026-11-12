<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261110213232 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Date et version des CGU acceptées par l\'utilisateur';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user ADD terms_accepted_at DATETIME DEFAULT NULL, ADD terms_version VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `user` DROP terms_accepted_at, DROP terms_version');
    }
}
