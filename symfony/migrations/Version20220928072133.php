<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220928072133 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE classification__category ADD CONSTRAINT FK_43629B36E25D857E FOREIGN KEY (context) REFERENCES classification__context (id)');
        $this->addSql('ALTER TABLE classification__collection ADD CONSTRAINT FK_A406B56AE25D857E FOREIGN KEY (context) REFERENCES classification__context (id)');
        $this->addSql('ALTER TABLE classification__tag DROP FOREIGN KEY FK_CA57A1C7E25D857E');
        $this->addSql('ALTER TABLE classification__tag CHANGE context context INT DEFAULT NULL');
        $this->addSql('ALTER TABLE classification__tag ADD CONSTRAINT FK_CA57A1C7E25D857E FOREIGN KEY (context) REFERENCES classification__context (id)');
        $this->addSql('ALTER TABLE Item CHANGE NeedsFixing NeedsFixing TINYINT(1) NOT NULL, CHANGE CreatedAt createdAt DATETIME NOT NULL, CHANGE UpdatedAt updatedAt DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE migration_versions (version VARCHAR(14) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, executed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(version)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE item_audit DROP FOREIGN KEY rev_942d62036718b31d0d69f2ed727fc990_fk');
        $this->addSql('DROP INDEX rev_942d62036718b31d0d69f2ed727fc990_idx ON item_audit');
        $this->addSql('CREATE INDEX rev_3c5f2b55d694c80478a68bde1a0e8d59_idx ON item_audit (rev)');
        $this->addSql('ALTER TABLE item_audit ADD CONSTRAINT rev_942d62036718b31d0d69f2ed727fc990_fk FOREIGN KEY (rev) REFERENCES revisions (id)');
        $this->addSql('ALTER TABLE item DROP FOREIGN KEY FK_1F1B251E12469DE2');
        $this->addSql('ALTER TABLE item DROP FOREIGN KEY FK_1F1B251E61220EA6');
        $this->addSql('ALTER TABLE item DROP FOREIGN KEY FK_1F1B251ED079F553');
        $this->addSql('ALTER TABLE item CHANGE NeedsFixing NeedsFixing TINYINT(1) DEFAULT NULL, CHANGE createdAt CreatedAt DATETIME DEFAULT NULL, CHANGE updatedAt UpdatedAt DATETIME DEFAULT NULL');
        $this->addSql('DROP INDEX idx_1f1b251e61220ea6 ON item');
        $this->addSql('CREATE INDEX IDX_BF298A2061220EA6 ON item (creator_id)');
        $this->addSql('DROP INDEX idx_1f1b251ed079f553 ON item');
        $this->addSql('CREATE INDEX IDX_BF298A20D079F553 ON item (modifier_id)');
        $this->addSql('DROP INDEX idx_1f1b251e12469de2 ON item');
        $this->addSql('CREATE INDEX IDX_BF298A2012469DE2 ON item (category_id)');
        $this->addSql('ALTER TABLE item ADD CONSTRAINT FK_1F1B251E12469DE2 FOREIGN KEY (category_id) REFERENCES classification__category (id)');
        $this->addSql('ALTER TABLE item ADD CONSTRAINT FK_1F1B251E61220EA6 FOREIGN KEY (creator_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE item ADD CONSTRAINT FK_1F1B251ED079F553 FOREIGN KEY (modifier_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE classification__category DROP FOREIGN KEY FK_43629B36E25D857E');
        $this->addSql('ALTER TABLE classification__collection DROP FOREIGN KEY FK_A406B56AE25D857E');
        $this->addSql('ALTER TABLE classification__tag DROP FOREIGN KEY FK_CA57A1C7E25D857E');
        $this->addSql('ALTER TABLE classification__tag CHANGE context context VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE classification__tag ADD CONSTRAINT FK_CA57A1C7E25D857E FOREIGN KEY (context) REFERENCES cc_old (id)');
    }
}
