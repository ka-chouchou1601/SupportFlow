<?php

/**
 * AppFixtures — données de test pour l'environnement de développement SupportFlow.
 *
 * Ces fixtures permettent d'avoir un jeu de données réaliste dès le démarrage
 * du projet, sans avoir à saisir manuellement des données dans l'interface.
 *
 * Chargement : php bin/console doctrine:fixtures:load --no-interaction
 * (ATTENTION : cette commande VIDE la base avant d'insérer les fixtures)
 *
 * Données créées :
 *  - 2 utilisateurs (1 user standard, 1 admin)
 *  - 4 tickets dans différents états
 *  - 2 commentaires par ticket (8 au total)
 *  - Des entrées d'historique pour chaque ticket
 */

namespace App\DataFixtures;

use App\Entity\Comment;
use App\Entity\Ticket;
use App\Entity\TicketHistory;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    /**
     * UserPasswordHasherInterface est injecté automatiquement par Symfony
     * grâce au container de dépendances.
     * Il permet de hacher les mots de passe avec l'algorithme configuré
     * dans security.yaml (argon2id par défaut dans Symfony 8).
     */
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher
    ) {}

    /**
     * Point d'entrée des fixtures — appelé par la commande doctrine:fixtures:load.
     * L'ObjectManager est utilisé pour persister les entités sans flush individuel,
     * puis un flush global à la fin optimise les requêtes SQL.
     */
    public function load(ObjectManager $manager): void
    {
        // =====================================================================
        // 1. CRÉATION DES UTILISATEURS
        // =====================================================================

        /**
         * Utilisateur standard — représente un client/employé qui soumet des tickets.
         * Mot de passe en clair "password" hashé par argon2id.
         */
        $user = new User();
        $user->setEmail('user@supportflow.test');
        $user->setName('Chouella User');
        $user->setRole('ROLE_USER');
        // hashPassword() prend l'entité User (pour lire l'algorithme depuis security.yaml)
        // et le mot de passe en clair, puis retourne le hash sécurisé
        $user->setPassword($this->hasher->hashPassword($user, 'password'));
        $manager->persist($user);

        /**
         * Administrateur — peut gérer tous les tickets, utilisateurs et paramètres.
         * Même mot de passe pour simplifier les tests de développement.
         */
        $admin = new User();
        $admin->setEmail('admin@supportflow.test');
        $admin->setName('Admin Support');
        $admin->setRole('ROLE_ADMIN');
        $admin->setPassword($this->hasher->hashPassword($admin, 'password'));
        $manager->persist($admin);

        // =====================================================================
        // 2. CRÉATION DES TICKETS
        // Chaque ticket est accompagné de son historique de création.
        // =====================================================================

        // ---------------------------------------------------------------------
        // Ticket 1 : Bug critique en cours de traitement
        // ---------------------------------------------------------------------
        $ticket1 = new Ticket();
        $ticket1->setTitle('Erreur lors de la connexion');
        $ticket1->setDescription(
            "Depuis ce matin, impossible de se connecter avec mon compte. " .
            "Le message d'erreur affiché est : \"Identifiants invalides\" alors que " .
            "mes informations sont correctes. J'ai essayé de réinitialiser mon mot de passe " .
            "mais l'email de réinitialisation n'arrive pas. Le problème est reproductible " .
            "sur Chrome 120, Firefox 121 et Safari 17. Capture d'écran jointe."
        );
        $ticket1->setCategory('Bug');
        $ticket1->setPriority('Haute');
        $ticket1->setStatus('En cours');
        $ticket1->setCreatedBy($user);
        // Forçage des dates pour des données de test cohérentes
        $ticket1->setCreatedAt(new \DateTime('-3 days'));
        $ticket1->setUpdatedAt(new \DateTime('-1 day'));
        $manager->persist($ticket1);

        // Historique : création puis prise en charge
        $this->addHistory($manager, $ticket1, $admin, 'Création',     null,     null,      null,       '-3 days');
        $this->addHistory($manager, $ticket1, $admin, 'Modification', 'status', 'Nouveau', 'En cours', '-1 day');

        // ---------------------------------------------------------------------
        // Ticket 2 : Demande d'amélioration — faible priorité
        // ---------------------------------------------------------------------
        $ticket2 = new Ticket();
        $ticket2->setTitle('Ajouter un filtre par priorité');
        $ticket2->setDescription(
            "Dans la liste des tickets, il serait utile de pouvoir filtrer par priorité " .
            "(Urgente, Haute, Moyenne, Basse). Actuellement on ne peut filtrer que par statut. " .
            "Cela aiderait les agents à identifier rapidement les tickets urgents " .
            "sans avoir à parcourir toute la liste. " .
            "Une suggestion : ajouter des boutons de filtre rapide en haut du tableau."
        );
        $ticket2->setCategory('Amélioration');
        $ticket2->setPriority('Moyenne');
        $ticket2->setStatus('Nouveau');
        $ticket2->setCreatedBy($user);
        $ticket2->setCreatedAt(new \DateTime('-2 days'));
        $ticket2->setUpdatedAt(new \DateTime('-2 days'));
        $manager->persist($ticket2);

        $this->addHistory($manager, $ticket2, $user, 'Création', null, null, null, '-2 days');

        // ---------------------------------------------------------------------
        // Ticket 3 : Bug résolu
        // ---------------------------------------------------------------------
        $ticket3 = new Ticket();
        $ticket3->setTitle("Problème d'affichage sur mobile");
        $ticket3->setDescription(
            "Sur iPhone 14 Pro (iOS 17) et Samsung Galaxy S23, la page d'accueil " .
            "s'affiche incorrectement : le menu de navigation se superpose au contenu " .
            "et les boutons d'action sont en dehors de l'écran. " .
            "Le problème est présent depuis la mise à jour v2.3.1 déployée vendredi. " .
            "Sur desktop (toutes résolutions testées) l'affichage est correct."
        );
        $ticket3->setCategory('Bug');
        $ticket3->setPriority('Basse');
        $ticket3->setStatus('Résolu');
        $ticket3->setCreatedBy($user);
        $ticket3->setCreatedAt(new \DateTime('-7 days'));
        $ticket3->setUpdatedAt(new \DateTime('-1 day'));
        $manager->persist($ticket3);

        $this->addHistory($manager, $ticket3, $user,  'Création',     null,     null,       null,       '-7 days');
        $this->addHistory($manager, $ticket3, $admin, 'Modification', 'status', 'Nouveau',  'En cours', '-5 days');
        $this->addHistory($manager, $ticket3, $admin, 'Modification', 'status', 'En cours', 'Résolu',   '-1 day');

        // ---------------------------------------------------------------------
        // Ticket 4 : Incident urgent — ouvert
        // ---------------------------------------------------------------------
        $ticket4 = new Ticket();
        $ticket4->setTitle('Incident sur la création de compte');
        $ticket4->setDescription(
            "INCIDENT EN PRODUCTION : depuis 14h30 aujourd'hui, la création de compte " .
            "échoue systématiquement pour tous les nouveaux utilisateurs. " .
            "L'erreur retournée est une erreur 500 côté API. " .
            "Les logs applicatifs indiquent : SQLSTATE[23000]: Integrity constraint violation. " .
            "Impact : aucun nouveau compte ne peut être créé. Les comptes existants " .
            "ne sont pas affectés. Rollback de la migration de ce matin en cours d'analyse."
        );
        $ticket4->setCategory('Incident');
        $ticket4->setPriority('Urgente');
        $ticket4->setStatus('Nouveau');
        $ticket4->setCreatedBy($admin);
        $ticket4->setCreatedAt(new \DateTime('-4 hours'));
        $ticket4->setUpdatedAt(new \DateTime('-4 hours'));
        $manager->persist($ticket4);

        $this->addHistory($manager, $ticket4, $admin, 'Création', null, null, null, '-4 hours');

        // =====================================================================
        // 3. COMMENTAIRES (2 par ticket)
        // =====================================================================

        // --- Ticket 1 : Erreur de connexion ---
        $this->addComment(
            $manager, $ticket1, $admin,
            "J'ai reproduit le problème sur notre environnement de staging. " .
            "Il semble lié à un changement récent dans la configuration du provider d'authentification. " .
            "Je prends en charge le ticket et j'investigate.",
            '-1 day 2 hours'
        );
        $this->addComment(
            $manager, $ticket1, $user,
            "Merci pour la prise en charge rapide. " .
            "Pour info, le problème est apparu exactement après 9h00 ce matin. " .
            "Mes collègues du même service ont le même problème.",
            '-1 day 1 hour'
        );

        // --- Ticket 2 : Filtre par priorité ---
        $this->addComment(
            $manager, $ticket2, $admin,
            "Bonne suggestion ! Ce filtre est effectivement manquant. " .
            "Je l'ajoute à notre backlog pour la prochaine sprint de features. " .
            "Estimation : 2-3 jours de développement.",
            '-1 day'
        );
        $this->addComment(
            $manager, $ticket2, $user,
            "Super, merci ! Si possible, un filtre combiné (statut + priorité) " .
            "serait encore plus utile pour les agents qui gèrent beaucoup de tickets.",
            '-20 hours'
        );

        // --- Ticket 3 : Affichage mobile ---
        $this->addComment(
            $manager, $ticket3, $admin,
            "Le problème est identifié : un conflit CSS entre la nouvelle barre de navigation " .
            "et le meta viewport. Le correctif est en cours de développement.",
            '-5 days'
        );
        $this->addComment(
            $manager, $ticket3, $user,
            "Parfait, le correctif fonctionne, l'affichage est maintenant correct sur mon iPhone. " .
            "Merci pour la résolution rapide !",
            '-1 day'
        );

        // --- Ticket 4 : Incident création de compte ---
        $this->addComment(
            $manager, $ticket4, $admin,
            "Incident confirmé. La migration de ce matin a ajouté une contrainte UNIQUE " .
            "sur une colonne qui contenait déjà des doublons en production. " .
            "Rollback de la migration en cours, ETA 30 minutes.",
            '-3 hours'
        );
        $this->addComment(
            $manager, $ticket4, $admin,
            "Le rollback est effectué. La création de compte fonctionne à nouveau. " .
            "La migration sera re-jouée ce soir après nettoyage des doublons. " .
            "Monitoring renforcé jusqu'à demain matin.",
            '-2 hours'
        );

        // =====================================================================
        // 4. FLUSH GLOBAL — envoie tous les INSERT en une seule transaction
        // =====================================================================
        // Un seul flush à la fin est bien plus performant que des flush individuels
        // car Doctrine optimise les requêtes en une seule transaction SQL.
        $manager->flush();
    }

    // =========================================================================
    // MÉTHODES UTILITAIRES PRIVÉES
    // Ces méthodes évitent la répétition de code dans load() et centralisent
    // la construction des entités Comment et TicketHistory.
    // =========================================================================

    /**
     * Crée et persiste une entrée d'historique pour un ticket.
     *
     * @param string      $action    Type d'action (ex: "Création", "Modification")
     * @param string|null $fieldName Champ modifié (null si action globale)
     * @param string|null $oldValue  Ancienne valeur (null si création)
     * @param string|null $newValue  Nouvelle valeur (null si suppression)
     * @param string      $dateExpr  Expression de date relative (ex: '-3 days')
     */
    private function addHistory(
        ObjectManager $manager,
        Ticket $ticket,
        User $user,
        string $action,
        ?string $fieldName,
        ?string $oldValue,
        ?string $newValue,
        string $dateExpr
    ): void {
        $history = new TicketHistory();
        $history->setTicket($ticket);
        $history->setUser($user);
        $history->setAction($action);
        $history->setFieldName($fieldName);
        $history->setOldValue($oldValue);
        $history->setNewValue($newValue);
        // Utilise une date relative pour avoir un historique cohérent et lisible
        $history->setCreatedAt(new \DateTime($dateExpr));
        $manager->persist($history);
    }

    /**
     * Crée et persiste un commentaire sur un ticket.
     *
     * @param string $dateExpr Expression de date relative (ex: '-1 day 2 hours')
     */
    private function addComment(
        ObjectManager $manager,
        Ticket $ticket,
        User $author,
        string $content,
        string $dateExpr
    ): void {
        $comment = new Comment();
        $comment->setTicket($ticket);
        $comment->setAuthor($author);
        $comment->setContent($content);
        $comment->setCreatedAt(new \DateTime($dateExpr));
        $manager->persist($comment);
    }
}
