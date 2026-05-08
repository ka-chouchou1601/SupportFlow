<?php

/**
 * Repository TicketHistoryRepository — couche d'accès aux données pour TicketHistory.
 *
 * Hérite des méthodes CRUD de base de ServiceEntityRepository.
 *
 * Ajouter ici les requêtes d'audit :
 * - findByTicket(Ticket $ticket) : fil d'activité complet d'un ticket
 * - findByUser(User $user) : toutes les actions d'un utilisateur (rapport d'activité)
 * - findRecentByTicket(Ticket $ticket, int $limit) : dernières N actions (widget résumé)
 */

namespace App\Repository;

use App\Entity\TicketHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TicketHistory>
 */
class TicketHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TicketHistory::class);
    }
}
