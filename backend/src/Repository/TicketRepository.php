<?php

/**
 * Repository TicketRepository — couche d'accès aux données pour l'entité Ticket.
 *
 * Fournit les méthodes de recherche, de filtrage et de pagination des tickets.
 * Hérite des méthodes CRUD de base de ServiceEntityRepository.
 *
 * Ajouter ici les requêtes métier spécifiques :
 * - findByStatus(string $status) : tickets selon leur statut
 * - findByPriority(string $priority) : tickets urgents
 * - findByUser(User $user) : tickets d'un utilisateur
 * - findOpenTickets() : tickets non fermés/résolus
 * - countByStatus() : statistiques de tableau de bord
 */

namespace App\Repository;

use App\Entity\Ticket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ticket>
 */
class TicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    /**
     * Retourne les tickets filtrés par statut, triés par date de création décroissante.
     * Exemple d'utilisation : afficher la file "Nouveau" dans le tableau de bord.
     *
     * @return Ticket[]
     */
    public function findByStatusOrderedByDate(string $status): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.status = :status')
            ->setParameter('status', $status)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne tous les tickets d'un utilisateur, du plus récent au plus ancien.
     * Utilisé pour la vue "Mes tickets" dans l'interface utilisateur.
     *
     * @return Ticket[]
     */
    public function findByCreatedBy(int $userId): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.createdBy = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
