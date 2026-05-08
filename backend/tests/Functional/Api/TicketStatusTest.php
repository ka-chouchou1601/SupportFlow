<?php

/**
 * TicketStatusTest — tests fonctionnels de la route PATCH /api/tickets/{id}/status.
 *
 * Cas couverts :
 *  1. Transition valide (Nouveau → En cours) : vérifie 200, statut et historique
 *  2. Transition invalide (Nouveau → Résolu) : vérifie 422 et message d'erreur
 *  3. Statut inconnu passé dans le body       : vérifie 422
 */

namespace App\Tests\Functional\Api;

use App\Entity\Ticket;
use App\Entity\TicketHistory;
use App\Tests\Functional\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TicketStatusTest extends WebTestCase
{
    use DatabaseTestTrait;

    /** @var Ticket Ticket de test créé avec statut "Nouveau" */
    private Ticket $ticket;

    /**
     * setUp() : remet la base à zéro et crée les données de test nécessaires.
     *
     * On crée un ticket directement via Doctrine (pas via l'API) pour :
     *  - Éviter les dépendances entre tests (la route POST n'a pas besoin de fonctionner)
     *  - Contrôler précisément l'état initial (statut, priorité, etc.)
     *  - Être plus rapide (pas de requête HTTP supplémentaire)
     */
    protected function setUp(): void
    {
        static::bootKernel();

        $this->resetDatabase();

        // Crée l'utilisateur simulé (nécessaire pour TicketController::getPrototypeUser())
        $user = $this->createTestUser();

        // Crée le ticket de test directement en BDD
        $em = $this->getEntityManager();

        $this->ticket = new Ticket();
        $this->ticket->setTitle('Ticket de test pour transitions de statut');
        $this->ticket->setDescription('Description de test.');
        $this->ticket->setCategory('Bug');
        $this->ticket->setPriority('Haute');
        $this->ticket->setStatus('Nouveau');  // statut initial pour les tests
        $this->ticket->setCreatedBy($user);
        $this->ticket->setCreatedAt(new \DateTime());
        $this->ticket->setUpdatedAt(new \DateTime());

        $em->persist($this->ticket);
        $em->flush();
    }

    // -------------------------------------------------------------------------
    // TEST 1 : Transition valide Nouveau → En cours
    // -------------------------------------------------------------------------

    /**
     * PATCH /api/tickets/{id}/status avec status = "En cours" depuis "Nouveau" doit :
     *  - Retourner 200
     *  - Retourner le ticket avec status = "En cours"
     *  - Avoir créé une entrée d'historique avec oldValue = "Nouveau"
     */
    public function testValidTransitionNouveauToEnCoursReturns200(): void
    {
        $client   = $this->createJsonClient();
        $ticketId = $this->ticket->getId();

        $client->request(
            'PATCH',
            "/api/tickets/{$ticketId}/status",
            [], [], [],
            json_encode(['status' => 'En cours'])
        );

        // --- Code HTTP ---
        $this->assertResponseStatusCodeSame(200);

        // --- Corps de la réponse ---
        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(
            'En cours',
            $body['status'],
            'Le statut retourné doit être "En cours" après la transition'
        );
        $this->assertSame($ticketId, $body['id']);

        // --- Vérification de l'entrée d'historique en BDD ---
        // On recharge l'EntityManager pour éviter de lire depuis le cache
        $em = $this->getEntityManager();
        $em->clear();

        $histories = $em->createQuery(
            'SELECT h FROM App\Entity\TicketHistory h WHERE h.ticket = :id ORDER BY h.createdAt ASC'
        )->setParameter('id', $ticketId)->getResult();

        $this->assertNotEmpty($histories, 'Une entrée d\'historique doit avoir été créée');

        // Trouve l'entrée de type "status"
        $statusHistory = array_filter(
            $histories,
            fn(TicketHistory $h) => $h->getFieldName() === 'status'
        );

        $this->assertNotEmpty(
            $statusHistory,
            'Une entrée d\'historique avec fieldName = "status" doit exister'
        );

        $entry = array_values($statusHistory)[0];
        $this->assertSame(
            'Nouveau',
            $entry->getOldValue(),
            'oldValue doit être "Nouveau"'
        );
        $this->assertSame(
            'En cours',
            $entry->getNewValue(),
            'newValue doit être "En cours"'
        );
    }

    // -------------------------------------------------------------------------
    // TEST 2 : Transition invalide Nouveau → Résolu
    // -------------------------------------------------------------------------

    /**
     * PATCH avec une transition non autorisée (Nouveau → Résolu) doit retourner 422
     * avec un message d'erreur explicite mentionnant la transition interdite.
     */
    public function testInvalidTransitionNouveauToResoluReturns422(): void
    {
        $client   = $this->createJsonClient();
        $ticketId = $this->ticket->getId();

        $client->request(
            'PATCH',
            "/api/tickets/{$ticketId}/status",
            [], [], [],
            json_encode(['status' => 'Résolu'])
        );

        $this->assertResponseStatusCodeSame(
            422,
            'La transition Nouveau → Résolu est interdite, doit retourner 422'
        );

        $body = json_decode($client->getResponse()->getContent(), true);

        // La réponse doit contenir une clé "error" avec le message de transition
        $this->assertArrayHasKey('error', $body);
        $this->assertStringContainsString(
            'Transition non autorisée',
            $body['error'],
            'Le message d\'erreur doit mentionner "Transition non autorisée"'
        );

        // Vérifie que le ticket n'a PAS été modifié en BDD
        $em = $this->getEntityManager();
        $em->clear();
        $reloaded = $em->find(Ticket::class, $ticketId);
        $this->assertSame(
            'Nouveau',
            $reloaded->getStatus(),
            'Le statut ne doit pas avoir changé après une transition refusée'
        );
    }

    // -------------------------------------------------------------------------
    // TEST 3 : Statut inexistant
    // -------------------------------------------------------------------------

    /**
     * PATCH avec un statut qui n'existe pas dans Ticket::STATUSES doit retourner 422.
     */
    public function testUnknownStatusValueReturns422(): void
    {
        $client = $this->createJsonClient();

        $client->request(
            'PATCH',
            "/api/tickets/{$this->ticket->getId()}/status",
            [], [], [],
            json_encode(['status' => 'StatutInexistant'])
        );

        $this->assertResponseStatusCodeSame(422);

        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $body);
        $this->assertArrayHasKey('status', $body['errors']);
    }

    // -------------------------------------------------------------------------
    // TEST 4 : Ticket inexistant
    // -------------------------------------------------------------------------

    public function testStatusUpdateOnUnknownTicketReturns404(): void
    {
        $client = $this->createJsonClient();

        $client->request(
            'PATCH',
            '/api/tickets/99999/status',
            [], [], [],
            json_encode(['status' => 'En cours'])
        );

        $this->assertResponseStatusCodeSame(404);
    }
}
