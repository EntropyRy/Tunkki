<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221116135726 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE nakki_booking DROP FOREIGN KEY FK_13C2BAC57597D3FE');
        $this->addSql('ALTER TABLE nakki_booking ADD CONSTRAINT FK_13C2BAC57597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE nakki_booking DROP FOREIGN KEY FK_13C2BAC57597D3FE');
        $this->addSql('ALTER TABLE nakki_booking ADD CONSTRAINT FK_13C2BAC57597D3FE FOREIGN KEY (member_id) REFERENCES member (id)');
    }
}
