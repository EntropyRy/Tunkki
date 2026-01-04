<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260104194521 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE member DROP rejectReasonSent');
        $this->addSql('ALTER TABLE ticket RENAME INDEX idx_97a0ada3a7e9f4aa TO IDX_97A0ADA3146D8724');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE member ADD rejectReasonSent TINYINT NOT NULL');
        $this->addSql('ALTER TABLE ticket RENAME INDEX idx_97a0ada3146d8724 TO IDX_97A0ADA3A7E9F4AA');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
