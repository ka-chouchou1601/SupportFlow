<?php

/**
 * Repository UserRepository — couche d'accès aux données pour l'entité User.
 *
 * Hérite de ServiceEntityRepository (Doctrine) qui fournit les méthodes de base :
 *  find(), findAll(), findBy(), findOneBy(), count()
 *
 * Implémente PasswordUpgraderInterface pour que Symfony Security puisse
 * automatiquement re-hacher les mots de passe avec un algorithme plus récent
 * lorsque l'utilisateur se connecte (transparent pour l'utilisateur).
 *
 * Ajouter ici les requêtes DQL/QueryBuilder spécifiques à User :
 * - recherche par email partiel
 * - liste des utilisateurs avec le nombre de tickets ouverts
 * - etc.
 */

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        // Enregistre ce repository pour l'entité User auprès du registre Doctrine
        parent::__construct($registry, User::class);
    }

    /**
     * Utilisé par le système de sécurité Symfony pour re-hacher le mot de passe
     * lors de la connexion si l'algorithme de hachage a changé depuis la dernière connexion.
     * Cela permet de migrer progressivement les mots de passe sans forcer
     * les utilisateurs à les réinitialiser.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Les instances de "%s" ne sont pas supportées.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }
}
