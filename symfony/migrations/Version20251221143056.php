<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251221143056 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create nakkikone table and migrate Event nakki configuration data';
    }

    public function up(Schema $schema): void
    {
        // 1. Create nakkikone table
        $this->addSql('CREATE TABLE nakkikone (id INT AUTO_INCREMENT NOT NULL, enabled TINYINT NOT NULL, info_fi LONGTEXT DEFAULT NULL, info_en LONGTEXT DEFAULT NULL, show_link_in_event TINYINT NOT NULL, require_different_times TINYINT NOT NULL, required_for_ticket_reservation TINYINT NOT NULL, event_id INT NOT NULL, UNIQUE INDEX UNIQ_784333E671F7E88B (event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        // 2. Create join table for responsible admins
        $this->addSql('CREATE TABLE nakkikone_responsible_admins (nakkikone_id INT NOT NULL, member_id INT NOT NULL, INDEX IDX_4CB340C6D0901C40 (nakkikone_id), INDEX IDX_4CB340C67597D3FE (member_id), PRIMARY KEY (nakkikone_id, member_id)) DEFAULT CHARACTER SET utf8mb4');

        // 3. Add FK constraints
        $this->addSql('ALTER TABLE nakkikone ADD CONSTRAINT FK_784333E671F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE nakkikone_responsible_admins ADD CONSTRAINT FK_4CB340C6D0901C40 FOREIGN KEY (nakkikone_id) REFERENCES nakkikone (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE nakkikone_responsible_admins ADD CONSTRAINT FK_4CB340C67597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE CASCADE');

        // 4. Migrate data from Event to Nakkikone
        // Only create Nakkikone for events that have nakki data or nakkikone_enabled = 1
        $this->addSql('
            INSERT INTO nakkikone (
                event_id,
                enabled,
                info_fi,
                info_en,
                show_link_in_event,
                require_different_times,
                required_for_ticket_reservation
            )
            SELECT
                e.id,
                COALESCE(e.nakkikone_enabled, 0),
                e.nakki_info_fi,
                e.nakki_info_en,
                COALESCE(e.show_nakkikone_link_in_event, 0),
                COALESCE(e.require_nakki_bookings_to_be_different_times, 1),
                COALESCE(e.nakki_required_for_ticket_reservation, 0)
            FROM event e
            WHERE
                e.nakkikone_enabled = 1
                OR EXISTS (SELECT 1 FROM nakki n WHERE n.event_id = e.id)
                OR EXISTS (SELECT 1 FROM nakki_booking nb WHERE nb.event_id = e.id)
        ');

        // 5. Migrate responsible admins from Event to join table (if event_member table exists and has data)
        // Note: This migration will work even if event_member doesn't exist - it will just insert 0 rows
        $this->addSql('
            INSERT IGNORE INTO nakkikone_responsible_admins (nakkikone_id, member_id)
            SELECT nk.id, em.member_id
            FROM nakkikone nk
            INNER JOIN event_member em ON em.event_id = nk.event_id
        ');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE nakkikone DROP FOREIGN KEY FK_784333E671F7E88B');
        $this->addSql('ALTER TABLE nakkikone_responsible_admins DROP FOREIGN KEY FK_4CB340C6D0901C40');
        $this->addSql('ALTER TABLE nakkikone_responsible_admins DROP FOREIGN KEY FK_4CB340C67597D3FE');
        $this->addSql('DROP TABLE nakkikone');
        $this->addSql('DROP TABLE nakkikone_responsible_admins');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
