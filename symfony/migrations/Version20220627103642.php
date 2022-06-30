<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220627103642 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE nakki ADD responsible_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE nakki ADD CONSTRAINT FK_955FE106602AD315 FOREIGN KEY (responsible_id) REFERENCES member (id)');
        $this->addSql('CREATE INDEX IDX_955FE106602AD315 ON nakki (responsible_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE nakki DROP FOREIGN KEY FK_955FE106602AD315');
        $this->addSql('DROP INDEX IDX_955FE106602AD315 ON nakki');
        $this->addSql('ALTER TABLE nakki DROP responsible_id');
    }
}
