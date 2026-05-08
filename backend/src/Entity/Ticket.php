<?php

/**
 * Entité Ticket — cœur métier de SupportFlow.
 *
 * Un ticket représente une demande de support : bug signalé, amélioration demandée,
 * incident, etc. Il est créé par un utilisateur, passe par différents statuts,
 * et accumule des commentaires et un historique des modifications.
 *
 * Valeurs autorisées (à valider côté formulaire/API) :
 *  - category : Bug | Demande utilisateur | Amélioration | Incident | Autre
 *  - priority  : Basse | Moyenne | Haute | Urgente
 *  - status    : Nouveau | En cours | Résolu | Fermé
 */

namespace App\Entity;

use App\Repository\TicketRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
// HasLifecycleCallbacks : active les méthodes annotées #[PrePersist] / #[PreUpdate]
// pour mettre à jour automatiquement createdAt et updatedAt
#[ORM\HasLifecycleCallbacks]
class Ticket
{
    // -------------------------------------------------------------------------
    // CONSTANTES MÉTIER
    // Ces constantes centralisent les valeurs autorisées pour category, priority
    // et status. Les utiliser dans les formulaires, validateurs et fixtures
    // garantit la cohérence et facilite les refactorisations futures.
    // -------------------------------------------------------------------------

    /** Catégories de tickets disponibles */
    public const CATEGORIES = [
        'Bug',
        'Demande utilisateur',
        'Amélioration',
        'Incident',
        'Autre',
    ];

    /** Niveaux de priorité du plus bas au plus critique */
    public const PRIORITIES = [
        'Basse',
        'Moyenne',
        'Haute',
        'Urgente',
    ];

    /** Cycle de vie d'un ticket */
    public const STATUSES = [
        'Nouveau',
        'En cours',
        'Résolu',
        'Fermé',
    ];

    // -------------------------------------------------------------------------
    // CHAMPS
    // -------------------------------------------------------------------------

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Titre court et descriptif du ticket (obligatoire, max 255 caractères).
     * Affiché dans les listes et les notifications.
     */
    #[ORM\Column(length: 255)]
    private ?string $title = null;

    /**
     * Description détaillée du problème ou de la demande.
     * Type 'text' = TEXT MySQL (jusqu'à ~65 000 caractères), adapté aux descriptions longues.
     */
    #[ORM\Column(type: 'text')]
    private ?string $description = null;

    /**
     * Catégorie du ticket. Doit être l'une des valeurs de self::CATEGORIES.
     * Stockée en VARCHAR(100) pour rester lisible dans la base de données.
     */
    #[ORM\Column(length: 100)]
    private ?string $category = null;

    /**
     * Priorité du ticket. Doit être l'une des valeurs de self::PRIORITIES.
     * Détermine l'ordre de traitement côté agents support.
     */
    #[ORM\Column(length: 50)]
    private ?string $priority = null;

    /**
     * Statut courant du ticket dans son cycle de vie.
     * Doit être l'une des valeurs de self::STATUSES.
     * Chaque changement de statut génère une entrée dans TicketHistory.
     */
    #[ORM\Column(length: 50)]
    private ?string $status = null;

    /**
     * Date/heure de création du ticket — renseignée automatiquement par
     * le lifecycle callback #[ORM\PrePersist].
     */
    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    /**
     * Date/heure de la dernière modification — mise à jour automatiquement
     * par le lifecycle callback #[ORM\PreUpdate].
     * Utile pour trier les tickets par activité récente.
     */
    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    // -------------------------------------------------------------------------
    // RELATIONS
    // -------------------------------------------------------------------------

    /**
     * L'utilisateur qui a créé le ticket.
     *
     * ManyToOne : plusieurs tickets → un seul créateur.
     * nullable: true permet de conserver les tickets si l'utilisateur est supprimé
     * (on lui assigne null plutôt que de supprimer le ticket — à gérer applicativement).
     *
     * inversedBy: 'tickets' indique que User::$tickets est le côté inverse de cette relation.
     */
    #[ORM\ManyToOne(inversedBy: 'tickets')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $createdBy = null;

    /**
     * Commentaires attachés à ce ticket.
     *
     * OneToMany : un ticket → plusieurs commentaires.
     * cascade: ['persist', 'remove'] : la suppression d'un ticket supprime ses commentaires.
     * orphanRemoval: true : un commentaire retiré de la collection est automatiquement supprimé en BDD.
     */
    #[ORM\OneToMany(mappedBy: 'ticket', targetEntity: Comment::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $comments;

    /**
     * Historique des modifications de ce ticket.
     *
     * Même configuration que les commentaires : l'historique est lié au ticket,
     * et sa suppression emporte l'historique.
     */
    #[ORM\OneToMany(mappedBy: 'ticket', targetEntity: TicketHistory::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $history;

    // -------------------------------------------------------------------------
    // CONSTRUCTEUR
    // -------------------------------------------------------------------------

    public function __construct()
    {
        $this->comments = new ArrayCollection();
        $this->history  = new ArrayCollection();
        // Le statut par défaut d'un nouveau ticket est "Nouveau"
        $this->status   = 'Nouveau';
    }

    // -------------------------------------------------------------------------
    // LIFECYCLE CALLBACKS
    // Ces méthodes sont appelées automatiquement par Doctrine lors des
    // opérations de persistance, sans avoir besoin d'un EventSubscriber externe.
    // -------------------------------------------------------------------------

    /**
     * #[PrePersist] : appelé juste avant le premier INSERT en base.
     * Initialise createdAt ET updatedAt (les deux sont requis dès la création).
     */
    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    /**
     * #[PreUpdate] : appelé juste avant chaque UPDATE en base.
     * Actualise updatedAt à chaque modification du ticket.
     */
    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    // -------------------------------------------------------------------------
    // GETTERS / SETTERS
    // -------------------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getPriority(): ?string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
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

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    // -------------------------------------------------------------------------
    // GESTION DES RELATIONS
    // -------------------------------------------------------------------------

    /** @return Collection<int, Comment> */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setTicket($this);
        }
        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getTicket() === $this) {
                $comment->setTicket(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, TicketHistory> */
    public function getHistory(): Collection
    {
        return $this->history;
    }

    public function addHistory(TicketHistory $history): static
    {
        if (!$this->history->contains($history)) {
            $this->history->add($history);
            $history->setTicket($this);
        }
        return $this;
    }

    public function removeHistory(TicketHistory $history): static
    {
        if ($this->history->removeElement($history)) {
            if ($history->getTicket() === $this) {
                $history->setTicket(null);
            }
        }
        return $this;
    }
}
