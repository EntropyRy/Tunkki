<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251127121825 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Align database column types with immutable datetime usage introduced by Doctrine DBAL 4 upgrade.
        $this->addSql('ALTER TABLE access_groups CHANGE roles roles JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE artist CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE links links JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE Booking CHANGE retrieval retrieval DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE return_date return_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE paid_date paid_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE modified_at modified_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE booking_date booking_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\'');
        $this->addSql('ALTER TABLE Booking_audit CHANGE retrieval retrieval DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE return_date return_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE paid_date paid_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE modified_at modified_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE booking_date booking_date DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\'');
        $this->addSql('ALTER TABLE checkout CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE contract CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE valid_from valid_from DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE door_log CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE email CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE sent_at sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE event CHANGE publish_date publish_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE links links JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE artist_sign_up_end artist_sign_up_end DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE artist_sign_up_start artist_sign_up_start DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE ticket_presale_start ticket_presale_start DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE ticket_presale_end ticket_presale_end DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE artist_display_config artist_display_config JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE happening_booking CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE Item CHANGE createdAt createdAt DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updatedAt updatedAt DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE Item_audit CHANGE createdAt createdAt DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updatedAt updatedAt DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE media__media CHANGE provider_metadata provider_metadata JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE member CHANGE createdAt createdAt DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updatedAt updatedAt DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE nakki CHANGE start_at start_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE end_at end_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE nakki_interval nakki_interval VARCHAR(255) NOT NULL COMMENT \'(DC2Type:dateinterval)\'');
        $this->addSql('ALTER TABLE nakki_booking CHANGE start_at start_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE end_at end_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE notification CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE sent_at sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE options options JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE oauth2_access_token CHANGE expiry expiry DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE scopes scopes TEXT DEFAULT NULL COMMENT \'(DC2Type:oauth2_scope)\'');
        $this->addSql('ALTER TABLE oauth2_authorization_code CHANGE expiry expiry DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE scopes scopes TEXT DEFAULT NULL COMMENT \'(DC2Type:oauth2_scope)\'');
        $this->addSql('ALTER TABLE oauth2_client CHANGE redirect_uris redirect_uris TEXT DEFAULT NULL COMMENT \'(DC2Type:oauth2_redirect_uri)\', CHANGE grants grants TEXT DEFAULT NULL COMMENT \'(DC2Type:oauth2_grant)\', CHANGE scopes scopes TEXT DEFAULT NULL COMMENT \'(DC2Type:oauth2_scope)\'');
        $this->addSql('ALTER TABLE oauth2_refresh_token CHANGE expiry expiry DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE page__block CHANGE settings settings JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE page__snapshot CHANGE content content JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE product CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE reset_password_request CHANGE requested_at requested_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE expires_at expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE reward CHANGE paid_date paid_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE rsvp CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE StatusEvent CHANGE CreatedAt CreatedAt DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE UpdatedAt UpdatedAt DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE stream CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE stream_artist CHANGE started_at started_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE stopped_at stopped_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE ticket CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE user CHANGE roles roles JSON NOT NULL COMMENT \'(DC2Type:json)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE last_login last_login DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Booking CHANGE retrieval retrieval DATETIME DEFAULT NULL, CHANGE return_date return_date DATETIME DEFAULT NULL, CHANGE paid_date paid_date DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE modified_at modified_at DATETIME NOT NULL, CHANGE booking_date booking_date DATE NOT NULL');
        $this->addSql('ALTER TABLE Booking_audit CHANGE retrieval retrieval DATETIME DEFAULT NULL, CHANGE return_date return_date DATETIME DEFAULT NULL, CHANGE paid_date paid_date DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE modified_at modified_at DATETIME DEFAULT NULL, CHANGE booking_date booking_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE Item CHANGE createdAt createdAt DATETIME NOT NULL, CHANGE updatedAt updatedAt DATETIME NOT NULL');
        $this->addSql('ALTER TABLE Item_audit CHANGE createdAt createdAt DATETIME DEFAULT NULL, CHANGE updatedAt updatedAt DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE StatusEvent CHANGE CreatedAt CreatedAt DATETIME NOT NULL, CHANGE UpdatedAt UpdatedAt DATETIME NOT NULL');
        $this->addSql('ALTER TABLE access_groups CHANGE roles roles JSON NOT NULL');
        $this->addSql('ALTER TABLE artist CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE links links JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE checkout CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE contract CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE valid_from valid_from DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE door_log CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE email CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE sent_at sent_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE event CHANGE publish_date publish_date DATETIME DEFAULT NULL, CHANGE links links JSON DEFAULT NULL, CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE artist_sign_up_start artist_sign_up_start DATETIME DEFAULT NULL, CHANGE artist_sign_up_end artist_sign_up_end DATETIME DEFAULT NULL, CHANGE ticket_presale_start ticket_presale_start DATETIME DEFAULT NULL, CHANGE ticket_presale_end ticket_presale_end DATETIME DEFAULT NULL, CHANGE artist_display_config artist_display_config JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE happening_booking CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE media__media CHANGE provider_metadata provider_metadata JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE member CHANGE createdAt createdAt DATETIME DEFAULT NULL, CHANGE updatedAt updatedAt DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE nakki CHANGE start_at start_at DATETIME NOT NULL, CHANGE end_at end_at DATETIME NOT NULL, CHANGE nakki_interval nakki_interval VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE nakki_booking CHANGE start_at start_at DATETIME NOT NULL, CHANGE end_at end_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE notification CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE sent_at sent_at DATETIME DEFAULT NULL, CHANGE options options JSON NOT NULL');
        $this->addSql('ALTER TABLE oauth2_access_token CHANGE expiry expiry DATETIME NOT NULL, CHANGE scopes scopes TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE oauth2_authorization_code CHANGE expiry expiry DATETIME NOT NULL, CHANGE scopes scopes TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE oauth2_client CHANGE redirect_uris redirect_uris TEXT DEFAULT NULL, CHANGE grants grants TEXT DEFAULT NULL, CHANGE scopes scopes TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE oauth2_refresh_token CHANGE expiry expiry DATETIME NOT NULL');
        $this->addSql('ALTER TABLE page__block CHANGE settings settings JSON NOT NULL');
        $this->addSql('ALTER TABLE page__snapshot CHANGE content content JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE product CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE reset_password_request CHANGE requested_at requested_at DATETIME NOT NULL, CHANGE expires_at expires_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE reward CHANGE paid_date paid_date DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE rsvp CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE stream CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE stream_artist CHANGE started_at started_at DATETIME NOT NULL, CHANGE stopped_at stopped_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE ticket CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE last_login last_login DATETIME DEFAULT NULL, CHANGE roles roles JSON NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
