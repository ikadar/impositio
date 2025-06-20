<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250620090055 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE jobs DROP FOREIGN KEY FK_A8936DC51722976C
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_A8936DC51722976C ON jobs
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE jobs CHANGE joblang_script_id joblang_line_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE jobs ADD CONSTRAINT FK_A8936DC5513B9451 FOREIGN KEY (joblang_line_id) REFERENCES joblang_line (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_A8936DC5513B9451 ON jobs (joblang_line_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE jobs DROP FOREIGN KEY FK_A8936DC5513B9451
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_A8936DC5513B9451 ON jobs
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE jobs CHANGE joblang_line_id joblang_script_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE jobs ADD CONSTRAINT FK_A8936DC51722976C FOREIGN KEY (joblang_script_id) REFERENCES joblang_script (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_A8936DC51722976C ON jobs (joblang_script_id)
        SQL);
    }
}
