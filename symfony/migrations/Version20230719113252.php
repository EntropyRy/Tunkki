<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230719113252 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Booking ADD accessory_price NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE Booking_audit ADD accessory_price NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE contract ADD valid_from DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contract DROP valid_from');
        $this->addSql('ALTER TABLE Booking_audit DROP accessory_price');
        $this->addSql('ALTER TABLE Booking DROP accessory_price');
    }
}
