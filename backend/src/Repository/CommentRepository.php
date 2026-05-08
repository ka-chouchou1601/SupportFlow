<?php

/**
 * Repository CommentRepository — couche d'accès aux données pour l'entité Comment.
 *
 * Hérite des méthodes CRUD de base de ServiceEntityRepository.
 *
 * Ajouter ici les requêtes spécifiques :
 * - findByTicket(Ticket $ticket) : commentaires d'un ticket, triés par date
 * - findByAuthor(User $author) : tous les commentaires d'un utilisateur
 * - countByTicket(Ticket $ticket) : nombre de commentaires (badge dans les listes)
 */

namespace App\Repository;

use App\Entity\Comment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }
}
