<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240613081854 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE Booking DROP FOREIGN KEY FK_2FB1D442F742942');
        $this->addSql('ALTER TABLE Booking ADD CONSTRAINT FK_2FB1D442BF6AC7F3 FOREIGN KEY (renting_privileges_id) REFERENCES WhoCanRentChoice (id)');
        $this->addSql('ALTER TABLE booking_package DROP FOREIGN KEY FK_177E70C33301C60');
        $this->addSql('ALTER TABLE booking_package ADD CONSTRAINT FK_84D622B53301C60 FOREIGN KEY (booking_id) REFERENCES Booking (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE File DROP FOREIGN KEY FK_C7F46F5D4584665A');
        $this->addSql('ALTER TABLE File DROP FOREIGN KEY FK_C7F46F5D93CB796C');
        $this->addSql('ALTER TABLE File ADD CONSTRAINT FK_2CAD992E4584665A FOREIGN KEY (product_id) REFERENCES Item (id)');
        $this->addSql('ALTER TABLE File ADD CONSTRAINT FK_2CAD992E93CB796C FOREIGN KEY (file_id) REFERENCES media__media (id)');
        $this->addSql('ALTER TABLE item_who_can_rent_choice DROP FOREIGN KEY FK_EAB3B69A126F525E');
        $this->addSql('ALTER TABLE item_who_can_rent_choice ADD CONSTRAINT FK_4F9286A126F525E FOREIGN KEY (item_id) REFERENCES Item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE item_package DROP FOREIGN KEY FK_2A1D3EF2126F525E');
        $this->addSql('ALTER TABLE item_package ADD CONSTRAINT FK_D53541C1126F525E FOREIGN KEY (item_id) REFERENCES Item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE package_who_can_rent_choice DROP FOREIGN KEY FK_48FF341F44CABFF');
        $this->addSql('ALTER TABLE package_who_can_rent_choice ADD CONSTRAINT FK_27443592F44CABFF FOREIGN KEY (package_id) REFERENCES Package (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE StatusEvent DROP FOREIGN KEY FK_FA6F25A33301C60');
        $this->addSql('ALTER TABLE StatusEvent DROP FOREIGN KEY FK_FA6F25A3126F525E');
        $this->addSql('ALTER TABLE StatusEvent ADD CONSTRAINT FK_19D077FB126F525E FOREIGN KEY (item_id) REFERENCES Item (id)');
        $this->addSql('ALTER TABLE StatusEvent ADD CONSTRAINT FK_19D077FB3301C60 FOREIGN KEY (booking_id) REFERENCES Booking (id)');
        $this->addSql('ALTER TABLE product ADD how_many_one_can_buy_at_one_time INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE item_package DROP FOREIGN KEY FK_D53541C1126F525E');
        $this->addSql('ALTER TABLE item_package ADD CONSTRAINT FK_2A1D3EF2126F525E FOREIGN KEY (item_id) REFERENCES Item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE Booking DROP FOREIGN KEY FK_2FB1D442BF6AC7F3');
        $this->addSql('ALTER TABLE Booking ADD CONSTRAINT FK_2FB1D442F742942 FOREIGN KEY (renting_privileges_id) REFERENCES WhoCanRentChoice (id)');
        $this->addSql('ALTER TABLE StatusEvent DROP FOREIGN KEY FK_19D077FB126F525E');
        $this->addSql('ALTER TABLE StatusEvent DROP FOREIGN KEY FK_19D077FB3301C60');
        $this->addSql('ALTER TABLE StatusEvent ADD CONSTRAINT FK_FA6F25A33301C60 FOREIGN KEY (booking_id) REFERENCES Booking (id)');
        $this->addSql('ALTER TABLE StatusEvent ADD CONSTRAINT FK_FA6F25A3126F525E FOREIGN KEY (item_id) REFERENCES Item (id)');
        $this->addSql('ALTER TABLE File DROP FOREIGN KEY FK_2CAD992E4584665A');
        $this->addSql('ALTER TABLE File DROP FOREIGN KEY FK_2CAD992E93CB796C');
        $this->addSql('ALTER TABLE File ADD CONSTRAINT FK_C7F46F5D4584665A FOREIGN KEY (product_id) REFERENCES Item (id)');
        $this->addSql('ALTER TABLE File ADD CONSTRAINT FK_C7F46F5D93CB796C FOREIGN KEY (file_id) REFERENCES media__media (id)');
        $this->addSql('ALTER TABLE item_who_can_rent_choice DROP FOREIGN KEY FK_4F9286A126F525E');
        $this->addSql('ALTER TABLE item_who_can_rent_choice ADD CONSTRAINT FK_EAB3B69A126F525E FOREIGN KEY (item_id) REFERENCES Item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE booking_package DROP FOREIGN KEY FK_84D622B53301C60');
        $this->addSql('ALTER TABLE booking_package ADD CONSTRAINT FK_177E70C33301C60 FOREIGN KEY (booking_id) REFERENCES Booking (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE package_who_can_rent_choice DROP FOREIGN KEY FK_27443592F44CABFF');
        $this->addSql('ALTER TABLE package_who_can_rent_choice ADD CONSTRAINT FK_48FF341F44CABFF FOREIGN KEY (package_id) REFERENCES Package (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product DROP how_many_one_can_buy_at_one_time');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
