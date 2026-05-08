<?php

/**
 * TicketHistoryServiceTest — tests unitaires du service d'audit SupportFlow.
 *
 * Ces tests vérifient le comportement de TicketHistoryService de façon isolée :
 *  - Pas de base de données réelle (EntityManagerInterface mocké)
 *  - Pas de requête HTTP
 *  - Exécution ultra-rapide (<10 ms par test)
 *
 * Stratégie de test :
 *  On utilise PHPUnit's MockObject pour intercepter l'appel à persist() et
 *  capturer l'entité TicketHistory passée. On vérifie ensuite les propriétés
 *  de cette entité (action, fieldName, oldValue, newValue).
 *
 * Avantage des mocks ici : si la vraie implémentation de flush() envoyait
 *  une requête réseau, le test resterait rapide et sans dépendance externe.
 */

namespace App\Tests\Unit\Service;

use App\Entity\Ticket;
use App\Entity\TicketHistory;
use App\Entity\User;
use App\Service\TicketHistoryService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class TicketHistoryServiceTest extends TestCase
{
    // -------------------------------------------------------------------------
    // MÉTHODE UTILITAIRE PRIVÉE
    // Factorise la création du mock EM + la capture de l'entité persistée
    // -------------------------------------------------------------------------

    /**
     * Crée un mock EntityManager qui capture l'entité passée à persist().
     *
     * Retourne un tableau [$em, &$capturedEntity] :
     *  - $em             : le mock prêt à injecter dans le service
     *  - &$capturedEntity : référence qui sera remplie lors de l'appel à persist()
     *
     * Le & (passage par référence) permet au callback de remplir la variable
     * externe depuis l'intérieur du closure.
     */
    private function buildEmMock(?TicketHistory &$captured = null): EntityManagerInterface
    {
        // createMock() génère une implémentation vide de l'interface.
        // Toutes les méthodes retournent null par défaut, sauf celles qu'on configure.
        $em = $this->createMock(EntityManagerInterface::class);

        // expects($this->once()) : vérifie que persist() est appelé exactement 1 fois
        $em->expects($this->once())
           ->method('persist')
           ->willReturnCallback(function (object $entity) use (&$captured): void {
               // Capture l'entité passée à persist() pour l'inspecter dans le test
               $captured = $entity;
           });

        // flush() doit aussi être appelé exactement une fois (persist sans flush = pas de BDD)
        $em->expects($this->once())
           ->method('flush');

        return $em;
    }

    /**
     * Crée un Ticket de test minimal avec les champs requis.
     * L'ID est null car on ne passe pas par Doctrine (pas de vraie BDD).
     */
    private function buildTicket(): Ticket
    {
        $ticket = new Ticket();
        $ticket->setTitle('Ticket de test');
        $ticket->setDescription('Description test');
        $ticket->setCategory('Bug');
        $ticket->setPriority('Haute');
        $ticket->setStatus('Nouveau');

        return $ticket;
    }

    /**
     * Crée un User de test minimal.
     */
    private function buildUser(string $name = 'Alice Martin'): User
    {
        $user = new User();
        $user->setEmail('alice@test.com');
        $user->setName($name);
        $user->setRole('ROLE_AGENT');
        $user->setPassword('$2y$hashed');  // valeur factice, non utilisée dans ces tests

        return $user;
    }

    // -------------------------------------------------------------------------
    // TESTS logCreation()
    // -------------------------------------------------------------------------

    /**
     * logCreation() doit créer un TicketHistory dont le champ action
     * contient la chaîne "Ticket créé" et le nom de l'utilisateur.
     */
    public function testLogCreationPersistsHistoryWithCorrectAction(): void
    {
        $captured = null;
        $em       = $this->buildEmMock($captured);
        $service  = new TicketHistoryService($em);

        $ticket = $this->buildTicket();
        $user   = $this->buildUser('Alice Martin');

        $service->logCreation($ticket, $user);

        // Vérifie qu'une TicketHistory a bien été persistée
        $this->assertInstanceOf(
            TicketHistory::class,
            $captured,
            'logCreation() devrait persister une instance de TicketHistory'
        );

        // Vérifie que l'action contient "Ticket créé"
        $this->assertStringContainsString(
            'Ticket créé',
            $captured->getAction(),
            'L\'action de logCreation() doit contenir la chaîne "Ticket créé"'
        );

        // Vérifie que le nom de l'utilisateur apparaît dans l'action
        $this->assertStringContainsString(
            'Alice Martin',
            $captured->getAction(),
            'L\'action doit mentionner le nom de l\'utilisateur auteur'
        );

        // Vérifie l'association avec le ticket
        $this->assertSame(
            $ticket,
            $captured->getTicket(),
            'L\'entrée d\'historique doit référencer le bon ticket'
        );

        // Vérifie l'association avec l'utilisateur
        $this->assertSame(
            $user,
            $captured->getUser(),
            'L\'entrée d\'historique doit référencer le bon utilisateur'
        );

        // Pour une création, fieldName/oldValue/newValue doivent être null
        $this->assertNull(
            $captured->getFieldName(),
            'logCreation() ne doit pas renseigner fieldName (action globale, pas de champ modifié)'
        );
        $this->assertNull($captured->getOldValue(), 'oldValue doit être null pour une création');
        $this->assertNull($captured->getNewValue(), 'newValue doit être null pour une création');
    }

    // -------------------------------------------------------------------------
    // TESTS logStatusChange()
    // -------------------------------------------------------------------------

    /**
     * logStatusChange() doit créer un TicketHistory avec :
     *  - fieldName = "status"
     *  - oldValue  = l'ancien statut passé en paramètre
     *  - newValue  = le nouveau statut passé en paramètre
     *  - action    = un message lisible mentionnant les deux statuts
     */
    public function testLogStatusChangeStoresOldAndNewValues(): void
    {
        $captured = null;
        $em       = $this->buildEmMock($captured);
        $service  = new TicketHistoryService($em);

        $ticket = $this->buildTicket();
        $user   = $this->buildUser('Bob Leclerc');

        $service->logStatusChange($ticket, $user, 'Nouveau', 'En cours');

        $this->assertInstanceOf(TicketHistory::class, $captured);

        // Vérification du fieldName
        $this->assertSame(
            'status',
            $captured->getFieldName(),
            'logStatusChange() doit renseigner fieldName = "status"'
        );

        // Vérification des valeurs avant/après
        $this->assertSame(
            'Nouveau',
            $captured->getOldValue(),
            'oldValue doit être "Nouveau"'
        );
        $this->assertSame(
            'En cours',
            $captured->getNewValue(),
            'newValue doit être "En cours"'
        );

        // Le message d'action doit mentionner les deux statuts pour être lisible
        $this->assertStringContainsString('Nouveau',   $captured->getAction());
        $this->assertStringContainsString('En cours',  $captured->getAction());
    }

    /**
     * logStatusChange() doit fonctionner avec n'importe quelle paire de statuts valides.
     * Test complémentaire pour éviter la fausse sécurité d'un seul cas.
     */
    public function testLogStatusChangeWithResolvedToClosedTransition(): void
    {
        $captured = null;
        $em       = $this->buildEmMock($captured);
        $service  = new TicketHistoryService($em);

        $service->logStatusChange(
            $this->buildTicket(),
            $this->buildUser(),
            'Résolu',
            'Fermé'
        );

        $this->assertSame('status',  $captured->getFieldName());
        $this->assertSame('Résolu',  $captured->getOldValue());
        $this->assertSame('Fermé',   $captured->getNewValue());
    }

    // -------------------------------------------------------------------------
    // TESTS logPriorityChange() — rapide, suit le même pattern
    // -------------------------------------------------------------------------

    public function testLogPriorityChangeStoresOldAndNewValues(): void
    {
        $captured = null;
        $em       = $this->buildEmMock($captured);
        $service  = new TicketHistoryService($em);

        $service->logPriorityChange($this->buildTicket(), $this->buildUser(), 'Basse', 'Urgente');

        $this->assertSame('priority', $captured->getFieldName());
        $this->assertSame('Basse',    $captured->getOldValue());
        $this->assertSame('Urgente',  $captured->getNewValue());
    }

    // -------------------------------------------------------------------------
    // TESTS logComment()
    // -------------------------------------------------------------------------

    public function testLogCommentPersistsHistoryWithUserName(): void
    {
        $captured = null;
        $em       = $this->buildEmMock($captured);
        $service  = new TicketHistoryService($em);

        $user = $this->buildUser('Claire Dupont');
        $service->logComment($this->buildTicket(), $user);

        $this->assertInstanceOf(TicketHistory::class, $captured);
        $this->assertStringContainsString('Commentaire', $captured->getAction());
        $this->assertStringContainsString('Claire Dupont', $captured->getAction());
        // logComment est une action globale — pas de fieldName/values
        $this->assertNull($captured->getFieldName());
    }
}
