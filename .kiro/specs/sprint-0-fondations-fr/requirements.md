# Requirements Document — Sprint 0 : Fondations

## Introduction

Ce sprint met en place les fondations du projet GETTESTED : infrastructure Docker, projet Symfony/API Platform, entités de référence (Country, Language), entité User complète et système d'authentification JWT. À la fin de ce sprint, un utilisateur peut s'inscrire, se connecter, vérifier son email et gérer son profil.

## Requirements

### Requirement 1 : Infrastructure Docker et projet Symfony

**Objective:** En tant que développeur, je veux un environnement Docker fonctionnel avec Symfony et API Platform, afin de pouvoir développer et tester l'API en local.

#### Acceptance Criteria
1. When `docker compose up -d` est exécuté, le système shall démarrer les containers PHP 8.4 (FrankenPHP), PostgreSQL 16 et Caddy
2. When le projet démarre, le système shall servir l'API sur `https://localhost`
3. When on accède à `/api`, le système shall afficher la documentation Swagger/OpenAPI générée par API Platform
4. The système shall inclure les bundles : `api-platform/core`, `lexik/jwt-authentication-bundle`, `nelmio/cors-bundle`, `doctrine/doctrine-fixtures-bundle`, `symfony/workflow`
5. The système shall utiliser les attributs PHP 8 pour le mapping Doctrine (pas XML ni YAML)

### Requirement 2 : Entités de référence Country et Language

**Objective:** En tant qu'utilisateur, je veux accéder à la liste des pays et des langues, afin de renseigner correctement mon profil (nationalité, pays d'origine, langue maternelle).

#### Acceptance Criteria
1. The système shall exposer une entité `Country` avec : code (ISO 3166-1 alpha-2, unique), alpha3 (ISO 3166-1 alpha-3, unique), nameOriginal, nameEn, nameFr, flag, demonymFr, demonymEn
2. The système shall exposer une entité `Language` avec : code (ISO 639-1, unique), nameOriginal, nameEn, nameFr
3. The système shall définir une relation ManyToMany entre Country et Language (langues parlées par pays)
4. When `GET /api/countries` est appelé sans authentification, le système shall retourner la liste de tous les pays
5. When `GET /api/languages` est appelé sans authentification, le système shall retourner la liste de toutes les langues
6. When `GET /api/countries/{code}` est appelé, le système shall retourner le détail du pays avec ses langues
7. When `POST /api/countries` est appelé par un utilisateur non PLATFORM_ADMIN, le système shall retourner 403
8. The système shall fournir des fichiers JSON de référence contenant l'intégralité des pays (ISO 3166-1, ~250 pays) et des langues (ISO 639-1, ~180 langues) avec les noms traduits (original, anglais, français), drapeaux emoji et gentilés
9. The système shall charger l'ensemble des pays et langues via les fixtures Doctrine à partir de ces fichiers JSON
10. The système shall établir les relations Country → Language (langues officielles/parlées) dans les fixtures

### Requirement 3 : Enums et Value Objects

**Objective:** En tant que développeur, je veux les enums et value objects de base disponibles, afin que les entités puissent les utiliser.

#### Acceptance Criteria
1. The système shall définir l'enum `PlatformRoleEnum` avec les valeurs : ADMIN, USER (backed enum string)
2. The système shall définir l'enum `CivilityEnum` avec les valeurs : M, MME, MLLE, AUTRE (backed enum string)
3. The système shall définir l'enum `GenderEnum` avec les valeurs : MASCULIN, FEMININ, AUTRE, NON_SPECIFIE (backed enum string)
4. The système shall définir le value object `Address` (embeddable Doctrine) avec : address1, address2, zipcode, city, country (référence Country)
5. The système shall définir le value object `Price` (embeddable Doctrine) avec : amount (float), currency (string), tva (float)

### Requirement 4 : Entité User

**Objective:** En tant qu'utilisateur, je veux créer un compte et gérer mon profil, afin de pouvoir utiliser la plateforme.

#### Acceptance Criteria
1. The système shall définir l'entité `User` avec les champs : email (unique, identifiant), password (hashé), civility, gender, firstname, lastname, phone, phoneCountryCode, address (embeddable), birthday, nativeCountry (Country), nationality (Country), firstlanguage (Language), isVerified (défaut false), emailVerifiedAt, isActive (défaut true), createdAt, updatedAt, previousRegistrationNumber, platformRole (défaut USER), avatar, deletedAt (soft delete)
2. The système shall implémenter l'interface `Contactable` sur User avec les méthodes : getName(), getAddress(), getZipcode(), getCity(), getCountry()
3. When `GET /api/users` est appelé par un non PLATFORM_ADMIN, le système shall retourner 403
4. When `GET /api/users` est appelé par un PLATFORM_ADMIN, le système shall retourner la liste des utilisateurs avec filtres (email, firstname, lastname, isActive, isVerified, platformRole)
5. When `GET /api/users/me` est appelé avec un JWT valide, le système shall retourner le profil complet de l'utilisateur connecté (groupe `user:read:self`)
6. When `PATCH /api/users/me` est appelé, le système shall permettre la modification des champs : avatar, phone, phoneCountryCode, address, birthday, nativeCountry, nationality, firstlanguage, gender, previousRegistrationNumber
7. When `PATCH /api/users/{id}` est appelé par un PLATFORM_ADMIN, le système shall permettre la modification de : isActive, platformRole
8. The système shall exclure automatiquement les utilisateurs soft-deleted des requêtes (filtre Doctrine)

### Requirement 5 : Authentification JWT

**Objective:** En tant qu'utilisateur, je veux m'inscrire, me connecter et gérer mon mot de passe, afin d'accéder de manière sécurisée à la plateforme.

#### Acceptance Criteria
1. When `POST /api/auth/register` est appelé avec email, password, firstname, lastname et civility valides, le système shall créer un User avec platformRole=USER, isVerified=false, isActive=true et retourner 201
2. When `POST /api/auth/register` est appelé avec un email déjà existant, le système shall retourner 422 avec un message d'erreur
3. When `POST /api/auth/register` réussit, le système shall envoyer un email de vérification contenant un lien avec un token unique
4. When `POST /api/auth/verify-email/{token}` est appelé avec un token valide, le système shall passer isVerified=true et emailVerifiedAt=now()
5. When `POST /api/auth/verify-email/{token}` est appelé avec un token expiré ou invalide, le système shall retourner 400
6. When `POST /api/auth/login` est appelé avec des identifiants valides et isActive=true, le système shall retourner un JWT (access token + refresh token)
7. When `POST /api/auth/login` est appelé avec isActive=false, le système shall retourner 403 "Compte désactivé"
8. When `POST /api/auth/refresh` est appelé avec un refresh token valide, le système shall retourner un nouveau JWT
9. When `POST /api/auth/forgot-password` est appelé avec un email existant, le système shall envoyer un email avec un lien de réinitialisation
10. When `POST /api/auth/reset-password/{token}` est appelé avec un token valide et un nouveau password, le système shall mettre à jour le mot de passe

### Requirement 6 : Groupes de sérialisation User

**Objective:** En tant que développeur, je veux des groupes de sérialisation distincts, afin que chaque contexte n'expose que les champs nécessaires.

#### Acceptance Criteria
1. The système shall définir le groupe `user:read:public` avec les champs : firstname, lastname, avatar
2. The système shall définir le groupe `user:read:self` avec tous les champs sauf password et deletedAt
3. The système shall définir le groupe `user:read:admin` avec tous les champs sauf password
4. The système shall définir le groupe `user:write:register` avec : email, password, firstname, lastname, civility
5. The système shall définir le groupe `user:write:self` avec : avatar, phone, phoneCountryCode, address, birthday, nativeCountry, nationality, firstlanguage, gender, previousRegistrationNumber
6. The système shall définir le groupe `user:write:admin` avec : isActive, platformRole
