<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251221143551 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nakkikone_id to Nakki and NakkiBooking tables, migrate relations';
    }

    public function up(Schema $schema): void
    {
        // === NAKKI TABLE ===

        // 1. Add nakkikone_id column (nullable initially)
        $this->addSql('ALTER TABLE nakki ADD nakkikone_id INT DEFAULT NULL');

        // 2. Populate nakkikone_id from event_id via join
        $this->addSql('
            UPDATE nakki n
            INNER JOIN nakkikone nk ON n.event_id = nk.event_id
            SET n.nakkikone_id = nk.id
        ');

        // 3. Add FK constraint and index
        $this->addSql('ALTER TABLE nakki ADD CONSTRAINT FK_955FE106D0901C40 FOREIGN KEY (nakkikone_id) REFERENCES nakkikone (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_955FE106D0901C40 ON nakki (nakkikone_id)');

        // === NAKKI_BOOKING TABLE ===

        // 4. Add nakkikone_id column (nullable initially)
        $this->addSql('ALTER TABLE nakki_booking ADD nakkikone_id INT DEFAULT NULL');

        // 5. Populate nakkikone_id from event_id via join
        $this->addSql('
            UPDATE nakki_booking nb
            INNER JOIN nakkikone nk ON nb.event_id = nk.event_id
            SET nb.nakkikone_id = nk.id
        ');

        // 6. Add FK constraint and index
        $this->addSql('ALTER TABLE nakki_booking ADD CONSTRAINT FK_13C2BAC5D0901C40 FOREIGN KEY (nakkikone_id) REFERENCES nakkikone (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_13C2BAC5D0901C40 ON nakki_booking (nakkikone_id)');

        // NOTE: We do NOT drop event_id columns yet - that happens in the final cleanup migration
        // This allows dual-write period for safety
    }

    public function postUp(Schema $schema): void
    {
        // Verify all Nakki records have nakkikone_id populated (runs after migration completes)
        $nullNakki = $this->connection->fetchOne('
            SELECT COUNT(*)
            FROM nakki
            WHERE nakkikone_id IS NULL
        ');

        if ($nullNakki > 0) {
            throw new \RuntimeException(
                "Migration incomplete: Found {$nullNakki} Nakki records without nakkikone_id."
            );
        }

        // Verify all NakkiBooking records have nakkikone_id populated
        $nullBooking = $this->connection->fetchOne('
            SELECT COUNT(*)
            FROM nakki_booking
            WHERE nakkikone_id IS NULL
        ');

        if ($nullBooking > 0) {
            throw new \RuntimeException(
                "Migration incomplete: Found {$nullBooking} NakkiBooking records without nakkikone_id."
            );
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE nakki DROP FOREIGN KEY FK_955FE106D0901C40');
        $this->addSql('DROP INDEX IDX_955FE106D0901C40 ON nakki');
        $this->addSql('ALTER TABLE nakki DROP nakkikone_id');
        $this->addSql('ALTER TABLE nakki_booking DROP FOREIGN KEY FK_13C2BAC5D0901C40');
        $this->addSql('DROP INDEX IDX_13C2BAC5D0901C40 ON nakki_booking');
        $this->addSql('ALTER TABLE nakki_booking DROP nakkikone_id');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
