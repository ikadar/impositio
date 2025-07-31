<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250731082132 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE action_path (id INT AUTO_INCREMENT NOT NULL, part_id_id INT NOT NULL, INDEX IDX_7DB53001EC37C9B4 (part_id_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE action_path ADD CONSTRAINT FK_7DB53001EC37C9B4 FOREIGN KEY (part_id_id) REFERENCES part (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE action_path DROP FOREIGN KEY FK_7DB53001EC37C9B4
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE action_path
        SQL);
    }
}
