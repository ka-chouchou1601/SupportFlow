<?php

/**
 * Entité Comment — représente un commentaire posté sur un ticket.
 *
 * Un commentaire permet aux utilisateurs et aux agents de communiquer
 * autour d'un ticket : poser des questions, fournir des mises à jour,
 * ou documenter les étapes de résolution.
 *
 * Relations :
 *  - ManyToOne → Ticket : un commentaire appartient à un et un seul ticket
 *  - ManyToOne → User ($author) : un commentaire est rédigé par un utilisateur
 */

namespace App\Entity;

use App\Repository\CommentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommentRepository::class)]
class Comment
{
    // -------------------------------------------------------------------------
    // CHAMPS
    // -------------------------------------------------------------------------

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Contenu textuel du commentaire.
     * Type 'text' (TEXT MySQL) pour supporter des messages longs (mise en page,
     * logs copiés-collés, étapes de reproduction, etc.)
     */
    #[ORM\Column(type: 'text')]
    private ?string $content = null;

    /**
     * Horodatage de création du commentaire.
     * Initialisé dans le constructeur pour garantir qu'il est toujours renseigné.
     */
    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    // -------------------------------------------------------------------------
    // RELATIONS
    // -------------------------------------------------------------------------

    /**
     * Le ticket auquel ce commentaire est rattaché.
     *
     * ManyToOne : plusieurs commentaires → un seul ticket.
     * nullable: false → un commentaire ne peut pas exister sans ticket
     * (contrainte d'intégrité : empêche les commentaires orphelins en base).
     *
     * inversedBy: 'comments' ← correspond à la propriété $comments dans Ticket.
     */
    #[ORM\ManyToOne(inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Ticket $ticket = null;

    /**
     * L'auteur du commentaire (l'utilisateur qui l'a rédigé).
     *
     * Propriété nommée $author (et non $user) pour plus de clarté sémantique :
     * "l'auteur du commentaire" est plus explicite que "l'utilisateur du commentaire".
     *
     * nullable: true → permet de conserver le commentaire si l'utilisateur est supprimé.
     *
     * inversedBy: 'comments' ← correspond à la propriété $comments dans User.
     */
    #[ORM\ManyToOne(inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $author = null;

    // -------------------------------------------------------------------------
    // CONSTRUCTEUR
    // -------------------------------------------------------------------------

    public function __construct()
    {
        // Horodatage automatique à la création de l'objet (avant persist Doctrine)
        $this->createdAt = new \DateTime();
    }

    // -------------------------------------------------------------------------
    // GETTERS / SETTERS
    // -------------------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
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

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;
        return $this;
    }
}
