<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231024091851 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3D59A4918');
        $this->addSql('DROP INDEX UNIQ_97A0ADA3D59A4918 ON ticket');
        $this->addSql('ALTER TABLE ticket DROP recommended_by_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ticket ADD recommended_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3D59A4918 FOREIGN KEY (recommended_by_id) REFERENCES member (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_97A0ADA3D59A4918 ON ticket (recommended_by_id)');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
