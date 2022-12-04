<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221204121641 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event ADD nakki_required_for_ticket_reservation TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE nakki DROP FOREIGN KEY FK_955FE106602AD315');
        $this->addSql('ALTER TABLE nakki ADD CONSTRAINT FK_955FE106602AD315 FOREIGN KEY (responsible_id) REFERENCES member (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA37E3C61F9');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA37E3C61F9 FOREIGN KEY (owner_id) REFERENCES member (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA37E3C61F9');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA37E3C61F9 FOREIGN KEY (owner_id) REFERENCES member (id)');
        $this->addSql('ALTER TABLE nakki DROP FOREIGN KEY FK_955FE106602AD315');
        $this->addSql('ALTER TABLE nakki ADD CONSTRAINT FK_955FE106602AD315 FOREIGN KEY (responsible_id) REFERENCES member (id)');
        $this->addSql('ALTER TABLE event DROP nakki_required_for_ticket_reservation');
    }
}
