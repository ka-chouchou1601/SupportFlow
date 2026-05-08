# SupportFlow

Mini-application de gestion de tickets support développée avec Symfony PHP, React.js et MySQL.
Prototype créé pour démontrer les compétences full stack : CRUD, API REST, base relationnelle,
composants React, logique métier, filtres, recherche et traçabilité.

## Stack technique

| Couche | Technologies |
|--------|-------------|
| Backend | Symfony 6.x, PHP 8.2, Doctrine ORM |
| Base de données | MySQL 8.0 |
| Frontend | React.js, Vite, Tailwind CSS, Axios |
| Outils | Docker Compose, Git, PHPUnit |

## Fonctionnalités

- Authentification (email + mot de passe)
- Création et gestion de tickets support
- Catégories : Bug, Demande utilisateur, Amélioration, Incident, Autre
- Priorités : Basse, Moyenne, Haute, Urgente
- Statuts avec transitions contrôlées : Nouveau → En cours → Résolu → Fermé
- Ajout de commentaires
- Historique automatique de toutes les actions
- Filtres par statut et priorité
- Recherche par mot-clé
- Tableau de bord avec statistiques simples

## Architecture

React.js (port 3000) → API REST JSON → Symfony (port 8000) → MySQL (port 3306)

Le frontend React consomme l'API REST exposée par Symfony.
Symfony utilise Doctrine ORM pour communiquer avec la base MySQL.
Chaque action sur un ticket génère automatiquement une entrée dans ticket_history.

## Lancer le projet

### Avec Docker

```bash
git clone [url-du-repo]
docker-compose up -d
```

### Backend

```bash
cd backend
composer install
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction
```

### Frontend

```bash
cd frontend
npm install
npm run dev
```

Application accessible sur http://localhost:3000

## Comptes de test

| Rôle | Email | Mot de passe |
|------|-------|--------------|
| Utilisateur | user@supportflow.test | password |
| Administrateur | admin@supportflow.test | password |

## Endpoints API

| Méthode | Route | Description |
|---------|-------|-------------|
| POST | /api/login | Connexion utilisateur |
| GET | /api/tickets | Liste des tickets (filtres : status, priority, search) |
| GET | /api/tickets/{id} | Détail ticket + commentaires + historique |
| POST | /api/tickets | Créer un ticket |
| PATCH | /api/tickets/{id}/status | Modifier le statut |
| PATCH | /api/tickets/{id}/priority | Modifier la priorité |
| POST | /api/tickets/{id}/comments | Ajouter un commentaire |

## Données de test

4 tickets préchargés :
- "Erreur lors de la connexion" — Bug — Haute — En cours
- "Ajouter un filtre par priorité" — Amélioration — Moyenne — Nouveau
- "Problème d'affichage sur mobile" — Bug — Basse — Résolu
- "Incident sur la création de compte" — Incident — Urgente — Nouveau

## Tests

```bash
cd backend
php bin/phpunit --testdox
```

5 tests couvrant :
- Création de ticket avec statut par défaut "Nouveau"
- Génération automatique de l'historique à la création
- Transitions de statut valides et invalides
- Changement de priorité avec historique
- Ajout de commentaire avec historique

## Limites du prototype

- Authentification par session simple (pas de JWT)
- Pas de notifications e-mail
- Pas de pagination backend
- Pas de pièces jointes
- Pas de déploiement en production configuré

## Améliorations possibles

- Authentification JWT (LexikJWTAuthenticationBundle)
- Pagination backend avec paramètres page/limit
- Notifications e-mail sur changement de statut
- Tests end-to-end avec Cypress ou Playwright
- Pipeline CI/CD avec GitHub Actions
- Tableau de bord statistique avec graphiques
