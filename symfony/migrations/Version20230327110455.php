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
        return 'Change classification context PK and dependent foreign keys from INT to VARCHAR(255) (Sonata ClassificationBundle upgrade safe migration).';
    }

    public function up(Schema $schema): void
    {
        // Adjust classification context/id & referencing FKs (robust against FK restrictions)
        //
        // Strategy:
        //  1. If already migrated (id already VARCHAR), exit early.
        //  2. Drop existing foreign keys referencing classification__context.id
        //  3. Alter primary table id type
        //  4. Alter referencing columns to VARCHAR(255) DEFAULT NULL
        //  5. Re-create foreign keys
        //
        // Note: We avoid relying solely on FOREIGN_KEY_CHECKS toggling (MySQL 8 can still
        //       error for certain ALTER sequences) by explicitly dropping/re-adding FKs.

        $schemaManager = $this->connection->createSchemaManager();
        if ('string' === $schemaManager->introspectTable('classification__context')->getColumn('id')->getType()->getName()) {
            // Already applied; no-op idempotency
            return;
        }

        // Drop foreign keys
        $this->addSql('ALTER TABLE classification__category DROP FOREIGN KEY FK_43629B36E25D857E');
        $this->addSql('ALTER TABLE classification__collection DROP FOREIGN KEY FK_A406B56AE25D857E');
        $this->addSql('ALTER TABLE classification__tag DROP FOREIGN KEY FK_CA57A1C7E25D857E');

        // Alter primary key table
        $this->addSql('ALTER TABLE classification__context CHANGE id id VARCHAR(255) NOT NULL');

        // Alter referencing columns
        $this->addSql('ALTER TABLE classification__category CHANGE context context VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE classification__collection CHANGE context context VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE classification__tag CHANGE context context VARCHAR(255) DEFAULT NULL');

        // Re-create foreign keys with updated type
        $this->addSql('ALTER TABLE classification__category ADD CONSTRAINT FK_43629B36E25D857E FOREIGN KEY (context) REFERENCES classification__context (id) ON UPDATE CASCADE ON DELETE SET NULL');
        $this->addSql('ALTER TABLE classification__collection ADD CONSTRAINT FK_A406B56AE25D857E FOREIGN KEY (context) REFERENCES classification__context (id) ON UPDATE CASCADE ON DELETE SET NULL');
        $this->addSql('ALTER TABLE classification__tag ADD CONSTRAINT FK_CA57A1C7E25D857E FOREIGN KEY (context) REFERENCES classification__context (id) ON UPDATE CASCADE ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // Reverse changes (convert VARCHAR back to INT) – only if current type is VARCHAR.
        // This keeps the down() safe if already reverted or never applied.

        $schemaManager = $this->connection->createSchemaManager();
        if ('string' !== $schemaManager->introspectTable('classification__context')->getColumn('id')->getType()->getName()) {
            return; // Already reverted or never upgraded
        }

        // Drop new foreign keys
        $this->addSql('ALTER TABLE classification__category DROP FOREIGN KEY FK_43629B36E25D857E');
        $this->addSql('ALTER TABLE classification__collection DROP FOREIGN KEY FK_A406B56AE25D857E');
        $this->addSql('ALTER TABLE classification__tag DROP FOREIGN KEY FK_CA57A1C7E25D857E');

        // Revert column types
        $this->addSql('ALTER TABLE classification__tag CHANGE context context INT DEFAULT NULL');
        $this->addSql('ALTER TABLE classification__category CHANGE context context INT DEFAULT NULL');
        $this->addSql('ALTER TABLE classification__collection CHANGE context context INT DEFAULT NULL');
        $this->addSql('ALTER TABLE classification__context CHANGE id id INT AUTO_INCREMENT NOT NULL');

        // Recreate original foreign keys (assuming original ON DELETE/UPDATE defaults)
        $this->addSql('ALTER TABLE classification__category ADD CONSTRAINT FK_43629B36E25D857E FOREIGN KEY (context) REFERENCES classification__context (id)');
        $this->addSql('ALTER TABLE classification__collection ADD CONSTRAINT FK_A406B56AE25D857E FOREIGN KEY (context) REFERENCES classification__context (id)');
        $this->addSql('ALTER TABLE classification__tag ADD CONSTRAINT FK_CA57A1C7E25D857E FOREIGN KEY (context) REFERENCES classification__context (id)');
    }
}
