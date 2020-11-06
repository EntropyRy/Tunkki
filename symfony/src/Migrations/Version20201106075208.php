<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201106075208 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D6497597D3FE');
        $this->addSql('DROP INDEX uniq_c560d7617597d3fe ON user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D6497597D3FE ON user (member_id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D6497597D3FE FOREIGN KEY (member_id) REFERENCES member (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D6497597D3FE');
        $this->addSql('DROP INDEX uniq_8d93d6497597d3fe ON user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C560D7617597D3FE ON user (member_id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D6497597D3FE FOREIGN KEY (member_id) REFERENCES member (id)');
    }
}
