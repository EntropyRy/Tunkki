<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230327110455 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
        $this->addSql('ALTER TABLE classification__category CHANGE context context VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE classification__collection CHANGE context context VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE classification__context CHANGE id id VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE classification__tag CHANGE context context VARCHAR(255) DEFAULT NULL');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE classification__tag CHANGE context context INT DEFAULT NULL');
        $this->addSql('ALTER TABLE classification__category CHANGE context context INT DEFAULT NULL');
        $this->addSql('ALTER TABLE classification__collection CHANGE context context INT DEFAULT NULL');
        $this->addSql('ALTER TABLE classification__context CHANGE id id INT AUTO_INCREMENT NOT NULL');
    }
}
