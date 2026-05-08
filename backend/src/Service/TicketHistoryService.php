<?php

/**
 * TicketHistoryService — centralize la création d'entrées d'historique de tickets.
 *
 * Ce service est le seul point d'écriture dans la table ticket_history.
 * En regroupant la logique d'audit ici (plutôt que dans les contrôleurs),
 * on garantit que :
 *  - le format des messages d'action est cohérent partout
 *  - ajouter un nouveau type d'action ne nécessite qu'une méthode de plus ici
 *  - les contrôleurs restent légers (Single Responsibility Principle)
 *
 * Injection automatique par le container Symfony grâce à l'autowiring :
 * il suffit de déclarer le type-hint EntityManagerInterface dans le constructeur.
 */

namespace App\Service;

use App\Entity\Ticket;
use App\Entity\TicketHistory;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class TicketHistoryService
{
    /**
     * EntityManagerInterface est l'interface Doctrine principale.
     * Elle permet de persist() (mettre en file) et flush() (envoyer en BDD)
     * sans dépendre d'une implémentation concrète (facilite les tests unitaires).
     */
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {}

    // -------------------------------------------------------------------------
    // MÉTHODES PUBLIQUES D'ENREGISTREMENT
    // Chacune crée une TicketHistory avec les métadonnées appropriées,
    // la persiste et la flush immédiatement pour qu'elle soit visible en BDD
    // dès la fin de l'appel.
    // -------------------------------------------------------------------------

    /**
     * Enregistre la création d'un ticket dans l'historique.
     *
     * Appelée systématiquement depuis TicketController::create() juste après
     * la persistance du ticket. Pas de fieldName/oldValue/newValue car c'est
     * une action globale (pas de champ modifié, c'est une création).
     */
    public function logCreation(Ticket $ticket, User $user): void
    {
        $history = new TicketHistory();
        $history->setTicket($ticket);
        $history->setUser($user);
        // Le message inclut le nom de l'auteur pour une meilleure lisibilité
        // dans le fil d'activité de l'interface
        $history->setAction(sprintf('Ticket créé par %s', $user->getName()));
        // fieldName/oldValue/newValue restent null (defaults dans l'entité)
        // : cohérent avec une action de type "création" sans comparaison before/after

        $this->em->persist($history);
        $this->em->flush();
    }

    /**
     * Enregistre un changement de statut dans l'historique.
     *
     * Les paramètres oldStatus et newStatus doivent être des valeurs valides
     * de Ticket::STATUSES. La validation est déléguée à l'appelant.
     *
     * @param string $oldStatus Statut avant le changement (ex: "Nouveau")
     * @param string $newStatus Statut après le changement (ex: "En cours")
     */
    public function logStatusChange(
        Ticket $ticket,
        User $user,
        string $oldStatus,
        string $newStatus
    ): void {
        $history = new TicketHistory();
        $history->setTicket($ticket);
        $history->setUser($user);
        // Message lisible pour le fil d'activité : "Statut changé de X à Y"
        $history->setAction(sprintf('Statut changé de %s à %s', $oldStatus, $newStatus));
        // fieldName/oldValue/newValue permettent à l'API ou aux rapports
        // de filtrer et comparer les changements de statut précisément
        $history->setFieldName('status');
        $history->setOldValue($oldStatus);
        $history->setNewValue($newStatus);

        $this->em->persist($history);
        $this->em->flush();
    }

    /**
     * Enregistre un changement de priorité dans l'historique.
     *
     * @param string $oldPriority Priorité avant le changement (ex: "Basse")
     * @param string $newPriority Priorité après le changement (ex: "Urgente")
     */
    public function logPriorityChange(
        Ticket $ticket,
        User $user,
        string $oldPriority,
        string $newPriority
    ): void {
        $history = new TicketHistory();
        $history->setTicket($ticket);
        $history->setUser($user);
        $history->setAction(sprintf('Priorité changée de %s à %s', $oldPriority, $newPriority));
        $history->setFieldName('priority');
        $history->setOldValue($oldPriority);
        $history->setNewValue($newPriority);

        $this->em->persist($history);
        $this->em->flush();
    }

    /**
     * Enregistre l'ajout d'un commentaire dans l'historique du ticket.
     *
     * Appelée depuis un futur CommentController::create().
     * Pas de fieldName/oldValue/newValue : l'action est globale.
     * Le commentaire lui-même est stocké dans la table comment —
     * cette entrée d'historique sert uniquement à la chronologie du ticket.
     */
    public function logComment(Ticket $ticket, User $user): void
    {
        $history = new TicketHistory();
        $history->setTicket($ticket);
        $history->setUser($user);
        $history->setAction(sprintf('Commentaire ajouté par %s', $user->getName()));

        $this->em->persist($history);
        $this->em->flush();
    }
}
