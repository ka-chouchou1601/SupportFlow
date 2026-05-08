<?php

/**
 * TicketController — CRUD et gestion du cycle de vie des tickets SupportFlow.
 *
 * Expose 5 routes JSON sous le préfixe /api/tickets :
 *  - GET   /api/tickets                  : liste filtrée des tickets
 *  - GET   /api/tickets/{id}             : détail complet (commentaires + historique)
 *  - POST  /api/tickets                  : création d'un nouveau ticket
 *  - PATCH /api/tickets/{id}/status      : changement de statut (transitions validées)
 *  - PATCH /api/tickets/{id}/priority    : changement de priorité
 *
 * Codes HTTP utilisés :
 *  - 200 OK                   : opération réussie (lecture ou mise à jour)
 *  - 201 Created              : ressource créée avec succès
 *  - 400 Bad Request          : JSON invalide ou manquant
 *  - 404 Not Found            : ticket introuvable
 *  - 422 Unprocessable Entity : données invalides ou transition interdite
 *
 * Prototype : pas d'authentification JWT — l'utilisateur est simulé (premier user en BDD).
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
     * Table des transitions de statut autorisées.
     *
     * Clé   = statut SOURCE (actuel du ticket)
     * Valeur = tableau des statuts CIBLES autorisés depuis ce statut
     *
     * Règles métier encodées :
     *  - Un ticket "Fermé" est terminal : aucune transition n'en part
     *  - On peut rouvrir un ticket "Résolu" (→ En cours) s'il n'est pas complètement résolu
     *  - On ne peut pas passer directement de "Nouveau" à "Résolu" ou "Fermé"
     *    (forçage du passage par "En cours" pour garantir qu'un agent a traité le ticket)
     */
    private const ALLOWED_TRANSITIONS = [
        'Nouveau'  => ['En cours'],
        'En cours' => ['Résolu', 'Nouveau'],
        'Résolu'   => ['En cours', 'Fermé'],
        'Fermé'    => [],  // statut terminal — aucune sortie possible
    ];

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
        // Délégué au helper getPrototypeUser() pour éviter la duplication.
        // À remplacer par $this->getUser() une fois JWT configuré.
        $author = $this->getPrototypeUser();

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
    // PATCH /api/tickets/{id}/status
    // Changement de statut avec validation des transitions métier
    // =========================================================================

    /**
     * Met à jour le statut d'un ticket en respectant les transitions autorisées.
     *
     * Format du body JSON :
     * { "status": "En cours" }
     *
     * Le graphe des transitions est défini dans ALLOWED_TRANSITIONS.
     * Tout saut non autorisé (ex: "Nouveau" → "Fermé") retourne un 422.
     *
     * La mise à jour de updatedAt est gérée doublement :
     *  - explicitement via setUpdatedAt() (intention claire)
     *  - automatiquement via le lifecycle callback #[PreUpdate] de l'entité
     *
     * Codes de retour :
     *  - 200 OK                   : statut mis à jour, ticket retourné
     *  - 400 Bad Request          : JSON invalide
     *  - 404 Not Found            : ticket introuvable
     *  - 422 Unprocessable Entity : statut invalide ou transition interdite
     */
    #[Route('/{id}/status', name: 'api_ticket_update_status', methods: ['PATCH'])]
    public function updateStatus(int $id, Request $request): JsonResponse
    {
        // ----- Récupération du ticket ----------------------------------------
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

        $newStatus = trim($data['status'] ?? '');

        // ----- Validation du statut cible ------------------------------------
        if ('' === $newStatus) {
            return $this->json(
                ['errors' => ['status' => 'Le statut est obligatoire.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (!in_array($newStatus, Ticket::STATUSES, strict: true)) {
            return $this->json(
                ['errors' => ['status' => sprintf(
                    'Statut invalide. Valeurs acceptées : %s.',
                    implode(', ', Ticket::STATUSES)
                )]],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // ----- Validation de la transition -----------------------------------
        $currentStatus = $ticket->getStatus();

        // Récupère les cibles autorisées depuis le statut actuel.
        // ?? [] sécurise le cas où currentStatus ne serait pas dans ALLOWED_TRANSITIONS
        // (données corrompues en BDD) : aucune transition ne serait alors autorisée.
        $allowedTargets = self::ALLOWED_TRANSITIONS[$currentStatus] ?? [];

        if ($currentStatus === $newStatus) {
            // Aucune transition nécessaire : le statut est déjà le bon
            // On retourne le ticket tel quel sans modifier ni l'historique.
            return $this->json($this->serializeTicket($ticket), Response::HTTP_OK);
        }

        if (!in_array($newStatus, $allowedTargets, strict: true)) {
            // Transition explicitement interdite par les règles métier
            return $this->json(
                ['error' => sprintf('Transition non autorisée : %s → %s', $currentStatus, $newStatus)],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // ----- Application de la transition ----------------------------------
        $oldStatus = $currentStatus;

        $ticket->setStatus($newStatus);
        // Mise à jour explicite de updatedAt — le callback #[PreUpdate] la fera
        // aussi, mais l'appel ici rend l'intention lisible dans le code métier.
        $ticket->setUpdatedAt(new \DateTime());

        // flush() déclenche le UPDATE SQL + le lifecycle callback #[PreUpdate]
        $this->em->flush();

        // Enregistrement dans l'historique APRÈS le flush pour s'assurer que
        // le ticket est bien persisté avant d'écrire la référence FK dans ticket_history
        $this->historyService->logStatusChange($ticket, $this->getPrototypeUser(), $oldStatus, $newStatus);

        return $this->json($this->serializeTicket($ticket), Response::HTTP_OK);
    }

    // =========================================================================
    // PATCH /api/tickets/{id}/priority
    // Changement de priorité (pas de contrainte de transition)
    // =========================================================================

    /**
     * Met à jour la priorité d'un ticket.
     *
     * Contrairement au statut, la priorité n'a pas de graphe de transitions :
     * on peut passer d'une priorité à n'importe quelle autre à tout moment.
     * C'est une décision intentionnelle (la priorité est un jugement éditorial,
     * pas un état dans un workflow).
     *
     * Format du body JSON :
     * { "priority": "Urgente" }
     *
     * Codes de retour :
     *  - 200 OK                   : priorité mise à jour, ticket retourné
     *  - 400 Bad Request          : JSON invalide
     *  - 404 Not Found            : ticket introuvable
     *  - 422 Unprocessable Entity : priorité invalide
     */
    #[Route('/{id}/priority', name: 'api_ticket_update_priority', methods: ['PATCH'])]
    public function updatePriority(int $id, Request $request): JsonResponse
    {
        // ----- Récupération du ticket ----------------------------------------
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

        $newPriority = trim($data['priority'] ?? '');

        // ----- Validation de la priorité -------------------------------------
        if ('' === $newPriority) {
            return $this->json(
                ['errors' => ['priority' => 'La priorité est obligatoire.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (!in_array($newPriority, Ticket::PRIORITIES, strict: true)) {
            return $this->json(
                ['errors' => ['priority' => sprintf(
                    'Priorité invalide. Valeurs acceptées : %s.',
                    implode(', ', Ticket::PRIORITIES)
                )]],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // ----- Application du changement -------------------------------------
        $oldPriority = $ticket->getPriority();

        $ticket->setPriority($newPriority);
        $ticket->setUpdatedAt(new \DateTime());

        $this->em->flush();

        // Log dans l'historique uniquement si la valeur a réellement changé
        // (évite les entrées d'historique parasites "Haute → Haute")
        if ($oldPriority !== $newPriority) {
            $this->historyService->logPriorityChange(
                $ticket,
                $this->getPrototypeUser(),
                $oldPriority,
                $newPriority
            );
        }

        return $this->json($this->serializeTicket($ticket), Response::HTTP_OK);
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

    /**
     * Retourne l'utilisateur simulé pour le prototype (premier user en BDD).
     *
     * Mutualisé entre create(), updateStatus() et updatePriority() pour éviter
     * la duplication. À remplacer par $this->getUser() une fois JWT configuré.
     *
     * Lève une \RuntimeException si la base est vide (fixtures non chargées) —
     * cas d'erreur de configuration développeur, pas un cas métier.
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
