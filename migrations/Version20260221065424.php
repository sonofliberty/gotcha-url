<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260221065424 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Switch primary keys from integer to UUID';
    }

    public function up(Schema $schema): void
    {
        // Drop foreign keys first so column types can be changed
        $this->addSql('ALTER TABLE visit DROP FOREIGN KEY FK_437EE939ADA40271');
        $this->addSql('ALTER TABLE link DROP FOREIGN KEY FK_36AC99F1A76ED395');

        // Change PK and FK columns from INT to BINARY(16)
        $this->addSql('ALTER TABLE `user` CHANGE id id BINARY(16) NOT NULL');
        $this->addSql('ALTER TABLE link CHANGE id id BINARY(16) NOT NULL, CHANGE user_id user_id BINARY(16) NOT NULL');
        $this->addSql('ALTER TABLE visit CHANGE id id BINARY(16) NOT NULL, CHANGE link_id link_id BINARY(16) NOT NULL');

        // Re-add foreign keys
        $this->addSql('ALTER TABLE link ADD CONSTRAINT FK_36AC99F1A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE visit ADD CONSTRAINT FK_437EE939ADA40271 FOREIGN KEY (link_id) REFERENCES link (id)');
    }

    public function down(Schema $schema): void
    {
        // Drop foreign keys first
        $this->addSql('ALTER TABLE visit DROP FOREIGN KEY FK_437EE939ADA40271');
        $this->addSql('ALTER TABLE link DROP FOREIGN KEY FK_36AC99F1A76ED395');

        // Change back to INT AUTO_INCREMENT
        $this->addSql('ALTER TABLE `user` CHANGE id id INT AUTO_INCREMENT NOT NULL');
        $this->addSql('ALTER TABLE link CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE user_id user_id INT NOT NULL');
        $this->addSql('ALTER TABLE visit CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE link_id link_id INT NOT NULL');

        // Re-add foreign keys
        $this->addSql('ALTER TABLE link ADD CONSTRAINT FK_36AC99F1A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE visit ADD CONSTRAINT FK_437EE939ADA40271 FOREIGN KEY (link_id) REFERENCES link (id)');
    }
}
