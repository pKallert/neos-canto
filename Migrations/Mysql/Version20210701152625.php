<?php
declare(strict_types=1);

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Canto OAuth account authorization storage
 */
final class Version20210701152625 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Canto OAuth account authorization storage';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE flownative_canto_domain_model_accountauthorization (persistence_object_identifier VARCHAR(40) NOT NULL, flowaccountidentifier VARCHAR(255) NOT NULL, authorizationid VARCHAR(255) NOT NULL, UNIQUE INDEX flow_identity_flownative_canto_domain_model_accountauthor_cde13 (flowaccountidentifier), PRIMARY KEY(persistence_object_identifier)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE flownative_canto_domain_model_accountauthorization');
    }
}
