<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260223084611 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Item ADD Decommissioned TINYINT NOT NULL');
        $this->addSql('ALTER TABLE Item_audit ADD Decommissioned TINYINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Item DROP Decommissioned');
        $this->addSql('ALTER TABLE Item_audit DROP Decommissioned');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
