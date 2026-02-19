# Implementation Plan — Sprint 0 : Fondations

## Task 1 : Infrastructure Docker et projet Symfony

- [ ] 1.1 Créer le `docker-compose.yml` avec les services : php (FrankenPHP 8.4), database (PostgreSQL 16), caddy (reverse proxy HTTPS), mailpit (capture emails dev) (P)
  - Volume pour le code source monté dans le container PHP
  - Variables d'environnement dans `.env` et `.env.local`
  - _Requirements: 1_

- [ ] 1.2 Créer le `Dockerfile` PHP basé sur `dunglas/frankenphp` (P)
  - Installation de Composer, extensions PHP (pdo_pgsql, intl, opcache)
  - Configuration PHP pour le développement (display_errors, xdebug optionnel)
  - _Requirements: 1_

- [ ] 1.3 Initialiser le projet Symfony 7 avec les dépendances
  - `composer create-project symfony/skeleton`
  - `composer require api-platform/core`
  - `composer require lexik/jwt-authentication-bundle`
  - `composer require nelmio/cors-bundle`
  - `composer require doctrine/doctrine-fixtures-bundle`
  - `composer require symfony/workflow`
  - `composer require --dev phpunit/phpunit symfony/test-pack`
  - Générer les clés JWT (private/public)
  - _Requirements: 1_

- [ ] 1.4 Configurer les fichiers Symfony
  - `config/packages/doctrine.yaml` : connexion PostgreSQL, mapping attributs
  - `config/packages/security.yaml` : firewalls, json_login, jwt
  - `config/packages/nelmio_cors.yaml` : CORS permissif en dev
  - `config/packages/api_platform.yaml` : format JSON-LD, pagination 30
  - `.env` : DATABASE_URL, JWT_SECRET_KEY, JWT_PUBLIC_KEY, MAILER_DSN
  - _Requirements: 1_

- [ ] 1.5 Vérifier que `docker compose up -d` démarre correctement et que `/api` affiche la doc Swagger
  - _Requirements: 1_

## Task 2 : Enums et Value Objects

- [ ] 2.1 Créer les enums PHP dans `src/Enum/` (P)
  - `PlatformRoleEnum` : ADMIN, USER (backed string)
  - `CivilityEnum` : M, MME, MLLE, AUTRE (backed string)
  - `GenderEnum` : MASCULIN, FEMININ, AUTRE, NON_SPECIFIE (backed string)
  - _Requirements: 3_

- [ ] 2.2 Créer les value objects (Doctrine Embeddables) dans `src/Entity/Embeddable/` (P)
  - `Address` : address1, address2, zipcode, city, country (ManyToOne Country, nullable)
  - `Price` : amount (float), currency (string), tva (float)
  - _Requirements: 3_

## Task 3 : Entités Country et Language

- [ ] 3.1 Créer l'entité `Country` dans `src/Entity/Country.php`
  - Mapping Doctrine : PK = code (string, pas UUID)
  - Attributs API Platform : `#[ApiResource]` avec opérations GET collection/item (PUBLIC), POST/PATCH (PLATFORM_ADMIN)
  - Validation : code NotBlank + Length(2), alpha3 NotBlank + Length(3), nameOriginal/nameEn/nameFr NotBlank
  - Relation ManyToMany vers Language (propriétaire du côté Country)
  - Groupes de sérialisation : `country:read`, `country:read:with-languages`, `country:write`
  - Filtres : SearchFilter sur code (exact), nameFr (partial), nameEn (partial)
  - _Requirements: 2_

- [ ] 3.2 Créer l'entité `Language` dans `src/Entity/Language.php`
  - Mapping Doctrine : PK = code (string, pas UUID)
  - Attributs API Platform : `#[ApiResource]` avec opérations GET collection/item (PUBLIC), POST/PATCH (PLATFORM_ADMIN)
  - Validation : code NotBlank + Length(min=2, max=3), nameOriginal/nameEn/nameFr NotBlank
  - Groupes de sérialisation : `language:read`, `language:write`
  - Filtres : SearchFilter sur code (exact), nameFr (partial)
  - _Requirements: 2_

- [ ] 3.3 Créer les fichiers JSON de référence dans `data/`
  - `data/countries.json` : intégralité des pays ISO 3166-1 (~250 entrées) avec code, alpha3, nameOriginal, nameEn, nameFr, flag (emoji), demonymFr, demonymEn
  - `data/languages.json` : intégralité des langues ISO 639-1 (~180 entrées) avec code, nameOriginal, nameEn, nameFr
  - `data/country_languages.json` : mapping pays → langues officielles/parlées (code pays → liste de codes langues)
  - Sources : packages NPM `i18n-iso-countries`, `@cospired/i18n-iso-languages` ou données Wikipedia pour les traductions
  - _Requirements: 2_

- [ ] 3.4 Créer les fixtures `LanguageFixtures` dans `src/DataFixtures/`
  - Charger `data/languages.json` et insérer toutes les langues
  - Groupe de fixtures : `reference` (données de référence, séparées des données de test)
  - _Requirements: 2_

- [ ] 3.5 Créer les fixtures `CountryFixtures` dans `src/DataFixtures/`
  - Dépend de `LanguageFixtures` (pour les relations)
  - Charger `data/countries.json` et insérer tous les pays
  - Charger `data/country_languages.json` et établir les relations ManyToMany Country → Language
  - Groupe de fixtures : `reference`
  - _Requirements: 2_

- [ ] 3.6 Générer et exécuter la migration Doctrine
  - `bin/console doctrine:migrations:diff`
  - `bin/console doctrine:migrations:migrate`
  - `bin/console doctrine:fixtures:load --group=reference`
  - Vérifier : `SELECT count(*) FROM country` → ~250, `SELECT count(*) FROM language` → ~180
  - _Requirements: 2_

## Task 4 : Entité User et Soft Delete

- [ ] 4.1 Implémenter le filtre Doctrine de soft delete
  - Créer `src/Doctrine/Filter/SoftDeleteFilter.php`
  - Enregistrer le filtre dans `config/packages/doctrine.yaml`
  - Le filtre exclut automatiquement les entités avec `deletedAt IS NOT NULL`
  - _Requirements: 4_

- [ ] 4.2 Créer l'entité `User` dans `src/Entity/User.php`
  - Implémenter `UserInterface` (Symfony Security) et `Contactable`
  - Identifier par email (`getUserIdentifier()`)
  - UUID v7 comme identifiant
  - Tous les champs selon le diagramme avec les types Doctrine corrects
  - Embeddable Address pour le champ address
  - Relations ManyToOne vers Country (nativeCountry, nationality) et Language (firstlanguage)
  - Champ deletedAt nullable pour le soft delete
  - Timestamps automatiques via `#[ORM\HasLifecycleCallbacks]` pour createdAt/updatedAt
  - _Requirements: 4_

- [ ] 4.3 Configurer les attributs API Platform sur User
  - Opérations : GetCollection (PLATFORM_ADMIN), Get, Patch (SELF ou PLATFORM_ADMIN), Delete (PLATFORM_ADMIN)
  - Endpoint custom `/api/users/me` via une opération Get avec controller/provider custom
  - Groupes de sérialisation : user:read:public, user:read:self, user:read:admin, user:write:register, user:write:self, user:write:admin
  - Filtres : SearchFilter sur email (exact), firstname/lastname (partial), BooleanFilter sur isActive/isVerified, SearchFilter sur platformRole (exact)
  - _Requirements: 4, 6_

- [ ] 4.4 Créer le `UserRepository` dans `src/Repository/UserRepository.php`
  - Étendre `ServiceEntityRepository`
  - Méthode `findOneByEmail(string $email): ?User`
  - _Requirements: 4_

- [ ] 4.5 Créer le Voter `UserVoter` dans `src/Security/Voter/UserVoter.php`
  - `VIEW_SELF` : l'utilisateur connecté accède à ses propres données
  - `EDIT_SELF` : l'utilisateur connecté modifie son profil
  - `ADMIN_ACCESS` : PLATFORM_ADMIN uniquement
  - _Requirements: 4_

- [ ] 4.6 Générer et exécuter la migration Doctrine pour User
  - _Requirements: 4_

## Task 5 : Authentification JWT

- [ ] 5.1 Configurer `security.yaml` pour l'authentification
  - Provider : `app_user_provider` basé sur `User::email`
  - Firewall `api` : stateless, jwt
  - Firewall `login` : json_login sur `/api/auth/login`
  - Access control : `/api/auth/*` public, `/api/*` authentifié
  - _Requirements: 5_

- [ ] 5.2 Créer le State Processor pour l'inscription (`src/State/UserRegistrationProcessor.php`)
  - Hasher le password via `UserPasswordHasherInterface`
  - Définir platformRole=USER, isVerified=false, isActive=true
  - Générer un token JWT de vérification (expiration 24h)
  - Envoyer l'email de vérification via `MailerInterface`
  - Retourner 201
  - _Requirements: 5_

- [ ] 5.3 Créer le controller pour la vérification email (`src/Controller/VerifyEmailController.php`)
  - Valider le token JWT (signature + expiration)
  - Extraire l'email du token
  - Mettre à jour isVerified=true, emailVerifiedAt=now()
  - Retourner 200 ou 400
  - _Requirements: 5_

- [ ] 5.4 Créer le controller pour forgot/reset password (`src/Controller/ResetPasswordController.php`)
  - `POST /api/auth/forgot-password` : chercher le user par email, générer token (1h), envoyer email, toujours 200
  - `POST /api/auth/reset-password/{token}` : valider token, hasher nouveau password, sauver, retourner 200
  - _Requirements: 5_

- [ ] 5.5 Créer le service `TokenService` dans `src/Service/TokenService.php`
  - Méthode `generateVerificationToken(User $user): string` — JWT signé, payload: email, exp: 24h
  - Méthode `generateResetToken(User $user): string` — JWT signé, payload: email, exp: 1h
  - Méthode `validateToken(string $token): array` — vérifie signature et expiration, retourne payload
  - _Requirements: 5_

- [ ] 5.6 Créer les templates d'email
  - `templates/email/verify_email.html.twig` : lien vers /api/auth/verify-email/{token}
  - `templates/email/reset_password.html.twig` : lien vers /api/auth/reset-password/{token}
  - _Requirements: 5_

- [ ] 5.7 Configurer le `EventSubscriber` pour bloquer les comptes inactifs au login
  - Écouter `lexik_jwt_authentication.on_authentication_success`
  - Si `user.isActive === false`, retourner 403 "Compte désactivé"
  - _Requirements: 5_

## Task 6 : Fixtures de test

- [ ] 6.1 Créer `UserFixtures` dans `src/DataFixtures/` (P)
  - admin@gettested.com (PLATFORM_ADMIN, vérifié, actif)
  - user1@test.com (USER, vérifié, actif)
  - user2@test.com (USER, non vérifié, actif)
  - inactive@test.com (USER, vérifié, inactif)
  - _Requirements: 4, 5_

## Task 7 : Tests fonctionnels

- [ ] 7.1 Créer `tests/Api/AuthTest.php`
  - Test register valide → 201
  - Test register email dupliqué → 422
  - Test register champs manquants → 422
  - Test login valide → 200 + JWT
  - Test login identifiants invalides → 401
  - Test login compte désactivé → 403
  - Test verify-email token valide → 200 + isVerified=true
  - Test verify-email token invalide → 400
  - _Requirements: 5_

- [ ] 7.2 Créer `tests/Api/UserTest.php`
  - Test GET /api/users/me avec JWT → 200 + champs user:read:self
  - Test GET /api/users/me sans JWT → 401
  - Test PATCH /api/users/me → 200 + champs modifiés
  - Test GET /api/users en tant que ADMIN → 200
  - Test GET /api/users en tant que USER → 403
  - Test PATCH /api/users/{id} isActive par ADMIN → 200
  - Test DELETE /api/users/{id} → soft delete, n'apparaît plus dans les listes
  - _Requirements: 4, 6_

- [ ] 7.3 Créer `tests/Api/CountryTest.php` (P)
  - Test GET /api/countries sans auth → 200 + liste
  - Test GET /api/countries/{code} → 200 + détail avec langues
  - Test POST /api/countries par USER → 403
  - Test POST /api/countries par ADMIN → 201
  - Test filtre ?nameFr=fran → résultats filtrés
  - _Requirements: 2_

- [ ] 7.4 Créer `tests/Api/LanguageTest.php` (P)
  - Test GET /api/languages sans auth → 200 + liste
  - Test POST /api/languages par USER → 403
  - Test POST /api/languages par ADMIN → 201
  - _Requirements: 2_
