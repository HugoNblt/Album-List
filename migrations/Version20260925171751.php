<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260925171751 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE review_likes (review_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_5A4462663E2E969B (review_id), INDEX IDX_5A446266A76ED395 (user_id), PRIMARY KEY (review_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE review_likes ADD CONSTRAINT FK_5A4462663E2E969B FOREIGN KEY (review_id) REFERENCES review (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE review_likes ADD CONSTRAINT FK_5A446266A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE review_likes DROP FOREIGN KEY FK_5A4462663E2E969B');
        $this->addSql('ALTER TABLE review_likes DROP FOREIGN KEY FK_5A446266A76ED395');
        $this->addSql('DROP TABLE review_likes');
    }
}
