<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250731082408 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE action_path DROP FOREIGN KEY FK_7DB53001EC37C9B4
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_7DB53001EC37C9B4 ON action_path
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE action_path CHANGE part_id_id part_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE action_path ADD CONSTRAINT FK_7DB530014CE34BEC FOREIGN KEY (part_id) REFERENCES part (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7DB530014CE34BEC ON action_path (part_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE action_path DROP FOREIGN KEY FK_7DB530014CE34BEC
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_7DB530014CE34BEC ON action_path
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE action_path CHANGE part_id part_id_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE action_path ADD CONSTRAINT FK_7DB53001EC37C9B4 FOREIGN KEY (part_id_id) REFERENCES part (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7DB53001EC37C9B4 ON action_path (part_id_id)
        SQL);
    }
}
