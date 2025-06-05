<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250605184146 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE club (id SERIAL NOT NULL, owner_id INT NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, image_path VARCHAR(500) DEFAULT NULL, is_public BOOLEAN NOT NULL, allow_join_requests BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_active BOOLEAN NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_B8EE38727E3C61F9 ON club (owner_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE club_manager (id SERIAL NOT NULL, user_id INT NOT NULL, club_id INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_F76C803BA76ED395 ON club_manager (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_F76C803B61190A32 ON club_manager (club_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE document_type (id SERIAL NOT NULL, team_id INT NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, type VARCHAR(50) NOT NULL, is_required BOOLEAN NOT NULL, has_expiration_date BOOLEAN NOT NULL, validity_duration_in_days INT DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_2B6ADBBA296CD8AE ON document_type (team_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE documents (id SERIAL NOT NULL, uploaded_by_id INT NOT NULL, related_user_id INT DEFAULT NULL, user_id INT NOT NULL, document_type_entity_id INT NOT NULL, validated_by_id INT DEFAULT NULL, original_name TEXT NOT NULL, secure_path VARCHAR(500) NOT NULL, mime_type VARCHAR(100) NOT NULL, file_size INT NOT NULL, document_type VARCHAR(100) NOT NULL, description TEXT DEFAULT NULL, is_confidential BOOLEAN NOT NULL, uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_accessed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, access_count INT DEFAULT 0 NOT NULL, access_token VARCHAR(255) DEFAULT NULL, access_token_expiry TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, security_metadata JSON DEFAULT NULL, is_active BOOLEAN DEFAULT true NOT NULL, name VARCHAR(255) NOT NULL, file_path VARCHAR(255) DEFAULT NULL, original_file_name VARCHAR(255) DEFAULT NULL, status VARCHAR(50) NOT NULL, expiration_date DATE DEFAULT NULL, validated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, rejection_reason TEXT DEFAULT NULL, validation_notes TEXT DEFAULT NULL, created_at VARCHAR(255) NOT NULL, updated_at VARCHAR(255) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_A2B07288A2B28FE8 ON documents (uploaded_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_A2B0728898771930 ON documents (related_user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_A2B07288A76ED395 ON documents (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_A2B072884FAEC941 ON documents (document_type_entity_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_A2B07288C69DE5E5 ON documents (validated_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_document_type ON documents (document_type)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_confidential ON documents (is_confidential)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_uploaded_at ON documents (uploaded_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE join_request (id SERIAL NOT NULL, user_id INT NOT NULL, team_id INT NOT NULL, club_id INT NOT NULL, reviewed_by_id INT DEFAULT NULL, message TEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, requested_role VARCHAR(20) DEFAULT NULL, assigned_role VARCHAR(20) DEFAULT NULL, reviewed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, review_notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_E932E4FFA76ED395 ON join_request (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_E932E4FF296CD8AE ON join_request (team_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_E932E4FF61190A32 ON join_request (club_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_E932E4FFFC6B21F1 ON join_request (reviewed_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_user_status ON join_request (user_id, status)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_team_status ON join_request (team_id, status)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_club_status ON join_request (club_id, status)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_reviewed_by_at ON join_request (reviewed_by_id, reviewed_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE notification (id SERIAL NOT NULL, user_id INT NOT NULL, type VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, message TEXT NOT NULL, is_read BOOLEAN NOT NULL, data JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_BF5476CAA76ED395 ON notification (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE refresh_token (id SERIAL NOT NULL, user_id INT NOT NULL, token VARCHAR(255) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(500) DEFAULT NULL, is_revoked BOOLEAN NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_C74F21955F37A13B ON refresh_token (token)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C74F2195A76ED395 ON refresh_token (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_refresh_token ON refresh_token (token)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_user_expires ON refresh_token (user_id, expires_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE season (id SERIAL NOT NULL, club_id INT NOT NULL, name VARCHAR(255) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_F0E45BA961190A32 ON season (club_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE team (id SERIAL NOT NULL, club_id INT NOT NULL, season_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, image_path VARCHAR(500) DEFAULT NULL, annual_price NUMERIC(10, 2) DEFAULT NULL, gender VARCHAR(20) DEFAULT NULL, min_birth_year INT DEFAULT NULL, max_birth_year INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_active BOOLEAN NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C4E0A61F61190A32 ON team (club_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C4E0A61F4EC001D1 ON team (season_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE team_member (id SERIAL NOT NULL, user_id INT NOT NULL, team_id INT NOT NULL, role VARCHAR(50) NOT NULL, joined_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, left_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, is_active BOOLEAN NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6FFBDA1A76ED395 ON team_member (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6FFBDA1296CD8AE ON team_member (team_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE "user" (id SERIAL NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, phone VARCHAR(20) DEFAULT NULL, date_of_birth DATE DEFAULT NULL, onboarding_type VARCHAR(20) DEFAULT NULL, onboarding_completed BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_active BOOLEAN NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_authentication (id SERIAL NOT NULL, user_id INT NOT NULL, provider VARCHAR(20) NOT NULL, provider_id VARCHAR(255) DEFAULT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) DEFAULT NULL, is_verified BOOLEAN NOT NULL, last_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_active BOOLEAN NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_953116A4A76ED395 ON user_authentication (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_provider_provider_id ON user_authentication (provider, provider_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_provider_email ON user_authentication (provider, email)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_user_active ON user_authentication (user_id, is_active)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE club ADD CONSTRAINT FK_B8EE38727E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE club_manager ADD CONSTRAINT FK_F76C803BA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE club_manager ADD CONSTRAINT FK_F76C803B61190A32 FOREIGN KEY (club_id) REFERENCES club (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_type ADD CONSTRAINT FK_2B6ADBBA296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents ADD CONSTRAINT FK_A2B07288A2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents ADD CONSTRAINT FK_A2B0728898771930 FOREIGN KEY (related_user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents ADD CONSTRAINT FK_A2B07288A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents ADD CONSTRAINT FK_A2B072884FAEC941 FOREIGN KEY (document_type_entity_id) REFERENCES document_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents ADD CONSTRAINT FK_A2B07288C69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE join_request ADD CONSTRAINT FK_E932E4FFA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE join_request ADD CONSTRAINT FK_E932E4FF296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE join_request ADD CONSTRAINT FK_E932E4FF61190A32 FOREIGN KEY (club_id) REFERENCES club (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE join_request ADD CONSTRAINT FK_E932E4FFFC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE refresh_token ADD CONSTRAINT FK_C74F2195A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE season ADD CONSTRAINT FK_F0E45BA961190A32 FOREIGN KEY (club_id) REFERENCES club (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE team ADD CONSTRAINT FK_C4E0A61F61190A32 FOREIGN KEY (club_id) REFERENCES club (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE team ADD CONSTRAINT FK_C4E0A61F4EC001D1 FOREIGN KEY (season_id) REFERENCES season (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE team_member ADD CONSTRAINT FK_6FFBDA1A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE team_member ADD CONSTRAINT FK_6FFBDA1296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_authentication ADD CONSTRAINT FK_953116A4A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE club DROP CONSTRAINT FK_B8EE38727E3C61F9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE club_manager DROP CONSTRAINT FK_F76C803BA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE club_manager DROP CONSTRAINT FK_F76C803B61190A32
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_type DROP CONSTRAINT FK_2B6ADBBA296CD8AE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents DROP CONSTRAINT FK_A2B07288A2B28FE8
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents DROP CONSTRAINT FK_A2B0728898771930
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents DROP CONSTRAINT FK_A2B07288A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents DROP CONSTRAINT FK_A2B072884FAEC941
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE documents DROP CONSTRAINT FK_A2B07288C69DE5E5
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE join_request DROP CONSTRAINT FK_E932E4FFA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE join_request DROP CONSTRAINT FK_E932E4FF296CD8AE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE join_request DROP CONSTRAINT FK_E932E4FF61190A32
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE join_request DROP CONSTRAINT FK_E932E4FFFC6B21F1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification DROP CONSTRAINT FK_BF5476CAA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE refresh_token DROP CONSTRAINT FK_C74F2195A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE season DROP CONSTRAINT FK_F0E45BA961190A32
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE team DROP CONSTRAINT FK_C4E0A61F61190A32
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE team DROP CONSTRAINT FK_C4E0A61F4EC001D1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE team_member DROP CONSTRAINT FK_6FFBDA1A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE team_member DROP CONSTRAINT FK_6FFBDA1296CD8AE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_authentication DROP CONSTRAINT FK_953116A4A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE club
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE club_manager
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE document_type
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE documents
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE join_request
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE notification
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE refresh_token
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE season
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE team
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE team_member
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE "user"
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_authentication
        SQL);
    }
}
