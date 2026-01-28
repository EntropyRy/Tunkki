<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260128100505 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE booking_accessory_snapshot (id INT AUTO_INCREMENT NOT NULL, compensation_price INT DEFAULT NULL, name VARCHAR(190) DEFAULT NULL, count VARCHAR(50) DEFAULT NULL, booking_id INT NOT NULL, accessory_id INT DEFAULT NULL, INDEX IDX_17E2BC6F3301C60 (booking_id), INDEX IDX_17E2BC6F27E8CC78 (accessory_id), UNIQUE INDEX uniq_booking_accessory_snapshot (booking_id, accessory_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE booking_accessory_snapshot ADD CONSTRAINT FK_17E2BC6F3301C60 FOREIGN KEY (booking_id) REFERENCES Booking (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE booking_accessory_snapshot ADD CONSTRAINT FK_17E2BC6F27E8CC78 FOREIGN KEY (accessory_id) REFERENCES Accessory (id) ON DELETE SET NULL');
        $this->addSql('INSERT INTO booking_accessory_snapshot (booking_id, accessory_id, name, count, compensation_price) SELECT ba.booking_id, ba.accessory_id, ac.name, a.count, ac.compensationPrice FROM booking_accessory ba INNER JOIN Accessory a ON a.id = ba.accessory_id LEFT JOIN AccessoryChoice ac ON ac.id = a.name_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking_accessory_snapshot DROP FOREIGN KEY FK_17E2BC6F3301C60');
        $this->addSql('ALTER TABLE booking_accessory_snapshot DROP FOREIGN KEY FK_17E2BC6F27E8CC78');
        $this->addSql('DROP TABLE booking_accessory_snapshot');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
