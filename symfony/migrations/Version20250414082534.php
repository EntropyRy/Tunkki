<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250414082534 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE stream (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', listeners INT NOT NULL, online TINYINT(1) NOT NULL, filename VARCHAR(190) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE stream_artist (id INT AUTO_INCREMENT NOT NULL, artist_id INT NOT NULL, stream_id INT NOT NULL, started_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', stopped_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_DB7646B2B7970CF8 (artist_id), INDEX IDX_DB7646B2D0ED463E (stream_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE stream_artist ADD CONSTRAINT FK_DB7646B2B7970CF8 FOREIGN KEY (artist_id) REFERENCES artist (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE stream_artist ADD CONSTRAINT FK_DB7646B2D0ED463E FOREIGN KEY (stream_id) REFERENCES stream (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE media__media CHANGE content_size content_size BIGINT DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE stream_artist DROP FOREIGN KEY FK_DB7646B2B7970CF8
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE stream_artist DROP FOREIGN KEY FK_DB7646B2D0ED463E
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE stream
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE stream_artist
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE media__media CHANGE content_size content_size INT DEFAULT NULL
        SQL);
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
