<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260127122721 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE booking_item_snapshot (id INT AUTO_INCREMENT NOT NULL, rent NUMERIC(7, 2) DEFAULT NULL, compensation_price NUMERIC(7, 2) DEFAULT NULL, booking_id INT NOT NULL, item_id INT DEFAULT NULL, INDEX IDX_4B799D3E3301C60 (booking_id), INDEX IDX_4B799D3E126F525E (item_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE booking_package_snapshot (id INT AUTO_INCREMENT NOT NULL, rent NUMERIC(7, 2) DEFAULT NULL, compensation_price NUMERIC(10, 2) DEFAULT NULL, booking_id INT NOT NULL, package_id INT DEFAULT NULL, INDEX IDX_F61FD01D3301C60 (booking_id), INDEX IDX_F61FD01DF44CABFF (package_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE booking_item_snapshot ADD CONSTRAINT FK_4B799D3E3301C60 FOREIGN KEY (booking_id) REFERENCES Booking (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE booking_item_snapshot ADD CONSTRAINT FK_4B799D3E126F525E FOREIGN KEY (item_id) REFERENCES Item (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE booking_package_snapshot ADD CONSTRAINT FK_F61FD01D3301C60 FOREIGN KEY (booking_id) REFERENCES Booking (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE booking_package_snapshot ADD CONSTRAINT FK_F61FD01DF44CABFF FOREIGN KEY (package_id) REFERENCES Package (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_booking_item_snapshot ON booking_item_snapshot (booking_id, item_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_booking_package_snapshot ON booking_package_snapshot (booking_id, package_id)');
        $this->addSql('INSERT INTO booking_item_snapshot (booking_id, item_id, rent, compensation_price) SELECT bi.booking_id, bi.item_id, i.Rent, i.compensationPrice FROM booking_item bi INNER JOIN Item i ON i.id = bi.item_id');
        $this->addSql('INSERT INTO booking_package_snapshot (booking_id, package_id, rent, compensation_price) SELECT bp.booking_id, bp.package_id, p.rent, p.compensation_price FROM booking_package bp INNER JOIN Package p ON p.id = bp.package_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking_item_snapshot DROP FOREIGN KEY FK_4B799D3E3301C60');
        $this->addSql('ALTER TABLE booking_item_snapshot DROP FOREIGN KEY FK_4B799D3E126F525E');
        $this->addSql('ALTER TABLE booking_package_snapshot DROP FOREIGN KEY FK_F61FD01D3301C60');
        $this->addSql('ALTER TABLE booking_package_snapshot DROP FOREIGN KEY FK_F61FD01DF44CABFF');
        $this->addSql('DROP TABLE booking_item_snapshot');
        $this->addSql('DROP TABLE booking_package_snapshot');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
