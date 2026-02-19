# Plan de sprints — GETTESTED API

Chaque sprint est **autonome et testable**. On ne passe au sprint suivant que lorsque le précédent est validé (tests passent, endpoints fonctionnent).

---

## Résumé

| Sprint | Contenu | Statut |
|--------|---------|--------|
| 0 | Fondations (User, Country, Language, Auth JWT) | ✅ |
| 1 | Entités du domaine métier (12 entités, 3 enums, 12 repositories) | ✅ |
| 2 | Instituts & Membres (logique métier, voters, invitation, tests) | → À faire |
| 3 | Catalogue de tests (Assessment endpoints, ownership, pricing, validations) | Planifié |
| 4 | Sessions & Planification (state machine, transitions, ScheduledExam) | Planifié |
| 5 | Inscriptions (EnrollmentSession, EnrollmentExam, scores) | Planifié |
| 6 | Facturation (Invoice, InvoiceLine, Counterparty, immutabilité) | Planifié |
| 7 | Paiements Stripe (Payment, webhooks, remboursements) | Planifié |
| 8 | Licences B2B (flux achat inter-instituts) | Planifié |

---

## Sprint 0 — Fondations ✅
**Objectif** : projet Symfony fonctionnel avec Docker, authentification JWT et premiers endpoints.

### Livrables
- [x] Docker Compose (PHP 8.4 / FrankenPHP, PostgreSQL 16, Caddy)
- [x] Optimisation perf Docker Windows : volumes natifs pour `var/` et `vendor/`
- [x] Projet Symfony 7 + API Platform 4.2
- [x] Format par défaut : **JSON** (`application/json`) au lieu de JSON-LD
- [x] Configuration `patch_formats` : `application/merge-patch+json` pour les PATCH
- [x] Configuration bundles : Doctrine, LexikJWT, Nelmio CORS
- [x] Enums PHP : `PlatformRoleEnum`, `CivilityEnum`, `GenderEnum`
- [x] Value Objects : `Address` (avec groupes de sérialisation pour User et Institute)
- [x] Entité `Country` (code ISO 3166-1, noms traduits, drapeau, gentilé)
- [x] Entité `Language` (code ISO 639-1, noms traduits)
- [x] Relation Country → Language (langues parlées par pays)
- [x] Sérialisation : Country et Language embarqués en objets complets dans les réponses User
- [x] Fixtures : pays (ISO 3166-1) + langues (ISO 639-1)
- [x] Fixtures Users : 4 utilisateurs réalistes (Baptiste Bauer/admin, Ayaka Oyama, Christophe Lefebre, Didier Moulard)
- [x] Endpoints publics `GET /api/countries`, `GET /api/languages`
- [x] Entité `User` (complète avec tous les champs, relations vers Country/Language)
- [x] Auth : register, login, verify-email, forgot/reset-password
- [x] Endpoints `GET/PATCH /api/users/me`
- [x] `PATCH /api/users/me` : champs modifiables — email, civility, firstname, lastname, avatar, gender, phone, phoneCountryCode, address, birthday, nativeCountry, nationality, firstlanguage, previousRegistrationNumber
- [x] Changement d'email → re-vérification automatique (isVerified=false, email de vérification envoyé)
- [x] `UserMePatchProcessor` : processor custom pour merge correct des données
- [x] Validation par groupes : `validationContext` sur les opérations PATCH pour éviter les faux positifs NotBlank
- [x] Endpoint admin `GET /api/users`, `PATCH /api/users/{id}` (isActive, platformRole)
- [x] Soft delete filter Doctrine
- [x] Collection Postman Sprint 0 (`postman/GETTESTED-Sprint0.postman_collection.json`)
- [x] Tests fonctionnels : inscription, login, profil

---

## Sprint 1 — Entités du domaine métier ✅
**Objectif** : créer toutes les entités du domaine métier avec leurs relations, enums et repositories.

### Livrables
- [x] Entité `Institute` (avec interface `Contactable`)
- [x] Entité `InstituteMembership`
- [x] Enum `InstituteRoleEnum`
- [x] Entité `StripeAccount`
- [x] Entité `Assessment` (avec hiérarchie parent/child)
- [x] Entité `Level`
- [x] Entité `Skill` (avec hiérarchie parent/child)
- [x] Entité `Exam`
- [x] Enum `OwnershipTypeEnum`
- [x] Entité `AssessmentOwnership`
- [x] Entité `Session`
- [x] Entité `ScheduledExam`
- [x] Entité `EnrollmentSession`
- [x] 12 repositories correspondants

---

## Sprint 2 — Instituts & Membres
**Objectif** : logique métier des instituts, système de membership, voters et invitations.

### Prérequis : Sprint 1 validé

### Livrables
- [ ] Fixtures Institute (2 instituts de test)
- [ ] `InstituteVoter` (VIEW, EDIT, DELETE, MANAGE_MEMBERS)
- [ ] Opérations API Platform de `Institute` avec voters
- [ ] `InstituteCreateProcessor` (membership ADMIN auto à la création)
- [ ] Sous-ressource `POST /api/institutes/{id}/memberships` (invitation)
- [ ] `MembershipInviteProcessor` + `InstituteMembershipProvider`
- [ ] Access control dans `security.yaml`
- [ ] Tests fonctionnels : CRUD institut, invitation, rôles

### Critères de validation
- `POST /api/institutes` → crée un institut + membership ADMIN automatique pour le créateur
- `POST /api/institutes/{id}/memberships` → seul INSTITUTE_ADMIN peut inviter
- `GET /api/institutes` → liste publique (200 sans auth)
- `PATCH /api/institutes/{id}` → interdit si pas INSTITUTE_ADMIN ou PLATFORM_ADMIN
- `DELETE /api/institutes/{id}` → réservé PLATFORM_ADMIN
- Les tests PHPUnit passent

---

## Sprint 3 — Catalogue de tests
**Objectif** : gestion du catalogue de tests (Assessment), niveaux, compétences, épreuves et pricing.

### Prérequis : Sprint 2 validé

### Livrables
- [ ] Endpoints CRUD `Assessment` (interne plateforme + institut)
- [ ] Endpoint `POST /api/institutes/{id}/assessments` (INSTITUTE_ADMIN → `isInternal=false` + AssessmentOwnership OWNER auto)
- [ ] CRUD Level, Skill, Exam en sous-ressources d'Assessment
- [ ] `AssessmentOwnership` endpoints
- [ ] `InstituteExamPricing` endpoints (prix custom par institut)
- [ ] Voters : propriétaire du test peut modifier
- [ ] Tests fonctionnels

---

## Sprint 4 — Sessions & Planification
**Objectif** : créer des sessions de test, planifier les épreuves, gérer le cycle de vie des sessions.

### Prérequis : Sprint 3 validé

### Livrables
- [ ] State machine Session (`symfony/workflow` : DRAFT → OPEN → CLOSE → CANCELLED)
- [ ] Endpoint de transition `PATCH /api/sessions/{id}/transition`
- [ ] Conditions de transition (StripeAccount activé, au moins 1 ScheduledExam, etc.)
- [ ] `ScheduledExam` endpoints avec examinateurs (TEACHER/STAFF)
- [ ] Liste publique des sessions OPEN
- [ ] Champs modifiables selon le statut
- [ ] Tests fonctionnels

---

## Sprint 5 — Inscriptions
**Objectif** : permettre aux candidats de s'inscrire à une session et choisir leurs épreuves.

### Prérequis : Sprint 4 validé

### Livrables
- [ ] Endpoint `POST /api/sessions/{id}/enroll` (inscription candidat)
- [ ] Création automatique InstituteMembership CUSTOMER
- [ ] `EnrollmentExam` avec saisie de score par examinateur
- [ ] Calcul automatique PASSED/FAILED selon `successScore`
- [ ] Annulation d'inscription
- [ ] Tests fonctionnels

---

## Sprint 6 — Facturation
**Objectif** : génération des factures conformes, cycle de vie DRAFT → ISSUED → PAID, avoirs.

### Prérequis : Sprint 5 validé

### Livrables
- [ ] Entités `Invoice`, `InvoiceLine`, `Counterparty`
- [ ] Enums facturation
- [ ] Génération auto de la facture DRAFT à l'inscription
- [ ] Numérotation séquentielle par institut
- [ ] Immutabilité si `status != DRAFT`
- [ ] Avoir (credit note)
- [ ] Tests fonctionnels

---

## Sprint 7 — Paiements Stripe
**Objectif** : intégration Stripe complète (paiement, webhooks, remboursement).

### Prérequis : Sprint 6 validé

### Livrables
- [ ] Entité `Payment` + enums
- [ ] Stripe Connect pour les comptes instituts
- [ ] Endpoint `POST /api/invoices/{id}/pay`
- [ ] Webhook Stripe (payment_intent.succeeded, failed, charge.refunded, account.updated)
- [ ] Process d'annulation : avoir + remboursement Stripe
- [ ] Tests fonctionnels (mock Stripe)

---

## Sprint 8 — Licences B2B
**Objectif** : permettre l'achat de tests entre instituts avec facturation B2B.

### Prérequis : Sprint 7 validé

### Livrables
- [ ] Endpoint `POST /api/institutes/{id}/ownerships` (demande d'achat BUYER)
- [ ] Génération automatique de la facture B2B (TEST_LICENSE)
- [ ] Mentions B2B obligatoires
- [ ] Paiement via Stripe Connect (transfert inter-instituts)
- [ ] Tests fonctionnels

---

## Résumé visuel

```
Sprint 0  ▶  Sprint 1  ▶  Sprint 2  ▶  Sprint 3  ▶  Sprint 4  ▶  Sprint 5  ▶  Sprint 6  ▶  Sprint 7  ▶  Sprint 8
Fondations   Entités      Instituts    Catalogue    Sessions    Inscriptions  Facturation  Paiements    Licences B2B
             domaine      & Membres    de tests                                            Stripe
```
