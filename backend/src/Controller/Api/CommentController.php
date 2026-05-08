<?php

/**
 * CommentController — API REST pour les commentaires de tickets SupportFlow.
 *
 * Expose 1 route sous le préfixe /api/tickets/{id}/comments :
 *  - POST /api/tickets/{id}/comments : ajoute un commentaire à un ticket
 *
 * Un commentaire est toujours associé à un ticket existant et à un auteur.
 * Ajouter un commentaire :
 *  1. Met à jour updatedAt du ticket (signale une activité récente)
 *  2. Enregistre une entrée dans ticket_history (journal d'audit)
 *
 * Prototype : l'auteur est simulé (premier user en BDD).
 */

namespace App\Controller\Api;

use App\Entity\Comment;
use App\Entity\Ticket;
use App\Repository\UserRepository;
use App\Service\TicketHistoryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// Préfixe partagé avec les routes tickets : les commentaires sont des sous-ressources
// de tickets (design REST : /api/tickets/{id}/comments)
#[Route('/api/tickets')]
class CommentController extends AbstractController
{
    /**
     * EntityManagerInterface : persist + flush des entités Comment et Ticket.
     * TicketHistoryService   : enregistre l'ajout de commentaire dans l'audit trail.
     * UserRepository         : récupère l'auteur simulé pour le prototype.
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TicketHistoryService $historyService,
        private readonly UserRepository $userRepository
    ) {}

    // =========================================================================
    // POST /api/tickets/{id}/comments
    // Ajout d'un commentaire à un ticket existant
    // =========================================================================

    /**
     * Crée un commentaire sur le ticket identifié par {id}.
     *
     * Format du body JSON :
     * { "content": "Texte du commentaire..." }
     *
     * Effets de bord :
     *  - Met à jour ticket.updatedAt (visible dans la liste des tickets)
     *  - Crée une entrée ticket_history "Commentaire ajouté par [nom]"
     *
     * Codes de retour :
     *  - 201 Created              : commentaire créé, retourné en JSON
     *  - 400 Bad Request          : JSON invalide ou manquant
     *  - 404 Not Found            : ticket parent introuvable
     *  - 422 Unprocessable Entity : content vide ou manquant
     */
    #[Route('/{id}/comments', name: 'api_comment_create', methods: ['POST'])]
    public function create(int $id, Request $request): JsonResponse
    {
        // ----- Récupération du ticket parent ---------------------------------
        // Un commentaire ne peut pas exister sans ticket : vérification en premier.
        $ticket = $this->em->getRepository(Ticket::class)->find($id);

        if (null === $ticket) {
            return $this->json(
                ['error' => sprintf('Ticket #%d introuvable', $id)],
                Response::HTTP_NOT_FOUND
            );
        }

        // ----- Décodage du body JSON -----------------------------------------
        $data = json_decode($request->getContent(), associative: true);

        if (!is_array($data)) {
            return $this->json(
                ['error' => 'Corps JSON invalide ou vide'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // ----- Validation du contenu -----------------------------------------
        // trim() supprime les espaces et sauts de ligne en début/fin :
        // un commentaire composé uniquement d'espaces est considéré comme vide.
        $content = trim($data['content'] ?? '');

        if ('' === $content) {
            return $this->json(
                ['errors' => ['content' => 'Le contenu du commentaire est obligatoire.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // ----- Auteur simulé (prototype) -------------------------------------
        // À remplacer par $this->getUser() une fois JWT configuré.
        $author = $this->getPrototypeUser();

        // ----- Création du commentaire ---------------------------------------
        $comment = new Comment();
        $comment->setContent($content);
        $comment->setTicket($ticket);
        $comment->setAuthor($author);
        // createdAt est initialisé dans le constructeur de Comment (new \DateTime())

        $this->em->persist($comment);

        // ----- Mise à jour du ticket parent ----------------------------------
        // Mettre à jour updatedAt signale que le ticket a eu une activité récente.
        // Cela permet de trier les tickets par "dernière activité" dans l'interface.
        // Note : flush() déclenchera aussi #[PreUpdate] sur le ticket si on le modifie,
        // mais setUpdatedAt() suffit ici car on ne change pas d'autre champ du ticket.
        $ticket->setUpdatedAt(new \DateTime());

        // Un seul flush pour les deux entités (Comment + Ticket) :
        // garantit l'atomicité — pas de commentaire sans mise à jour du ticket.
        $this->em->flush();

        // ----- Enregistrement dans l'historique du ticket --------------------
        // Appelé après le flush pour que le commentaire ait un ID valide en BDD.
        $this->historyService->logComment($ticket, $author);

        // ----- Réponse 201 ---------------------------------------------------
        // Retourne la représentation du commentaire créé.
        // L'auteur est inclus avec id ET name pour permettre l'affichage immédiat
        // côté frontend sans requête supplémentaire.
        return $this->json([
            'id'        => $comment->getId(),
            'content'   => $comment->getContent(),
            'author'    => [
                'id'   => $author->getId(),
                'name' => $author->getName(),
            ],
            'createdAt' => $comment->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ], Response::HTTP_CREATED);
    }

    // =========================================================================
    // MÉTHODE PRIVÉE UTILITAIRE
    // =========================================================================

    /**
     * Retourne l'utilisateur simulé pour le prototype.
     * Même logique que dans TicketController — dupliquée ici pour l'instant,
     * à extraire dans un trait ou service dédié une fois l'auth JWT en place.
     */
    private function getPrototypeUser(): \App\Entity\User
    {
        $user = $this->userRepository->findOneBy([]);

        if (null === $user) {
            throw new \RuntimeException('Aucun utilisateur en base. Lancez : php bin/console doctrine:fixtures:load');
        }

        return $user;
    }
}
