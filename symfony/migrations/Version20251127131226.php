<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251127131226 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE Accessory (id INT AUTO_INCREMENT NOT NULL, name_id INT DEFAULT NULL, count VARCHAR(50) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, INDEX IDX_2340A7BF71179CD6 (name_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE Accessory ADD CONSTRAINT `FK_2340A7BF71179CD6` FOREIGN KEY (name_id) REFERENCES AccessoryChoice (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE AccessoryChoice (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(190) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, compensationPrice INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE access_groups (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, roles JSON NOT NULL COMMENT \'(DC2Type:json)\', active TINYINT(1) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE access_groups_user (access_groups_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_6A61C5565E842834 (access_groups_id), INDEX IDX_6A61C556A76ED395 (user_id), PRIMARY KEY (access_groups_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE access_groups_user ADD CONSTRAINT `FK_6A61C5565E842834` FOREIGN KEY (access_groups_id) REFERENCES access_groups (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE access_groups_user ADD CONSTRAINT `FK_6A61C556A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE artist (id INT AUTO_INCREMENT NOT NULL, member_id INT DEFAULT NULL, picture_id INT DEFAULT NULL, name VARCHAR(190) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, genre VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, type VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, bio LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, hardware VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', bio_en LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, links JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', copy_for_archive TINYINT(1) DEFAULT NULL, INDEX IDX_1599687EE45BDBF (picture_id), INDEX IDX_15996877597D3FE (member_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE artist ADD CONSTRAINT `FK_15996877597D3FE` FOREIGN KEY (member_id) REFERENCES member (id)');
        $this->addSql('ALTER TABLE artist ADD CONSTRAINT `FK_1599687EE45BDBF` FOREIGN KEY (picture_id) REFERENCES media__media (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE BillableEvent (id INT AUTO_INCREMENT NOT NULL, booking_id INT DEFAULT NULL, description LONGTEXT CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, unitPrice NUMERIC(7, 2) NOT NULL, INDEX IDX_7FF8C1D3301C60 (booking_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE BillableEvent ADD CONSTRAINT `FK_7FF8C1D3301C60` FOREIGN KEY (booking_id) REFERENCES Booking (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE Booking (id INT AUTO_INCREMENT NOT NULL, renter_id INT DEFAULT NULL, creator_id INT DEFAULT NULL, modifier_id INT DEFAULT NULL, name VARCHAR(190) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, referenceNumber VARCHAR(190) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, paid TINYINT(1) NOT NULL, itemsReturned TINYINT(1) NOT NULL, retrieval DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', return_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', paid_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', actualPrice NUMERIC(7, 2) DEFAULT NULL, numberOfRentDays INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', modified_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', booking_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', renting_privileges_id INT DEFAULT NULL, given_away_by_id INT DEFAULT NULL, received_by_id INT DEFAULT NULL, invoiceSent TINYINT(1) NOT NULL, renterHash VARCHAR(199) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, renterConsent TINYINT(1) NOT NULL, cancelled TINYINT(1) NOT NULL, reason_for_discount VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, renter_signature LONGTEXT CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, accessory_price NUMERIC(10, 2) DEFAULT NULL, version INT DEFAULT 1 NOT NULL, INDEX IDX_2FB1D4426950F2D5 (given_away_by_id), INDEX IDX_2FB1D44261220EA6 (creator_id), INDEX IDX_2FB1D4426F8DDD17 (received_by_id), INDEX IDX_2FB1D442D079F553 (modifier_id), INDEX IDX_2FB1D442BF6AC7F3 (renting_privileges_id), INDEX IDX_2FB1D442E289A545 (renter_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE Booking ADD CONSTRAINT `FK_2FB1D44261220EA6` FOREIGN KEY (creator_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE Booking ADD CONSTRAINT `FK_2FB1D4426950F2D5` FOREIGN KEY (given_away_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE Booking ADD CONSTRAINT `FK_2FB1D4426F8DDD17` FOREIGN KEY (received_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE Booking ADD CONSTRAINT `FK_2FB1D442BF6AC7F3` FOREIGN KEY (renting_privileges_id) REFERENCES WhoCanRentChoice (id)');
        $this->addSql('ALTER TABLE Booking ADD CONSTRAINT `FK_2FB1D442D079F553` FOREIGN KEY (modifier_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE Booking ADD CONSTRAINT `FK_2FB1D442E289A545` FOREIGN KEY (renter_id) REFERENCES Renter (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE booking_accessory (booking_id INT NOT NULL, accessory_id INT NOT NULL, INDEX IDX_3283EE1F3301C60 (booking_id), INDEX IDX_3283EE1F27E8CC78 (accessory_id), PRIMARY KEY (booking_id, accessory_id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE booking_accessory ADD CONSTRAINT `FK_3283EE1F27E8CC78` FOREIGN KEY (accessory_id) REFERENCES Accessory (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE booking_accessory ADD CONSTRAINT `FK_3283EE1F3301C60` FOREIGN KEY (booking_id) REFERENCES Booking (id) ON DELETE CASCADE');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE booking_accessory_audit (booking_id INT NOT NULL, accessory_id INT NOT NULL, rev INT NOT NULL, revtype VARCHAR(4) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, INDEX rev_c69824926ddea4c5d0eba82a5c805a8e_idx (rev), PRIMARY KEY (booking_id, accessory_id, rev)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE Booking_audit (id INT NOT NULL, rev INT NOT NULL, renter_id INT DEFAULT NULL, creator_id INT DEFAULT NULL, modifier_id INT DEFAULT NULL, name VARCHAR(190) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, referenceNumber VARCHAR(190) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, paid TINYINT(1) DEFAULT NULL, itemsReturned TINYINT(1) DEFAULT NULL, retrieval DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', return_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', paid_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', actualPrice NUMERIC(7, 2) DEFAULT NULL, numberOfRentDays INT DEFAULT NULL, created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', modified_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', booking_date DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', revtype VARCHAR(4) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, invoiceSent TINYINT(1) DEFAULT NULL, renterHash VARCHAR(199) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, renterConsent TINYINT(1) DEFAULT NULL, cancelled TINYINT(1) DEFAULT NULL, renting_privileges_id INT DEFAULT NULL, given_away_by_id INT DEFAULT NULL, received_by_id INT DEFAULT NULL, reason_for_discount VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, renter_signature LONGTEXT CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, accessory_price NUMERIC(10, 2) DEFAULT NULL, version INT DEFAULT 1, INDEX rev_b0495a931e8f8e31a90ec3920ac00d97_idx (rev), PRIMARY KEY (id, rev)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE Booking_audit ADD CONSTRAINT `rev_b0495a931e8f8e31a90ec3920ac00d97_fk` FOREIGN KEY (rev) REFERENCES revisions (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE booking_item (booking_id INT NOT NULL, item_id INT NOT NULL, INDEX IDX_78A0750126F525E (item_id), INDEX IDX_78A07503301C60 (booking_id), PRIMARY KEY (booking_id, item_id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE booking_item ADD CONSTRAINT `FK_78A0750126F525E` FOREIGN KEY (item_id) REFERENCES Item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE booking_item ADD CONSTRAINT `FK_78A07503301C60` FOREIGN KEY (booking_id) REFERENCES Booking (id) ON DELETE CASCADE');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE booking_item_audit (booking_id INT NOT NULL, item_id INT NOT NULL, rev INT NOT NULL, revtype VARCHAR(4) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, INDEX rev_6dad32ab0ec1d63cd52478ceab886472_idx (rev), PRIMARY KEY (booking_id, item_id, rev)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE booking_package (booking_id INT NOT NULL, package_id INT NOT NULL, INDEX IDX_84D622B53301C60 (booking_id), INDEX IDX_84D622B5F44CABFF (package_id), PRIMARY KEY (booking_id, package_id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE booking_package ADD CONSTRAINT `FK_84D622B53301C60` FOREIGN KEY (booking_id) REFERENCES Booking (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE booking_package ADD CONSTRAINT `FK_84D622B5F44CABFF` FOREIGN KEY (package_id) REFERENCES Package (id) ON DELETE CASCADE');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE booking_package_audit (booking_id INT NOT NULL, package_id INT NOT NULL, rev INT NOT NULL, revtype VARCHAR(4) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, INDEX rev_826893407febabf0c08145ef83b9fdf0_idx (rev), PRIMARY KEY (booking_id, package_id, rev)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE cart (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE cart_item (id INT AUTO_INCREMENT NOT NULL, product_id INT DEFAULT NULL, cart_id INT DEFAULT NULL, quantity INT DEFAULT NULL, INDEX IDX_F0FE25274584665A (product_id), INDEX IDX_F0FE25271AD5CDBF (cart_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE cart_item ADD CONSTRAINT `FK_F0FE25271AD5CDBF` FOREIGN KEY (cart_id) REFERENCES cart (id)');
        $this->addSql('ALTER TABLE cart_item ADD CONSTRAINT `FK_F0FE25274584665A` FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE checkout (id INT AUTO_INCREMENT NOT NULL, cart_id INT DEFAULT NULL, stripe_session_id LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, status INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_AF382D4E1AD5CDBF (cart_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE checkout ADD CONSTRAINT `FK_AF382D4E1AD5CDBF` FOREIGN KEY (cart_id) REFERENCES cart (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE classification__category (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, context VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, name VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, enabled TINYINT(1) NOT NULL, slug VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, description VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, position INT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_43629B36727ACA70 (parent_id), INDEX IDX_43629B36E25D857E (context), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE classification__category ADD CONSTRAINT `FK_43629B36727ACA70` FOREIGN KEY (parent_id) REFERENCES classification__category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE classification__category ADD CONSTRAINT `FK_43629B36E25D857E` FOREIGN KEY (context) REFERENCES classification__context (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE classification__collection (id INT AUTO_INCREMENT NOT NULL, context VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, name VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, enabled TINYINT(1) NOT NULL, slug VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, description VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_A406B56AE25D857E (context), UNIQUE INDEX tag_collection (slug, context), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE classification__collection ADD CONSTRAINT `FK_A406B56AE25D857E` FOREIGN KEY (context) REFERENCES classification__context (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE classification__context (id VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, name VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, enabled TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE classification__tag (id INT AUTO_INCREMENT NOT NULL, context VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, name VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, enabled TINYINT(1) NOT NULL, slug VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_CA57A1C7E25D857E (context), UNIQUE INDEX tag_context (slug, context), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE classification__tag ADD CONSTRAINT `FK_CA57A1C7E25D857E` FOREIGN KEY (context) REFERENCES classification__context (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE contract (id INT AUTO_INCREMENT NOT NULL, content_fi LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', purpose VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', content_en LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, valid_from DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_E98F2859B887B3EB (purpose), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE door_log (id INT AUTO_INCREMENT NOT NULL, member_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', message VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_D5DDED277597D3FE (member_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE door_log ADD CONSTRAINT `FK_D5DDED277597D3FE` FOREIGN KEY (member_id) REFERENCES member (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE email (id INT AUTO_INCREMENT NOT NULL, subject VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, body LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, purpose VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', add_login_links_to_footer TINYINT(1) DEFAULT NULL, event_id INT DEFAULT NULL, reply_to VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', sent_by_id INT DEFAULT NULL, INDEX IDX_E7927C74A45BB98C (sent_by_id), INDEX IDX_E7927C7471F7E88B (event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE email ADD CONSTRAINT `FK_E7927C7471F7E88B` FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE email ADD CONSTRAINT `FK_E7927C74A45BB98C` FOREIGN KEY (sent_by_id) REFERENCES member (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE event (id INT AUTO_INCREMENT NOT NULL, picture_id INT DEFAULT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, nimi VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, event_date DATETIME NOT NULL, publish_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', css LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, content LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, sisallys LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, url VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, published TINYINT(1) NOT NULL, type VARCHAR(180) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, epics VARCHAR(180) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, external_url TINYINT(1) NOT NULL, until DATETIME DEFAULT NULL, sticky TINYINT(1) NOT NULL, picture_position VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, cancelled TINYINT(1) NOT NULL, attachment_id INT DEFAULT NULL, links JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', rsvp_system_enabled TINYINT(1) DEFAULT NULL, nakkikone_enabled TINYINT(1) NOT NULL, nakki_info_fi LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, nakki_info_en LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, include_safer_space_guidelines TINYINT(1) DEFAULT NULL, header_theme VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, stream_player_url VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, img_filter_color VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, img_filter_blend_mode VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, artist_sign_up_enabled TINYINT(1) DEFAULT NULL, artist_sign_up_start DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', artist_sign_up_end DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', web_meeting_url VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, show_artist_sign_up_only_for_logged_in_members TINYINT(1) DEFAULT NULL, ticket_count INT NOT NULL, tickets_enabled TINYINT(1) DEFAULT NULL, ticket_info_fi LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, ticket_price INT DEFAULT NULL, ticket_info_en LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, ticket_presale_start DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ticket_presale_end DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ticket_presale_count INT NOT NULL, show_nakkikone_link_in_event TINYINT(1) DEFAULT NULL, require_nakki_bookings_to_be_different_times TINYINT(1) DEFAULT NULL, rsvp_only_to_active_members TINYINT(1) DEFAULT NULL, nakki_required_for_ticket_reservation TINYINT(1) DEFAULT NULL, background_effect VARCHAR(30) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, background_effect_opacity INT DEFAULT NULL, background_effect_position VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, artist_sign_up_ask_set_length TINYINT(1) NOT NULL, allow_members_to_create_happenings TINYINT(1) DEFAULT NULL, location_id INT DEFAULT NULL, template VARCHAR(200) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, abstract_fi VARCHAR(200) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, abstract_en VARCHAR(200) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, artist_sign_up_info_fi LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, artist_sign_up_info_en LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, version INT NOT NULL, send_rsvp_email TINYINT(1) DEFAULT NULL, link_to_forums VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, wiki_page VARCHAR(190) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, background_effect_config LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, multiday TINYINT(1) DEFAULT 0 NOT NULL, artist_display_config JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', ticket_total_amount INT DEFAULT NULL, INDEX IDX_3BAE0AA764D218E (location_id), INDEX IDX_3BAE0AA7EE45BDBF (picture_id), INDEX IDX_3BAE0AA7464E68B (attachment_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT `FK_3BAE0AA7464E68B` FOREIGN KEY (attachment_id) REFERENCES media__media (id)');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT `FK_3BAE0AA764D218E` FOREIGN KEY (location_id) REFERENCES location (id)');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT `FK_3BAE0AA7EE45BDBF` FOREIGN KEY (picture_id) REFERENCES media__media (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE event_artist_info (id INT AUTO_INCREMENT NOT NULL, event_id INT DEFAULT NULL, artist_id INT DEFAULT NULL, artist_clone_id INT DEFAULT NULL, set_length VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, start_time DATETIME DEFAULT NULL, wish_for_play_time VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, free_word LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, stage VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, agree_on_recording TINYINT(1) DEFAULT NULL, INDEX IDX_DCBE644871F7E88B (event_id), INDEX IDX_DCBE6448B7970CF8 (artist_id), INDEX IDX_DCBE64488502DB41 (artist_clone_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE event_artist_info ADD CONSTRAINT `FK_DCBE644871F7E88B` FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE event_artist_info ADD CONSTRAINT `FK_DCBE64488502DB41` FOREIGN KEY (artist_clone_id) REFERENCES artist (id)');
        $this->addSql('ALTER TABLE event_artist_info ADD CONSTRAINT `FK_DCBE6448B7970CF8` FOREIGN KEY (artist_id) REFERENCES artist (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE event_member (event_id INT NOT NULL, member_id INT NOT NULL, INDEX IDX_427D8D2A71F7E88B (event_id), INDEX IDX_427D8D2A7597D3FE (member_id), PRIMARY KEY (event_id, member_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE event_member ADD CONSTRAINT `FK_427D8D2A71F7E88B` FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_member ADD CONSTRAINT `FK_427D8D2A7597D3FE` FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE CASCADE');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE File (id INT AUTO_INCREMENT NOT NULL, product_id INT DEFAULT NULL, file_id INT DEFAULT NULL, tiedostoinfo VARCHAR(190) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, INDEX IDX_2CAD992E4584665A (product_id), INDEX IDX_2CAD992E93CB796C (file_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE File ADD CONSTRAINT `FK_2CAD992E4584665A` FOREIGN KEY (product_id) REFERENCES Item (id)');
        $this->addSql('ALTER TABLE File ADD CONSTRAINT `FK_2CAD992E93CB796C` FOREIGN KEY (file_id) REFERENCES media__media (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE happening (id INT AUTO_INCREMENT NOT NULL, picture_id INT DEFAULT NULL, event_id INT DEFAULT NULL, name_fi VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, name_en VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, description_fi LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, description_en LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, time DATETIME NOT NULL, needs_preliminary_sign_up TINYINT(1) NOT NULL, needs_preliminary_payment TINYINT(1) NOT NULL, payment_info_fi LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, payment_info_en LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, type VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, max_sign_ups INT NOT NULL, slug_fi VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, slug_en VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, price_fi VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, price_en VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, release_this_happening_in_event TINYINT(1) NOT NULL, sign_ups_open_until DATETIME DEFAULT NULL, allow_sign_up_comments TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_1F3C70ADEE45BDBF (picture_id), INDEX IDX_1F3C70AD71F7E88B (event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE happening ADD CONSTRAINT `FK_1F3C70AD71F7E88B` FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE happening ADD CONSTRAINT `FK_1F3C70ADEE45BDBF` FOREIGN KEY (picture_id) REFERENCES media__media (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE happening_booking (id INT AUTO_INCREMENT NOT NULL, member_id INT DEFAULT NULL, happening_id INT NOT NULL, comment VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_D86CE9507597D3FE (member_id), INDEX IDX_D86CE950B7B10E6D (happening_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE happening_booking ADD CONSTRAINT `FK_D86CE9507597D3FE` FOREIGN KEY (member_id) REFERENCES member (id)');
        $this->addSql('ALTER TABLE happening_booking ADD CONSTRAINT `FK_D86CE950B7B10E6D` FOREIGN KEY (happening_id) REFERENCES happening (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE happening_member (happening_id INT NOT NULL, member_id INT NOT NULL, INDEX IDX_C822B0DAB7B10E6D (happening_id), INDEX IDX_C822B0DA7597D3FE (member_id), PRIMARY KEY (happening_id, member_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE happening_member ADD CONSTRAINT `FK_C822B0DA7597D3FE` FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE happening_member ADD CONSTRAINT `FK_C822B0DAB7B10E6D` FOREIGN KEY (happening_id) REFERENCES happening (id) ON DELETE CASCADE');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE Item (id INT AUTO_INCREMENT NOT NULL, Name VARCHAR(190) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, Manufacturer VARCHAR(190) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, Model VARCHAR(190) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, SerialNumber VARCHAR(190) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, PlaceInStorage VARCHAR(190) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, Description VARCHAR(4000) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, Rent NUMERIC(7, 2) DEFAULT NULL, RentNotice VARCHAR(5000) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, NeedsFixing TINYINT(1) NOT NULL, ForSale TINYINT(1) DEFAULT NULL, createdAt DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updatedAt DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', Commission DATETIME DEFAULT NULL, creator_id INT DEFAULT NULL, modifier_id INT DEFAULT NULL, category_id INT DEFAULT NULL, ToSpareParts TINYINT(1) NOT NULL, purchasePrice NUMERIC(7, 2) DEFAULT NULL, Url VARCHAR(500) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, CannotBeRented TINYINT(1) NOT NULL, compensationPrice NUMERIC(7, 2) DEFAULT NULL, INDEX IDX_BF298A2012469DE2 (category_id), INDEX IDX_BF298A2061220EA6 (creator_id), INDEX IDX_BF298A20D079F553 (modifier_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE Item ADD CONSTRAINT `FK_BF298A2012469DE2` FOREIGN KEY (category_id) REFERENCES classification__category (id)');
        $this->addSql('ALTER TABLE Item ADD CONSTRAINT `FK_BF298A2061220EA6` FOREIGN KEY (creator_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE Item ADD CONSTRAINT `FK_BF298A20D079F553` FOREIGN KEY (modifier_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE Item_audit (id INT NOT NULL, rev INT NOT NULL, category_id INT DEFAULT NULL, creator_id INT DEFAULT NULL, modifier_id INT DEFAULT NULL, Name VARCHAR(190) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, Manufacturer VARCHAR(190) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, Model VARCHAR(190) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, Url VARCHAR(500) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, SerialNumber VARCHAR(190) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, PlaceInStorage VARCHAR(190) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, Description VARCHAR(4000) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, Rent NUMERIC(7, 2) DEFAULT NULL, RentNotice VARCHAR(5000) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, NeedsFixing TINYINT(1) DEFAULT NULL, ToSpareParts TINYINT(1) DEFAULT NULL, CannotBeRented TINYINT(1) DEFAULT NULL, ForSale TINYINT(1) DEFAULT NULL, Commission DATETIME DEFAULT NULL, purchasePrice NUMERIC(7, 2) DEFAULT NULL, createdAt DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', updatedAt DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', revtype VARCHAR(4) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, compensationPrice NUMERIC(7, 2) DEFAULT NULL, INDEX rev_3c5f2b55d694c80478a68bde1a0e8d59_idx (rev), PRIMARY KEY (id, rev)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE Item_audit ADD CONSTRAINT `rev_3c5f2b55d694c80478a68bde1a0e8d59_fk` FOREIGN KEY (rev) REFERENCES revisions (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE item_booking (item_id INT NOT NULL, booking_id INT NOT NULL, INDEX IDX_EB51CB8A3301C60 (booking_id), INDEX IDX_EB51CB8A126F525E (item_id), PRIMARY KEY (item_id, booking_id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE item_booking ADD CONSTRAINT `FK_EB51CB8A126F525E` FOREIGN KEY (item_id) REFERENCES Item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE item_booking ADD CONSTRAINT `FK_EB51CB8A3301C60` FOREIGN KEY (booking_id) REFERENCES Booking (id) ON DELETE CASCADE');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE item_booking_audit (item_id INT NOT NULL, booking_id INT NOT NULL, rev INT NOT NULL, revtype VARCHAR(4) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, INDEX rev_a661a911851649749a3f8933c8374d44_idx (rev), PRIMARY KEY (item_id, booking_id, rev)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE item_package (item_id INT NOT NULL, package_id INT NOT NULL, INDEX IDX_D53541C1F44CABFF (package_id), INDEX IDX_D53541C1126F525E (item_id), PRIMARY KEY (item_id, package_id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE item_package ADD CONSTRAINT `FK_D53541C1126F525E` FOREIGN KEY (item_id) REFERENCES Item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE item_package ADD CONSTRAINT `FK_D53541C1F44CABFF` FOREIGN KEY (package_id) REFERENCES Package (id) ON DELETE CASCADE');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE item_package_audit (item_id INT NOT NULL, package_id INT NOT NULL, rev INT NOT NULL, revtype VARCHAR(4) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, INDEX rev_00218f2eb7af6a761489ed026afab81c_idx (rev), PRIMARY KEY (item_id, package_id, rev)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE Item_tags (item_id INT NOT NULL, tag_id INT NOT NULL, INDEX IDX_257D527E126F525E (item_id), INDEX IDX_257D527EBAD26311 (tag_id), PRIMARY KEY (item_id, tag_id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE Item_tags ADD CONSTRAINT `FK_257D527E126F525E` FOREIGN KEY (item_id) REFERENCES Item (id)');
        $this->addSql('ALTER TABLE Item_tags ADD CONSTRAINT `FK_257D527EBAD26311` FOREIGN KEY (tag_id) REFERENCES classification__tag (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE Item_tags_audit (item_id INT NOT NULL, tag_id INT NOT NULL, rev INT NOT NULL, revtype VARCHAR(4) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, INDEX rev_3f42baaf4b9c0a3f950bc850464bf19d_idx (rev), PRIMARY KEY (item_id, tag_id, rev)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE item_who_can_rent_choice (item_id INT NOT NULL, who_can_rent_choice_id INT NOT NULL, INDEX IDX_4F9286AB44A2EEE (who_can_rent_choice_id), INDEX IDX_4F9286A126F525E (item_id), PRIMARY KEY (item_id, who_can_rent_choice_id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE item_who_can_rent_choice ADD CONSTRAINT `FK_4F9286A126F525E` FOREIGN KEY (item_id) REFERENCES Item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE item_who_can_rent_choice ADD CONSTRAINT `FK_4F9286AB44A2EEE` FOREIGN KEY (who_can_rent_choice_id) REFERENCES WhoCanRentChoice (id) ON DELETE CASCADE');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE item_who_can_rent_choice_audit (item_id INT NOT NULL, who_can_rent_choice_id INT NOT NULL, rev INT NOT NULL, revtype VARCHAR(4) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, INDEX rev_8cf66f943eb8b37edcdc173cdd415544_idx (rev), PRIMARY KEY (item_id, who_can_rent_choice_id, rev)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE location (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, longitude VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, latitude VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, street_address VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, name_en VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE media__gallery (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, context VARCHAR(64) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, default_format VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, enabled TINYINT(1) NOT NULL, updated_at DATETIME NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE media__gallery_item (id INT AUTO_INCREMENT NOT NULL, gallery_id INT DEFAULT NULL, media_id INT DEFAULT NULL, position INT NOT NULL, enabled TINYINT(1) NOT NULL, updated_at DATETIME NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_3238519A4E7AF8F (gallery_id), INDEX IDX_3238519AEA9FDD75 (media_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE media__gallery_item ADD CONSTRAINT `FK_3238519A4E7AF8F` FOREIGN KEY (gallery_id) REFERENCES media__gallery (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE media__gallery_item ADD CONSTRAINT `FK_3238519AEA9FDD75` FOREIGN KEY (media_id) REFERENCES media__media (id) ON DELETE CASCADE');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE media__media (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, description TEXT CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, enabled TINYINT(1) NOT NULL, provider_name VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, provider_status INT NOT NULL, provider_reference VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, provider_metadata JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', width INT DEFAULT NULL, height INT DEFAULT NULL, length NUMERIC(10, 0) DEFAULT NULL, content_type VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, content_size BIGINT DEFAULT NULL, copyright VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, author_name VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, context VARCHAR(64) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, cdn_is_flushable TINYINT(1) NOT NULL, cdn_flush_identifier VARCHAR(64) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, cdn_flush_at DATETIME DEFAULT NULL, cdn_status INT DEFAULT NULL, updated_at DATETIME NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE member (id INT AUTO_INCREMENT NOT NULL, firstname VARCHAR(190) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, lastname VARCHAR(190) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, email VARCHAR(190) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, phone VARCHAR(190) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, CityOfResidence VARCHAR(190) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, createdAt DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', updatedAt DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', isActiveMember TINYINT(1) NOT NULL, rejectReasonSent TINYINT(1) NOT NULL, StudentUnionMember TINYINT(1) NOT NULL, Application LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, reject_reason LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, ApplicationDate DATETIME DEFAULT NULL, ApplicationHandledDate DATETIME DEFAULT NULL, username VARCHAR(190) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, accepted_as_honorary_member DATETIME DEFAULT NULL, is_full_member TINYINT(1) NOT NULL, locale VARCHAR(8) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, theme VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, deny_kerde_access TINYINT(1) DEFAULT NULL, code VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, email_verified TINYINT(1) NOT NULL, allow_info_mails TINYINT(1) NOT NULL, allow_active_member_mails TINYINT(1) NOT NULL, epics_username VARCHAR(190) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, UNIQUE INDEX UNIQ_70E4FA78E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE menu (id INT AUTO_INCREMENT NOT NULL, page_fi_id INT DEFAULT NULL, page_en_id INT DEFAULT NULL, root_id INT DEFAULT NULL, parent_id INT DEFAULT NULL, label VARCHAR(180) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, nimi VARCHAR(180) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, url VARCHAR(180) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, enabled TINYINT(1) NOT NULL, lft INT NOT NULL, lvl INT NOT NULL, rgt INT NOT NULL, position INT NOT NULL, INDEX IDX_7D053A93B3886CE4 (page_en_id), INDEX IDX_7D053A9379066886 (root_id), INDEX IDX_7D053A93727ACA70 (parent_id), INDEX IDX_7D053A9369FF2E8D (page_fi_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE menu ADD CONSTRAINT `FK_7D053A9369FF2E8D` FOREIGN KEY (page_fi_id) REFERENCES page__page (id)');
        $this->addSql('ALTER TABLE menu ADD CONSTRAINT `FK_7D053A93727ACA70` FOREIGN KEY (parent_id) REFERENCES menu (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu ADD CONSTRAINT `FK_7D053A9379066886` FOREIGN KEY (root_id) REFERENCES menu (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu ADD CONSTRAINT `FK_7D053A93B3886CE4` FOREIGN KEY (page_en_id) REFERENCES page__page (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE nakki (id INT AUTO_INCREMENT NOT NULL, definition_id INT NOT NULL, start_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', end_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', event_id INT NOT NULL, nakki_interval VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'(DC2Type:dateinterval)\', responsible_id INT DEFAULT NULL, mattermost_channel VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, disable_bookings TINYINT(1) DEFAULT NULL, INDEX IDX_955FE106D11EA911 (definition_id), INDEX IDX_955FE10671F7E88B (event_id), INDEX IDX_955FE106602AD315 (responsible_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE nakki ADD CONSTRAINT `FK_955FE106602AD315` FOREIGN KEY (responsible_id) REFERENCES member (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE nakki ADD CONSTRAINT `FK_955FE10671F7E88B` FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE nakki ADD CONSTRAINT `FK_955FE106D11EA911` FOREIGN KEY (definition_id) REFERENCES nakki_definition (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE nakki_booking (id INT AUTO_INCREMENT NOT NULL, nakki_id INT NOT NULL, member_id INT DEFAULT NULL, event_id INT NOT NULL, start_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', end_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_13C2BAC5DE7D37DF (nakki_id), INDEX IDX_13C2BAC57597D3FE (member_id), INDEX IDX_13C2BAC571F7E88B (event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE nakki_booking ADD CONSTRAINT `FK_13C2BAC571F7E88B` FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE nakki_booking ADD CONSTRAINT `FK_13C2BAC57597D3FE` FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE nakki_booking ADD CONSTRAINT `FK_13C2BAC5DE7D37DF` FOREIGN KEY (nakki_id) REFERENCES nakki (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE nakki_definition (id INT AUTO_INCREMENT NOT NULL, name_fi VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, description_fi LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, name_en VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, description_en LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, only_for_active_members TINYINT(1) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, event_id INT DEFAULT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', message_id INT DEFAULT NULL, message LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, locale VARCHAR(8) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, options JSON NOT NULL COMMENT \'(DC2Type:json)\', INDEX IDX_BF5476CA71F7E88B (event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT `FK_BF5476CA71F7E88B` FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE oauth2_access_token (identifier CHAR(80) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, client VARCHAR(32) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, expiry DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', user_identifier VARCHAR(128) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, scopes TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'(DC2Type:oauth2_scope)\', revoked TINYINT(1) NOT NULL, INDEX IDX_454D9673C7440455 (client), PRIMARY KEY (identifier)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE oauth2_access_token ADD CONSTRAINT `FK_454D9673C7440455` FOREIGN KEY (client) REFERENCES oauth2_client (identifier) ON DELETE CASCADE');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE oauth2_authorization_code (identifier CHAR(80) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, client VARCHAR(32) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, expiry DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', user_identifier VARCHAR(128) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, scopes TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'(DC2Type:oauth2_scope)\', revoked TINYINT(1) NOT NULL, INDEX IDX_509FEF5FC7440455 (client), PRIMARY KEY (identifier)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE oauth2_authorization_code ADD CONSTRAINT `FK_509FEF5FC7440455` FOREIGN KEY (client) REFERENCES oauth2_client (identifier) ON DELETE CASCADE');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE oauth2_client (identifier VARCHAR(32) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, secret VARCHAR(128) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, redirect_uris TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'(DC2Type:oauth2_redirect_uri)\', grants TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'(DC2Type:oauth2_grant)\', scopes TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'(DC2Type:oauth2_scope)\', active TINYINT(1) NOT NULL, allow_plain_text_pkce TINYINT(1) DEFAULT 0 NOT NULL, name VARCHAR(128) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, PRIMARY KEY (identifier)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE oauth2_refresh_token (identifier CHAR(80) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, access_token CHAR(80) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, expiry DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', revoked TINYINT(1) NOT NULL, INDEX IDX_4DD90732B6A2DD68 (access_token), PRIMARY KEY (identifier)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE oauth2_refresh_token ADD CONSTRAINT `FK_4DD90732B6A2DD68` FOREIGN KEY (access_token) REFERENCES oauth2_access_token (identifier) ON DELETE SET NULL');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE Package (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(190) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, rent VARCHAR(190) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, needs_fixing TINYINT(1) NOT NULL, notes LONGTEXT CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, compensation_price NUMERIC(10, 2) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE package_who_can_rent_choice (package_id INT NOT NULL, who_can_rent_choice_id INT NOT NULL, INDEX IDX_27443592B44A2EEE (who_can_rent_choice_id), INDEX IDX_27443592F44CABFF (package_id), PRIMARY KEY (package_id, who_can_rent_choice_id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE package_who_can_rent_choice ADD CONSTRAINT `FK_27443592B44A2EEE` FOREIGN KEY (who_can_rent_choice_id) REFERENCES WhoCanRentChoice (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE package_who_can_rent_choice ADD CONSTRAINT `FK_27443592F44CABFF` FOREIGN KEY (package_id) REFERENCES Package (id) ON DELETE CASCADE');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE page__block (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, page_id INT DEFAULT NULL, name VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, type VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, settings JSON NOT NULL COMMENT \'(DC2Type:json)\', enabled TINYINT(1) DEFAULT NULL, position INT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_66F58DA0727ACA70 (parent_id), INDEX IDX_66F58DA0C4663E4 (page_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE page__block ADD CONSTRAINT `FK_66F58DA0727ACA70` FOREIGN KEY (parent_id) REFERENCES page__block (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE page__block ADD CONSTRAINT `FK_66F58DA0C4663E4` FOREIGN KEY (page_id) REFERENCES page__page (id) ON DELETE CASCADE');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE page__page (id INT AUTO_INCREMENT NOT NULL, site_id INT DEFAULT NULL, parent_id INT DEFAULT NULL, route_name VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, page_alias VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, type VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, position INT NOT NULL, enabled TINYINT(1) NOT NULL, decorate TINYINT(1) NOT NULL, edited TINYINT(1) NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, slug LONGTEXT CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, url LONGTEXT CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, custom_url LONGTEXT CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, request_method VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, title VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, meta_keyword VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, meta_description VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, javascript LONGTEXT CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, stylesheet LONGTEXT CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, raw_headers LONGTEXT CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, template VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_2FAE39ED727ACA70 (parent_id), INDEX IDX_2FAE39EDF6BD1646 (site_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE page__page ADD CONSTRAINT `FK_2FAE39ED727ACA70` FOREIGN KEY (parent_id) REFERENCES page__page (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE page__page ADD CONSTRAINT `FK_2FAE39EDF6BD1646` FOREIGN KEY (site_id) REFERENCES page__site (id) ON DELETE CASCADE');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE page__site (id INT AUTO_INCREMENT NOT NULL, enabled TINYINT(1) NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, relative_path VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, host VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, enabled_from DATETIME DEFAULT NULL, enabled_to DATETIME DEFAULT NULL, is_default TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, locale VARCHAR(7) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, title VARCHAR(64) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, meta_keywords VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, meta_description VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE page__snapshot (id INT AUTO_INCREMENT NOT NULL, site_id INT DEFAULT NULL, page_id INT DEFAULT NULL, route_name VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, page_alias VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, type VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, position INT NOT NULL, enabled TINYINT(1) NOT NULL, decorate TINYINT(1) NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, url LONGTEXT CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, parent_id INT DEFAULT NULL, content JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', publication_date_start DATETIME DEFAULT NULL, publication_date_end DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_3963EF9AC4663E4 (page_id), INDEX idx_snapshot_dates_enabled (publication_date_start, publication_date_end, enabled), INDEX IDX_3963EF9AF6BD1646 (site_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE page__snapshot ADD CONSTRAINT `FK_3963EF9AC4663E4` FOREIGN KEY (page_id) REFERENCES page__page (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE page__snapshot ADD CONSTRAINT `FK_3963EF9AF6BD1646` FOREIGN KEY (site_id) REFERENCES page__site (id) ON DELETE CASCADE');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, event_id INT DEFAULT NULL, picture_id INT DEFAULT NULL, stripe_id VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, name_en VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, stripe_price_id VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', quantity INT NOT NULL, active TINYINT(1) NOT NULL, amount INT NOT NULL, ticket TINYINT(1) NOT NULL, service_fee TINYINT(1) NOT NULL, description_fi LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, description_en LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, name_fi VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, how_many_one_can_buy_at_one_time INT NOT NULL, INDEX IDX_D34A04AD71F7E88B (event_id), INDEX IDX_D34A04ADEE45BDBF (picture_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT `FK_D34A04AD71F7E88B` FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT `FK_D34A04ADEE45BDBF` FOREIGN KEY (picture_id) REFERENCES media__media (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE Renter (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(190) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, streetadress VARCHAR(190) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, organization VARCHAR(190) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, zipcode VARCHAR(190) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, city VARCHAR(190) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, phone VARCHAR(190) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, email VARCHAR(190) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE reset_password_request (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, selector VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, hashed_token VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, requested_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_7CE748AA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT `FK_7CE748AA76ED395` FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE revisions (id INT AUTO_INCREMENT NOT NULL, timestamp DATETIME NOT NULL, username VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE reward (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, payment_handled_by_id INT DEFAULT NULL, reward NUMERIC(10, 2) DEFAULT NULL, paid TINYINT(1) NOT NULL, paid_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', weight INT NOT NULL, evenout NUMERIC(10, 2) DEFAULT NULL, INDEX IDX_4ED17253A76ED395 (user_id), INDEX IDX_4ED17253361A68D (payment_handled_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE reward ADD CONSTRAINT `FK_4ED17253361A68D` FOREIGN KEY (payment_handled_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE reward ADD CONSTRAINT `FK_4ED17253A76ED395` FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE reward_booking (reward_id INT NOT NULL, booking_id INT NOT NULL, INDEX IDX_ABB1026E466ACA1 (reward_id), INDEX IDX_ABB10263301C60 (booking_id), PRIMARY KEY (reward_id, booking_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE reward_booking ADD CONSTRAINT `FK_ABB10263301C60` FOREIGN KEY (booking_id) REFERENCES Booking (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reward_booking ADD CONSTRAINT `FK_ABB1026E466ACA1` FOREIGN KEY (reward_id) REFERENCES reward (id) ON DELETE CASCADE');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE rsvp (id INT AUTO_INCREMENT NOT NULL, event_id INT NOT NULL, member_id INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', first_name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, email VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, last_name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_9FA5CE4E7597D3FE (member_id), UNIQUE INDEX UNIQ_9FA5CE4EE7927C74 (email), INDEX IDX_9FA5CE4E71F7E88B (event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE rsvp ADD CONSTRAINT `FK_9FA5CE4E71F7E88B` FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE rsvp ADD CONSTRAINT `FK_9FA5CE4E7597D3FE` FOREIGN KEY (member_id) REFERENCES member (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE StatusEvent (id INT AUTO_INCREMENT NOT NULL, item_id INT DEFAULT NULL, Description VARCHAR(5000) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, CreatedAt DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UpdatedAt DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', creator_id INT DEFAULT NULL, modifier_id INT DEFAULT NULL, booking_id INT DEFAULT NULL, INDEX IDX_19D077FB61220EA6 (creator_id), INDEX IDX_19D077FBD079F553 (modifier_id), INDEX IDX_19D077FB126F525E (item_id), INDEX IDX_19D077FB3301C60 (booking_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE StatusEvent ADD CONSTRAINT `FK_19D077FB126F525E` FOREIGN KEY (item_id) REFERENCES Item (id)');
        $this->addSql('ALTER TABLE StatusEvent ADD CONSTRAINT `FK_19D077FB3301C60` FOREIGN KEY (booking_id) REFERENCES Booking (id)');
        $this->addSql('ALTER TABLE StatusEvent ADD CONSTRAINT `FK_19D077FB61220EA6` FOREIGN KEY (creator_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE StatusEvent ADD CONSTRAINT `FK_19D077FBD079F553` FOREIGN KEY (modifier_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE stream (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', listeners INT NOT NULL, online TINYINT(1) NOT NULL, filename VARCHAR(190) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE stream_artist (id INT AUTO_INCREMENT NOT NULL, artist_id INT NOT NULL, stream_id INT NOT NULL, started_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', stopped_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_DB7646B2B7970CF8 (artist_id), INDEX IDX_DB7646B2D0ED463E (stream_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE stream_artist ADD CONSTRAINT `FK_DB7646B2B7970CF8` FOREIGN KEY (artist_id) REFERENCES artist (id)');
        $this->addSql('ALTER TABLE stream_artist ADD CONSTRAINT `FK_DB7646B2D0ED463E` FOREIGN KEY (stream_id) REFERENCES stream (id)');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE ticket (id INT AUTO_INCREMENT NOT NULL, event_id INT NOT NULL, owner_id INT DEFAULT NULL, price INT NOT NULL, reference_number INT DEFAULT NULL, status VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', given TINYINT(1) DEFAULT NULL, email VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, stripe_product_id VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_97A0ADA371F7E88B (event_id), INDEX IDX_97A0ADA37E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT `FK_97A0ADA371F7E88B` FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT `FK_97A0ADA37E3C61F9` FOREIGN KEY (owner_id) REFERENCES member (id) ON DELETE SET NULL');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, password VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, last_login DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', roles JSON NOT NULL COMMENT \'(DC2Type:json)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', member_id INT NOT NULL, mattermost_id VARCHAR(255) CHARACTER SET utf8mb3 DEFAULT NULL COLLATE `utf8mb3_unicode_ci`, auth_id VARCHAR(255) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, UNIQUE INDEX UNIQ_8D93D6497597D3FE (member_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT `FK_8D93D6497597D3FE` FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE CASCADE');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('CREATE TABLE WhoCanRentChoice (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(190) CHARACTER SET utf8mb3 NOT NULL COLLATE `utf8mb3_unicode_ci`, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb3 COLLATE `utf8mb3_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `Accessory`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `AccessoryChoice`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `access_groups`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `access_groups_user`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `artist`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `BillableEvent`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `Booking`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `booking_accessory`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `booking_accessory_audit`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `Booking_audit`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `booking_item`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `booking_item_audit`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `booking_package`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `booking_package_audit`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `cart`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `cart_item`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `checkout`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `classification__category`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `classification__collection`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `classification__context`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `classification__tag`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `contract`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `door_log`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `email`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `event`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `event_artist_info`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `event_member`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `File`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `happening`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `happening_booking`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `happening_member`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `Item`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `Item_audit`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `item_booking`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `item_booking_audit`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `item_package`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `item_package_audit`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `Item_tags`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `Item_tags_audit`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `item_who_can_rent_choice`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `item_who_can_rent_choice_audit`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `location`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `media__gallery`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `media__gallery_item`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `media__media`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `member`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `menu`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `nakki`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `nakki_booking`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `nakki_definition`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `notification`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `oauth2_access_token`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `oauth2_authorization_code`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `oauth2_client`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `oauth2_refresh_token`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `Package`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `package_who_can_rent_choice`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `page__block`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `page__page`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `page__site`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `page__snapshot`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `product`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `Renter`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `reset_password_request`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `revisions`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `reward`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `reward_booking`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `rsvp`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `StatusEvent`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `stream`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `stream_artist`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `ticket`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `user`');
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MariaDB110700Platform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MariaDB110700Platform'."
        );

        $this->addSql('DROP TABLE `WhoCanRentChoice`');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
