<?php

/**
 * TicketController — CRUD de l'API REST pour les tickets SupportFlow.
 *
 * Expose 3 routes JSON sous le préfixe /api/tickets :
 *  - GET  /api/tickets         : liste filtrée des tickets
 *  - GET  /api/tickets/{id}    : détail d'un ticket avec commentaires et historique
 *  - POST /api/tickets         : création d'un nouveau ticket
 *
 * Toutes les réponses utilisent JsonResponse avec les codes HTTP sémantiques :
 *  - 200 OK          : ressource retournée avec succès
 *  - 201 Created     : ressource créée avec succès
 *  - 404 Not Found   : ticket introuvable
 *  - 422 Unprocessable Entity : données de formulaire invalides
 *
 * Prototype : pas d'authentification JWT — le créateur est simulé (premier user en BDD).
 */

namespace App\Controller\Api;

use App\Entity\Ticket;
use App\Repository\UserRepository;
use App\Service\TicketHistoryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/tickets')]
class TicketController extends AbstractController
{
    /**
     * EntityManagerInterface : accès direct à Doctrine pour les requêtes personnalisées.
     * TicketHistoryService   : service d'audit — trace toutes les modifications.
     * UserRepository         : récupère l'utilisateur simulé pour le prototype.
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TicketHistoryService $historyService,
        private readonly UserRepository $userRepository
    ) {}

    // =========================================================================
    // GET /api/tickets
    // Liste des tickets avec filtres optionnels en query string
    // =========================================================================

    /**
     * Retourne la liste des tickets, filtrée selon les query params.
     *
     * Query params disponibles (tous optionnels) :
     *  - ?status=En cours     filtre sur le statut exact
     *  - ?priority=Urgente    filtre sur la priorité exacte
     *  - ?search=connexion    filtre sur le titre (LIKE %search%)
     *
     * On utilise un QueryBuilder plutôt que findBy() pour pouvoir chaîner
     * les conditions de manière dynamique selon les paramètres présents.
     *
     * @return JsonResponse tableau JSON de tickets
     */
    #[Route('', name: 'api_tickets_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        // ----- Lecture des query params --------------------------------------
        // $request->query->get() retourne null si le paramètre est absent,
        // ce qui nous permet de distinguer "non fourni" de "fourni vide"
        $status   = $request->query->get('status');
        $priority = $request->query->get('priority');
        $search   = $request->query->get('search');

        // ----- Construction de la requête Doctrine ---------------------------
        // createQueryBuilder('t') crée un alias 't' pour l'entité Ticket.
        // On y ajoute les conditions uniquement si le paramètre est présent et non vide.
        $qb = $this->em->createQueryBuilder()
            ->select('t', 'u')    // Eager-load 'u' (createdBy) pour éviter N+1 queries
            ->from(Ticket::class, 't')
            ->leftJoin('t.createdBy', 'u')  // LEFT JOIN pour garder les tickets sans créateur
            ->orderBy('t.createdAt', 'DESC'); // Plus récents en premier

        // Filtre sur le statut : comparaison exacte (= pas LIKE)
        if (null !== $status && '' !== $status) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $status);
        }

        // Filtre sur la priorité : comparaison exacte
        if (null !== $priority && '' !== $priority) {
            $qb->andWhere('t.priority = :priority')
               ->setParameter('priority', $priority);
        }

        // Filtre recherche textuelle sur le titre : LOWER() pour insensibilité à la casse
        if (null !== $search && '' !== $search) {
            $qb->andWhere('LOWER(t.title) LIKE LOWER(:search)')
               ->setParameter('search', '%' . $search . '%');
        }

        $tickets = $qb->getQuery()->getResult();

        // ----- Sérialisation manuelle ----------------------------------------
        // On n'utilise pas le Serializer Symfony pour avoir un contrôle total
        // sur les champs exposés (ne pas exposer des champs sensibles par inadvertance).
        $data = array_map(fn(Ticket $t) => $this->serializeTicket($t), $tickets);

        return $this->json($data, Response::HTTP_OK);
    }

    // =========================================================================
    // GET /api/tickets/{id}
    // Détail complet d'un ticket : données + commentaires + historique
    // =========================================================================

    /**
     * Retourne le détail complet d'un ticket, ses commentaires et son historique.
     *
     * Les relations comments et history sont lazy-loaded par défaut dans Doctrine.
     * On les charge ici explicitement via getComments() et getHistory() car
     * on a besoin de les sérialiser dans la réponse.
     *
     * @param int $id Identifiant du ticket (injecté depuis le path {id})
     */
    #[Route('/{id}', name: 'api_ticket_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        // Recherche du ticket par son ID primaire
        $ticket = $this->em->getRepository(Ticket::class)->find($id);

        // find() retourne null si aucun enregistrement trouvé
        if (null === $ticket) {
            return $this->json(
                ['error' => sprintf('Ticket #%d introuvable', $id)],
                Response::HTTP_NOT_FOUND
            );
        }

        // ----- Sérialisation du ticket avec ses relations --------------------
        $data = $this->serializeTicket($ticket);

        // Sérialisation des commentaires
        // Pour chaque commentaire : id, content, auteur (nom seulement), date
        $data['comments'] = array_map(function ($comment) {
            return [
                'id'        => $comment->getId(),
                'content'   => $comment->getContent(),
                // L'auteur peut être null si l'utilisateur a été supprimé
                'author'    => $comment->getAuthor()
                    ? ['name' => $comment->getAuthor()->getName()]
                    : null,
                'createdAt' => $comment->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            ];
        }, $ticket->getComments()->toArray());

        // Sérialisation de l'historique (journal d'audit)
        // Trié par date croissante pour une lecture chronologique dans l'interface
        $history = $ticket->getHistory()->toArray();
        usort($history, fn($a, $b) => $a->getCreatedAt() <=> $b->getCreatedAt());

        $data['history'] = array_map(function ($entry) {
            return [
                'id'        => $entry->getId(),
                'action'    => $entry->getAction(),
                'fieldName' => $entry->getFieldName(),
                'oldValue'  => $entry->getOldValue(),
                'newValue'  => $entry->getNewValue(),
                'createdAt' => $entry->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                // L'utilisateur peut être null (action système ou user supprimé)
                'user'      => $entry->getUser()
                    ? ['name' => $entry->getUser()->getName()]
                    : null,
            ];
        }, $history);

        return $this->json($data, Response::HTTP_OK);
    }

    // =========================================================================
    // POST /api/tickets
    // Création d'un nouveau ticket
    // =========================================================================

    /**
     * Crée un nouveau ticket à partir du body JSON de la requête.
     *
     * Format du body JSON attendu :
     * {
     *   "title":       "Erreur 500 sur la page d'accueil",
     *   "description": "Description détaillée du problème...",
     *   "category":    "Bug",
     *   "priority":    "Haute"
     * }
     *
     * Validation :
     *  - title       : obligatoire, non vide
     *  - description : obligatoire, non vide
     *  - category    : doit être dans Ticket::CATEGORIES
     *  - priority    : doit être dans Ticket::PRIORITIES
     *
     * Codes de retour :
     *  - 201 Created             : ticket créé avec succès
     *  - 400 Bad Request         : JSON invalide
     *  - 422 Unprocessable Entity: données invalides (erreurs de validation)
     */
    #[Route('', name: 'api_ticket_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        // ----- Décodage du body JSON -----------------------------------------
        $data = json_decode($request->getContent(), associative: true);

        if (!is_array($data)) {
            return $this->json(
                ['error' => 'Corps JSON invalide ou vide'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // ----- Validation des champs ----------------------------------------
        // On collecte toutes les erreurs avant de répondre (pas de fail-fast)
        // pour que le frontend puisse afficher tous les messages en une seule requête.
        $errors = [];

        $title       = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $category    = trim($data['category'] ?? '');
        $priority    = trim($data['priority'] ?? '');

        if ('' === $title) {
            $errors['title'] = 'Le titre est obligatoire.';
        }

        if ('' === $description) {
            $errors['description'] = 'La description est obligatoire.';
        }

        // in_array strict : vérifie que la valeur est dans la liste des valeurs autorisées
        if ('' === $category) {
            $errors['category'] = 'La catégorie est obligatoire.';
        } elseif (!in_array($category, Ticket::CATEGORIES, strict: true)) {
            $errors['category'] = sprintf(
                'Catégorie invalide. Valeurs acceptées : %s.',
                implode(', ', Ticket::CATEGORIES)
            );
        }

        if ('' === $priority) {
            $errors['priority'] = 'La priorité est obligatoire.';
        } elseif (!in_array($priority, Ticket::PRIORITIES, strict: true)) {
            $errors['priority'] = sprintf(
                'Priorité invalide. Valeurs acceptées : %s.',
                implode(', ', Ticket::PRIORITIES)
            );
        }

        // Si des erreurs sont présentes : réponse 422 avec le détail des erreurs
        if (!empty($errors)) {
            return $this->json(
                ['errors' => $errors],
                Response::HTTP_UNPROCESSABLE_ENTITY  // 422
            );
        }

        // ----- Utilisateur simulé (prototype sans authentification) ----------
        // Pour le prototype, on récupère le premier utilisateur en base.
        // À remplacer par $this->getUser() une fois JWT configuré.
        $author = $this->userRepository->findOneBy([]);

        if (null === $author) {
            // Cas de sécurité : si la base est vide (fixtures non chargées)
            return $this->json(
                ['error' => 'Aucun utilisateur en base. Chargez les fixtures.'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        // ----- Création du ticket -------------------------------------------
        $ticket = new Ticket();
        $ticket->setTitle($title);
        $ticket->setDescription($description);
        $ticket->setCategory($category);
        $ticket->setPriority($priority);
        // Le statut initial est toujours "Nouveau" — c'est une règle métier
        $ticket->setStatus('Nouveau');
        $ticket->setCreatedBy($author);

        // persist() met le ticket en file d'attente pour INSERT
        $this->em->persist($ticket);
        // flush() déclenche l'INSERT SQL et génère l'ID auto-incrémenté
        // On flush avant logCreation() pour que le ticket ait un ID valide
        // (nécessaire pour la FK dans ticket_history)
        $this->em->flush();

        // ----- Enregistrement dans l'historique ------------------------------
        // Appel au service d'audit après le flush du ticket (ID disponible)
        $this->historyService->logCreation($ticket, $author);

        // ----- Réponse 201 ---------------------------------------------------
        // Retourne la représentation complète du ticket nouvellement créé.
        // Le client peut utiliser l'ID retourné pour naviguer vers /api/tickets/{id}.
        return $this->json(
            $this->serializeTicket($ticket),
            Response::HTTP_CREATED  // 201
        );
    }

    // =========================================================================
    // MÉTHODE PRIVÉE DE SÉRIALISATION
    // Centralise le formatage d'un Ticket en tableau PHP → JSON.
    // Utilisée par list(), show() et create() pour garantir un format cohérent.
    // =========================================================================

    /**
     * Sérialise un Ticket en tableau associatif prêt pour json_encode().
     *
     * Les dates sont formatées en ISO 8601 (DateTimeInterface::ATOM) :
     * format universel, lisible par JavaScript (new Date("2024-01-01T10:00:00+00:00"))
     * et conforme aux standards API REST.
     *
     * Le ? dans ?->format() est le null-safe operator PHP 8 :
     * évite un crash si la date est null (ne devrait pas arriver en production
     * avec les lifecycle callbacks, mais protège les fixtures avec dates forcées).
     */
    private function serializeTicket(Ticket $ticket): array
    {
        return [
            'id'          => $ticket->getId(),
            'title'       => $ticket->getTitle(),
            'description' => $ticket->getDescription(),
            'category'    => $ticket->getCategory(),
            'priority'    => $ticket->getPriority(),
            'status'      => $ticket->getStatus(),
            // createdBy peut être null si l'utilisateur créateur a été supprimé
            'createdBy'   => $ticket->getCreatedBy() ? [
                'id'   => $ticket->getCreatedBy()->getId(),
                'name' => $ticket->getCreatedBy()->getName(),
            ] : null,
            // Format ISO 8601 pour compatibilité JavaScript et standards REST
            'createdAt'   => $ticket->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt'   => $ticket->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
