<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250527154630 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE cagnotte (id SERIAL NOT NULL, user_id INT NOT NULL, team_id INT NOT NULL, current_amount NUMERIC(10, 2) NOT NULL, total_earned NUMERIC(10, 2) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6342C752A76ED395 ON cagnotte (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6342C752296CD8AE ON cagnotte (team_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE cagnotte_transaction (id SERIAL NOT NULL, cagnotte_id INT NOT NULL, event_id INT DEFAULT NULL, amount NUMERIC(10, 2) NOT NULL, type VARCHAR(20) NOT NULL, description TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_8BF920B315105EB8 ON cagnotte_transaction (cagnotte_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_8BF920B371F7E88B ON cagnotte_transaction (event_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE club (id SERIAL NOT NULL, owner_id INT NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, image_path VARCHAR(500) DEFAULT NULL, is_public BOOLEAN NOT NULL, allow_join_requests BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_active BOOLEAN NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_B8EE38727E3C61F9 ON club (owner_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE club_finance (id SERIAL NOT NULL, club_id INT NOT NULL, total_commission NUMERIC(12, 2) NOT NULL, current_balance NUMERIC(12, 2) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_C3604F6261190A32 ON club_finance (club_id)
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
            CREATE TABLE club_transaction (id SERIAL NOT NULL, club_id INT NOT NULL, event_id INT DEFAULT NULL, amount NUMERIC(10, 2) NOT NULL, type VARCHAR(20) NOT NULL, description TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_ACC55D5A61190A32 ON club_transaction (club_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_ACC55D5A71F7E88B ON club_transaction (event_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE document (id SERIAL NOT NULL, user_id INT NOT NULL, document_type_id INT NOT NULL, validated_by_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, file_path VARCHAR(255) DEFAULT NULL, original_file_name VARCHAR(255) DEFAULT NULL, mime_type VARCHAR(100) DEFAULT NULL, file_size INT DEFAULT NULL, status VARCHAR(50) NOT NULL, expiration_date DATE DEFAULT NULL, validated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, rejection_reason TEXT DEFAULT NULL, created_at VARCHAR(255) NOT NULL, updated_at VARCHAR(255) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_D8698A76A76ED395 ON document (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_D8698A7661232A4F ON document (document_type_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_D8698A76C69DE5E5 ON document (validated_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE document_type (id SERIAL NOT NULL, team_id INT NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, type VARCHAR(50) NOT NULL, is_required BOOLEAN NOT NULL, has_expiration_date BOOLEAN NOT NULL, validity_duration_in_days INT DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_2B6ADBBA296CD8AE ON document_type (team_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE event (id SERIAL NOT NULL, team_id INT NOT NULL, created_by_id INT NOT NULL, title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, total_budget NUMERIC(10, 2) NOT NULL, club_percentage NUMERIC(5, 2) NOT NULL, image_path VARCHAR(500) DEFAULT NULL, event_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(20) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_3BAE0AA7296CD8AE ON event (team_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_3BAE0AA7B03A8386 ON event (created_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE event_participant (id SERIAL NOT NULL, event_id INT NOT NULL, user_id INT NOT NULL, amount_earned NUMERIC(10, 2) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7C16B89171F7E88B ON event_participant (event_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7C16B891A76ED395 ON event_participant (user_id)
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
            CREATE TABLE payment (id SERIAL NOT NULL, user_id INT NOT NULL, team_id INT NOT NULL, payment_schedule_id INT DEFAULT NULL, amount NUMERIC(10, 2) NOT NULL, amount_paid NUMERIC(10, 2) NOT NULL, due_date DATE NOT NULL, paid_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status VARCHAR(20) NOT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6D28840DA76ED395 ON payment (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6D28840D296CD8AE ON payment (team_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6D28840D5287120F ON payment (payment_schedule_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE payment_deduction (id SERIAL NOT NULL, team_id INT NOT NULL, created_by_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, type VARCHAR(50) NOT NULL, calculation_type VARCHAR(50) NOT NULL, value NUMERIC(10, 2) NOT NULL, max_amount NUMERIC(10, 2) DEFAULT NULL, min_amount NUMERIC(10, 2) DEFAULT NULL, is_active BOOLEAN NOT NULL, is_automatic BOOLEAN NOT NULL, valid_from DATE DEFAULT NULL, valid_until DATE DEFAULT NULL, created_at VARCHAR(255) NOT NULL, updated_at VARCHAR(255) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_2D1440BB296CD8AE ON payment_deduction (team_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_2D1440BBB03A8386 ON payment_deduction (created_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE payment_schedule (id SERIAL NOT NULL, team_id INT NOT NULL, amount NUMERIC(10, 2) NOT NULL, due_date DATE NOT NULL, description TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_1AFE5393296CD8AE ON payment_schedule (team_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE season (id SERIAL NOT NULL, club_id INT NOT NULL, name VARCHAR(255) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_F0E45BA961190A32 ON season (club_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE team (id SERIAL NOT NULL, club_id INT NOT NULL, season_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, image_path VARCHAR(500) DEFAULT NULL, annual_price NUMERIC(10, 2) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_active BOOLEAN NOT NULL, PRIMARY KEY(id))
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
            CREATE TABLE "user" (id SERIAL NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, phone VARCHAR(20) DEFAULT NULL, onboarding_type VARCHAR(20) DEFAULT NULL, onboarding_completed BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_active BOOLEAN NOT NULL, PRIMARY KEY(id))
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
            ALTER TABLE cagnotte ADD CONSTRAINT FK_6342C752A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cagnotte ADD CONSTRAINT FK_6342C752296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cagnotte_transaction ADD CONSTRAINT FK_8BF920B315105EB8 FOREIGN KEY (cagnotte_id) REFERENCES cagnotte (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cagnotte_transaction ADD CONSTRAINT FK_8BF920B371F7E88B FOREIGN KEY (event_id) REFERENCES event (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE club ADD CONSTRAINT FK_B8EE38727E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE club_finance ADD CONSTRAINT FK_C3604F6261190A32 FOREIGN KEY (club_id) REFERENCES club (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE club_manager ADD CONSTRAINT FK_F76C803BA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE club_manager ADD CONSTRAINT FK_F76C803B61190A32 FOREIGN KEY (club_id) REFERENCES club (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE club_transaction ADD CONSTRAINT FK_ACC55D5A61190A32 FOREIGN KEY (club_id) REFERENCES club (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE club_transaction ADD CONSTRAINT FK_ACC55D5A71F7E88B FOREIGN KEY (event_id) REFERENCES event (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document ADD CONSTRAINT FK_D8698A76A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document ADD CONSTRAINT FK_D8698A7661232A4F FOREIGN KEY (document_type_id) REFERENCES document_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document ADD CONSTRAINT FK_D8698A76C69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_type ADD CONSTRAINT FK_2B6ADBBA296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event_participant ADD CONSTRAINT FK_7C16B89171F7E88B FOREIGN KEY (event_id) REFERENCES event (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event_participant ADD CONSTRAINT FK_7C16B891A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
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
            ALTER TABLE payment ADD CONSTRAINT FK_6D28840DA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment ADD CONSTRAINT FK_6D28840D296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment ADD CONSTRAINT FK_6D28840D5287120F FOREIGN KEY (payment_schedule_id) REFERENCES payment_schedule (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment_deduction ADD CONSTRAINT FK_2D1440BB296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment_deduction ADD CONSTRAINT FK_2D1440BBB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment_schedule ADD CONSTRAINT FK_1AFE5393296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) NOT DEFERRABLE INITIALLY IMMEDIATE
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
            ALTER TABLE cagnotte DROP CONSTRAINT FK_6342C752A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cagnotte DROP CONSTRAINT FK_6342C752296CD8AE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cagnotte_transaction DROP CONSTRAINT FK_8BF920B315105EB8
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cagnotte_transaction DROP CONSTRAINT FK_8BF920B371F7E88B
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE club DROP CONSTRAINT FK_B8EE38727E3C61F9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE club_finance DROP CONSTRAINT FK_C3604F6261190A32
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE club_manager DROP CONSTRAINT FK_F76C803BA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE club_manager DROP CONSTRAINT FK_F76C803B61190A32
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE club_transaction DROP CONSTRAINT FK_ACC55D5A61190A32
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE club_transaction DROP CONSTRAINT FK_ACC55D5A71F7E88B
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document DROP CONSTRAINT FK_D8698A76A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document DROP CONSTRAINT FK_D8698A7661232A4F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document DROP CONSTRAINT FK_D8698A76C69DE5E5
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE document_type DROP CONSTRAINT FK_2B6ADBBA296CD8AE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event DROP CONSTRAINT FK_3BAE0AA7296CD8AE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event DROP CONSTRAINT FK_3BAE0AA7B03A8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event_participant DROP CONSTRAINT FK_7C16B89171F7E88B
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event_participant DROP CONSTRAINT FK_7C16B891A76ED395
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
            ALTER TABLE payment DROP CONSTRAINT FK_6D28840DA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment DROP CONSTRAINT FK_6D28840D296CD8AE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment DROP CONSTRAINT FK_6D28840D5287120F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment_deduction DROP CONSTRAINT FK_2D1440BB296CD8AE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment_deduction DROP CONSTRAINT FK_2D1440BBB03A8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE payment_schedule DROP CONSTRAINT FK_1AFE5393296CD8AE
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
            DROP TABLE cagnotte
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE cagnotte_transaction
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE club
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE club_finance
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE club_manager
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE club_transaction
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE document
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE document_type
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE event
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE event_participant
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE join_request
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE notification
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE payment
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE payment_deduction
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE payment_schedule
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
