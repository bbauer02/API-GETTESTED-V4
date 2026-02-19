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

- JSON-LD (format par défaut API Platform)
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
| `GET` | `/api/users/me` | SELF | Retourne le profil complet de l'utilisateur connecté. |
| `PATCH` | `/api/users/me` | SELF | Modifie le profil de l'utilisateur connecté. |
| `GET` | `/api/users` | PLATFORM_ADMIN | Liste tous les utilisateurs (avec filtres). |
| `GET` | `/api/users/{id}` | PLATFORM_ADMIN | Détail d'un utilisateur. |
| `PATCH` | `/api/users/{id}` | PLATFORM_ADMIN | Modifie un utilisateur (isActive, platformRole...). |
| `DELETE` | `/api/users/{id}` | PLATFORM_ADMIN | Soft delete d'un utilisateur. |

### Groupes de sérialisation

| Groupe | Champs inclus | Utilisé par |
|---|---|---|
| `user:read:public` | firstname, lastname, avatar | Listes publiques (examinateurs, etc.) |
| `user:read:self` | Tous les champs sauf password, deletedAt | `GET /api/users/me` |
| `user:read:admin` | Tous les champs sauf password | `GET /api/users/{id}` (PLATFORM_ADMIN) |
| `user:write:register` | email, password, firstname, lastname, civility | `POST /api/auth/register` |
| `user:write:self` | avatar, phone, phoneCountryCode, address, birthday, nativeCountry, nationality, firstlanguage, gender, previousRegistrationNumber | `PATCH /api/users/me` |
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

Les InvoiceLines sont gérées comme sous-ressource de Invoice. Elles sont créées automatiquement lors du process d'inscription ou d'achat de licence. Pas d'endpoint de modification directe (la facture est immutable une fois émise).

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
