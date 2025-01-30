<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250130201552 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert links fields from array to JSON in artist and event tables';
    }

    public function up(Schema $schema): void
    {
        // First change the column types to remove constraints
        $this->addSql('ALTER TABLE artist MODIFY links LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE event MODIFY links LONGTEXT DEFAULT NULL');

        // Get and convert existing data from artist table
        $artistResult = $this->connection->executeQuery('SELECT id, links FROM artist WHERE links IS NOT NULL');
        while ($row = $artistResult->fetchAssociative()) {
            try {
                $links = @unserialize($row['links']);
                $jsonLinks = $links !== false ? json_encode($links) : 'null';

                $this->addSql('UPDATE artist SET links = :links WHERE id = :id', [
                    'links' => $jsonLinks,
                    'id' => $row['id']
                ]);
            } catch (\Exception $e) {
                $this->write(sprintf('Could not convert links for artist ID %d: %s', $row['id'], $e->getMessage()));
                // Set to null if conversion fails
                $this->addSql('UPDATE artist SET links = NULL WHERE id = :id', ['id' => $row['id']]);
            }
        }

        // Get and convert existing data from event table
        $eventResult = $this->connection->executeQuery('SELECT id, links FROM event WHERE links IS NOT NULL');
        while ($row = $eventResult->fetchAssociative()) {
            try {
                $links = @unserialize($row['links']);
                $jsonLinks = $links !== false ? json_encode($links) : 'null';

                $this->addSql('UPDATE event SET links = :links WHERE id = :id', [
                    'links' => $jsonLinks,
                    'id' => $row['id']
                ]);
            } catch (\Exception $e) {
                $this->write(sprintf('Could not convert links for event ID %d: %s', $row['id'], $e->getMessage()));
                // Set to null if conversion fails
                $this->addSql('UPDATE event SET links = NULL WHERE id = :id', ['id' => $row['id']]);
            }
        }

        // Now change the column types to JSON
        $this->addSql('ALTER TABLE artist MODIFY links JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE event MODIFY links JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
    }

    public function down(Schema $schema): void
    {
        // First change the column types to remove constraints
        $this->addSql('ALTER TABLE artist MODIFY links LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE event MODIFY links LONGTEXT DEFAULT NULL');

        // Get and convert existing data from artist table back to array
        $artistResult = $this->connection->executeQuery('SELECT id, links FROM artist WHERE links IS NOT NULL');
        while ($row = $artistResult->fetchAssociative()) {
            try {
                $links = json_decode($row['links'], true);
                $serializedLinks = $links !== null ? serialize($links) : 'N;';

                $this->addSql('UPDATE artist SET links = :links WHERE id = :id', [
                    'links' => $serializedLinks,
                    'id' => $row['id']
                ]);
            } catch (\Exception $e) {
                $this->write(sprintf('Could not convert links for artist ID %d: %s', $row['id'], $e->getMessage()));
                // Set to null if conversion fails
                $this->addSql('UPDATE artist SET links = NULL WHERE id = :id', ['id' => $row['id']]);
            }
        }

        // Get and convert existing data from event table back to array
        $eventResult = $this->connection->executeQuery('SELECT id, links FROM event WHERE links IS NOT NULL');
        while ($row = $eventResult->fetchAssociative()) {
            try {
                $links = json_decode($row['links'], true);
                $serializedLinks = $links !== null ? serialize($links) : 'N;';

                $this->addSql('UPDATE event SET links = :links WHERE id = :id', [
                    'links' => $serializedLinks,
                    'id' => $row['id']
                ]);
            } catch (\Exception $e) {
                $this->write(sprintf('Could not convert links for event ID %d: %s', $row['id'], $e->getMessage()));
                // Set to null if conversion fails
                $this->addSql('UPDATE event SET links = NULL WHERE id = :id', ['id' => $row['id']]);
            }
        }

        // Change column types back to array
        $this->addSql('ALTER TABLE artist MODIFY links LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:array)\'');
        $this->addSql('ALTER TABLE event MODIFY links LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:array)\'');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
