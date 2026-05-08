<?php

/**
 * Entité TicketHistory — journal d'audit des modifications d'un ticket.
 *
 * Chaque fois qu'un ticket est modifié (création, changement de statut,
 * de priorité, de catégorie, réassignation…), une entrée TicketHistory
 * est créée pour tracer QUI a fait QUOI et QUAND.
 *
 * Ce mécanisme d'audit trail permet :
 *  - la transparence envers les utilisateurs (fil d'activité sur le ticket)
 *  - la conformité (traçabilité des actions)
 *  - le débogage (comprendre l'historique d'un ticket problématique)
 *
 * Exemples d'entrées générées :
 *  - action: "Création",  fieldName: null,     oldValue: null,          newValue: null
 *  - action: "Modification", fieldName: "status", oldValue: "Nouveau",  newValue: "En cours"
 *  - action: "Modification", fieldName: "priority", oldValue: "Basse",  newValue: "Urgente"
 */

namespace App\Entity;

use App\Repository\TicketHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TicketHistoryRepository::class)]
class TicketHistory
{
    // -------------------------------------------------------------------------
    // CHAMPS
    // -------------------------------------------------------------------------

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Type d'action effectuée.
     * Exemples : "Création", "Modification", "Commentaire ajouté", "Fermeture".
     * Stocké en VARCHAR(100) pour laisser de la flexibilité applicative.
     */
    #[ORM\Column(length: 100)]
    private ?string $action = null;

    /**
     * Nom du champ modifié (null si l'action ne concerne pas un champ spécifique).
     * Exemples : "status", "priority", "assignedTo".
     * nullable: true → peut être absent pour des actions globales (création, fermeture).
     */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $fieldName = null;

    /**
     * Valeur du champ AVANT la modification (null si c'est une création).
     * Stocké en VARCHAR(255) : on s'attend à des valeurs courtes (statuts, priorités…).
     * nullable: true → absent lors d'une création ou d'une action sans comparaison.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $oldValue = null;

    /**
     * Valeur du champ APRÈS la modification (null si c'est une suppression de valeur).
     * nullable: true → logiquement symétrique à oldValue.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $newValue = null;

    /**
     * Horodatage de l'entrée d'historique.
     * Initialisé dans le constructeur pour garantir qu'il est toujours présent.
     */
    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    // -------------------------------------------------------------------------
    // RELATIONS
    // -------------------------------------------------------------------------

    /**
     * Le ticket concerné par cette entrée d'historique.
     *
     * nullable: false → une entrée d'historique doit toujours référencer un ticket.
     * inversedBy: 'history' ← correspond à la propriété $history dans Ticket.
     */
    #[ORM\ManyToOne(inversedBy: 'history')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Ticket $ticket = null;

    /**
     * L'utilisateur qui a effectué l'action tracée.
     *
     * nullable: true → permet de conserver l'historique si l'utilisateur est supprimé.
     * Peut aussi être null pour les actions système automatiques (ex: escalade planifiée).
     * inversedBy: 'histories' ← correspond à la propriété $histories dans User.
     */
    #[ORM\ManyToOne(inversedBy: 'histories')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    // -------------------------------------------------------------------------
    // CONSTRUCTEUR
    // -------------------------------------------------------------------------

    public function __construct()
    {
        // Horodatage automatique à la création de l'entrée d'historique
        $this->createdAt = new \DateTime();
    }

    // -------------------------------------------------------------------------
    // GETTERS / SETTERS
    // -------------------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getFieldName(): ?string
    {
        return $this->fieldName;
    }

    public function setFieldName(?string $fieldName): static
    {
        $this->fieldName = $fieldName;
        return $this;
    }

    public function getOldValue(): ?string
    {
        return $this->oldValue;
    }

    public function setOldValue(?string $oldValue): static
    {
        $this->oldValue = $oldValue;
        return $this;
    }

    public function getNewValue(): ?string
    {
        return $this->newValue;
    }

    public function setNewValue(?string $newValue): static
    {
        $this->newValue = $newValue;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getTicket(): ?Ticket
    {
        return $this->ticket;
    }

    public function setTicket(?Ticket $ticket): static
    {
        $this->ticket = $ticket;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }
}
