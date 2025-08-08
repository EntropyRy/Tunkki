<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250808071442 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Booking CHANGE retrieval retrieval DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE return_date return_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE paid_date paid_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE modified_at modified_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE booking_date booking_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\'');
        $this->addSql('ALTER TABLE Booking_audit CHANGE retrieval retrieval DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE return_date return_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE paid_date paid_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE modified_at modified_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE booking_date booking_date DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Booking_audit CHANGE retrieval retrieval DATETIME DEFAULT NULL, CHANGE return_date return_date DATETIME DEFAULT NULL, CHANGE paid_date paid_date DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE modified_at modified_at DATETIME DEFAULT NULL, CHANGE booking_date booking_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE Booking CHANGE retrieval retrieval DATETIME DEFAULT NULL, CHANGE return_date return_date DATETIME DEFAULT NULL, CHANGE paid_date paid_date DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE modified_at modified_at DATETIME NOT NULL, CHANGE booking_date booking_date DATE NOT NULL');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
