<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250826090235 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add name_en column and backfill with existing name values';
    }

    public function up(Schema $schema): void
    {
        // Add English name column
        $this->addSql('ALTER TABLE location ADD name_en VARCHAR(255) DEFAULT NULL');
        // Backfill new column from existing name data
        $this->addSql('UPDATE location SET name_en = name WHERE name IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE location DROP name_en');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
