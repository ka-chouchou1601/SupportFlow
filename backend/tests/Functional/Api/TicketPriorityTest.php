<?php

/**
 * TicketPriorityTest — tests fonctionnels de la route PATCH /api/tickets/{id}/priority.
 *
 * Cas couverts :
 *  1. Changement de priorité valide (Basse → Haute) : vérifie 200, valeur et historique
 *  2. Priorité invalide : vérifie 422
 *  3. Ticket inexistant : vérifie 404
 */

namespace App\Tests\Functional\Api;

use App\Entity\Ticket;
use App\Entity\TicketHistory;
use App\Tests\Functional\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TicketPriorityTest extends WebTestCase
{
    use DatabaseTestTrait;

    private Ticket $ticket;

    /**
     * setUp() : remet la base à zéro et crée un ticket avec priorité "Basse".
     */
    protected function setUp(): void
    {
        static::bootKernel();

        $this->resetDatabase();
        $user = $this->createTestUser();

        $em = $this->getEntityManager();

        $this->ticket = new Ticket();
        $this->ticket->setTitle('Ticket priorité test');
        $this->ticket->setDescription('Test du changement de priorité.');
        $this->ticket->setCategory('Amélioration');
        // Priorité initiale explicitement "Basse" pour ce test
        $this->ticket->setPriority('Basse');
        $this->ticket->setStatus('Nouveau');
        $this->ticket->setCreatedBy($user);
        $this->ticket->setCreatedAt(new \DateTime());
        $this->ticket->setUpdatedAt(new \DateTime());

        $em->persist($this->ticket);
        $em->flush();
    }

    // -------------------------------------------------------------------------
    // TEST 1 : Changement de priorité Basse → Haute
    // -------------------------------------------------------------------------

    /**
     * PATCH /api/tickets/{id}/priority avec priority = "Haute" depuis "Basse" doit :
     *  - Retourner 200
     *  - Retourner le ticket avec priority = "Haute"
     *  - Avoir créé une entrée d'historique avec oldValue = "Basse" et newValue = "Haute"
     */
    public function testChangePriorityFromBasseToHauteReturns200(): void
    {
        $client   = $this->createJsonClient();
        $ticketId = $this->ticket->getId();

        $client->request(
            'PATCH',
            "/api/tickets/{$ticketId}/priority",
            [], [], [],
            json_encode(['priority' => 'Haute'])
        );

        // --- Code HTTP ---
        $this->assertResponseStatusCodeSame(200);

        // --- Corps de la réponse ---
        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(
            'Haute',
            $body['priority'],
            'La priorité retournée doit être "Haute" après la mise à jour'
        );

        // --- Vérification de l'historique en BDD ---
        $em = $this->getEntityManager();
        $em->clear();

        $histories = $em->createQuery(
            'SELECT h FROM App\Entity\TicketHistory h WHERE h.ticket = :id AND h.fieldName = :field'
        )
        ->setParameter('id', $ticketId)
        ->setParameter('field', 'priority')
        ->getResult();

        $this->assertNotEmpty(
            $histories,
            'Une entrée d\'historique avec fieldName = "priority" doit exister'
        );

        $entry = $histories[0];
        $this->assertSame(
            'Basse',
            $entry->getOldValue(),
            'oldValue doit être "Basse"'
        );
        $this->assertSame(
            'Haute',
            $entry->getNewValue(),
            'newValue doit être "Haute"'
        );
    }

    // -------------------------------------------------------------------------
    // TEST 2 : Priorité invalide
    // -------------------------------------------------------------------------

    /**
     * PATCH avec une priorité hors de Ticket::PRIORITIES doit retourner 422.
     */
    public function testInvalidPriorityReturns422(): void
    {
        $client = $this->createJsonClient();

        $client->request(
            'PATCH',
            "/api/tickets/{$this->ticket->getId()}/priority",
            [], [], [],
            json_encode(['priority' => 'Très urgente'])
        );

        $this->assertResponseStatusCodeSame(422);

        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors',    $body);
        $this->assertArrayHasKey('priority',  $body['errors']);
    }

    // -------------------------------------------------------------------------
    // TEST 3 : Ticket inexistant
    // -------------------------------------------------------------------------

    public function testPriorityUpdateOnUnknownTicketReturns404(): void
    {
        $client = $this->createJsonClient();

        $client->request(
            'PATCH',
            '/api/tickets/99999/priority',
            [], [], [],
            json_encode(['priority' => 'Haute'])
        );

        $this->assertResponseStatusCodeSame(404);
    }

    // -------------------------------------------------------------------------
    // TEST 4 : Même priorité — pas de doublon d'historique
    // -------------------------------------------------------------------------

    /**
     * Si on envoie la même priorité que celle déjà en place, aucune entrée
     * d'historique ne doit être créée (logique de TicketController::updatePriority()).
     */
    public function testSamePriorityDoesNotCreateHistoryEntry(): void
    {
        $client   = $this->createJsonClient();
        $ticketId = $this->ticket->getId();

        // Envoie "Basse" alors que le ticket est déjà à "Basse"
        $client->request(
            'PATCH',
            "/api/tickets/{$ticketId}/priority",
            [], [], [],
            json_encode(['priority' => 'Basse'])
        );

        $this->assertResponseStatusCodeSame(200);

        // Aucune entrée d'historique avec fieldName = "priority" ne doit exister
        $em = $this->getEntityManager();
        $em->clear();

        $count = $em->createQuery(
            'SELECT COUNT(h) FROM App\Entity\TicketHistory h WHERE h.ticket = :id AND h.fieldName = :field'
        )
        ->setParameter('id', $ticketId)
        ->setParameter('field', 'priority')
        ->getSingleScalarResult();

        $this->assertSame(
            0,
            (int) $count,
            'Aucune entrée d\'historique ne doit être créée si la priorité n\'a pas changé'
        );
    }
}
