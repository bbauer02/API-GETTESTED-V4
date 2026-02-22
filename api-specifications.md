# Spécifications API — GETTESTED

Ce fichier décrit les spécifications techniques de l'API REST pour la plateforme GETTESTED.
Il est destiné à être utilisé conjointement avec `diagramme.puml` (modèle de données) et `description-metier.md` (règles métier).

**Stack technique** :
- **Langage** : PHP 8.4
- **Framework** : Symfony 7
- **API** : API Platform 4.2 (génération REST/JSON-LD automatique depuis les entités)
- **ORM** : Doctrine ORM avec attributs PHP 8
- **Base de données** : PostgreSQL 16
- **Authentification** : JWT via `lexik/jwt-authentication-bundle`
- **State machines** : `symfony/workflow`
- **Paiements** : Stripe PHP SDK + Stripe Connect
- **PDF / E-invoicing** : `atgp/factur-x` (Factur-X PDF/A-3 + XML CII)
- **Conteneurisation** : Docker Compose (PHP-FPM ou FrankenPHP, PostgreSQL, Caddy)
- **Tests** : PHPUnit + API Platform Test Client

---

## Conventions générales

### Authentification

- **JWT** (JSON Web Token) via `lexik/jwt-authentication-bundle`
- Endpoint de login : `POST /api/auth/login` (email + password → token)
- Refresh token : `POST /api/auth/refresh`
- Toutes les routes sauf celles marquées `PUBLIC` nécessitent un token JWT valide

### Format de réponse

- **JSON** (format par défaut, `application/json`)
- JSON-LD disponible via `Accept: application/ld+json`
- PATCH : `Content-Type: application/merge-patch+json` (obligatoire pour merge partiel)
- Pagination : 30 éléments par page par défaut
- Tri par défaut : `id ASC` sauf mention contraire

### Soft delete

- Les entités `User`, `Institute`, `Assessment`, `Session` possèdent un champ `deletedAt: datetime` (nullable)
- Les entités supprimées sont exclues des requêtes par défaut (filtre Doctrine)
- Les données de facturation (`Invoice`, `InvoiceLine`, `Payment`) ne sont **jamais supprimées** (obligation légale 10 ans)
- Lors du soft delete d'un `User`, les données personnelles sont conservées mais le compte est désactivé (`isActive=false`). L'anonymisation est un process séparé sur demande RGPD.

### Rôles référencés

- **PLATFORM_ADMIN** : `User.platformRole = ADMIN`
- **USER** : `User.platformRole = USER` (tout utilisateur connecté)
- **INSTITUTE_ADMIN** : `InstituteMembership.role = ADMIN` pour l'institut concerné
- **INSTITUTE_STAFF** : `InstituteMembership.role = STAFF` pour l'institut concerné
- **INSTITUTE_TEACHER** : `InstituteMembership.role = TEACHER` pour l'institut concerné
- **CUSTOMER** : `InstituteMembership.role = CUSTOMER` pour l'institut concerné
- **SELF** : l'utilisateur connecté accède à ses propres données
- **PUBLIC** : accessible sans authentification

---

## Entité `Country`

Représente un pays avec ses noms traduits, son drapeau et ses habitants. Données de référence en lecture seule (chargées par fixtures).

### Contraintes de validation

| Champ | Contraintes |
|---|---|
| `code` | Obligatoire, unique, format ISO 3166-1 alpha-2 (ex: "FR"), 2 caractères |
| `alpha3` | Obligatoire, unique, format ISO 3166-1 alpha-3 (ex: "FRA"), 3 caractères |
| `nameOriginal` | Obligatoire, max 255 caractères |
| `nameEn` | Obligatoire, max 255 caractères |
| `nameFr` | Obligatoire, max 255 caractères |
| `flag` | Optionnel, emoji drapeau ou URL image |
| `demonymFr` | Optionnel, max 100 caractères (ex: "Français") |
| `demonymEn` | Optionnel, max 100 caractères (ex: "French") |

### Contraintes d'unicité

- `code` : unique
- `alpha3` : unique

### Endpoints

| Méthode | Route | Accès | Description |
|---|---|---|---|
| `GET` | `/api/countries` | PUBLIC | Liste de tous les pays. |
| `GET` | `/api/countries/{code}` | PUBLIC | Détail d'un pays (avec ses langues). |
| `POST` | `/api/countries` | PLATFORM_ADMIN | Ajoute un pays. |
| `PATCH` | `/api/countries/{code}` | PLATFORM_ADMIN | Modifie un pays. |

### Détail des routes

#### `GET /api/countries`
Retourne la liste paginée de tous les pays enregistrés dans la plateforme. Chaque pays inclut ses codes ISO, noms traduits (français, anglais, original), emoji drapeau et gentilés. Les résultats peuvent être filtrés par code exact ou par recherche partielle sur le nom français ou anglais. Aucune authentification requise.

- **Réponse** : `200 OK` — tableau paginé d'objets Country (groupe `country:read`)

#### `GET /api/countries/{code}`
Retourne le détail complet d'un pays identifié par son code ISO 3166-1 alpha-2 (ex: `FR`). Inclut la liste des langues associées au pays, embarquées en objets complets.

- **Paramètre** : `code` — code ISO alpha-2 du pays (2 caractères, ex: `FR`)
- **Réponse** : `200 OK` — objet Country avec langues imbriquées (groupe `country:read:with-languages`)
- **Erreur** : `404 Not Found` — pays inexistant

#### `POST /api/countries`
Crée un nouveau pays dans le référentiel. Réservé aux administrateurs de la plateforme. Tous les champs obligatoires (code, alpha3, noms) doivent être fournis. Les codes ISO doivent être uniques.

- **Corps de la requête** : objet Country (groupe `country:write`)
- **Réponse** : `201 Created` — objet Country créé
- **Erreurs** : `422 Unprocessable Entity` — validation échouée (code dupliqué, champs manquants) · `403 Forbidden` — rôle insuffisant

#### `PATCH /api/countries/{code}`
Modifie partiellement un pays existant. Seuls les champs envoyés sont mis à jour (merge-patch). Réservé aux administrateurs de la plateforme.

- **Content-Type** : `application/merge-patch+json`
- **Réponse** : `200 OK` — objet Country mis à jour
- **Erreurs** : `404 Not Found` — pays inexistant · `422 Unprocessable Entity` — validation échouée · `403 Forbidden` — rôle insuffisant

### Groupes de sérialisation

| Groupe | Champs inclus | Utilisé par |
|---|---|---|
| `country:read` | code, alpha3, nameOriginal, nameEn, nameFr, flag, demonymFr, demonymEn | Lecture |
| `country:read:with-languages` | Tous + languages imbriquées | Détail |
| `country:write` | Tous les champs | Création / modification (PLATFORM_ADMIN) |

### Filtres

| Filtre | Type | Exemple |
|---|---|---|
| `code` | exact | `?code=FR` |
| `nameFr` | partial | `?nameFr=fran` |
| `nameEn` | partial | `?nameEn=fran` |

---

## Entité `Language`

Représente une langue avec ses noms traduits. Données de référence en lecture seule (chargées par fixtures).

### Contraintes de validation

| Champ | Contraintes |
|---|---|
| `code` | Obligatoire, unique, format ISO 639-1 (ex: "fr"), 2-3 caractères |
| `nameOriginal` | Obligatoire, max 255 caractères (ex: "Français") |
| `nameEn` | Obligatoire, max 255 caractères (ex: "French") |
| `nameFr` | Obligatoire, max 255 caractères (ex: "Français") |

### Contraintes d'unicité

- `code` : unique

### Endpoints

| Méthode | Route | Accès | Description |
|---|---|---|---|
| `GET` | `/api/languages` | PUBLIC | Liste de toutes les langues. |
| `GET` | `/api/languages/{code}` | PUBLIC | Détail d'une langue. |
| `POST` | `/api/languages` | PLATFORM_ADMIN | Ajoute une langue. |
| `PATCH` | `/api/languages/{code}` | PLATFORM_ADMIN | Modifie une langue. |

### Détail des routes

#### `GET /api/languages`
Retourne la liste paginée de toutes les langues enregistrées dans la plateforme. Chaque langue inclut son code ISO 639-1, ses noms traduits (original, français, anglais). Utilisé notamment pour alimenter les sélecteurs de langue maternelle dans le profil utilisateur. Aucune authentification requise.

- **Réponse** : `200 OK` — tableau paginé d'objets Language (groupe `language:read`)

#### `GET /api/languages/{code}`
Retourne le détail d'une langue identifiée par son code ISO 639-1 (ex: `fr`, `ja`).

- **Paramètre** : `code` — code ISO 639-1 de la langue (2-3 caractères)
- **Réponse** : `200 OK` — objet Language
- **Erreur** : `404 Not Found` — langue inexistante

#### `POST /api/languages`
Crée une nouvelle langue dans le référentiel. Réservé aux administrateurs de la plateforme. Le code ISO doit être unique.

- **Corps de la requête** : objet Language (groupe `language:write`)
- **Réponse** : `201 Created` — objet Language créé
- **Erreurs** : `422 Unprocessable Entity` — validation échouée · `403 Forbidden` — rôle insuffisant

#### `PATCH /api/languages/{code}`
Modifie partiellement une langue existante (noms traduits). Réservé aux administrateurs de la plateforme.

- **Content-Type** : `application/merge-patch+json`
- **Réponse** : `200 OK` — objet Language mis à jour
- **Erreurs** : `404 Not Found` — langue inexistante · `422 Unprocessable Entity` — validation échouée · `403 Forbidden` — rôle insuffisant

### Groupes de sérialisation

| Groupe | Champs inclus | Utilisé par |
|---|---|---|
| `language:read` | code, nameOriginal, nameEn, nameFr | Lecture |
| `language:write` | Tous les champs | Création / modification (PLATFORM_ADMIN) |

### Filtres

| Filtre | Type | Exemple |
|---|---|---|
| `code` | exact | `?code=fr` |
| `nameFr` | partial | `?nameFr=jap` |

---

## Entité `User`

### Contraintes de validation

| Champ | Contraintes |
|---|---|
| `email` | Obligatoire, format email valide, unique, max 180 caractères |
| `password` | Obligatoire à la création, min 8 caractères, hashé (bcrypt/argon2) |
| `firstname` | Obligatoire, max 100 caractères |
| `lastname` | Obligatoire, max 100 caractères |
| `civility` | Obligatoire, valeur de `CivilityEnum` |
| `gender` | Optionnel, valeur de `GenderEnum` |
| `phone` | Optionnel, max 20 caractères |
| `phoneCountryCode` | Optionnel, format `+XX` ou `+XXX`, max 5 caractères |
| `birthday` | Optionnel, doit être dans le passé |
| `address` | Optionnel (obligatoire pour s'inscrire à une session) |
| `nativeCountry` | Optionnel, référence `Country` existant |
| `nationality` | Optionnel, référence `Country` existant |
| `firstlanguage` | Optionnel, référence `Language` existant |
| `previousRegistrationNumber` | Optionnel, max 50 caractères |
| `platformRole` | Défaut `USER`, seul un PLATFORM_ADMIN peut modifier ce champ |
| `isVerified` | Défaut `false`, passe à `true` via le lien de vérification email |
| `isActive` | Défaut `true`, seul un PLATFORM_ADMIN peut modifier ce champ |

### Contraintes d'unicité

- `email` : unique

### Endpoints

| Méthode | Route | Accès | Description |
|---|---|---|---|
| `POST` | `/api/auth/register` | PUBLIC | Inscription. Crée un User avec `platformRole=USER`, `isVerified=false`, `isActive=true`. Envoie un email de vérification. |
| `POST` | `/api/auth/login` | PUBLIC | Connexion. Retourne un JWT si `isActive=true`. |
| `POST` | `/api/auth/refresh` | USER | Rafraîchit le JWT. |
| `POST` | `/api/auth/verify-email/{token}` | PUBLIC | Vérifie l'email. Passe `isVerified=true`, `emailVerifiedAt=now()`. |
| `POST` | `/api/auth/forgot-password` | PUBLIC | Envoie un email de réinitialisation de mot de passe. |
| `POST` | `/api/auth/reset-password/{token}` | PUBLIC | Réinitialise le mot de passe. |
| `GET` | `/api/users/me` | SELF | Retourne le profil complet de l'utilisateur connecté. Les relations (nationality, nativeCountry, firstlanguage) sont embarquées en objets complets. |
| `PATCH` | `/api/users/me` | SELF | Modifie le profil de l'utilisateur connecté. Voir détails ci-dessous. |
| `GET` | `/api/users` | PLATFORM_ADMIN | Liste tous les utilisateurs (avec filtres). |
| `GET` | `/api/users/{id}` | PLATFORM_ADMIN | Détail d'un utilisateur. |
| `PATCH` | `/api/users/{id}` | PLATFORM_ADMIN | Modifie un utilisateur (isActive, platformRole). |
| `DELETE` | `/api/users/{id}` | PLATFORM_ADMIN | Soft delete d'un utilisateur. |

### Détail des routes

#### `POST /api/auth/register`
Crée un nouveau compte utilisateur sur la plateforme. Le compte est créé avec `platformRole=USER`, `isVerified=false` et `isActive=true`. Un email de vérification est envoyé à l'adresse fournie contenant un lien avec un token à usage unique. L'utilisateur ne pourra pas s'inscrire à des sessions ni créer d'institut tant que son email n'est pas vérifié.

- **Corps de la requête** : `{ email, password, firstname, lastname, civility }` (groupe `user:write:register`)
- **Réponse** : `201 Created` — objet User créé (groupe `user:read:self`)
- **Erreurs** : `422 Unprocessable Entity` — email déjà utilisé, mot de passe trop court, champs obligatoires manquants

#### `POST /api/auth/login`
Authentifie un utilisateur par email et mot de passe. Retourne un JWT (JSON Web Token) à utiliser dans le header `Authorization: Bearer {token}` pour les requêtes authentifiées. Le login échoue si le compte est désactivé (`isActive=false`).

- **Corps de la requête** : `{ email, password }`
- **Réponse** : `200 OK` — `{ token }` (JWT valide)
- **Erreurs** : `401 Unauthorized` — identifiants invalides ou compte désactivé

#### `POST /api/auth/refresh`
Rafraîchit un JWT expiré ou sur le point d'expirer. Nécessite un token valide (non expiré ou dans sa période de grâce).

- **Réponse** : `200 OK` — `{ token }` (nouveau JWT)
- **Erreur** : `401 Unauthorized` — token invalide ou expiré

#### `POST /api/auth/verify-email/{token}`
Vérifie l'adresse email d'un utilisateur à partir du token reçu par email. Passe `isVerified=true` et enregistre `emailVerifiedAt`. Le token est à usage unique et expire après un délai configurable.

- **Paramètre** : `token` — token de vérification (UUID, reçu par email)
- **Réponse** : `200 OK` — confirmation de vérification
- **Erreurs** : `404 Not Found` — token invalide ou expiré

#### `POST /api/auth/forgot-password`
Envoie un email de réinitialisation de mot de passe à l'adresse fournie. Par sécurité, retourne toujours un succès même si l'email n'existe pas dans la base (prévention de l'énumération de comptes).

- **Corps de la requête** : `{ email }`
- **Réponse** : `200 OK` — confirmation d'envoi

#### `POST /api/auth/reset-password/{token}`
Réinitialise le mot de passe d'un utilisateur à partir du token reçu par email. Le nouveau mot de passe doit respecter les contraintes de sécurité (min 8 caractères). Le token est à usage unique.

- **Paramètre** : `token` — token de réinitialisation
- **Corps de la requête** : `{ password }`
- **Réponse** : `200 OK` — confirmation de réinitialisation
- **Erreurs** : `404 Not Found` — token invalide ou expiré · `422 Unprocessable Entity` — mot de passe trop court

#### `GET /api/users/me`
Retourne le profil complet de l'utilisateur actuellement authentifié. Les relations (nationality, nativeCountry, firstlanguage) sont embarquées en objets complets pour éviter les requêtes supplémentaires. Inclut toutes les informations personnelles sauf le mot de passe et la date de suppression.

- **Réponse** : `200 OK` — objet User complet (groupe `user:read:self`)
- **Erreur** : `401 Unauthorized` — non authentifié

#### `PATCH /api/users/me`
Modifie le profil de l'utilisateur connecté. Seuls les champs envoyés sont mis à jour. **Attention** : un changement d'email déclenche une re-vérification (`isVerified` repasse à `false`, un email de vérification est envoyé au nouvel email). Voir le tableau détaillé ci-dessous pour les champs modifiables et leurs contraintes.

- **Content-Type** : `application/merge-patch+json`
- **Réponse** : `200 OK` — objet User mis à jour (groupe `user:read:self`)
- **Erreurs** : `401 Unauthorized` — non authentifié · `422 Unprocessable Entity` — validation échouée

#### `GET /api/users`
Liste paginée de tous les utilisateurs de la plateforme. Réservé aux administrateurs. Supporte le filtrage par email, nom, prénom, rôle, statut actif et statut de vérification.

- **Réponse** : `200 OK` — tableau paginé d'objets User (groupe `user:read:admin`)
- **Erreur** : `403 Forbidden` — rôle insuffisant

#### `GET /api/users/{id}`
Retourne le détail complet d'un utilisateur identifié par son UUID. Réservé aux administrateurs de la plateforme.

- **Paramètre** : `id` — UUID de l'utilisateur
- **Réponse** : `200 OK` — objet User (groupe `user:read:admin`)
- **Erreurs** : `404 Not Found` — utilisateur inexistant · `403 Forbidden` — rôle insuffisant

#### `PATCH /api/users/{id}`
Permet à un administrateur de la plateforme de modifier le statut ou le rôle d'un utilisateur. Seuls deux champs sont modifiables : `isActive` (pour désactiver/réactiver un compte) et `platformRole` (pour promouvoir/rétrograder un administrateur).

- **Content-Type** : `application/merge-patch+json`
- **Corps de la requête** : `{ isActive?, platformRole? }` (groupe `user:write:admin`)
- **Réponse** : `200 OK` — objet User mis à jour
- **Erreurs** : `404 Not Found` — utilisateur inexistant · `403 Forbidden` — rôle insuffisant

#### `DELETE /api/users/{id}`
Effectue un soft delete de l'utilisateur : le champ `deletedAt` est renseigné et `isActive` passe à `false`. Le compte n'est plus accessible mais les données sont conservées (InstituteMemberships, EnrollmentSessions, Invoices/Payments restent intacts). L'anonymisation RGPD est un processus séparé sur demande.

- **Réponse** : `204 No Content`
- **Erreurs** : `404 Not Found` — utilisateur inexistant · `403 Forbidden` — rôle insuffisant

### `PATCH /api/users/me` — Champs modifiables et conditions

**Content-Type obligatoire** : `application/merge-patch+json`

| Champ | Type | Contraintes | Condition spéciale |
|---|---|---|---|
| `email` | string | Format email valide, unique, max 180 car. | **Changement → re-vérification** : `isVerified` passe à `false`, `emailVerifiedAt` à `null`, un email de vérification est envoyé au nouvel email |
| `civility` | enum | Valeurs : `M`, `MME`, `MLLE`, `AUTRE` | — |
| `firstname` | string | Non vide, max 100 car. | — |
| `lastname` | string | Non vide, max 100 car. | — |
| `avatar` | string | Max 255 car. (URL) | — |
| `gender` | enum | Valeurs : `MASCULIN`, `FEMININ`, `AUTRE`, `NON_SPECIFIE` | — |
| `phone` | string | Max 20 car. | — |
| `phoneCountryCode` | string | Format `+XX` ou `+XXX`, max 5 car. | — |
| `birthday` | date | Format `YYYY-MM-DD`, doit être dans le passé | — |
| `address` | objet | Objet avec : `address1` (max 255), `address2` (max 255), `zipcode` (max 20), `city` (max 255), `countryCode` (2 car. ISO) | — |
| `nativeCountry` | IRI | Format : `/api/countries/{code}`, doit référencer un pays existant | — |
| `nationality` | IRI | Format : `/api/countries/{code}`, doit référencer un pays existant | — |
| `firstlanguage` | IRI | Format : `/api/languages/{code}`, doit référencer une langue existante | — |
| `previousRegistrationNumber` | string | Max 50 car. | — |

### `PATCH /api/users/{id}` (PLATFORM_ADMIN) — Champs modifiables

| Champ | Type | Contraintes |
|---|---|---|
| `isActive` | boolean | `true` / `false` |
| `platformRole` | enum | Valeurs : `ADMIN`, `USER` |

### Groupes de sérialisation

| Groupe | Champs inclus | Utilisé par |
|---|---|---|
| `user:read:public` | firstname, lastname, avatar | Listes publiques (examinateurs, etc.) |
| `user:read:self` | Tous les champs sauf password, deletedAt | `GET /api/users/me` |
| `user:read:admin` | Tous les champs sauf password | `GET /api/users/{id}` (PLATFORM_ADMIN) |
| `user:write:register` | email, password, firstname, lastname, civility | `POST /api/auth/register` |
| `user:write:self` | email, civility, firstname, lastname, avatar, phone, phoneCountryCode, address, birthday, nativeCountry, nationality, firstlanguage, gender, previousRegistrationNumber | `PATCH /api/users/me` |
| `user:write:admin` | isActive, platformRole | `PATCH /api/users/{id}` (PLATFORM_ADMIN) |

### Filtres

| Filtre | Type | Exemple |
|---|---|---|
| `email` | exact | `?email=john@example.com` |
| `firstname` | partial | `?firstname=joh` |
| `lastname` | partial | `?lastname=doe` |
| `isActive` | boolean | `?isActive=true` |
| `isVerified` | boolean | `?isVerified=false` |
| `platformRole` | exact | `?platformRole=ADMIN` |

---

## Entité `Institute`

### Contraintes de validation

| Champ | Contraintes |
|---|---|
| `label` | Obligatoire, max 255 caractères, unique |
| `siteweb` | Optionnel, format URL valide |
| `socialNetworks` | Optionnel, JSON valide |
| `address` | Obligatoire |
| `vatNumber` | Optionnel, format TVA intracommunautaire (ex: FR12345678901) |

### Contraintes d'unicité

- `label` : unique

### Endpoints

| Méthode | Route | Accès | Description |
|---|---|---|---|
| `POST` | `/api/institutes` | USER (isVerified=true) | Crée un institut. Le créateur devient automatiquement INSTITUTE_ADMIN via InstituteMembership. |
| `GET` | `/api/institutes` | PUBLIC | Liste des instituts (informations publiques). |
| `GET` | `/api/institutes/{id}` | PUBLIC | Détail d'un institut. |
| `PATCH` | `/api/institutes/{id}` | INSTITUTE_ADMIN | Modifie un institut. |
| `DELETE` | `/api/institutes/{id}` | PLATFORM_ADMIN | Soft delete d'un institut. |

### Détail des routes

#### `POST /api/institutes`
Crée un nouvel institut (centre de formation, organisme certificateur…). L'utilisateur authentifié doit avoir un email vérifié (`isVerified=true`). Le créateur devient automatiquement membre de l'institut avec le rôle `ADMIN` via la création d'un `InstituteMembership`. L'adresse est obligatoire dès la création.

- **Corps de la requête** : `{ label, siteweb?, socialNetworks?, address, vatNumber? }` (groupe `institute:write:create`)
- **Réponse** : `201 Created` — objet Institute créé
- **Erreurs** : `422 Unprocessable Entity` — label dupliqué, champs manquants · `403 Forbidden` — email non vérifié

#### `GET /api/institutes`
Retourne la liste paginée des instituts avec leurs informations publiques (nom, site web, réseaux sociaux, adresse). Accessible sans authentification. Permet aux candidats de découvrir les centres d'examen disponibles. Supporte le filtrage par nom (recherche partielle).

- **Réponse** : `200 OK` — tableau paginé d'objets Institute (groupe `institute:read:public`)

#### `GET /api/institutes/{id}`
Retourne le détail public d'un institut identifié par son UUID. Inclut les informations générales et l'adresse complète.

- **Paramètre** : `id` — UUID de l'institut
- **Réponse** : `200 OK` — objet Institute (groupe `institute:read:public`)
- **Erreur** : `404 Not Found` — institut inexistant

#### `PATCH /api/institutes/{id}`
Modifie partiellement les informations d'un institut. Réservé aux administrateurs de l'institut (membres avec le rôle `ADMIN`). Les champs modifiables sont : label, site web, réseaux sociaux, adresse et numéro de TVA.

- **Content-Type** : `application/merge-patch+json`
- **Corps de la requête** : champs à modifier (groupe `institute:write:update`)
- **Réponse** : `200 OK` — objet Institute mis à jour
- **Erreurs** : `404 Not Found` — institut inexistant · `403 Forbidden` — l'utilisateur n'est pas ADMIN de cet institut

#### `DELETE /api/institutes/{id}`
Effectue un soft delete de l'institut. Réservé aux administrateurs de la plateforme. Lors de la suppression : les sessions OPEN/CLOSE passent en `CANCELLED` (avec remboursement des inscriptions payées), les sessions DRAFT sont annulées, les InstituteMemberships sont conservés, le StripeAccount est désactivé.

- **Réponse** : `204 No Content`
- **Erreurs** : `404 Not Found` — institut inexistant · `403 Forbidden` — rôle insuffisant (seul PLATFORM_ADMIN)

### Groupes de sérialisation

| Groupe | Champs inclus | Utilisé par |
|---|---|---|
| `institute:read:public` | label, siteweb, socialNetworks, address | Listes publiques |
| `institute:read:admin` | Tous les champs | INSTITUTE_ADMIN, PLATFORM_ADMIN |
| `institute:write:create` | label, siteweb, socialNetworks, address, vatNumber | Création |
| `institute:write:update` | label, siteweb, socialNetworks, address, vatNumber | Modification |

### Filtres

| Filtre | Type | Exemple |
|---|---|---|
| `label` | partial | `?label=cambridge` |

---

## Entité `InstituteMembership`

### Contraintes de validation

| Champ | Contraintes |
|---|---|
| `role` | Obligatoire, valeur de `InstituteRoleEnum` |
| `since` | Automatique (date courante à la création) |

### Contraintes d'unicité

- `(user, institute)` : unique — un utilisateur ne peut avoir qu'un seul rôle par institut

### Endpoints

| Méthode | Route | Accès | Description |
|---|---|---|---|
| `GET` | `/api/institutes/{id}/memberships` | INSTITUTE_ADMIN, INSTITUTE_STAFF | Liste des membres d'un institut. |
| `POST` | `/api/institutes/{id}/memberships/invite` | INSTITUTE_ADMIN | Invite un utilisateur (par email) avec un rôle TEACHER ou STAFF. Envoie un email d'invitation. |
| `PATCH` | `/api/institute-memberships/{id}` | INSTITUTE_ADMIN | Modifie le rôle d'un membre. |
| `DELETE` | `/api/institute-memberships/{id}` | INSTITUTE_ADMIN | Retire un membre de l'institut. |

### Détail des routes

#### `GET /api/institutes/{id}/memberships`
Retourne la liste des membres d'un institut donné, avec leur rôle et la date d'adhésion. Accessible aux administrateurs et au personnel de l'institut (ADMIN, STAFF). Chaque membre inclut ses informations publiques (prénom, nom, avatar). Permet à l'équipe de l'institut de visualiser et gérer ses collaborateurs.

- **Paramètre** : `id` — UUID de l'institut
- **Réponse** : `200 OK` — tableau d'objets InstituteMembership (groupe `membership:read`)
- **Erreurs** : `404 Not Found` — institut inexistant · `403 Forbidden` — l'utilisateur n'est pas membre de cet institut ou n'a pas un rôle suffisant

#### `POST /api/institutes/{id}/memberships/invite`
Invite un utilisateur existant à rejoindre l'institut avec un rôle spécifié (TEACHER, STAFF ou CUSTOMER). L'utilisateur cible est identifié par son IRI. Si l'utilisateur est déjà membre de l'institut, la requête est rejetée avec un code `409 Conflict`. La date d'adhésion (`since`) est automatiquement renseignée. Un email d'invitation peut être envoyé.

- **Paramètre** : `id` — UUID de l'institut
- **Corps de la requête** : `{ user: "/api/users/{id}", role: "TEACHER|STAFF|CUSTOMER" }` (groupe `membership:write:invite`)
- **Réponse** : `201 Created` — objet InstituteMembership créé
- **Erreurs** : `404 Not Found` — institut inexistant · `409 Conflict` — l'utilisateur est déjà membre de cet institut · `422 Unprocessable Entity` — utilisateur cible manquant · `403 Forbidden` — l'utilisateur n'est pas ADMIN de cet institut

#### `PATCH /api/institute-memberships/{id}`
Modifie le rôle d'un membre existant au sein d'un institut. Seul le champ `role` est modifiable. Réservé aux administrateurs de l'institut ou de la plateforme.

- **Content-Type** : `application/merge-patch+json`
- **Corps de la requête** : `{ role: "ADMIN|TEACHER|STAFF|CUSTOMER" }` (groupe `membership:write:update`)
- **Réponse** : `200 OK` — objet InstituteMembership mis à jour
- **Erreurs** : `404 Not Found` — membership inexistant · `403 Forbidden` — rôle insuffisant

#### `DELETE /api/institute-memberships/{id}`
Retire un membre d'un institut. Le membership est supprimé définitivement. Un ADMIN ne peut pas se retirer lui-même s'il est le dernier administrateur de l'institut (l'institut doit toujours avoir au moins un ADMIN).

- **Réponse** : `204 No Content`
- **Erreurs** : `404 Not Found` — membership inexistant · `403 Forbidden` — rôle insuffisant · `409 Conflict` — dernier ADMIN de l'institut

### Groupes de sérialisation

| Groupe | Champs inclus | Utilisé par |
|---|---|---|
| `membership:read` | role, since, user (user:read:public), institute (institute:read:public) | Lecture |
| `membership:write:invite` | email (de l'invité), role | Invitation |
| `membership:write:update` | role | Modification |

### Règles métier

- L'invitation crée le `InstituteMembership` avec le rôle spécifié. Si l'utilisateur n'existe pas encore, l'invitation est en attente jusqu'à son inscription.
- Le rôle `CUSTOMER` est créé automatiquement lorsqu'un utilisateur s'inscrit à une session d'un institut (pas d'invitation nécessaire).
- Un INSTITUTE_ADMIN ne peut pas se retirer lui-même s'il est le dernier ADMIN de l'institut.

---

## Entité `StripeAccount`

### Contraintes de validation

| Champ | Contraintes |
|---|---|
| `stripeId` | Obligatoire, unique, max 255 caractères |
| `isActivated` | Défaut `false` |

### Contraintes d'unicité

- `stripeId` : unique

### Endpoints

| Méthode | Route | Accès | Description |
|---|---|---|---|
| `POST` | `/api/institutes/{id}/stripe-account` | INSTITUTE_ADMIN | Initie la connexion Stripe Connect. Crée un StripeAccount avec `isActivated=false`. |
| `GET` | `/api/institutes/{id}/stripe-account` | INSTITUTE_ADMIN | Détail du compte Stripe de l'institut. |
| `DELETE` | `/api/institutes/{id}/stripe-account` | INSTITUTE_ADMIN | Déconnecte le compte Stripe. |

### Détail des routes

#### `POST /api/institutes/{id}/stripe-account`
Initie la procédure de connexion Stripe Connect pour un institut. Crée un objet `StripeAccount` en état inactif (`isActivated=false`) et retourne l'URL d'onboarding Stripe vers laquelle rediriger l'administrateur. L'activation effective se fait via le webhook Stripe `account.updated` une fois les vérifications KYC terminées côté Stripe. Un institut sans StripeAccount activé ne peut pas publier de sessions (transition DRAFT → OPEN bloquée).

- **Paramètre** : `id` — UUID de l'institut
- **Réponse** : `201 Created` — objet StripeAccount avec URL d'onboarding
- **Erreurs** : `403 Forbidden` — l'utilisateur n'est pas ADMIN de cet institut · `409 Conflict` — un StripeAccount existe déjà pour cet institut

#### `GET /api/institutes/{id}/stripe-account`
Retourne les informations du compte Stripe Connect associé à l'institut, notamment son statut d'activation. Permet à l'administrateur de vérifier si la connexion Stripe est opérationnelle avant de publier des sessions.

- **Paramètre** : `id` — UUID de l'institut
- **Réponse** : `200 OK` — objet StripeAccount (stripeId, isActivated)
- **Erreurs** : `404 Not Found` — aucun StripeAccount pour cet institut · `403 Forbidden` — rôle insuffisant

#### `DELETE /api/institutes/{id}/stripe-account`
Déconnecte le compte Stripe Connect de l'institut. Le StripeAccount est supprimé. Cette action empêche la publication de nouvelles sessions et la réception de nouveaux paiements. Les paiements en cours et les factures existantes ne sont pas affectés.

- **Réponse** : `204 No Content`
- **Erreurs** : `404 Not Found` — aucun StripeAccount pour cet institut · `403 Forbidden` — rôle insuffisant

### Règles métier

- Un institut doit avoir un `StripeAccount` avec `isActivated=true` pour pouvoir publier des sessions (passage DRAFT → OPEN).
- L'activation est confirmée par un webhook Stripe (`account.updated`).

---

## Entité `Assessment`

### Contraintes de validation

| Champ | Contraintes |
|---|---|
| `label` | Obligatoire, max 255 caractères |
| `ref` | Obligatoire, unique, max 50 caractères, format alphanumérique + tirets |
| `isInternal` | Obligatoire, défaut `false` |
| `parent` | Optionnel, référence un `Assessment` existant |

### Contraintes d'unicité

- `ref` : unique

### Endpoints

| Méthode | Route | Accès | Description |
|---|---|---|---|
| `GET` | `/api/assessments` | PUBLIC | Liste des tests disponibles. |
| `GET` | `/api/assessments/{id}` | PUBLIC | Détail d'un test (avec niveaux, skills, exams). |
| `POST` | `/api/assessments` | PLATFORM_ADMIN | Crée un test interne (`isInternal=true`). |
| `POST` | `/api/institutes/{id}/assessments` | INSTITUTE_ADMIN | Crée un test d'institut (`isInternal=false`). Crée automatiquement un `AssessmentOwnership(OWNER)`. |
| `PATCH` | `/api/assessments/{id}` | PLATFORM_ADMIN ou INSTITUTE_ADMIN propriétaire | Modifie un test. |
| `DELETE` | `/api/assessments/{id}` | PLATFORM_ADMIN ou INSTITUTE_ADMIN propriétaire | Soft delete. Interdit si des sessions OPEN ou CLOSE y sont liées. |

### Détail des routes

#### `GET /api/assessments`
Retourne la liste paginée de tous les tests disponibles sur la plateforme (TOEIC, DELF, DALF, JLPT, etc.). Inclut les tests internes (créés par la plateforme) et les tests d'instituts. Chaque test affiche son label, sa référence unique et son éventuel test parent (pour la hiérarchie d'héritage). Aucune authentification requise.

- **Réponse** : `200 OK` — tableau paginé d'objets Assessment (groupe `assessment:read:list`)

#### `GET /api/assessments/{id}`
Retourne le détail complet d'un test, incluant ses niveaux (Level), compétences (Skill) et épreuves (Exam) imbriqués. Cette vue complète permet au frontend d'afficher la structure d'un test sans requêtes supplémentaires.

- **Paramètre** : `id` — UUID du test
- **Réponse** : `200 OK` — objet Assessment avec sous-entités imbriquées (groupe `assessment:read:detail`)
- **Erreur** : `404 Not Found` — test inexistant

#### `POST /api/assessments`
Crée un test interne sur la plateforme (`isInternal=true`). Réservé aux administrateurs de la plateforme. Les tests internes sont des certifications officielles standardisées (TOEIC, DELF…). Ils peuvent être utilisés par tous les instituts via le mécanisme d'AssessmentOwnership.

- **Corps de la requête** : `{ label, ref, parent? }` (groupe `assessment:write`)
- **Réponse** : `201 Created` — objet Assessment créé
- **Erreurs** : `422 Unprocessable Entity` — ref dupliquée, champs manquants · `403 Forbidden` — rôle insuffisant

#### `POST /api/institutes/{id}/assessments`
Crée un test propre à un institut (`isInternal=false`). L'institut créateur obtient automatiquement un `AssessmentOwnership` de type `OWNER`. Ce test peut ensuite être vendu à d'autres instituts via le mécanisme de licence (AssessmentOwnership de type `BUYER`).

- **Paramètre** : `id` — UUID de l'institut
- **Corps de la requête** : `{ label, ref, parent? }` (groupe `assessment:write`)
- **Réponse** : `201 Created` — objet Assessment créé (avec ownership auto-créé)
- **Erreurs** : `422 Unprocessable Entity` — ref dupliquée · `403 Forbidden` — l'utilisateur n'est pas ADMIN de cet institut

#### `PATCH /api/assessments/{id}`
Modifie partiellement un test existant. Accessible aux administrateurs de la plateforme (pour les tests internes) ou aux administrateurs de l'institut propriétaire (pour les tests d'institut). Les champs modifiables sont le label, la référence et le test parent.

- **Content-Type** : `application/merge-patch+json`
- **Réponse** : `200 OK` — objet Assessment mis à jour
- **Erreurs** : `404 Not Found` — test inexistant · `403 Forbidden` — rôle insuffisant

#### `DELETE /api/assessments/{id}`
Effectue un soft delete d'un test. La suppression est **interdite** si des sessions en statut `OPEN` ou `CLOSE` y sont liées (des candidats pourraient être impactés). Les sessions en `DRAFT` liées au test sont automatiquement annulées.

- **Réponse** : `204 No Content`
- **Erreurs** : `404 Not Found` — test inexistant · `409 Conflict` — sessions OPEN/CLOSE liées · `403 Forbidden` — rôle insuffisant

### Groupes de sérialisation

| Groupe | Champs inclus | Utilisé par |
|---|---|---|
| `assessment:read:list` | label, ref, isInternal, parent (ref seulement) | Liste |
| `assessment:read:detail` | Tous les champs + levels, skills, exams imbriqués | Détail |
| `assessment:write` | label, ref, parent | Création / modification |

### Filtres

| Filtre | Type | Exemple |
|---|---|---|
| `label` | partial | `?label=toeic` |
| `ref` | exact | `?ref=TOEIC` |
| `isInternal` | boolean | `?isInternal=true` |
| `parent` | exact (IRI) | `?parent=/api/assessments/5` |

---

## Entité `AssessmentOwnership`

### Contraintes de validation

| Champ | Contraintes |
|---|---|
| `ownershipType` | Obligatoire, valeur de `OwnershipTypeEnum` |
| `relationshipDate` | Automatique (date courante à la création) |
| `user` | Obligatoire, référence `User` ayant effectué l'opération |

### Contraintes d'unicité

- `(institute, assessment, ownershipType)` : unique

### Endpoints

| Méthode | Route | Accès | Description |
|---|---|---|---|
| `GET` | `/api/institutes/{id}/ownerships` | INSTITUTE_ADMIN | Liste des tests détenus/achetés par l'institut. |
| `POST` | `/api/institutes/{id}/ownerships` | INSTITUTE_ADMIN | Demande d'achat d'un test (`BUYER`). Déclenche le process de facturation TEST_LICENSE. |
| `GET` | `/api/assessment-ownerships/{id}` | INSTITUTE_ADMIN concerné | Détail d'un ownership. |

### Détail des routes

#### `GET /api/institutes/{id}/ownerships`
Retourne la liste des tests détenus ou achetés par un institut. Chaque ownership indique le type de relation (`OWNER` pour un test créé par l'institut, `BUYER` pour un test acquis sous licence), la date de la relation et l'utilisateur ayant effectué l'opération. Permet à l'administrateur de visualiser le catalogue de tests disponibles pour son institut.

- **Paramètre** : `id` — UUID de l'institut
- **Réponse** : `200 OK` — tableau d'objets AssessmentOwnership
- **Erreurs** : `404 Not Found` — institut inexistant · `403 Forbidden` — l'utilisateur n'est pas ADMIN de cet institut

#### `POST /api/institutes/{id}/ownerships`
Crée une demande d'achat d'un test par un institut (type `BUYER`). Cette action déclenche le processus de facturation B2B (`businessType=TEST_LICENSE`). L'institut acheteur recevra une facture de la part de l'institut propriétaire du test. L'ownership est créé immédiatement mais l'institut ne pourra utiliser le test qu'après paiement de la licence.

- **Paramètre** : `id` — UUID de l'institut acheteur
- **Corps de la requête** : `{ assessment: "/api/assessments/{id}", ownershipType: "BUYER" }`
- **Réponse** : `201 Created` — objet AssessmentOwnership créé
- **Erreurs** : `409 Conflict` — l'institut possède déjà cet ownership (combinaison institut/assessment/type unique) · `403 Forbidden` — rôle insuffisant

#### `GET /api/assessment-ownerships/{id}`
Retourne le détail d'un ownership, incluant l'institut, le test, le type de relation, la date et l'utilisateur ayant effectué l'opération. Accessible aux administrateurs de l'institut concerné ou aux administrateurs de la plateforme.

- **Paramètre** : `id` — UUID de l'ownership
- **Réponse** : `200 OK` — objet AssessmentOwnership
- **Erreurs** : `404 Not Found` — ownership inexistant · `403 Forbidden` — rôle insuffisant

### Règles métier

- Le type `OWNER` est créé automatiquement lors de la création d'un test par un institut.
- Le type `BUYER` est créé via une demande d'achat qui déclenche une facturation B2B.
- Un institut ne peut pas être à la fois `OWNER` et `BUYER` du même test.

---

## Entité `Level`

### Contraintes de validation

| Champ | Contraintes |
|---|---|
| `label` | Obligatoire, max 50 caractères |
| `ref` | Obligatoire, max 10 caractères |
| `description` | Optionnel, max 500 caractères |

### Contraintes d'unicité

- `(ref, assessment)` : unique par test (via la relation Assessment → Level)

### Endpoints

| Méthode | Route | Accès | Description |
|---|---|---|---|
| `GET` | `/api/assessments/{id}/levels` | PUBLIC | Niveaux d'un test. |
| `POST` | `/api/assessments/{id}/levels` | PLATFORM_ADMIN ou INSTITUTE_ADMIN propriétaire | Ajoute un niveau. |
| `PATCH` | `/api/levels/{id}` | PLATFORM_ADMIN ou INSTITUTE_ADMIN propriétaire | Modifie un niveau. |
| `DELETE` | `/api/levels/{id}` | PLATFORM_ADMIN ou INSTITUTE_ADMIN propriétaire | Supprime un niveau. Interdit si des Exams y sont liés. |

### Détail des routes

#### `GET /api/assessments/{id}/levels`
Retourne la liste des niveaux définis pour un test donné (ex: A1, A2, B1, B2, C1, C2 pour le DELF). Chaque niveau possède un label, une référence unique au sein du test et une description optionnelle. Aucune authentification requise.

- **Paramètre** : `id` — UUID de l'assessment
- **Réponse** : `200 OK` — tableau d'objets Level

#### `POST /api/assessments/{id}/levels`
Ajoute un niveau à un test. La référence du niveau doit être unique au sein du test (contrainte `(ref, assessment)`). Réservé aux administrateurs de la plateforme (tests internes) ou aux administrateurs de l'institut propriétaire (tests d'institut).

- **Paramètre** : `id` — UUID de l'assessment
- **Corps de la requête** : `{ label, ref, description? }`
- **Réponse** : `201 Created` — objet Level créé
- **Erreurs** : `422 Unprocessable Entity` — ref dupliquée au sein du test · `403 Forbidden` — rôle insuffisant

#### `PATCH /api/levels/{id}`
Modifie partiellement un niveau existant (label, ref, description). La modification de la ref est soumise à la contrainte d'unicité par test.

- **Content-Type** : `application/merge-patch+json`
- **Réponse** : `200 OK` — objet Level mis à jour
- **Erreurs** : `404 Not Found` — niveau inexistant · `403 Forbidden` — rôle insuffisant

#### `DELETE /api/levels/{id}`
Supprime définitivement un niveau. La suppression est **interdite** si des épreuves (Exam) sont liées à ce niveau, car cela casserait l'intégrité référentielle du catalogue.

- **Réponse** : `204 No Content`
- **Erreurs** : `404 Not Found` — niveau inexistant · `409 Conflict` — des épreuves sont liées à ce niveau · `403 Forbidden` — rôle insuffisant

---

## Entité `Skill`

### Contraintes de validation

| Champ | Contraintes |
|---|---|
| `label` | Obligatoire, max 255 caractères |
| `description` | Optionnel, max 1000 caractères |
| `parent` | Optionnel, référence un `Skill` existant |

### Endpoints

| Méthode | Route | Accès | Description |
|---|---|---|---|
| `GET` | `/api/assessments/{id}/skills` | PUBLIC | Skills d'un test. |
| `POST` | `/api/assessments/{id}/skills` | PLATFORM_ADMIN ou INSTITUTE_ADMIN propriétaire | Ajoute un skill. |
| `PATCH` | `/api/skills/{id}` | PLATFORM_ADMIN ou INSTITUTE_ADMIN propriétaire | Modifie un skill. |
| `DELETE` | `/api/skills/{id}` | PLATFORM_ADMIN ou INSTITUTE_ADMIN propriétaire | Supprime un skill. Interdit si des Exams y sont liés. |

### Détail des routes

#### `GET /api/assessments/{id}/skills`
Retourne la liste des compétences évaluées par un test (ex: Compréhension orale, Expression écrite, Grammaire). Les skills supportent une hiérarchie parent/enfant permettant de modéliser des sous-compétences. Aucune authentification requise.

- **Paramètre** : `id` — UUID de l'assessment
- **Réponse** : `200 OK` — tableau d'objets Skill

#### `POST /api/assessments/{id}/skills`
Ajoute une compétence à un test. Un skill peut optionnellement référencer un skill parent pour créer une hiérarchie (ex: « Compréhension » → « Compréhension orale », « Compréhension écrite »). Un test doit avoir au moins 1 skill pour que ses sessions puissent être publiées (transition DRAFT → OPEN).

- **Paramètre** : `id` — UUID de l'assessment
- **Corps de la requête** : `{ label, description?, parent? }`
- **Réponse** : `201 Created` — objet Skill créé
- **Erreurs** : `422 Unprocessable Entity` — champs manquants · `403 Forbidden` — rôle insuffisant

#### `PATCH /api/skills/{id}`
Modifie partiellement une compétence existante (label, description, parent).

- **Content-Type** : `application/merge-patch+json`
- **Réponse** : `200 OK` — objet Skill mis à jour
- **Erreurs** : `404 Not Found` — skill inexistant · `403 Forbidden` — rôle insuffisant

#### `DELETE /api/skills/{id}`
Supprime définitivement une compétence. La suppression est **interdite** si des épreuves (Exam) référencent cette compétence, pour préserver l'intégrité du catalogue.

- **Réponse** : `204 No Content`
- **Erreurs** : `404 Not Found` — skill inexistant · `409 Conflict` — des épreuves sont liées à ce skill · `403 Forbidden` — rôle insuffisant

---

## Entité `Exam`

### Contraintes de validation

| Champ | Contraintes |
|---|---|
| `label` | Obligatoire, max 255 caractères |
| `isWritten` | Obligatoire |
| `isOption` | Obligatoire, défaut `false` |
| `coeff` | Obligatoire, min 1 |
| `nbrQuestions` | Optionnel, min 0 |
| `duration` | Obligatoire, min 1 (en minutes) |
| `successScore` | Obligatoire, min 0 |
| `level` | Obligatoire, référence un `Level` appartenant au même `Assessment` |
| `price` | Obligatoire (ValueObject `Price` : amount > 0, currency obligatoire, tva >= 0) |
| `skills` | Au moins 1 skill obligatoire |

### Endpoints

| Méthode | Route | Accès | Description |
|---|---|---|---|
| `GET` | `/api/assessments/{id}/exams` | PUBLIC | Épreuves d'un test. |
| `GET` | `/api/exams/{id}` | PUBLIC | Détail d'une épreuve. |
| `POST` | `/api/assessments/{id}/exams` | PLATFORM_ADMIN ou INSTITUTE_ADMIN propriétaire | Crée une épreuve. |
| `PATCH` | `/api/exams/{id}` | PLATFORM_ADMIN ou INSTITUTE_ADMIN propriétaire | Modifie une épreuve. Interdit si des ScheduledExams en session OPEN/CLOSE y sont liés. |
| `DELETE` | `/api/exams/{id}` | PLATFORM_ADMIN ou INSTITUTE_ADMIN propriétaire | Supprime une épreuve. Interdit si des ScheduledExams y sont liés. |

### Détail des routes

#### `GET /api/assessments/{id}/exams`
Retourne la liste des épreuves composant un test (ex: TOEIC Listening, TOEIC Reading). Chaque épreuve inclut son label, sa durée, son score de réussite, son coefficient, son niveau, ses compétences évaluées et son prix de base. Aucune authentification requise.

- **Paramètre** : `id` — UUID de l'assessment
- **Réponse** : `200 OK` — tableau d'objets Exam (groupe `exam:read:public`)

#### `GET /api/exams/{id}`
Retourne le détail complet d'une épreuve, incluant toutes ses propriétés techniques (nombre de questions, coefficient, score de réussite, si écrite ou orale, si optionnelle) ainsi que le prix de base et les compétences évaluées.

- **Paramètre** : `id` — UUID de l'épreuve
- **Réponse** : `200 OK` — objet Exam (groupe `exam:read:detail`)
- **Erreur** : `404 Not Found` — épreuve inexistante

#### `POST /api/assessments/{id}/exams`
Crée une nouvelle épreuve pour un test. L'épreuve doit référencer un niveau (Level) appartenant au même test et au moins une compétence (Skill). Le prix de base est défini via le value object `Price` (montant, devise, taux de TVA). Les instituts peuvent ensuite définir des prix personnalisés via `InstituteExamPricing`.

- **Paramètre** : `id` — UUID de l'assessment
- **Corps de la requête** : `{ label, isWritten, isOption?, coeff, nbrQuestions?, duration, successScore, level, price, skills }` (groupe `exam:write`)
- **Réponse** : `201 Created` — objet Exam créé
- **Erreurs** : `422 Unprocessable Entity` — champs manquants, level/skills invalides · `403 Forbidden` — rôle insuffisant

#### `PATCH /api/exams/{id}`
Modifie partiellement une épreuve existante. La modification est **interdite** si des ScheduledExams en session `OPEN` ou `CLOSE` référencent cette épreuve, pour éviter de modifier les conditions d'un examen en cours d'inscription ou déjà planifié.

- **Content-Type** : `application/merge-patch+json`
- **Réponse** : `200 OK` — objet Exam mis à jour
- **Erreurs** : `404 Not Found` — épreuve inexistante · `409 Conflict` — des ScheduledExams OPEN/CLOSE y sont liés · `403 Forbidden` — rôle insuffisant

#### `DELETE /api/exams/{id}`
Supprime définitivement une épreuve. La suppression est **interdite** si des ScheduledExams y sont liés (quel que soit le statut de la session), pour préserver l'historique des planifications.

- **Réponse** : `204 No Content`
- **Erreurs** : `404 Not Found` — épreuve inexistante · `409 Conflict` — des ScheduledExams y sont liés · `403 Forbidden` — rôle insuffisant

### Groupes de sérialisation

| Groupe | Champs inclus | Utilisé par |
|---|---|---|
| `exam:read:public` | label, isWritten, isOption, duration, level, skills, price | Liste publique |
| `exam:read:detail` | Tous les champs | Détail |
| `exam:write` | label, isWritten, isOption, coeff, nbrQuestions, duration, successScore, level, price, skills | Création / modification |

### Filtres

| Filtre | Type | Exemple |
|---|---|---|
| `isOption` | boolean | `?isOption=true` |
| `isWritten` | boolean | `?isWritten=false` |
| `level` | exact (IRI) | `?level=/api/levels/3` |

---

## Entité `InstituteExamPricing`

### Contraintes de validation

| Champ | Contraintes |
|---|---|
| `price` | Obligatoire (ValueObject `Price`) |
| `createdAt` | Automatique |
| `isActive` | Obligatoire, défaut `true` |

### Contraintes d'unicité

- `(institute, exam, isActive=true)` : un seul prix actif par épreuve par institut

### Endpoints

| Méthode | Route | Accès | Description |
|---|---|---|---|
| `GET` | `/api/institutes/{id}/exam-pricings` | INSTITUTE_ADMIN, INSTITUTE_STAFF | Liste des prix personnalisés. |
| `POST` | `/api/institutes/{id}/exam-pricings` | INSTITUTE_ADMIN | Crée un prix personnalisé. Désactive automatiquement l'ancien prix actif pour le même Exam. |
| `PATCH` | `/api/institute-exam-pricings/{id}` | INSTITUTE_ADMIN | Modifie (uniquement `isActive`). |

### Détail des routes

#### `GET /api/institutes/{id}/exam-pricings`
Retourne la liste des prix personnalisés définis par un institut pour ses épreuves. Chaque pricing inclut le prix (montant, devise, TVA), son statut actif/inactif et la date de création. Un institut peut avoir des prix différents des prix de base définis sur l'Exam, permettant une tarification flexible par centre d'examen.

- **Paramètre** : `id` — UUID de l'institut
- **Réponse** : `200 OK` — tableau d'objets InstituteExamPricing
- **Erreur** : `403 Forbidden` — l'utilisateur n'est pas ADMIN ou STAFF de cet institut

#### `POST /api/institutes/{id}/exam-pricings`
Crée un prix personnalisé pour une épreuve dans un institut. Si un prix actif existe déjà pour le même Exam, il est automatiquement désactivé (`isActive=false`) et le nouveau prix prend le relais. Cette mécanique permet de conserver l'historique des prix tout en garantissant qu'un seul prix est actif à un instant donné. Le prix personnalisé est celui utilisé lors de la facturation des inscriptions.

- **Paramètre** : `id` — UUID de l'institut
- **Corps de la requête** : `{ exam: "/api/exams/{id}", price: { amount, currency, tva } }`
- **Réponse** : `201 Created` — objet InstituteExamPricing créé
- **Erreurs** : `422 Unprocessable Entity` — champs manquants, exam invalide · `403 Forbidden` — rôle insuffisant

#### `PATCH /api/institute-exam-pricings/{id}`
Modifie un prix personnalisé. Seul le champ `isActive` est modifiable, permettant de désactiver manuellement un prix sans en créer un nouveau (l'épreuve revient alors au prix de base).

- **Content-Type** : `application/merge-patch+json`
- **Corps de la requête** : `{ isActive: true|false }`
- **Réponse** : `200 OK` — objet InstituteExamPricing mis à jour
- **Erreurs** : `404 Not Found` — pricing inexistant · `403 Forbidden` — rôle insuffisant

---

## Entité `Session`

### Contraintes de validation

| Champ | Contraintes |
|---|---|
| `start` | Obligatoire, doit être dans le futur (à la création) |
| `end` | Obligatoire, doit être > `start` |
| `limitDateSubscribe` | Obligatoire, doit être < `start` |
| `placesAvailable` | Obligatoire, min 1 |
| `validation` | Défaut `DRAFT` |

### Transitions de statut (`validation`)

| De | Vers | Condition |
|---|---|---|
| `DRAFT` | `OPEN` | L'institut a un StripeAccount activé. L'Assessment a au moins 1 Skill. Au moins 1 ScheduledExam est planifié. |
| `OPEN` | `CLOSE` | `limitDateSubscribe` atteinte OU action manuelle INSTITUTE_ADMIN. |
| `OPEN` | `CANCELLED` | Action manuelle INSTITUTE_ADMIN. Déclenche le remboursement de toutes les inscriptions payées. |
| `CLOSE` | `CANCELLED` | Action manuelle INSTITUTE_ADMIN. Déclenche le remboursement. |
| `DRAFT` | `CANCELLED` | Action manuelle INSTITUTE_ADMIN. Pas de remboursement (pas d'inscriptions possibles en DRAFT). |

### Endpoints

| Méthode | Route | Accès | Description |
|---|---|---|---|
| `GET` | `/api/sessions` | PUBLIC | Liste des sessions `OPEN` (tri par `start ASC`). |
| `GET` | `/api/sessions/{id}` | PUBLIC | Détail d'une session (avec ScheduledExams, épreuves, prix). |
| `GET` | `/api/institutes/{id}/sessions` | INSTITUTE_ADMIN, INSTITUTE_STAFF | Toutes les sessions d'un institut (tous statuts). |
| `POST` | `/api/institutes/{id}/sessions` | INSTITUTE_ADMIN | Crée une session en `DRAFT`. |
| `PATCH` | `/api/sessions/{id}` | INSTITUTE_ADMIN | Modifie une session. Les champs modifiables dépendent du statut (voir ci-dessous). |
| `PATCH` | `/api/sessions/{id}/transition` | INSTITUTE_ADMIN | Change le statut (DRAFT→OPEN, OPEN→CLOSE, etc.). |
| `DELETE` | `/api/sessions/{id}` | INSTITUTE_ADMIN | Soft delete. Uniquement si `DRAFT`. |

### Détail des routes

#### `GET /api/sessions`
Retourne la liste paginée des sessions de tests **ouvertes aux inscriptions** (statut `OPEN` uniquement). Triées par date de début croissante (`start ASC`). C'est la route principale pour les candidats cherchant une session d'examen. Chaque session inclut les dates, le nombre de places, l'assessment, le niveau, l'institut organisateur et les épreuves planifiées avec leurs prix. Aucune authentification requise.

- **Réponse** : `200 OK` — tableau paginé d'objets Session (groupe `session:read:public`)

#### `GET /api/sessions/{id}`
Retourne le détail complet d'une session, incluant les ScheduledExams (épreuves planifiées avec dates, salles, adresses, prix personnalisés de l'institut), les inscriptions (nombre en public, détail complet pour les admins), l'assessment et l'institut. Si la session n'est pas `OPEN`, son accès public peut être restreint selon la configuration.

- **Paramètre** : `id` — UUID de la session
- **Réponse** : `200 OK` — objet Session complet (groupe `session:read:detail`)
- **Erreur** : `404 Not Found` — session inexistante

#### `GET /api/institutes/{id}/sessions`
Retourne **toutes** les sessions d'un institut, quel que soit leur statut (DRAFT, OPEN, CLOSE, CANCELLED). Permet aux administrateurs et au personnel de gérer l'ensemble du cycle de vie des sessions. Accessible aux membres ADMIN et STAFF de l'institut.

- **Paramètre** : `id` — UUID de l'institut
- **Réponse** : `200 OK` — tableau paginé d'objets Session (tous statuts)
- **Erreurs** : `404 Not Found` — institut inexistant · `403 Forbidden` — l'utilisateur n'est pas membre ADMIN/STAFF de cet institut

#### `POST /api/institutes/{id}/sessions`
Crée une nouvelle session en statut `DRAFT` pour un institut. Les dates (début, fin, limite d'inscription) et le nombre de places sont requis. La session doit référencer un Assessment existant. Une session DRAFT n'est pas visible publiquement et ne peut pas recevoir d'inscriptions tant qu'elle n'est pas publiée (transition vers `OPEN`).

- **Paramètre** : `id` — UUID de l'institut
- **Corps de la requête** : `{ start, end, limitDateSubscribe, placesAvailable, assessment }` (groupe `session:write`)
- **Réponse** : `201 Created` — objet Session en statut DRAFT
- **Erreurs** : `422 Unprocessable Entity` — dates invalides, assessment inexistant · `403 Forbidden` — l'utilisateur n'est pas ADMIN de cet institut

#### `PATCH /api/sessions/{id}`
Modifie partiellement une session. Les champs modifiables dépendent strictement du statut actuel de la session (voir tableau ci-dessous). En statut `DRAFT`, tous les champs sont modifiables. En statut `OPEN`, seuls `limitDateSubscribe` et `placesAvailable` (augmentation uniquement) sont modifiables. En statut `CLOSE` ou `CANCELLED`, aucune modification n'est autorisée (erreur `409 Conflict`). La validation des champs en statut `OPEN` (ex: tentative de modifier `start`) retourne une erreur `422 Unprocessable Entity`.

- **Content-Type** : `application/merge-patch+json`
- **Réponse** : `200 OK` — objet Session mis à jour
- **Erreurs** : `404 Not Found` — session inexistante · `409 Conflict` — session en statut CLOSE/CANCELLED (modification impossible) · `422 Unprocessable Entity` — champ non modifiable dans le statut actuel · `403 Forbidden` — rôle insuffisant

#### `PATCH /api/sessions/{id}/transition`
Effectue une transition de statut sur la session via la state machine Symfony Workflow. Le champ `transition` dans le corps de la requête indique la transition souhaitée (ex: `open`, `close`, `cancel_from_draft`, `cancel_from_open`, `cancel_from_close`). Chaque transition est soumise à des conditions métier (voir tableau des transitions). Si la transition n'est pas possible depuis le statut actuel, une erreur `409 Conflict` est retournée.

- **Content-Type** : `application/merge-patch+json`
- **Corps de la requête** : `{ transition: "open|close|cancel_from_draft|cancel_from_open|cancel_from_close" }`
- **Réponse** : `200 OK` — objet Session avec le nouveau statut
- **Erreurs** : `409 Conflict` — transition impossible depuis le statut actuel · `422 Unprocessable Entity` — champ `transition` manquant · `403 Forbidden` — rôle insuffisant

#### `DELETE /api/sessions/{id}`
Effectue un soft delete de la session (renseigne `deletedAt`). La suppression n'est possible que si la session est en statut `DRAFT`. Toute tentative de supprimer une session dans un autre statut retourne une erreur `409 Conflict`. Pour annuler une session OPEN/CLOSE, utiliser la transition `cancel_from_open`/`cancel_from_close` à la place.

- **Réponse** : `204 No Content`
- **Erreurs** : `409 Conflict` — session pas en statut DRAFT · `404 Not Found` — session inexistante · `403 Forbidden` — rôle insuffisant

### Champs modifiables selon le statut

| Champ | DRAFT | OPEN | CLOSE | CANCELLED |
|---|---|---|---|---|
| `start` | Oui | Non | Non | Non |
| `end` | Oui | Non | Non | Non |
| `limitDateSubscribe` | Oui | Oui | Non | Non |
| `placesAvailable` | Oui | Oui (augmenter uniquement) | Non | Non |

### Groupes de sérialisation

| Groupe | Champs inclus | Utilisé par |
|---|---|---|
| `session:read:public` | start, end, limitDateSubscribe, placesAvailable, validation, assessment (label, ref), level (label, ref), institute (label) | Liste publique |
| `session:read:detail` | Tous les champs + scheduledExams, enrollmentSessions (count seulement en public) | Détail |
| `session:read:admin` | Tous les champs + enrollmentSessions (détail complet) | INSTITUTE_ADMIN |
| `session:write` | start, end, limitDateSubscribe, placesAvailable, assessment, level | Création / modification |

### Filtres

| Filtre | Type | Exemple |
|---|---|---|
| `validation` | exact | `?validation=OPEN` |
| `assessment` | exact (IRI) | `?assessment=/api/assessments/1` |
| `level` | exact (IRI) | `?level=/api/levels/2` |
| `start` | range | `?start[after]=2026-03-01` |
| `institute` | exact (IRI) | `?institute=/api/institutes/5` |

### Tri

| Champ | Défaut |
|---|---|
| `start` | ASC |
| `limitDateSubscribe` | ASC |

---

## Entité `ScheduledExam`

### Contraintes de validation

| Champ | Contraintes |
|---|---|
| `startDate` | Obligatoire, doit être entre `session.start` et `session.end` |
| `address` | Obligatoire |
| `room` | Optionnel, max 100 caractères |
| `examinators` | Au moins 1 examinateur obligatoire |

### Règles métier

- L'Exam référencé doit appartenir au même Assessment que la Session.
- Les examinateurs doivent être des Users membres de l'institut (TEACHER ou STAFF).

### Endpoints

| Méthode | Route | Accès | Description |
|---|---|---|---|
| `GET` | `/api/sessions/{id}/scheduled-exams` | PUBLIC (si session OPEN/CLOSE), INSTITUTE_ADMIN/STAFF sinon | Liste des épreuves planifiées. |
| `POST` | `/api/sessions/{id}/scheduled-exams` | INSTITUTE_ADMIN | Planifie une épreuve. Uniquement si session `DRAFT` ou `OPEN`. |
| `PATCH` | `/api/scheduled-exams/{id}` | INSTITUTE_ADMIN | Modifie. Uniquement si session `DRAFT` ou `OPEN`. |
| `DELETE` | `/api/scheduled-exams/{id}` | INSTITUTE_ADMIN | Supprime. Uniquement si session `DRAFT`. Interdit si des EnrollmentExams y sont liés. |

### Détail des routes

#### `GET /api/sessions/{id}/scheduled-exams`
Retourne la liste des épreuves planifiées pour une session. Chaque ScheduledExam inclut la date/heure de passage, la salle, l'adresse, l'épreuve (Exam) avec ses détails et le prix personnalisé de l'institut (InstituteExamPricing). Pour les sessions `OPEN`/`CLOSE`, cette route est publique. Pour les sessions `DRAFT`/`CANCELLED`, elle est réservée aux ADMIN/STAFF de l'institut.

- **Paramètre** : `id` — UUID de la session
- **Réponse** : `200 OK` — tableau d'objets ScheduledExam

#### `POST /api/sessions/{id}/scheduled-exams`
Planifie une épreuve dans une session. La session doit être en statut `DRAFT` ou `OPEN` (erreur `409 Conflict` sinon). L'épreuve (Exam) référencée doit appartenir au même Assessment que la session. La date de début de l'épreuve doit être comprise entre les dates de début et de fin de la session. Au moins un examinateur (membre TEACHER ou STAFF de l'institut) doit être assigné. Une session doit avoir au moins 1 ScheduledExam pour pouvoir être publiée (DRAFT → OPEN).

- **Paramètre** : `id` — UUID de la session
- **Corps de la requête** : `{ startDate, room?, exam, address, examinators? }`
- **Réponse** : `201 Created` — objet ScheduledExam créé
- **Erreurs** : `409 Conflict` — session pas en DRAFT/OPEN · `422 Unprocessable Entity` — exam d'un autre assessment, date hors plage · `404 Not Found` — session inexistante · `403 Forbidden` — rôle insuffisant

#### `PATCH /api/scheduled-exams/{id}`
Modifie partiellement une épreuve planifiée. Uniquement possible si la session est en statut `DRAFT` ou `OPEN`. Permet de changer la date, la salle, l'adresse ou les examinateurs.

- **Content-Type** : `application/merge-patch+json`
- **Réponse** : `200 OK` — objet ScheduledExam mis à jour
- **Erreurs** : `404 Not Found` — ScheduledExam inexistant · `409 Conflict` — session pas en DRAFT/OPEN · `403 Forbidden` — rôle insuffisant

#### `DELETE /api/scheduled-exams/{id}`
Supprime définitivement une épreuve planifiée. Uniquement possible si la session est en statut `DRAFT`. La suppression est **interdite** si des EnrollmentExams (inscriptions à cette épreuve) existent déjà, pour ne pas casser les inscriptions des candidats.

- **Réponse** : `204 No Content`
- **Erreurs** : `404 Not Found` — ScheduledExam inexistant · `409 Conflict` — session pas en DRAFT ou EnrollmentExams liés · `403 Forbidden` — rôle insuffisant

---

## Entité `EnrollmentSession`

### Contraintes de validation

| Champ | Contraintes |
|---|---|
| `registrationDate` | Automatique (date courante) |
| `information` | Optionnel, max 1000 caractères |

### Contraintes d'unicité

- `(user, session)` : unique — un utilisateur ne peut s'inscrire qu'une fois à une session

### Endpoints

| Méthode | Route | Accès | Description |
|---|---|---|---|
| `POST` | `/api/sessions/{id}/enroll` | USER (isVerified=true) | Inscription à une session. Conditions : session `OPEN`, places disponibles > inscriptions actuelles, `limitDateSubscribe` non dépassée. Crée automatiquement un `InstituteMembership(CUSTOMER)` si l'utilisateur n'est pas déjà membre. |
| `GET` | `/api/users/me/enrollments` | SELF | Mes inscriptions. |
| `GET` | `/api/sessions/{id}/enrollments` | INSTITUTE_ADMIN, INSTITUTE_STAFF | Inscriptions d'une session. |
| `GET` | `/api/enrollment-sessions/{id}` | SELF ou INSTITUTE_ADMIN/STAFF | Détail d'une inscription (avec EnrollmentExams, Invoice). |
| `DELETE` | `/api/enrollment-sessions/{id}` | SELF ou INSTITUTE_ADMIN | Annulation d'inscription. Déclenche le process d'annulation/remboursement si une facture est ISSUED/PAID. |

### Détail des routes

#### `POST /api/sessions/{id}/enroll`
Inscrit l'utilisateur authentifié à une session de test. Cette route effectue plusieurs vérifications avant de créer l'inscription :
1. La session doit être en statut `OPEN` (sinon `409 Conflict`)
2. Des places doivent être disponibles (sinon `409 Conflict`)
3. La date limite d'inscription ne doit pas être dépassée (sinon `422 Unprocessable Entity`)
4. L'utilisateur ne doit pas être déjà inscrit (sinon `409 Conflict`)

Si toutes les conditions sont remplies, un `EnrollmentSession` est créé avec la date d'inscription actuelle, et un `EnrollmentExam` est automatiquement créé pour chaque épreuve planifiée (ScheduledExam) de la session, en statut `REGISTERED`. Si l'utilisateur n'est pas encore membre de l'institut, un `InstituteMembership(CUSTOMER)` est automatiquement créé.

- **Paramètre** : `id` — UUID de la session
- **Réponse** : `201 Created` — objet EnrollmentSession avec EnrollmentExams imbriqués
- **Erreurs** : `404 Not Found` — session inexistante · `409 Conflict` — session pas OPEN, plus de places, déjà inscrit · `422 Unprocessable Entity` — date limite dépassée · `401 Unauthorized` — non authentifié

#### `GET /api/users/me/enrollments`
Retourne la liste des inscriptions de l'utilisateur connecté, avec le statut de chaque inscription, la session associée et les épreuves. Permet au candidat de suivre l'ensemble de ses inscriptions passées et en cours.

- **Réponse** : `200 OK` — tableau d'objets EnrollmentSession (groupe `enrollment:read:self`)
- **Erreur** : `401 Unauthorized` — non authentifié

#### `GET /api/sessions/{id}/enrollments`
Retourne la liste des inscriptions pour une session donnée. Réservé aux administrateurs et au personnel de l'institut (ADMIN, STAFF). Inclut les informations des candidats (nom, prénom, email) et le statut de chaque inscription.

- **Paramètre** : `id` — UUID de la session
- **Réponse** : `200 OK` — tableau d'objets EnrollmentSession (groupe `enrollment:read:admin`)
- **Erreurs** : `404 Not Found` — session inexistante · `403 Forbidden` — rôle insuffisant

#### `GET /api/enrollment-sessions/{id}`
Retourne le détail complet d'une inscription, incluant les EnrollmentExams (avec scores et statuts), la session et éventuellement la facture associée. Accessible au candidat propriétaire de l'inscription ou aux ADMIN/STAFF de l'institut organisateur.

- **Paramètre** : `id` — UUID de l'EnrollmentSession
- **Réponse** : `200 OK` — objet EnrollmentSession complet
- **Erreurs** : `404 Not Found` — inscription inexistante · `403 Forbidden` — ni propriétaire, ni ADMIN/STAFF de l'institut

#### `DELETE /api/enrollment-sessions/{id}`
Annule l'inscription d'un candidat à une session. Accessible au candidat lui-même ou à un administrateur de l'institut/plateforme. L'annulation n'est possible que si la session est en statut `OPEN` (ou `DRAFT` pour un admin). Tous les `EnrollmentExam` associés sont supprimés. Si une facture `ISSUED` ou `PAID` est liée à cette inscription, le processus d'avoir et de remboursement est automatiquement déclenché.

- **Réponse** : `204 No Content`
- **Erreurs** : `409 Conflict` — la session n'est pas en statut compatible pour l'annulation · `403 Forbidden` — ni propriétaire, ni ADMIN

### Groupes de sérialisation

| Groupe | Champs inclus | Utilisé par |
|---|---|---|
| `enrollment:read:self` | registrationDate, information, session (session:read:public), enrollmentExams, invoice (statut seulement) | Candidat |
| `enrollment:read:admin` | Tous les champs + user (user:read:public) + invoice (complète) | INSTITUTE_ADMIN |

---

## Entité `EnrollmentExam`

### Contraintes de validation

| Champ | Contraintes |
|---|---|
| `finalScore` | Optionnel, min 0. Modifiable uniquement par un examinateur. |
| `status` | Défaut `REGISTERED` |

### Transitions de statut

| De | Vers | Condition |
|---|---|---|
| `REGISTERED` | `PASSED` | `finalScore` >= `exam.successScore`. Renseigné par l'examinateur. |
| `REGISTERED` | `FAILED` | `finalScore` < `exam.successScore`. Renseigné par l'examinateur. |

### Endpoints

| Méthode | Route | Accès | Description |
|---|---|---|---|
| `GET` | `/api/enrollment-sessions/{id}/exams` | SELF ou INSTITUTE_ADMIN/STAFF/TEACHER | Liste des épreuves d'une inscription. |
| `PATCH` | `/api/enrollment-exams/{id}` | INSTITUTE_TEACHER (examinateur assigné au ScheduledExam) | Saisie du score. Le statut PASSED/FAILED est calculé automatiquement. |

### Détail des routes

#### `GET /api/enrollment-sessions/{id}/exams`
Retourne la liste des épreuves d'une inscription, avec pour chaque épreuve le score final (si renseigné), le statut (REGISTERED, PASSED, FAILED) et les informations de l'épreuve planifiée (exam, date, salle). Accessible au candidat propriétaire, ou aux ADMIN/STAFF/TEACHER de l'institut.

- **Paramètre** : `id` — UUID de l'EnrollmentSession
- **Réponse** : `200 OK` — tableau d'objets EnrollmentExam (groupe `enrollment-exam:read`)
- **Erreurs** : `404 Not Found` — inscription inexistante · `403 Forbidden` — accès refusé

#### `PATCH /api/enrollment-exams/{id}/score`
Permet à un examinateur de saisir le score d'un candidat pour une épreuve. L'examinateur doit être assigné au ScheduledExam correspondant (membre TEACHER ou STAFF de l'institut). Le statut est **automatiquement calculé** en comparant le score au seuil de réussite de l'épreuve (`exam.successScore`) : si `finalScore >= successScore` → `PASSED`, sinon → `FAILED`.

- **Content-Type** : `application/merge-patch+json`
- **Corps de la requête** : `{ finalScore: number }` (groupe `enrollment-exam:write:score`)
- **Réponse** : `200 OK` — objet EnrollmentExam avec score et statut calculé
- **Erreurs** : `404 Not Found` — EnrollmentExam inexistant · `403 Forbidden` — l'utilisateur n'est pas examinateur assigné à cette épreuve · `422 Unprocessable Entity` — score invalide

### Groupes de sérialisation

| Groupe | Champs inclus | Utilisé par |
|---|---|---|
| `enrollment-exam:read` | finalScore, status, scheduledExam (exam label, level, startDate) | Lecture |
| `enrollment-exam:write:score` | finalScore | Saisie du score |

---

## Entité `Invoice`

### Contraintes de validation

| Champ | Contraintes |
|---|---|
| `invoiceNumber` | Auto-généré au passage `DRAFT→ISSUED`. Unique par institut. Format : `{PREFIX}-{ANNEE}-{SEQUENCE}`. |
| `invoiceDate` | Auto-généré au passage `DRAFT→ISSUED`. |
| `serviceDate` | Optionnel. |
| `seller` | Obligatoire (Counterparty). |
| `buyer` | Obligatoire (Counterparty). |
| `invoiceType` | Obligatoire, défaut `INVOICE`. |
| `businessType` | Obligatoire. |
| `operationCategory` | Obligatoire, défaut `SERVICE`. |
| `paymentDueDate` | Obligatoire. |
| `paymentTerms` | Obligatoire pour B2B (`TEST_LICENSE`). |
| `earlyPaymentDiscount` | Obligatoire pour B2B, défaut "Néant". |
| `latePaymentPenaltyRate` | Obligatoire pour B2B. |
| `fixedRecoveryIndemnity` | Obligatoire pour B2B, défaut 40.00. |
| `totalHT` | Calculé (somme des InvoiceLine.totalHT). |
| `totalTVA` | Calculé (somme des InvoiceLine.tvaAmount). |
| `totalTTC` | Calculé (totalHT + totalTVA). |
| `currency` | Obligatoire, défaut `EUR`. |
| `status` | Défaut `DRAFT`. |
| `creditedInvoice` | Obligatoire si `invoiceType=CREDIT_NOTE`. Référence une Invoice `ISSUED` ou `PAID`. |
| `pdfPath` | Auto-généré au passage `DRAFT→ISSUED`. |

### Immutabilité

**Tous les champs sont en lecture seule lorsque `status != DRAFT`.** Aucune modification n'est autorisée sur une facture émise. Aucune suppression n'est jamais autorisée.

### Endpoints

| Méthode | Route | Accès | Description |
|---|---|---|---|
| `GET` | `/api/institutes/{id}/invoices` | INSTITUTE_ADMIN | Liste des factures émises par l'institut. |
| `GET` | `/api/users/me/invoices` | SELF | Mes factures (en tant qu'acheteur/candidat). |
| `GET` | `/api/invoices/{id}` | SELF (buyer) ou INSTITUTE_ADMIN (seller) | Détail d'une facture. |
| `GET` | `/api/invoices/{id}/pdf` | SELF (buyer) ou INSTITUTE_ADMIN (seller) | Télécharge le PDF Factur-X. |
| `POST` | `/api/invoices/{id}/issue` | INSTITUTE_ADMIN | Passage DRAFT → ISSUED. Génère le numéro et le PDF. |
| `POST` | `/api/invoices/{id}/credit-note` | INSTITUTE_ADMIN | Émet un avoir. Crée une nouvelle Invoice(CREDIT_NOTE) et passe l'originale en CANCELLED. |

### Détail des routes

#### `GET /api/institutes/{id}/invoices`
Retourne la liste paginée des factures émises par un institut. Inclut factures classiques et avoirs (credit notes). Permet aux administrateurs de l'institut de suivre la facturation. Supporte le filtrage par statut, type, type métier et date.

- **Paramètre** : `id` — UUID de l'institut
- **Réponse** : `200 OK` — tableau paginé d'objets Invoice (groupe `invoice:read:list`)
- **Erreurs** : `404 Not Found` — institut inexistant · `403 Forbidden` — l'utilisateur n'est pas ADMIN de cet institut

#### `GET /api/users/me/invoices`
Retourne la liste des factures adressées à l'utilisateur connecté (en tant qu'acheteur/candidat). Inclut le numéro de facture, la date, le montant TTC, la devise et le statut. Permet au candidat de retrouver et télécharger ses factures.

- **Réponse** : `200 OK` — tableau d'objets Invoice (groupe `invoice:read:buyer`)
- **Erreur** : `401 Unauthorized` — non authentifié

#### `GET /api/invoices/{id}`
Retourne le détail complet d'une facture, incluant les informations du vendeur et de l'acheteur (Counterparty), les lignes de facture (InvoiceLine), les totaux HT/TVA/TTC, le numéro de facture et les conditions de paiement. Accessible au candidat concerné (buyer) ou à l'administrateur de l'institut émetteur (seller).

- **Paramètre** : `id` — UUID de la facture
- **Réponse** : `200 OK` — objet Invoice complet (groupe `invoice:read:detail`)
- **Erreurs** : `404 Not Found` — facture inexistante · `403 Forbidden` — ni buyer, ni admin institut

#### `GET /api/invoices/{id}/pdf`
Télécharge le fichier PDF Factur-X de la facture. Le PDF est généré au format PDF/A-3 avec les données structurées CII embarquées (conformité Factur-X). Le PDF n'est disponible qu'une fois la facture émise (statut `ISSUED`, `PAID` ou `CANCELLED`).

- **Paramètre** : `id` — UUID de la facture
- **Réponse** : `200 OK` — fichier PDF (Content-Type: application/pdf)
- **Erreurs** : `404 Not Found` — facture inexistante ou PDF non généré · `403 Forbidden` — accès refusé

#### `POST /api/institutes/{id}/invoices`
Crée une nouvelle facture en statut `DRAFT` pour un institut. La facture est automatiquement pré-remplie : les informations du vendeur (Counterparty seller) sont tirées de l'institut, les informations de l'acheteur (Counterparty buyer) sont tirées du candidat ou de l'institut acheteur. Pour les factures de type `ENROLLMENT`, les lignes sont automatiquement créées à partir des épreuves planifiées et des prix personnalisés de l'institut. La facture reste en `DRAFT` jusqu'à son émission explicite.

- **Paramètre** : `id` — UUID de l'institut émetteur
- **Corps de la requête** : `{ businessType: "ENROLLMENT|TEST_LICENSE", enrollmentSession? }`
- **Réponse** : `201 Created` — objet Invoice en statut DRAFT avec lignes pré-remplies
- **Erreurs** : `422 Unprocessable Entity` — champs manquants · `403 Forbidden` — rôle insuffisant

#### `PATCH /api/invoices/{id}/issue`
Émet une facture, la faisant passer de `DRAFT` à `ISSUED`. Cette opération irréversible effectue plusieurs actions atomiques : calcul des totaux (HT, TVA, TTC) depuis les lignes, attribution d'un numéro séquentiel unique par institut et par année (format `{PREFIX}-{ANNEE}-{SEQUENCE}`), renseignement de la date d'émission, et génération du PDF Factur-X. Un verrou pessimiste (LOCK TABLE) garantit l'unicité de la numérotation. La facture doit contenir au moins une ligne pour être émise.

- **Content-Type** : `application/merge-patch+json`
- **Réponse** : `200 OK` — objet Invoice avec numéro, date, totaux et statut ISSUED
- **Erreurs** : `409 Conflict` — facture pas en statut DRAFT (ex: déjà émise) · `422 Unprocessable Entity` — aucune ligne de facture · `403 Forbidden` — rôle insuffisant

#### `POST /api/invoices/{id}/credit-note`
Émet un avoir (credit note) pour une facture. Crée une nouvelle Invoice de type `CREDIT_NOTE` avec des lignes en montant négatif reprenant les lignes de la facture originale (avec les mêmes taux de TVA). La facture originale passe en statut `CANCELLED`. L'avoir est directement émis (statut `ISSUED`) avec son propre numéro séquentiel. Le PDF Factur-X de l'avoir est généré automatiquement.

- **Paramètre** : `id` — UUID de la facture à créditer
- **Réponse** : `201 Created` — objet Invoice (credit note) émis
- **Erreurs** : `409 Conflict` — facture pas en statut ISSUED ou PAID · `403 Forbidden` — rôle insuffisant

### Groupes de sérialisation

| Groupe | Champs inclus | Utilisé par |
|---|---|---|
| `invoice:read:list` | invoiceNumber, invoiceDate, invoiceType, businessType, totalTTC, currency, status | Listes |
| `invoice:read:detail` | Tous les champs + invoiceLines | Détail |
| `invoice:read:buyer` | invoiceNumber, invoiceDate, totalTTC, currency, status, pdfPath | Candidat |

### Filtres

| Filtre | Type | Exemple |
|---|---|---|
| `status` | exact | `?status=ISSUED` |
| `invoiceType` | exact | `?invoiceType=CREDIT_NOTE` |
| `businessType` | exact | `?businessType=ENROLLMENT` |
| `invoiceDate` | range | `?invoiceDate[after]=2026-01-01` |

---

## Entité `InvoiceLine`

### Contraintes de validation

| Champ | Contraintes |
|---|---|
| `label` | Obligatoire, max 255 caractères |
| `description` | Optionnel, max 500 caractères |
| `quantity` | Obligatoire, min 1 |
| `unitPriceHT` | Obligatoire, min 0 |
| `tvaRate` | Obligatoire, min 0 (ex: 20.0 pour 20%) |
| `tvaAmount` | Calculé (`totalHT × tvaRate / 100`) |
| `totalHT` | Calculé (`quantity × unitPriceHT`) |
| `totalTTC` | Calculé (`totalHT + tvaAmount`) |

### Endpoints

Les InvoiceLines sont gérées comme sous-ressource de Invoice. Elles sont créées automatiquement lors du process de facturation (inscription ou achat de licence). Des lignes supplémentaires peuvent être ajoutées manuellement tant que la facture est en `DRAFT`.

### Endpoints

| Méthode | Route | Accès | Description |
|---|---|---|---|
| `GET` | `/api/invoices/{id}/lines` | SELF (buyer) ou INSTITUTE_ADMIN | Liste des lignes d'une facture. |
| `POST` | `/api/invoices/{id}/lines` | INSTITUTE_ADMIN | Ajoute une ligne à une facture DRAFT. |
| `PATCH` | `/api/invoice-lines/{id}` | INSTITUTE_ADMIN | Modifie une ligne (facture DRAFT uniquement). |
| `DELETE` | `/api/invoice-lines/{id}` | INSTITUTE_ADMIN | Supprime une ligne (facture DRAFT uniquement). |

### Détail des routes

#### `GET /api/invoices/{id}/lines`
Retourne la liste des lignes d'une facture. Chaque ligne inclut le label, la description, la quantité, le prix unitaire HT, le taux de TVA, et les montants calculés (totalHT, tvaAmount, totalTTC). Accessible au candidat (buyer) ou à l'administrateur de l'institut.

- **Paramètre** : `id` — UUID de la facture
- **Réponse** : `200 OK` — tableau d'objets InvoiceLine

#### `POST /api/invoices/{id}/lines`
Ajoute manuellement une ligne à une facture en statut `DRAFT` (ex: frais de dossier, frais supplémentaires). Les montants (totalHT, tvaAmount, totalTTC) sont automatiquement calculés à partir de la quantité, du prix unitaire HT et du taux de TVA. La facture doit être en `DRAFT` ; toute tentative d'ajout sur une facture émise retourne une erreur `409 Conflict`.

- **Paramètre** : `id` — UUID de la facture
- **Corps de la requête** : `{ label, description?, unitPriceHT, quantity, tvaRate, exam? }`
- **Réponse** : `201 Created` — objet InvoiceLine avec montants calculés
- **Erreurs** : `409 Conflict` — facture pas en statut DRAFT · `404 Not Found` — facture inexistante · `403 Forbidden` — rôle insuffisant

#### `PATCH /api/invoice-lines/{id}`
Modifie partiellement une ligne de facture. Uniquement possible si la facture est en statut `DRAFT`. Les montants sont recalculés automatiquement.

- **Content-Type** : `application/merge-patch+json`
- **Réponse** : `200 OK` — objet InvoiceLine mis à jour
- **Erreurs** : `409 Conflict` — facture pas en DRAFT · `404 Not Found` — ligne inexistante · `403 Forbidden` — rôle insuffisant

#### `DELETE /api/invoice-lines/{id}`
Supprime une ligne de facture. Uniquement possible si la facture est en statut `DRAFT`. Après suppression, les totaux de la facture devront être recalculés lors de l'émission.

- **Réponse** : `204 No Content`
- **Erreurs** : `409 Conflict` — facture pas en DRAFT · `404 Not Found` — ligne inexistante · `403 Forbidden` — rôle insuffisant

---

## Entité `Payment`

### Contraintes de validation

| Champ | Contraintes |
|---|---|
| `amount` | Obligatoire, > 0 |
| `currency` | Obligatoire, défaut `EUR` |
| `status` | Défaut `PENDING` |
| `date` | Automatique |
| `paymentMethod` | Obligatoire |
| `stripePaymentIntentId` | Obligatoire si `paymentMethod=STRIPE`, unique |
| `refundedPayment` | Obligatoire si `status=REFUNDED` |

### Contraintes d'unicité

- `stripePaymentIntentId` : unique (quand non null)

### Endpoints

| Méthode | Route | Accès | Description |
|---|---|---|---|
| `POST` | `/api/invoices/{id}/pay` | SELF (buyer) | Initie un paiement Stripe. Crée un Payment PENDING et retourne le `client_secret` Stripe pour le frontend. |
| `GET` | `/api/invoices/{id}/payments` | SELF (buyer) ou INSTITUTE_ADMIN (seller) | Liste des paiements d'une facture. |
| `POST` | `/api/webhooks/stripe` | PUBLIC (vérifié par signature Stripe) | Webhook Stripe. Met à jour le statut des Payment (COMPLETED/FAILED) et des Invoice (PAID). |

### Détail des routes

#### `POST /api/invoices/{id}/payments`
Initie un paiement pour une facture émise. Crée un objet `Payment` en statut `PENDING` et, si la méthode est Stripe, crée un `PaymentIntent` côté Stripe et retourne le `client_secret` nécessaire au frontend pour afficher le formulaire de paiement Stripe Elements. La facture doit être en statut `ISSUED` (erreur `409 Conflict` si DRAFT, PAID ou CANCELLED). Le montant et la méthode de paiement sont spécifiés par le client.

- **Paramètre** : `id` — UUID de la facture
- **Corps de la requête** : `{ amount, paymentMethod: "STRIPE|BANK_TRANSFER" }`
- **Réponse** : `201 Created` — objet Payment en statut PENDING (avec `stripeClientSecret` si Stripe)
- **Erreurs** : `409 Conflict` — facture pas en statut ISSUED · `404 Not Found` — facture inexistante · `403 Forbidden` — rôle insuffisant

#### `GET /api/invoices/{id}/payments`
Retourne la liste des paiements effectués ou en cours pour une facture. Chaque paiement inclut le montant, la méthode, le statut (PENDING, COMPLETED, FAILED, REFUNDED) et la date. Permet de suivre l'avancement du paiement d'une facture, notamment en cas de paiements partiels.

- **Paramètre** : `id` — UUID de la facture
- **Réponse** : `200 OK` — tableau d'objets Payment
- **Erreurs** : `404 Not Found` — facture inexistante · `403 Forbidden` — ni buyer, ni admin institut

#### `PATCH /api/payments/{id}/complete`
Marque un paiement comme complété (`COMPLETED`). Typiquement appelé via le webhook Stripe (`payment_intent.succeeded`). Le paiement doit être en statut `PENDING` (erreur `409 Conflict` sinon). Après la complétion, le système vérifie si la somme des paiements `COMPLETED` couvre le `totalTTC` de la facture ; si oui, la facture passe automatiquement en statut `PAID`.

- **Content-Type** : `application/merge-patch+json`
- **Réponse** : `200 OK` — objet Payment en statut COMPLETED
- **Erreur** : `409 Conflict` — paiement pas en statut PENDING

#### `PATCH /api/payments/{id}/fail`
Marque un paiement comme échoué (`FAILED`). Typiquement appelé via le webhook Stripe (`payment_intent.payment_failed`). Le paiement doit être en statut `PENDING` (erreur `409 Conflict` sinon). La facture reste en statut `ISSUED`, permettant au candidat de retenter le paiement.

- **Content-Type** : `application/merge-patch+json`
- **Réponse** : `200 OK` — objet Payment en statut FAILED
- **Erreur** : `409 Conflict` — paiement pas en statut PENDING

#### `PATCH /api/payments/{id}/refund`
Marque un paiement comme remboursé (`REFUNDED`). Typiquement appelé via le webhook Stripe (`charge.refunded`). Le paiement doit être en statut `COMPLETED` (erreur `409 Conflict` sinon). Le remboursement déclenche automatiquement la création d'un avoir (credit note) : une nouvelle facture de type `CREDIT_NOTE` est créée avec des lignes en montant négatif reprenant la facture originale, la facture originale passe en `CANCELLED`. L'avoir est directement émis avec son numéro séquentiel.

- **Content-Type** : `application/merge-patch+json`
- **Réponse** : `200 OK` — objet Payment en statut REFUNDED
- **Erreur** : `409 Conflict` — paiement pas en statut COMPLETED

#### `POST /api/webhooks/stripe`
Point d'entrée pour les notifications Stripe. Cette route est publique mais sécurisée par vérification de la signature Stripe (`Stripe-Signature` header) via le webhook secret. Traite les événements : `payment_intent.succeeded`, `payment_intent.payment_failed`, `charge.refunded` et `account.updated`. La route identifie le Payment ou StripeAccount concerné via le `PaymentIntent ID` ou le `Account ID` Stripe.

- **Réponse** : `200 OK` — confirmation de traitement
- **Erreur** : `400 Bad Request` — signature invalide ou événement non reconnu

### Règles métier

- Les statuts des Payment sont mis à jour **exclusivement par les webhooks Stripe**, jamais par des appels API directs.
- Lorsqu'un Payment passe en `COMPLETED` et que la somme des paiements COMPLETED couvre le `totalTTC` de la facture, celle-ci passe automatiquement en `PAID`.
- Un remboursement est initié côté serveur (pas par le candidat) via `POST /api/invoices/{id}/credit-note`.

---

## Cascades et comportements de suppression

| Entité supprimée | Impact |
|---|---|
| `User` (soft delete) | `isActive=false`. Ses InstituteMemberships restent intacts. Ses EnrollmentSessions restent intactes. Ses Invoices/Payments sont intouchés. |
| `Institute` (soft delete) | Ses Sessions passent en `CANCELLED` (avec remboursement des sessions OPEN/CLOSE). Ses InstituteMemberships sont conservés. Ses StripeAccount est désactivé. |
| `Assessment` (soft delete) | Interdit si des Sessions OPEN ou CLOSE y sont liées. Les Sessions DRAFT sont annulées. |
| `Session` (CANCELLED) | Toutes les EnrollmentSessions avec facture PAID → process d'avoir + remboursement. Les EnrollmentSessions avec facture DRAFT → facture supprimée (pas encore émise). |

---

## Webhooks Stripe à implémenter

| Événement | Action |
|---|---|
| `payment_intent.succeeded` | Payment → COMPLETED. Si totalTTC couvert → Invoice → PAID. |
| `payment_intent.payment_failed` | Payment → FAILED. |
| `charge.refunded` | Nouveau Payment REFUNDED lié au Payment original. |
| `account.updated` | Met à jour `StripeAccount.isActivated` selon le statut du compte Stripe Connect. |

---

## Index de base de données recommandés

| Table | Colonnes | Type | Justification |
|---|---|---|---|
| `user` | `email` | Unique | Login |
| `user` | `deleted_at` | Index | Filtre soft delete |
| `institute` | `label` | Unique | Recherche |
| `institute` | `deleted_at` | Index | Filtre soft delete |
| `assessment` | `ref` | Unique | Recherche par référence |
| `institute_membership` | `(user_id, institute_id)` | Unique | Un rôle par institut par user |
| `assessment_ownership` | `(institute_id, assessment_id, ownership_type)` | Unique | Pas de doublon |
| `enrollment_session` | `(user_id, session_id)` | Unique | Une inscription par session |
| `invoice` | `(invoice_number)` | Unique | Numérotation séquentielle |
| `invoice` | `(status)` | Index | Filtrage fréquent |
| `payment` | `stripe_payment_intent_id` | Unique (nullable) | Réconciliation Stripe |
| `session` | `(validation, start)` | Index composé | Liste sessions OPEN triées par date |
| `institute_exam_pricing` | `(institute_id, exam_id, is_active)` | Index | Lookup prix |
