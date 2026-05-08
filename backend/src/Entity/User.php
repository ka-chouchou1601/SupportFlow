<?php

/**
 * Entité User — représente un utilisateur de l'application SupportFlow.
 *
 * Cette classe implémente deux interfaces Symfony Security :
 *  - UserInterface              : contrat de base pour tout utilisateur (rôles, identifiant, effacement des credentials)
 *  - PasswordAuthenticatedUserInterface : indique que l'authentification repose sur un mot de passe hashé
 *
 * Doctrine ORM mappe automatiquement cette classe vers la table `user` en base de données
 * grâce aux attributs PHP 8 (#[ORM\...]).
 */

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

// #[ORM\Entity] : lie cette classe à Doctrine et pointe vers son repository personnalisé
// #[ORM\Table] : nom explicite de la table SQL générée (évite les collisions avec des mots réservés)
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    // -------------------------------------------------------------------------
    // CHAMPS DE BASE
    // -------------------------------------------------------------------------

    /**
     * Identifiant auto-incrémenté — clé primaire générée par la base de données.
     * GeneratedValue::IDENTITY utilise l'AUTO_INCREMENT MySQL.
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Email de l'utilisateur — sert d'identifiant de connexion (getUserIdentifier).
     * unique: true garantit l'unicité au niveau SQL (contrainte UNIQUE).
     */
    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    /**
     * Mot de passe hashé par Symfony (argon2id par défaut).
     * Ne jamais stocker le mot de passe en clair.
     */
    #[ORM\Column]
    private ?string $password = null;

    /**
     * Nom d'affichage de l'utilisateur (prénom + nom ou pseudo).
     */
    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * Rôle unique de l'utilisateur, stocké comme chaîne de caractères.
     * Valeurs possibles : ROLE_USER, ROLE_ADMIN, ROLE_AGENT.
     * Doctrine stocke la valeur directement en VARCHAR dans la colonne `role`.
     *
     * Note : UserInterface::getRoles() retourne un tableau ; cette propriété
     * stocke le rôle principal, ROLE_USER est toujours ajouté automatiquement.
     */
    #[ORM\Column(length: 50)]
    private string $role = 'ROLE_USER';

    /**
     * Date et heure de création du compte — renseignée automatiquement
     * dans le constructeur (pas besoin d'un lifecycle callback pour ce cas simple).
     */
    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    // -------------------------------------------------------------------------
    // RELATIONS
    // -------------------------------------------------------------------------

    /**
     * Collection de tous les tickets créés par cet utilisateur.
     *
     * OneToMany : un utilisateur → plusieurs tickets.
     * mappedBy: 'createdBy' correspond à la propriété $createdBy dans Ticket.
     * cascade: ['persist'] : persister un User persiste aussi ses nouveaux tickets.
     * orphanRemoval: false : supprimer un user ne supprime PAS ses tickets (préférer une politique de soft-delete ou de réassignation).
     */
    #[ORM\OneToMany(mappedBy: 'createdBy', targetEntity: Ticket::class, cascade: ['persist'])]
    private Collection $tickets;

    /**
     * Collection de tous les commentaires rédigés par cet utilisateur.
     *
     * mappedBy: 'author' correspond à la propriété $author dans Comment.
     */
    #[ORM\OneToMany(mappedBy: 'author', targetEntity: Comment::class, cascade: ['persist'])]
    private Collection $comments;

    /**
     * Collection des entrées d'historique générées par les actions de cet utilisateur.
     *
     * mappedBy: 'user' correspond à la propriété $user dans TicketHistory.
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: TicketHistory::class, cascade: ['persist'])]
    private Collection $histories;

    // -------------------------------------------------------------------------
    // CONSTRUCTEUR
    // -------------------------------------------------------------------------

    public function __construct()
    {
        // Initialise les collections Doctrine avec ArrayCollection (implémentation de base)
        // Cela évite les erreurs "null is not iterable" si on accède aux relations
        // avant la persistence de l'entité
        $this->tickets   = new ArrayCollection();
        $this->comments  = new ArrayCollection();
        $this->histories = new ArrayCollection();

        // Date de création automatique à l'instanciation de l'objet
        $this->createdAt = new \DateTime();
    }

    // -------------------------------------------------------------------------
    // IMPLÉMENTATION UserInterface
    // -------------------------------------------------------------------------

    /**
     * Retourne l'identifiant unique utilisé par le système d'authentification Symfony.
     * Symfony Security utilise cet identifiant pour récupérer l'utilisateur en session
     * et pour les tokens de sécurité.
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * Retourne la liste des rôles de l'utilisateur sous forme de tableau.
     *
     * Symfony Security attend un tableau de chaînes commençant par "ROLE_".
     * On s'assure que ROLE_USER est toujours présent (convention Symfony) :
     * cela permet aux vérifications de base (IS_AUTHENTICATED_FULLY) de fonctionner
     * même si le rôle stocké est ROLE_ADMIN.
     *
     * array_unique évite les doublons si $role vaut déjà 'ROLE_USER'.
     */
    public function getRoles(): array
    {
        return array_unique([$this->role, 'ROLE_USER']);
    }

    /**
     * Efface les données d'authentification sensibles temporaires après connexion.
     * Utilisé par le système de session Symfony pour ne pas persister
     * des informations sensibles (mot de passe en clair, token temporaire…).
     * Dans notre cas, tout est hashé donc rien à effacer.
     */
    public function eraseCredentials(): void
    {
        // Pas de credential temporaire dans cette implémentation
    }

    // -------------------------------------------------------------------------
    // IMPLÉMENTATION PasswordAuthenticatedUserInterface
    // -------------------------------------------------------------------------

    /**
     * Retourne le mot de passe hashé stocké en base.
     * Utilisé par le composant PasswordHasher pour vérifier le mot de passe saisi.
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    // -------------------------------------------------------------------------
    // GETTERS / SETTERS
    // -------------------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;
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

    // -------------------------------------------------------------------------
    // GESTION DES RELATIONS
    // -------------------------------------------------------------------------

    /** @return Collection<int, Ticket> */
    public function getTickets(): Collection
    {
        return $this->tickets;
    }

    public function addTicket(Ticket $ticket): static
    {
        if (!$this->tickets->contains($ticket)) {
            $this->tickets->add($ticket);
            // Synchronise le côté inverse : le ticket connaît son créateur
            $ticket->setCreatedBy($this);
        }
        return $this;
    }

    public function removeTicket(Ticket $ticket): static
    {
        if ($this->tickets->removeElement($ticket)) {
            // Rompt la relation côté Ticket si ce User en était le propriétaire
            if ($ticket->getCreatedBy() === $this) {
                $ticket->setCreatedBy(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, Comment> */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setAuthor($this);
        }
        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getAuthor() === $this) {
                $comment->setAuthor(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, TicketHistory> */
    public function getHistories(): Collection
    {
        return $this->histories;
    }

    public function addHistory(TicketHistory $history): static
    {
        if (!$this->histories->contains($history)) {
            $this->histories->add($history);
            $history->setUser($this);
        }
        return $this;
    }

    public function removeHistory(TicketHistory $history): static
    {
        if ($this->histories->removeElement($history)) {
            if ($history->getUser() === $this) {
                $history->setUser(null);
            }
        }
        return $this;
    }
}
