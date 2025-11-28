<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251127165045 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE account (id CHAR(36) NOT NULL, currency VARCHAR(3) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE balance (id CHAR(36) NOT NULL, available NUMERIC(18, 2) NOT NULL, updated_at DATETIME NOT NULL, account_id CHAR(36) NOT NULL, UNIQUE INDEX UNIQ_ACF41FFE9B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE transfer (id CHAR(36) NOT NULL, amount NUMERIC(18, 2) NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(255) NOT NULL, idempotency_key VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, from_account_id CHAR(36) DEFAULT NULL, to_account_id CHAR(36) DEFAULT NULL, INDEX IDX_4034A3C0B0CF99BD (from_account_id), INDEX IDX_4034A3C0BC58BDC7 (to_account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE balance ADD CONSTRAINT FK_ACF41FFE9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE transfer ADD CONSTRAINT FK_4034A3C0B0CF99BD FOREIGN KEY (from_account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE transfer ADD CONSTRAINT FK_4034A3C0BC58BDC7 FOREIGN KEY (to_account_id) REFERENCES account (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE balance DROP FOREIGN KEY FK_ACF41FFE9B6B5FBA');
        $this->addSql('ALTER TABLE transfer DROP FOREIGN KEY FK_4034A3C0B0CF99BD');
        $this->addSql('ALTER TABLE transfer DROP FOREIGN KEY FK_4034A3C0BC58BDC7');
        $this->addSql('DROP TABLE account');
        $this->addSql('DROP TABLE balance');
        $this->addSql('DROP TABLE transfer');
    }
}
