<?php

/**
 * Migration Doctrine — création du schéma initial de SupportFlow.
 *
 * Générée automatiquement par : php bin/console make:migration
 * Exécutée avec : php bin/console doctrine:migrations:migrate
 *
 * Cette migration crée les 5 tables suivantes :
 *  - `user`              → comptes utilisateurs (clients et admins)
 *  - `ticket`            → demandes de support
 *  - `comment`           → commentaires sur les tickets
 *  - `ticket_history`    → journal d'audit des modifications
 *  - `messenger_messages`→ file de messages Symfony Messenger (async)
 *
 * Les méthodes up() et down() permettent d'appliquer ou d'annuler la migration.
 * Doctrine Migrations garde la trace des migrations exécutées dans la table
 * `doctrine_migration_versions` pour éviter les doubles exécutions.
 */

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508183625 extends AbstractMigration
{
    /**
     * Description affichée dans la liste des migrations (doctrine:migrations:list).
     */
    public function getDescription(): string
    {
        return 'Création du schéma initial SupportFlow : user, ticket, comment, ticket_history';
    }

    /**
     * up() — applique la migration (sens ascendant).
     * Crée toutes les tables et les contraintes de clés étrangères.
     *
     * Choix techniques :
     *  - utf8mb4 + utf8mb4_unicode_ci : support complet de l'Unicode (emojis, accents)
     *  - LONGTEXT pour content/description : pas de limite de taille pratique
     *  - DEFAULT NULL sur les FKs optionnelles (author_id, user_id, created_by_id)
     *    → permet de conserver les enregistrements si l'utilisateur référencé est supprimé
     *  - INDEX sur toutes les clés étrangères : performance des JOINs Doctrine
     */
    public function up(Schema $schema): void
    {
        // --- TABLE comment ---------------------------------------------------
        // Stocke les messages échangés sur un ticket.
        // INDEX sur ticket_id et author_id pour les requêtes "commentaires d'un ticket"
        // et "commentaires d'un utilisateur".
        $this->addSql('CREATE TABLE comment (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, ticket_id INT NOT NULL, author_id INT DEFAULT NULL, INDEX IDX_9474526C700047D2 (ticket_id), INDEX IDX_9474526CF675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        // --- TABLE ticket ----------------------------------------------------
        // Cœur métier : chaque ligne représente une demande de support.
        // created_by_id nullable : ticket conservé si l'utilisateur créateur est supprimé.
        $this->addSql('CREATE TABLE ticket (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, category VARCHAR(100) NOT NULL, priority VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by_id INT DEFAULT NULL, INDEX IDX_97A0ADA3B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        // --- TABLE ticket_history --------------------------------------------
        // Journal d'audit : une ligne par action sur un ticket.
        // field_name, old_value, new_value sont NULL pour les actions globales (création).
        // user_id nullable : l'historique est conservé si l'auteur de l'action est supprimé.
        $this->addSql('CREATE TABLE ticket_history (id INT AUTO_INCREMENT NOT NULL, action VARCHAR(100) NOT NULL, field_name VARCHAR(100) DEFAULT NULL, old_value VARCHAR(255) DEFAULT NULL, new_value VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, ticket_id INT NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_2B762919700047D2 (ticket_id), INDEX IDX_2B762919A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        // --- TABLE user ------------------------------------------------------
        // Backticks autour de `user` car c'est un mot réservé MySQL.
        // UNIQUE INDEX sur email : garantit qu'un email ne peut être enregistré qu'une fois.
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, role VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        // --- TABLE messenger_messages ----------------------------------------
        // Utilisée par Symfony Messenger pour la gestion asynchrone des messages
        // (emails, notifications, jobs en arrière-plan). INDEX composite optimisé
        // pour les requêtes de dépilement de la file.
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        // --- CONTRAINTES DE CLÉS ÉTRANGÈRES ----------------------------------
        // Garantissent l'intégrité référentielle au niveau de la base de données,
        // en plus des validations applicatives Symfony.
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C700047D2 FOREIGN KEY (ticket_id) REFERENCES ticket (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CF675F31B FOREIGN KEY (author_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE ticket_history ADD CONSTRAINT FK_2B762919700047D2 FOREIGN KEY (ticket_id) REFERENCES ticket (id)');
        $this->addSql('ALTER TABLE ticket_history ADD CONSTRAINT FK_2B762919A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
    }

    /**
     * down() — annule la migration (rollback).
     * Supprime les contraintes FK avant les tables pour respecter l'ordre des dépendances.
     *
     * Utilisation : php bin/console doctrine:migrations:migrate prev
     */
    public function down(Schema $schema): void
    {
        // Suppression des FKs d'abord pour éviter les erreurs d'intégrité
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C700047D2');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CF675F31B');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3B03A8386');
        $this->addSql('ALTER TABLE ticket_history DROP FOREIGN KEY FK_2B762919700047D2');
        $this->addSql('ALTER TABLE ticket_history DROP FOREIGN KEY FK_2B762919A76ED395');
        // Suppression des tables dans l'ordre inverse des dépendances
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE ticket');
        $this->addSql('DROP TABLE ticket_history');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
