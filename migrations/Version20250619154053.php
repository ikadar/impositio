<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250619154053 extends AbstractMigration
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
            ALTER TABLE jobs ADD CONSTRAINT FK_A8936DC51722976C FOREIGN KEY (joblang_script_id) REFERENCES joblang_script (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE jobs DROP FOREIGN KEY FK_A8936DC51722976C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE jobs ADD CONSTRAINT FK_A8936DC51722976C FOREIGN KEY (joblang_script_id) REFERENCES joblang_script (id)
        SQL);
    }
}
