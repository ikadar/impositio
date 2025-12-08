<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251208103852 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE process_action_paths (id INT AUTO_INCREMENT NOT NULL, process_part_id INT NOT NULL, json JSON NOT NULL COMMENT '(DC2Type:json)', INDEX IDX_6CA1F9C49B9139 (process_part_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE process_parts (id INT AUTO_INCREMENT NOT NULL, process_request_id INT NOT NULL, part_id VARCHAR(255) NOT NULL, actions JSON NOT NULL COMMENT '(DC2Type:json)', properties JSON NOT NULL COMMENT '(DC2Type:json)', required_parts JSON NOT NULL COMMENT '(DC2Type:json)', INDEX IDX_3F1C8A0FEC359BAD (process_request_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE process_requests (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', payload JSON NOT NULL COMMENT '(DC2Type:json)', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE process_action_paths ADD CONSTRAINT FK_6CA1F9C49B9139 FOREIGN KEY (process_part_id) REFERENCES process_parts (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE process_parts ADD CONSTRAINT FK_3F1C8A0FEC359BAD FOREIGN KEY (process_request_id) REFERENCES process_requests (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE process_action_paths DROP FOREIGN KEY FK_6CA1F9C49B9139
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE process_parts DROP FOREIGN KEY FK_3F1C8A0FEC359BAD
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE process_action_paths
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE process_parts
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE process_requests
        SQL);
    }
}
