<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250122125125 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE email ADD sent_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE email ADD CONSTRAINT FK_E7927C74A45BB98C FOREIGN KEY (sent_by_id) REFERENCES member (id)');
        $this->addSql('CREATE INDEX IDX_E7927C74A45BB98C ON email (sent_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE email DROP FOREIGN KEY FK_E7927C74A45BB98C');
        $this->addSql('DROP INDEX IDX_E7927C74A45BB98C ON email');
        $this->addSql('ALTER TABLE email DROP sent_by_id');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
