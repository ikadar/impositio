<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250619141131 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE joblang_script ADD job_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE joblang_script ADD CONSTRAINT FK_3001F66BBE04EA9 FOREIGN KEY (job_id) REFERENCES jobs (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_3001F66BBE04EA9 ON joblang_script (job_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE joblang_script DROP FOREIGN KEY FK_3001F66BBE04EA9
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_3001F66BBE04EA9 ON joblang_script
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE joblang_script DROP job_id
        SQL);
    }
}
