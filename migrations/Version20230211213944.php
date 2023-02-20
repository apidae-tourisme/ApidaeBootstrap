<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230211213944 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE tache_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE tache (id INT NOT NULL, user_email VARCHAR(255) DEFAULT NULL, tache VARCHAR(255) NOT NULL, fichier VARCHAR(255) DEFAULT NULL, parametres JSON DEFAULT NULL, parametres_caches JSON DEFAULT NULL, status VARCHAR(255) DEFAULT NULL, result JSON DEFAULT NULL, progress JSON DEFAULT NULL, startdate TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, enddate TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, creationdate TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, pid INT DEFAULT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE tache_id_seq CASCADE');
        $this->addSql('DROP TABLE tache');
    }
}
