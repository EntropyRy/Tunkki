<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250122103558 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE member ADD allow_info_mails TINYINT(1) NOT NULL, ADD allow_active_member_mails TINYINT(1) NOT NULL');
        $this->addSql('UPDATE member SET allow_info_mails = 1, allow_active_member_mails = 1');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE member DROP allow_info_mails, DROP allow_active_member_mails');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
