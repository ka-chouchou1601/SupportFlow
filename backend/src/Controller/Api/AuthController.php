<?php

/**
 * AuthController — gestion de l'authentification de l'API SupportFlow.
 *
 * Ce contrôleur expose une route de login simplifiée pour le prototype :
 * il vérifie les identifiants et retourne les données de l'utilisateur en JSON.
 *
 * IMPORTANT : ce contrôleur ne crée PAS de session ni de token JWT.
 * C'est intentionnel pour le prototype — la gestion de session/token
 * sera ajoutée dans une itération ultérieure (ex: LexikJWTAuthenticationBundle).
 *
 * Toutes les routes de ce contrôleur sont préfixées par /api (voir #[Route] sur la classe).
 */

namespace App\Controller\Api;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

// Préfixe de route appliqué à toutes les méthodes du contrôleur.
// Évite de répéter "/api" dans chaque #[Route] de méthode.
#[Route('/api')]
class AuthController extends AbstractController
{
    /**
     * AbstractController fournit des méthodes utilitaires (json(), redirectToRoute()…).
     * Les dépendances sont injectées par le container Symfony via le constructeur.
     *
     * UserRepository : accès aux données utilisateur en base de données.
     * UserPasswordHasherInterface : comparaison sécurisée du mot de passe hashé.
     */
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {}

    /**
     * POST /api/login — vérifie les identifiants et retourne les données utilisateur.
     *
     * Format du body JSON attendu :
     * {
     *   "email": "user@supportflow.test",
     *   "password": "password"
     * }
     *
     * Réponses :
     *  - 200 OK  : { id, email, name, role }
     *  - 400 Bad Request : { error: "Corps JSON invalide ou vide" }
     *  - 401 Unauthorized : { error: "Identifiants incorrects" }
     *
     * Note : on utilise Request $request (objet Symfony) et non les superglobales
     * PHP directement, pour rester testable et cohérent avec le framework.
     */
    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        // ----- Décodage du corps JSON -----------------------------------------
        // json_decode retourne null si le contenu n'est pas du JSON valide.
        // On s'assure que le Content-Type est bien application/json ou que
        // le body contient du JSON decodable.
        $data = json_decode($request->getContent(), associative: true);

        if (!is_array($data)) {
            return $this->json(
                ['error' => 'Corps JSON invalide ou vide'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // ----- Extraction des champs -----------------------------------------
        // ?? '' évite les notices PHP si les clés sont absentes.
        $email    = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if ('' === $email || '' === $password) {
            return $this->json(
                ['error' => 'Identifiants incorrects'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        // ----- Recherche de l'utilisateur ------------------------------------
        // findOneBy recherche par critère exact (pas de LIKE, sensible à la casse en MySQL).
        // On renvoie la même erreur générique 401 qu'un mauvais mot de passe :
        // ne pas divulguer si l'email existe ou non (bonne pratique de sécurité).
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (null === $user) {
            return $this->json(
                ['error' => 'Identifiants incorrects'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        // ----- Vérification du mot de passe ----------------------------------
        // isPasswordValid() compare le mot de passe en clair avec le hash stocké.
        // Elle utilise l'algorithme configuré dans security.yaml (argon2id par défaut).
        // Ne jamais comparer les mots de passe avec == ou strcmp !
        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(
                ['error' => 'Identifiants incorrects'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        // ----- Succès : retourne les données utilisateur ---------------------
        // On retourne uniquement les champs nécessaires au frontend.
        // NE PAS inclure le mot de passe hashé dans la réponse, même hashé.
        return $this->json([
            'id'    => $user->getId(),
            'email' => $user->getEmail(),
            'name'  => $user->getName(),
            'role'  => $user->getRole(),
        ], Response::HTTP_OK);
    }
}
