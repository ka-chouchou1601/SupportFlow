<?php

/**
 * CommentTest — tests fonctionnels de la route POST /api/tickets/{id}/comments.
 *
 * Cas couverts :
 *  1. Ajout de commentaire valide : vérifie 201, contenu retourné et entrée d'historique
 *  2. Contenu vide : vérifie 422
 *  3. Ticket inexistant : vérifie 404
 */

namespace App\Tests\Functional\Api;

use App\Entity\Ticket;
use App\Tests\Functional\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CommentTest extends WebTestCase
{
    use DatabaseTestTrait;

    private Ticket $ticket;

    protected function setUp(): void
    {
        static::bootKernel();

        $this->resetDatabase();
        $user = $this->createTestUser();

        $em = $this->getEntityManager();

        $this->ticket = new Ticket();
        $this->ticket->setTitle('Ticket pour test de commentaire');
        $this->ticket->setDescription('Description de test.');
        $this->ticket->setCategory('Bug');
        $this->ticket->setPriority('Haute');
        $this->ticket->setStatus('Nouveau');
        $this->ticket->setCreatedBy($user);
        $this->ticket->setCreatedAt(new \DateTime());
        $this->ticket->setUpdatedAt(new \DateTime());

        $em->persist($this->ticket);
        $em->flush();
    }

    // -------------------------------------------------------------------------
    // TEST 1 : Ajout de commentaire valide
    // -------------------------------------------------------------------------

    /**
     * POST /api/tickets/{id}/comments avec content valide doit :
     *  - Retourner 201
     *  - Retourner le commentaire avec son contenu
     *  - Avoir créé une entrée d'historique "Commentaire ajouté"
     */
    public function testAddCommentWithValidContentReturns201(): void
    {
        $client   = $this->createJsonClient();
        $ticketId = $this->ticket->getId();

        $client->request(
            'POST',
            "/api/tickets/{$ticketId}/comments",
            [], [], [],
            json_encode(['content' => 'Ceci est un commentaire de test.'])
        );

        // --- Code HTTP ---
        $this->assertResponseStatusCodeSame(201);

        // --- Corps de la réponse ---
        $body = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('id', $body, 'La réponse doit contenir l\'ID du commentaire');
        $this->assertSame(
            'Ceci est un commentaire de test.',
            $body['content'],
            'Le contenu retourné doit correspondre au contenu envoyé'
        );
        $this->assertArrayHasKey('author', $body, 'La réponse doit contenir les infos de l\'auteur');
        $this->assertArrayHasKey('createdAt', $body, 'La réponse doit contenir createdAt');

        // --- Vérification de l'historique en BDD ---
        $em = $this->getEntityManager();
        $em->clear();

        $histories = $em->createQuery(
            'SELECT h FROM App\Entity\TicketHistory h WHERE h.ticket = :id'
        )->setParameter('id', $ticketId)->getResult();

        $this->assertNotEmpty($histories, 'Une entrée d\'historique doit avoir été créée');

        $commentHistory = array_filter(
            $histories,
            fn($h) => str_contains($h->getAction(), 'Commentaire')
        );

        $this->assertNotEmpty(
            $commentHistory,
            'Une entrée d\'historique mentionnant "Commentaire" doit exister'
        );
    }

    // -------------------------------------------------------------------------
    // TEST 2 : Contenu vide
    // -------------------------------------------------------------------------

    /**
     * POST avec content vide doit retourner 422.
     */
    public function testAddCommentWithEmptyContentReturns422(): void
    {
        $client = $this->createJsonClient();

        $client->request(
            'POST',
            "/api/tickets/{$this->ticket->getId()}/comments",
            [], [], [],
            json_encode(['content' => ''])
        );

        $this->assertResponseStatusCodeSame(422);

        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $body);
        $this->assertArrayHasKey('content', $body['errors']);
    }

    // -------------------------------------------------------------------------
    // TEST 3 : Ticket inexistant
    // -------------------------------------------------------------------------

    public function testAddCommentOnUnknownTicketReturns404(): void
    {
        $client = $this->createJsonClient();

        $client->request(
            'POST',
            '/api/tickets/99999/comments',
            [], [], [],
            json_encode(['content' => 'Commentaire sur ticket inexistant.'])
        );

        $this->assertResponseStatusCodeSame(404);
    }
}
