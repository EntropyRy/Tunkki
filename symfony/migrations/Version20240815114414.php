<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240815114414 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Booking ADD version INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE Booking_audit ADD version INT DEFAULT 1');
        $this->addSql('ALTER TABLE event ADD version INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event DROP version');
        $this->addSql('ALTER TABLE Booking_audit DROP version');
        $this->addSql('ALTER TABLE Booking DROP version');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
