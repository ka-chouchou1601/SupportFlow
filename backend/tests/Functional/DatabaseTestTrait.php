<?php

/**
 * DatabaseTestTrait — utilitaires partagés entre tous les tests fonctionnels.
 *
 * Ce trait centralise la gestion du cycle de vie de la base de test :
 *  - Création du schéma avant la première classe de test
 *  - Remise à zéro des données avant chaque test (tables tronquées)
 *  - Accès à l'EntityManager du container de test
 *
 * Utilisé par tous les WebTestCase de tests/Functional/Api/.
 *
 * Pourquoi un trait plutôt qu'une classe parente ?
 * WebTestCase hérite déjà de KernelTestCase → on ne peut pas hériter d'une
 * deuxième classe en PHP. Un trait est la solution idiomatique pour partager
 * du comportement sans héritage multiple.
 */

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

trait DatabaseTestTrait
{
    private static bool $schemaCreated = false;

    // -------------------------------------------------------------------------
    // CYCLE DE VIE
    // -------------------------------------------------------------------------

    /**
     * Appelé avant chaque test (défini dans les classes de test qui utilisent ce trait).
     * Vide toutes les tables métier pour garantir l'isolation entre les tests.
     *
     * On utilise TRUNCATE plutôt que DELETE pour réinitialiser aussi les séquences
     * AUTO_INCREMENT — les IDs recommencent à 1 à chaque test, rendant les assertions
     * sur les IDs prévisibles.
     */
    protected function resetDatabase(): void
    {
        $em = $this->getEntityManager();
        $connection = $em->getConnection();

        // Désactive les contraintes FK le temps du truncate pour éviter les erreurs
        // d'intégrité liées à l'ordre de suppression des tables
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $connection->executeStatement('TRUNCATE TABLE ticket_history');
        $connection->executeStatement('TRUNCATE TABLE comment');
        $connection->executeStatement('TRUNCATE TABLE ticket');
        $connection->executeStatement('TRUNCATE TABLE `user`');
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        // Vide le cache d'identité Doctrine pour éviter de servir des entités
        // obsolètes depuis la session précédente
        $em->clear();
    }

    /**
     * Crée et persiste un utilisateur de test minimal.
     *
     * @param string $email   Email unique de l'utilisateur
     * @param string $role    Rôle ('ROLE_USER' par défaut)
     * @return User           L'entité persistée avec un ID assigné
     */
    protected function createTestUser(string $email = 'test@test.com', string $role = 'ROLE_USER'): User
    {
        $em = $this->getEntityManager();

        // Récupère le hasher via le container — cohérent avec le code de production
        $hasher = static::getContainer()->get('security.user_password_hasher');

        $user = new User();
        $user->setEmail($email);
        $user->setName('Test User');
        $user->setRole($role);
        $user->setPassword($hasher->hashPassword($user, 'password'));

        $em->persist($user);
        $em->flush();

        return $user;
    }

    /**
     * Retourne l'EntityManager du container de test.
     *
     * static::getContainer() accède au container de service Symfony injecté
     * par KernelTestCase. Le container de test a accès à tous les services privés
     * (contrairement au container de production).
     */
    protected function getEntityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * Crée un client HTTP de test avec le Content-Type JSON préconfigurée.
     * Évite de répéter les headers dans chaque test.
     */
    protected function createJsonClient(): KernelBrowser
    {
        // setUp() boots the kernel via bootKernel(); createClient() would throw if
        // the kernel is still running. Shut it down first so createClient() can
        // boot a fresh instance for the HTTP layer.
        static::ensureKernelShutdown();

        return static::createClient([], [
            'CONTENT_TYPE' => 'application/json',
        ]);
    }
}
