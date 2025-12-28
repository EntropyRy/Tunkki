<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251230120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link tickets to checkouts and store Stripe receipt URL on checkout';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE checkout ADD receipt_url VARCHAR(2048) DEFAULT NULL');
        $this->addSql('ALTER TABLE ticket ADD checkout_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3A7E9F4AA FOREIGN KEY (checkout_id) REFERENCES checkout (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_97A0ADA3A7E9F4AA ON ticket (checkout_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3A7E9F4AA');
        $this->addSql('DROP INDEX IDX_97A0ADA3A7E9F4AA ON ticket');
        $this->addSql('ALTER TABLE ticket DROP checkout_id');
        $this->addSql('ALTER TABLE checkout DROP receipt_url');
    }
}
