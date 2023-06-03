<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230603111444 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE happening (id INT AUTO_INCREMENT NOT NULL, picture_id INT DEFAULT NULL, event_id INT DEFAULT NULL, name_fi VARCHAR(255) NOT NULL, name_en VARCHAR(255) NOT NULL, description_fi LONGTEXT NOT NULL, description_en LONGTEXT NOT NULL, time DATETIME NOT NULL, needs_preliminary_sign_up TINYINT(1) NOT NULL, needs_preliminary_payment TINYINT(1) NOT NULL, payment_info_fi LONGTEXT DEFAULT NULL, payment_info_en LONGTEXT DEFAULT NULL, type VARCHAR(255) NOT NULL, max_sign_ups INT NOT NULL, slug_fi VARCHAR(255) NOT NULL, slug_en VARCHAR(255) NOT NULL, price_fi VARCHAR(255) DEFAULT NULL, price_en VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_1F3C70ADEE45BDBF (picture_id), INDEX IDX_1F3C70AD71F7E88B (event_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE happening_member (happening_id INT NOT NULL, member_id INT NOT NULL, INDEX IDX_C822B0DAB7B10E6D (happening_id), INDEX IDX_C822B0DA7597D3FE (member_id), PRIMARY KEY(happening_id, member_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE happening_booking (id INT AUTO_INCREMENT NOT NULL, member_id INT DEFAULT NULL, happening_id INT NOT NULL, comment VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_D86CE9507597D3FE (member_id), INDEX IDX_D86CE950B7B10E6D (happening_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE happening ADD CONSTRAINT FK_1F3C70ADEE45BDBF FOREIGN KEY (picture_id) REFERENCES media__media (id)');
        $this->addSql('ALTER TABLE happening ADD CONSTRAINT FK_1F3C70AD71F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE happening_member ADD CONSTRAINT FK_C822B0DAB7B10E6D FOREIGN KEY (happening_id) REFERENCES happening (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE happening_member ADD CONSTRAINT FK_C822B0DA7597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE happening_booking ADD CONSTRAINT FK_D86CE9507597D3FE FOREIGN KEY (member_id) REFERENCES member (id)');
        $this->addSql('ALTER TABLE happening_booking ADD CONSTRAINT FK_D86CE950B7B10E6D FOREIGN KEY (happening_id) REFERENCES happening (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE happening DROP FOREIGN KEY FK_1F3C70ADEE45BDBF');
        $this->addSql('ALTER TABLE happening DROP FOREIGN KEY FK_1F3C70AD71F7E88B');
        $this->addSql('ALTER TABLE happening_member DROP FOREIGN KEY FK_C822B0DAB7B10E6D');
        $this->addSql('ALTER TABLE happening_member DROP FOREIGN KEY FK_C822B0DA7597D3FE');
        $this->addSql('ALTER TABLE happening_booking DROP FOREIGN KEY FK_D86CE9507597D3FE');
        $this->addSql('ALTER TABLE happening_booking DROP FOREIGN KEY FK_D86CE950B7B10E6D');
        $this->addSql('DROP TABLE happening');
        $this->addSql('DROP TABLE happening_member');
        $this->addSql('DROP TABLE happening_booking');
    }
}
