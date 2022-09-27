<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220926163021 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE media__gallery_item (id INT AUTO_INCREMENT NOT NULL, gallery_id INT DEFAULT NULL, media_id INT DEFAULT NULL, position INT NOT NULL, enabled TINYINT(1) NOT NULL, updated_at DATETIME NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_3238519A4E7AF8F (gallery_id), INDEX IDX_3238519AEA9FDD75 (media_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE "utf8mb4_unicode_ci" ENGINE = InnoDB');
        $this->addSql('ALTER TABLE media__gallery_item ADD CONSTRAINT FK_3238519A4E7AF8F FOREIGN KEY (gallery_id) REFERENCES media__gallery (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE media__gallery_item ADD CONSTRAINT FK_3238519AEA9FDD75 FOREIGN KEY (media_id) REFERENCES media__media (id) ON DELETE CASCADE');
        $this->addSql('DROP TABLE media__gallery_media');
        $this->addSql('DROP TABLE notification__message');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
        $this->addSql('alter table classification__category drop foreign key FK_43629B36E25D857E');
        $this->addSql('alter table classification__collection drop foreign key FK_A406B56AE25D857E');
        $this->addSql('UPDATE media__media SET cdn_is_flushable = false WHERE cdn_is_flushable IS NULL');
        $this->addSql('UPDATE classification__category SET context = 1 WHERE context="default"');
        $this->addSql('UPDATE classification__category SET context = 2 WHERE context="item"');
        $this->addSql('UPDATE classification__category SET context = 3 WHERE context="sonata_category"');
        $this->addSql('UPDATE classification__category SET context = 4 WHERE context="sonata_collection"');
/*        $this->addSql('UPDATE classification__context SET id = NULL WHERE id="default"');
        $this->addSql('UPDATE classification__context SET id = NULL WHERE id="item"');
        $this->addSql('UPDATE classification__context SET id = NULL WHERE id="sonata_category"');
        $this->addSql('UPDATE classification__context SET id = NULL WHERE id="sonata_collection"');*/
        $this->addSql('UPDATE classification__collection SET context = 1 WHERE context="default"');
        $this->addSql('UPDATE classification__collection SET context = 2 WHERE context="item"');
        $this->addSql('UPDATE classification__collection SET context = 3 WHERE context="sonata_category"');
        $this->addSql('UPDATE classification__collection SET context = 4 WHERE context="sonata_collection"');
        $this->addSql('UPDATE classification__tag SET context = 1 WHERE context="default"');
        $this->addSql('UPDATE classification__tag SET context = 2 WHERE context="item"');
        $this->addSql('UPDATE classification__tag SET context = 3 WHERE context="sonata_category"');
        $this->addSql('UPDATE classification__tag SET context = 4 WHERE context="sonata_collection"');
        $this->addSql('ALTER TABLE media__media CHANGE provider_metadata provider_metadata LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE cdn_is_flushable cdn_is_flushable TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE page__page DROP FOREIGN KEY FK_2FAE39ED158E0B66');
        $this->addSql('DROP INDEX IDX_2FAE39ED158E0B66 ON page__page');
        $this->addSql('ALTER TABLE page__page DROP target_id');
        $this->addSql('ALTER TABLE page__snapshot DROP target_id');
        $this->addSql('ALTER TABLE classification__category DROP FOREIGN KEY FK_43629B36EA9FDD75');
        $this->addSql('DROP INDEX IDX_43629B36EA9FDD75 ON classification__category');
        $this->addSql('ALTER TABLE classification__category DROP media_id, CHANGE context context INT DEFAULT NULL');
        $this->addSql('ALTER TABLE classification__collection DROP FOREIGN KEY FK_A406B56AEA9FDD75');
        $this->addSql('DROP INDEX IDX_A406B56AEA9FDD75 ON classification__collection');
        $this->addSql('ALTER TABLE classification__collection DROP media_id, CHANGE context context INT DEFAULT NULL');
        $this->addSql('CREATE TABLE classification__context_new LIKE classification__context');
        $this->addSql('ALTER TABLE classification__context_new CHANGE id id INT AUTO_INCREMENT NOT NULL');
        $this->addSql('Insert into classification__context_new (name,enabled,created_at,updated_at) SELECT name,enabled,created_at,updated_at from classification__context');
        $this->addSql('RENAME table classification__context TO cc_old, classification__context_new TO classification__context');
        $this->addSql('DROP table cc_old');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE media__gallery_media (id INT AUTO_INCREMENT NOT NULL, gallery_id INT DEFAULT NULL, media_id INT DEFAULT NULL, position INT NOT NULL, enabled TINYINT(1) NOT NULL, updated_at DATETIME NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_80D4C541EA9FDD75 (media_id), INDEX IDX_80D4C5414E7AF8F (gallery_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE "utf8_unicode_ci" ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE notification__message (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE "utf8mb3_unicode_ci", body LONGTEXT CHARACTER SET utf8mb3 NOT NULL COLLATE "utf8mb3_unicode_ci" COMMENT \'(DC2Type:json)\', state INT NOT NULL, restart_count INT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, started_at DATETIME DEFAULT NULL, completed_at DATETIME DEFAULT NULL, INDEX notification_message_created_at_idx (created_at), INDEX notification_message_state_idx (state), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE "utf8_unicode_ci" ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE media__gallery_media ADD CONSTRAINT FK_80D4C5414E7AF8F FOREIGN KEY (gallery_id) REFERENCES media__gallery (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE media__gallery_media ADD CONSTRAINT FK_80D4C541EA9FDD75 FOREIGN KEY (media_id) REFERENCES media__media (id) ON DELETE CASCADE');
        $this->addSql('DROP TABLE media__gallery_item');
        $this->addSql('ALTER TABLE classification__category ADD media_id INT DEFAULT NULL, CHANGE context context VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE "utf8mb3_unicode_ci"');
        $this->addSql('ALTER TABLE classification__category ADD CONSTRAINT FK_43629B36EA9FDD75 FOREIGN KEY (media_id) REFERENCES media__media (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_43629B36EA9FDD75 ON classification__category (media_id)');
        $this->addSql('ALTER TABLE classification__collection ADD media_id INT DEFAULT NULL, CHANGE context context VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE "utf8mb3_unicode_ci"');
        $this->addSql('ALTER TABLE classification__collection ADD CONSTRAINT FK_A406B56AEA9FDD75 FOREIGN KEY (media_id) REFERENCES media__media (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_A406B56AEA9FDD75 ON classification__collection (media_id)');
        $this->addSql('ALTER TABLE classification__context CHANGE id id VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE "utf8mb3_unicode_ci"');
        $this->addSql('ALTER TABLE classification__tag CHANGE context context VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE "utf8mb3_unicode_ci"');
        $this->addSql('ALTER TABLE media__media CHANGE provider_metadata provider_metadata LONGTEXT CHARACTER SET utf8mb3 DEFAULT NULL COLLATE "utf8mb3_unicode_ci" COMMENT \'(DC2Type:json)\', CHANGE cdn_is_flushable cdn_is_flushable TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE page__page ADD target_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE page__page ADD CONSTRAINT FK_2FAE39ED158E0B66 FOREIGN KEY (target_id) REFERENCES page__page (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_2FAE39ED158E0B66 ON page__page (target_id)');
        $this->addSql('ALTER TABLE page__snapshot ADD target_id INT DEFAULT NULL');
    }
}
