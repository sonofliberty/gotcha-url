<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260221062432 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE link (id INT AUTO_INCREMENT NOT NULL, target_url VARCHAR(2048) NOT NULL, slug VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_36AC99F1989D9B62 (slug), INDEX IDX_36AC99F1A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, account_code VARCHAR(36) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_8D93D649FB86B732 (account_code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE visit (id INT AUTO_INCREMENT NOT NULL, ip_address VARCHAR(45) NOT NULL, user_agent VARCHAR(512) DEFAULT NULL, referrer VARCHAR(2048) DEFAULT NULL, screen_resolution VARCHAR(20) DEFAULT NULL, timezone VARCHAR(64) DEFAULT NULL, language VARCHAR(10) DEFAULT NULL, platform VARCHAR(64) DEFAULT NULL, cookies_enabled TINYINT DEFAULT NULL, country_code VARCHAR(2) DEFAULT NULL, city VARCHAR(128) DEFAULT NULL, created_at DATETIME NOT NULL, link_id INT NOT NULL, INDEX IDX_437EE939ADA40271 (link_id), INDEX idx_visit_link_created (link_id, created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE link ADD CONSTRAINT FK_36AC99F1A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE visit ADD CONSTRAINT FK_437EE939ADA40271 FOREIGN KEY (link_id) REFERENCES link (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE link DROP FOREIGN KEY FK_36AC99F1A76ED395');
        $this->addSql('ALTER TABLE visit DROP FOREIGN KEY FK_437EE939ADA40271');
        $this->addSql('DROP TABLE link');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE visit');
    }
}
