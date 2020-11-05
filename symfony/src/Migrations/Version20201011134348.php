<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201011134348 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE fos_user_user_group DROP FOREIGN KEY FK_B3C77447FE54D947');
        $this->addSql('ALTER TABLE Booking DROP FOREIGN KEY FK_2FB1D4425783CABD');
        $this->addSql('ALTER TABLE Booking DROP FOREIGN KEY FK_2FB1D44261220EA6');
        $this->addSql('ALTER TABLE Booking DROP FOREIGN KEY FK_2FB1D442D079F553');
        $this->addSql('ALTER TABLE Booking DROP FOREIGN KEY FK_2FB1D442E6C5D2A3');
        $this->addSql('ALTER TABLE Item DROP FOREIGN KEY FK_BF298A2061220EA6');
        $this->addSql('ALTER TABLE Item DROP FOREIGN KEY FK_BF298A20D079F553');
        $this->addSql('ALTER TABLE StatusEvent DROP FOREIGN KEY FK_FA6F25A361220EA6');
        $this->addSql('ALTER TABLE StatusEvent DROP FOREIGN KEY FK_FA6F25A3D079F553');
        $this->addSql('ALTER TABLE fos_user_user_group DROP FOREIGN KEY FK_B3C77447A76ED395');
        $this->addSql('ALTER TABLE reward DROP FOREIGN KEY FK_4ED17253361A68D');
        $this->addSql('ALTER TABLE reward DROP FOREIGN KEY FK_4ED17253A76ED395');
        $this->addSql('CREATE TABLE reset_password_request (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, selector VARCHAR(20) NOT NULL, hashed_token VARCHAR(100) NOT NULL, requested_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_7CE748AA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('DROP TABLE fos_user_group');
        $this->addSql('DROP TABLE fos_user_user');
        $this->addSql('DROP TABLE fos_user_user_group');
        $this->addSql('ALTER TABLE Booking DROP FOREIGN KEY FK_2FB1D44261220EA6');
        $this->addSql('ALTER TABLE Booking DROP FOREIGN KEY FK_2FB1D442D079F553');
        $this->addSql('ALTER TABLE Booking ADD CONSTRAINT FK_2FB1D4426950F2D5 FOREIGN KEY (given_away_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE Booking ADD CONSTRAINT FK_2FB1D4426F8DDD17 FOREIGN KEY (received_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE Booking ADD CONSTRAINT FK_2FB1D44261220EA6 FOREIGN KEY (creator_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE Booking ADD CONSTRAINT FK_2FB1D442D079F553 FOREIGN KEY (modifier_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE reward DROP FOREIGN KEY FK_4ED17253361A68D');
        $this->addSql('ALTER TABLE reward DROP FOREIGN KEY FK_4ED17253A76ED395');
        $this->addSql('ALTER TABLE reward ADD CONSTRAINT FK_4ED17253361A68D FOREIGN KEY (payment_handled_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE reward ADD CONSTRAINT FK_4ED17253A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE Item DROP FOREIGN KEY FK_BF298A2061220EA6');
        $this->addSql('ALTER TABLE Item DROP FOREIGN KEY FK_BF298A20D079F553');
        $this->addSql('ALTER TABLE Item ADD CONSTRAINT FK_BF298A2061220EA6 FOREIGN KEY (creator_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE Item ADD CONSTRAINT FK_BF298A20D079F553 FOREIGN KEY (modifier_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE StatusEvent ADD CONSTRAINT FK_19D077FB61220EA6 FOREIGN KEY (creator_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE StatusEvent ADD CONSTRAINT FK_19D077FBD079F553 FOREIGN KEY (modifier_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE fos_user_group (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(180) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, roles LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci` COMMENT \'(DC2Type:array)\', UNIQUE INDEX UNIQ_583D1F3E5E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE fos_user_user (id INT AUTO_INCREMENT NOT NULL, member_id INT DEFAULT NULL, username VARCHAR(180) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, username_canonical VARCHAR(180) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, email VARCHAR(180) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, email_canonical VARCHAR(180) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, enabled TINYINT(1) NOT NULL, salt VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, password VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, last_login DATETIME DEFAULT NULL, confirmation_token VARCHAR(180) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, password_requested_at DATETIME DEFAULT NULL, roles LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci` COMMENT \'(DC2Type:array)\', created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, date_of_birth DATETIME DEFAULT NULL, firstname VARCHAR(64) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, lastname VARCHAR(64) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, website VARCHAR(64) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, biography VARCHAR(1000) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, gender VARCHAR(1) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, locale VARCHAR(8) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, timezone VARCHAR(64) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, phone VARCHAR(64) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, facebook_uid VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, facebook_name VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, facebook_data LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci` COMMENT \'(DC2Type:json)\', twitter_uid VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, twitter_name VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, twitter_data LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci` COMMENT \'(DC2Type:json)\', gplus_uid VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, gplus_name VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, gplus_data LONGTEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci` COMMENT \'(DC2Type:json)\', token VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, two_step_code VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, UNIQUE INDEX UNIQ_C560D761A0D96FBF (email_canonical), UNIQUE INDEX UNIQ_C560D761C05FB297 (confirmation_token), UNIQUE INDEX UNIQ_C560D76192FC23A8 (username_canonical), UNIQUE INDEX UNIQ_C560D7617597D3FE (member_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE fos_user_user_group (user_id INT NOT NULL, group_id INT NOT NULL, INDEX IDX_B3C77447A76ED395 (user_id), INDEX IDX_B3C77447FE54D947 (group_id), PRIMARY KEY(user_id, group_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE fos_user_user ADD CONSTRAINT FK_C560D7617597D3FE FOREIGN KEY (member_id) REFERENCES member (id)');
        $this->addSql('ALTER TABLE fos_user_user_group ADD CONSTRAINT FK_B3C77447A76ED395 FOREIGN KEY (user_id) REFERENCES fos_user_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE fos_user_user_group ADD CONSTRAINT FK_B3C77447FE54D947 FOREIGN KEY (group_id) REFERENCES fos_user_group (id) ON DELETE CASCADE');
        $this->addSql('DROP TABLE reset_password_request');
        $this->addSql('ALTER TABLE Booking DROP FOREIGN KEY FK_2FB1D4426950F2D5');
        $this->addSql('ALTER TABLE Booking DROP FOREIGN KEY FK_2FB1D4426F8DDD17');
        $this->addSql('ALTER TABLE Booking DROP FOREIGN KEY FK_2FB1D44261220EA6');
        $this->addSql('ALTER TABLE Booking DROP FOREIGN KEY FK_2FB1D442D079F553');
        $this->addSql('ALTER TABLE Booking ADD CONSTRAINT FK_2FB1D4425783CABD FOREIGN KEY (given_away_by_id) REFERENCES fos_user_user (id)');
        $this->addSql('ALTER TABLE Booking ADD CONSTRAINT FK_2FB1D442E6C5D2A3 FOREIGN KEY (received_by_id) REFERENCES fos_user_user (id)');
        $this->addSql('ALTER TABLE Booking ADD CONSTRAINT FK_2FB1D44261220EA6 FOREIGN KEY (creator_id) REFERENCES fos_user_user (id)');
        $this->addSql('ALTER TABLE Booking ADD CONSTRAINT FK_2FB1D442D079F553 FOREIGN KEY (modifier_id) REFERENCES fos_user_user (id)');
        $this->addSql('ALTER TABLE Item DROP FOREIGN KEY FK_BF298A2061220EA6');
        $this->addSql('ALTER TABLE Item DROP FOREIGN KEY FK_BF298A20D079F553');
        $this->addSql('ALTER TABLE Item ADD CONSTRAINT FK_BF298A2061220EA6 FOREIGN KEY (creator_id) REFERENCES fos_user_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE Item ADD CONSTRAINT FK_BF298A20D079F553 FOREIGN KEY (modifier_id) REFERENCES fos_user_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE StatusEvent DROP FOREIGN KEY FK_19D077FB61220EA6');
        $this->addSql('ALTER TABLE StatusEvent DROP FOREIGN KEY FK_19D077FBD079F553');
        $this->addSql('ALTER TABLE StatusEvent ADD CONSTRAINT FK_FA6F25A361220EA6 FOREIGN KEY (creator_id) REFERENCES fos_user_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE StatusEvent ADD CONSTRAINT FK_FA6F25A3D079F553 FOREIGN KEY (modifier_id) REFERENCES fos_user_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE reward DROP FOREIGN KEY FK_4ED17253A76ED395');
        $this->addSql('ALTER TABLE reward DROP FOREIGN KEY FK_4ED17253361A68D');
        $this->addSql('ALTER TABLE reward ADD CONSTRAINT FK_4ED17253A76ED395 FOREIGN KEY (user_id) REFERENCES fos_user_user (id)');
        $this->addSql('ALTER TABLE reward ADD CONSTRAINT FK_4ED17253361A68D FOREIGN KEY (payment_handled_by_id) REFERENCES fos_user_user (id)');
    }
}
