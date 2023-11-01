<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231024142004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE product_event (product_id INT NOT NULL, event_id INT NOT NULL, INDEX IDX_9AF271FB4584665A (product_id), INDEX IDX_9AF271FB71F7E88B (event_id), PRIMARY KEY(product_id, event_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE product_event ADD CONSTRAINT FK_9AF271FB4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_event ADD CONSTRAINT FK_9AF271FB71F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product ADD stripe_price_id VARCHAR(255) NOT NULL, ADD created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product_event DROP FOREIGN KEY FK_9AF271FB4584665A');
        $this->addSql('ALTER TABLE product_event DROP FOREIGN KEY FK_9AF271FB71F7E88B');
        $this->addSql('DROP TABLE product_event');
        $this->addSql('ALTER TABLE product DROP stripe_price_id, DROP created_at, DROP updated_at');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
