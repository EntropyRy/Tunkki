<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191217145045 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Booking CHANGE `returning` return_date DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE Booking CHANGE rentingPrivileges_id renting_privileges_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE Booking CHANGE givenAwayBy_id given_away_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE Booking CHANGE receivedBy_id received_by_id INT DEFAULT NULL');
        $this->addSql('RENAME TABLE package_whocanrentchoice TO package_who_can_rent_choice');
        $this->addSql('RENAME TABLE item_whocanrentchoice TO item_who_can_rent_choice');
// ALTER TABLE Booking_audit ADD renting_privileges_id INT DEFAULT NULL, ADD given_away_by_id INT DEFAULT NULL, ADD received_by_id INT DEFAULT NULL, DROP rentingPrivileges_id, DROP givenAwayBy_id, DROP receivedBy_id, CHANGE name name VARCHAR(190) DEFAULT NULL, CHANGE referenceNumber referenceNumber VARCHAR(190) DEFAULT NULL, CHANGE renterHash renterHash VARCHAR(199) DEFAULT NULL, CHANGE `returning` return_date DATETIME DEFAULT NULL;
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Booking CHANGE return_date `returning` DATETIME DEFAULT NULL');
    }
}
