<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230803192705 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE happening_booking DROP INDEX UNIQ_D86CE9507597D3FE, ADD INDEX IDX_D86CE9507597D3FE (member_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE happening_booking DROP INDEX IDX_D86CE9507597D3FE, ADD UNIQUE INDEX UNIQ_D86CE9507597D3FE (member_id)');
    }
}
