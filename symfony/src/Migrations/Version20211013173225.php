<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211013173225 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE nakki_booking (id INT AUTO_INCREMENT NOT NULL, nakki_id INT NOT NULL, member_id INT DEFAULT NULL, event_id INT NOT NULL, start_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', end_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_13C2BAC5DE7D37DF (nakki_id), INDEX IDX_13C2BAC57597D3FE (member_id), INDEX IDX_13C2BAC571F7E88B (event_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE nakki_booking ADD CONSTRAINT FK_13C2BAC5DE7D37DF FOREIGN KEY (nakki_id) REFERENCES nakki (id)');
        $this->addSql('ALTER TABLE nakki_booking ADD CONSTRAINT FK_13C2BAC57597D3FE FOREIGN KEY (member_id) REFERENCES member (id)');
        $this->addSql('ALTER TABLE nakki_booking ADD CONSTRAINT FK_13C2BAC571F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE event ADD nakkikone_enabled TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE nakki ADD event_id INT NOT NULL, ADD nakki_interval VARCHAR(255) NOT NULL COMMENT \'(DC2Type:dateinterval)\'');
        $this->addSql('ALTER TABLE nakki ADD CONSTRAINT FK_955FE10671F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('CREATE INDEX IDX_955FE10671F7E88B ON nakki (event_id)');
        $this->addSql('ALTER TABLE nakki_definition ADD name_en VARCHAR(255) NOT NULL, ADD description_en LONGTEXT NOT NULL, CHANGE name name_fi VARCHAR(255) NOT NULL, CHANGE description description_fi LONGTEXT NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE nakki_booking');
        $this->addSql('ALTER TABLE event DROP nakkikone_enabled');
        $this->addSql('ALTER TABLE nakki DROP FOREIGN KEY FK_955FE10671F7E88B');
        $this->addSql('DROP INDEX IDX_955FE10671F7E88B ON nakki');
        $this->addSql('ALTER TABLE nakki DROP event_id, DROP nakki_interval');
        $this->addSql('ALTER TABLE nakki_definition ADD name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, ADD description LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, DROP name_fi, DROP name_en, DROP description_fi, DROP description_en');
    }
}
