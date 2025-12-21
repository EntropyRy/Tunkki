<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251221153056 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove old Event nakki fields and dual-write columns after Nakkikone aggregate extraction';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event_member DROP FOREIGN KEY `FK_427D8D2A71F7E88B`');
        $this->addSql('ALTER TABLE event_member DROP FOREIGN KEY `FK_427D8D2A7597D3FE`');
        $this->addSql('DROP TABLE event_member');
        $this->addSql('ALTER TABLE event DROP nakkikone_enabled, DROP nakki_info_fi, DROP nakki_info_en, DROP show_nakkikone_link_in_event, DROP require_nakki_bookings_to_be_different_times, DROP nakki_required_for_ticket_reservation');
        $this->addSql('ALTER TABLE nakki DROP FOREIGN KEY `FK_955FE10671F7E88B`');
        $this->addSql('DROP INDEX IDX_955FE10671F7E88B ON nakki');
        $this->addSql('ALTER TABLE nakki DROP event_id, CHANGE nakkikone_id nakkikone_id INT NOT NULL');
        $this->addSql('ALTER TABLE nakki_booking DROP FOREIGN KEY `FK_13C2BAC571F7E88B`');
        $this->addSql('DROP INDEX IDX_13C2BAC571F7E88B ON nakki_booking');
        $this->addSql('ALTER TABLE nakki_booking DROP event_id, CHANGE nakkikone_id nakkikone_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE event_member (event_id INT NOT NULL, member_id INT NOT NULL, INDEX IDX_427D8D2A71F7E88B (event_id), INDEX IDX_427D8D2A7597D3FE (member_id), PRIMARY KEY (event_id, member_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE event_member ADD CONSTRAINT `FK_427D8D2A71F7E88B` FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_member ADD CONSTRAINT `FK_427D8D2A7597D3FE` FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event ADD nakkikone_enabled TINYINT NOT NULL, ADD nakki_info_fi LONGTEXT DEFAULT NULL, ADD nakki_info_en LONGTEXT DEFAULT NULL, ADD show_nakkikone_link_in_event TINYINT DEFAULT NULL, ADD require_nakki_bookings_to_be_different_times TINYINT DEFAULT NULL, ADD nakki_required_for_ticket_reservation TINYINT DEFAULT NULL');
        $this->addSql('ALTER TABLE nakki ADD event_id INT NOT NULL, CHANGE nakkikone_id nakkikone_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE nakki ADD CONSTRAINT `FK_955FE10671F7E88B` FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('CREATE INDEX IDX_955FE10671F7E88B ON nakki (event_id)');
        $this->addSql('ALTER TABLE nakki_booking ADD event_id INT NOT NULL, CHANGE nakkikone_id nakkikone_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE nakki_booking ADD CONSTRAINT `FK_13C2BAC571F7E88B` FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('CREATE INDEX IDX_13C2BAC571F7E88B ON nakki_booking (event_id)');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
