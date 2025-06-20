<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250620104611 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE part (id INT AUTO_INCREMENT NOT NULL, job_id INT NOT NULL, part_id VARCHAR(255) NOT NULL, INDEX IDX_490F70C6BE04EA9 (job_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE part ADD CONSTRAINT FK_490F70C6BE04EA9 FOREIGN KEY (job_id) REFERENCES jobs (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE part DROP FOREIGN KEY FK_490F70C6BE04EA9
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE part
        SQL);
    }
}
