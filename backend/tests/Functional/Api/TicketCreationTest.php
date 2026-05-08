<?php

/**
 * TicketCreationTest — tests fonctionnels de la route POST /api/tickets.
 *
 * Ces tests vérifient l'API de création de ticket de bout en bout :
 *  - Requête HTTP réelle → contrôleur → service → base de données → réponse JSON
 *  - La base supportflow_db_test est remise à zéro avant chaque test
 *
 * Cas couverts :
 *  1. Création réussie (201) : vérifie la réponse et l'entrée d'historique en BDD
 *  2. Données invalides (422) : title manquant, vérification du message d'erreur
 */

namespace App\Tests\Functional\Api;

use App\Entity\TicketHistory;
use App\Tests\Functional\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TicketCreationTest extends WebTestCase
{
    // Inclut resetDatabase(), createTestUser(), getEntityManager(), createJsonClient()
    use DatabaseTestTrait;

    /**
     * setUp() : appelé avant chaque méthode de test.
     * Vide toutes les tables ET crée un utilisateur simulé (nécessaire car
     * TicketController::create() récupère le premier user en base via UserRepository).
     */
    protected function setUp(): void
    {
        // Démarre le kernel Symfony de test (nécessaire pour getContainer())
        static::bootKernel();

        $this->resetDatabase();

        // Crée l'utilisateur simulé AVANT les tests
        // (sans user en BDD, POST /api/tickets retournerait une RuntimeException)
        $this->createTestUser('proto@supportflow.test');
    }

    // -------------------------------------------------------------------------
    // TEST 1 : Création réussie
    // -------------------------------------------------------------------------

    /**
     * POST /api/tickets avec des données valides doit :
     *  - Retourner un code HTTP 201
     *  - Retourner un ticket avec status = "Nouveau"
     *  - Avoir créé une entrée dans ticket_history pour ce ticket
     */
    public function testCreateTicketWithValidDataReturns201(): void
    {
        $client = $this->createJsonClient();

        // Envoi de la requête POST avec un payload valide
        $client->request('POST', '/api/tickets', [], [], [], json_encode([
            'title'       => 'Bug critique sur la page de connexion',
            'description' => 'Les utilisateurs ne peuvent plus se connecter depuis 10h.',
            'category'    => 'Bug',
            'priority'    => 'Haute',
        ]));

        // --- Vérification du code HTTP ---
        $this->assertResponseStatusCodeSame(
            201,
            'POST /api/tickets avec données valides doit retourner 201 Created'
        );

        // --- Vérification du corps de la réponse ---
        $body = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('id', $body, 'La réponse doit contenir l\'ID du ticket créé');
        $this->assertSame(
            'Nouveau',
            $body['status'],
            'Le statut initial d\'un nouveau ticket doit toujours être "Nouveau"'
        );
        $this->assertSame('Bug critique sur la page de connexion', $body['title']);
        $this->assertSame('Bug',    $body['category']);
        $this->assertSame('Haute',  $body['priority']);

        // createdAt et updatedAt doivent être des dates ISO 8601
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $body['createdAt'],
            'createdAt doit être au format ISO 8601'
        );

        // --- Vérification de l'entrée d'historique en BDD ---
        // On vérifie directement en base que logCreation() a bien été appelé
        $em = $this->getEntityManager();
        $ticketId = $body['id'];

        $historyEntries = $em->createQuery(
            'SELECT h FROM App\Entity\TicketHistory h WHERE h.ticket = :id'
        )->setParameter('id', $ticketId)->getResult();

        $this->assertNotEmpty(
            $historyEntries,
            'Une entrée d\'historique doit exister pour le ticket créé'
        );

        // L'entrée d'historique doit contenir "Ticket créé"
        $firstEntry = $historyEntries[0];
        $this->assertStringContainsString(
            'Ticket créé',
            $firstEntry->getAction(),
            'L\'action d\'historique doit mentionner "Ticket créé"'
        );
    }

    // -------------------------------------------------------------------------
    // TEST 2 : Validation — title manquant
    // -------------------------------------------------------------------------

    /**
     * POST /api/tickets sans title doit :
     *  - Retourner 422 Unprocessable Entity
     *  - Retourner une structure { "errors": { "title": "..." } }
     */
    public function testCreateTicketWithoutTitleReturns422(): void
    {
        $client = $this->createJsonClient();

        // Payload invalide : title manquant
        $client->request('POST', '/api/tickets', [], [], [], json_encode([
            'description' => 'Description présente mais titre absent.',
            'category'    => 'Bug',
            'priority'    => 'Haute',
        ]));

        $this->assertResponseStatusCodeSame(
            422,
            'POST /api/tickets sans title doit retourner 422'
        );

        $body = json_decode($client->getResponse()->getContent(), true);

        // La réponse doit avoir une clé "errors"
        $this->assertArrayHasKey(
            'errors',
            $body,
            'La réponse 422 doit contenir une clé "errors"'
        );

        // L'erreur doit référencer le champ "title"
        $this->assertArrayHasKey(
            'title',
            $body['errors'],
            'Le tableau errors doit contenir une entrée pour le champ "title"'
        );

        // Le message d'erreur doit être non-vide
        $this->assertNotEmpty(
            $body['errors']['title'],
            'Le message d\'erreur pour "title" ne doit pas être vide'
        );
    }

    /**
     * POST /api/tickets avec category invalide doit retourner 422
     * avec un message mentionnant les valeurs acceptées.
     */
    public function testCreateTicketWithInvalidCategoryReturns422(): void
    {
        $client = $this->createJsonClient();

        $client->request('POST', '/api/tickets', [], [], [], json_encode([
            'title'       => 'Titre valide',
            'description' => 'Description valide',
            'category'    => 'Catégorie invalide',
            'priority'    => 'Haute',
        ]));

        $this->assertResponseStatusCodeSame(422);

        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('category', $body['errors']);
    }
}
