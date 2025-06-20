<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250619160153 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE joblang_line (id INT AUTO_INCREMENT NOT NULL, joblang_script_id INT NOT NULL, source LONGTEXT NOT NULL, parsed JSON NOT NULL COMMENT '(DC2Type:json)', INDEX IDX_8DF31D8A1722976C (joblang_script_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE joblang_line ADD CONSTRAINT FK_8DF31D8A1722976C FOREIGN KEY (joblang_script_id) REFERENCES joblang_script (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE joblang_line DROP FOREIGN KEY FK_8DF31D8A1722976C
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE joblang_line
        SQL);
    }
}
