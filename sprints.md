# Plan de sprints — GETTESTED API

Chaque sprint est **autonome et testable**. On ne passe au sprint suivant que lorsque le précédent est validé (tests passent, endpoints fonctionnent).

---

## Sprint 0 — Fondations
**Objectif** : projet Symfony fonctionnel avec Docker, authentification JWT et premiers endpoints.

### Livrables
- [ ] Docker Compose (PHP 8.4 / FrankenPHP, PostgreSQL 16, Caddy)
- [ ] Projet Symfony 7 + API Platform 4.2
- [ ] Configuration bundles : Doctrine, LexikJWT, Nelmio CORS
- [ ] Enums PHP : `PlatformRoleEnum`, `CivilityEnum`, `GenderEnum`
- [ ] Value Objects : `Address`, `Price`
- [ ] Entité `Country` (code ISO 3166-1, noms traduits, drapeau, gentilé)
- [ ] Entité `Language` (code ISO 639-1, noms traduits)
- [ ] Relation Country → Language (langues parlées par pays)
- [ ] Fixtures : pays (ISO 3166-1) + langues (ISO 639-1)
- [ ] Endpoints publics `GET /api/countries`, `GET /api/languages`
- [ ] Entité `User` (complète avec tous les champs, relations vers Country)
- [ ] Auth : register, login, verify-email, forgot/reset-password
- [ ] Endpoints `GET/PATCH /api/users/me`
- [ ] Endpoint admin `GET /api/users`
- [ ] Soft delete filter Doctrine
- [ ] Tests fonctionnels : inscription, login, profil

### Entités
```
Country, Language, User
Enums : PlatformRoleEnum, CivilityEnum, GenderEnum
ValueObjects : Address, Price
```

### Critères de validation
- `GET /api/countries` → retourne la liste des pays (publique)
- `GET /api/languages` → retourne la liste des langues (publique)
- `POST /api/auth/register` → crée un user, retourne 201
- `POST /api/auth/login` → retourne un JWT valide
- `GET /api/users/me` → retourne le profil avec le bon groupe de sérialisation
- `GET /api/users` → interdit sans rôle ADMIN plateforme
- Un user `isActive=false` ne peut pas se connecter
- Les tests PHPUnit passent

### Modèle recommandé : Sonnet

---

## Sprint 1 — Instituts et membres
**Objectif** : gestion des instituts, système de membership et invitations.

### Prérequis : Sprint 0 validé

### Livrables
- [ ] Entité `Institute` (avec interface `Contactable`)
- [ ] Entité `InstituteMembership`
- [ ] Enum `InstituteRoleEnum`
- [ ] CRUD Institut (create, read, update, soft delete)
- [ ] Endpoint d'invitation de membres (`POST /api/institutes/{id}/memberships/invite`)
- [ ] Voters : qui peut créer/modifier un institut, qui peut inviter
- [ ] Entité `StripeAccount` (structure seulement, pas d'intégration Stripe)
- [ ] Tests fonctionnels : création institut, invitation, rôles

### Entités
```
Institute, InstituteMembership, StripeAccount
Enum : InstituteRoleEnum
```

### Critères de validation
- `POST /api/institutes` → crée un institut + membership ADMIN automatique pour le créateur
- `POST /api/institutes/{id}/memberships/invite` → seul INSTITUTE_ADMIN peut inviter
- `GET /api/institutes` → liste publique
- `PATCH /api/institutes/{id}` → interdit si pas INSTITUTE_ADMIN
- Un USER non vérifié ne peut pas créer d'institut
- Un INSTITUTE_ADMIN ne peut pas se retirer s'il est le dernier ADMIN
- Les tests PHPUnit passent

### Modèle recommandé : Sonnet (Opus pour les voters)

---

## Sprint 2 — Tests et épreuves
**Objectif** : gestion du catalogue de tests (Assessment), niveaux, compétences et épreuves.

### Prérequis : Sprint 1 validé

### Livrables
- [ ] Entité `Assessment` (avec hiérarchie parent/child)
- [ ] Entité `Level`
- [ ] Entité `Skill` (avec hiérarchie parent/child)
- [ ] Entité `Exam`
- [ ] Enum `OwnershipTypeEnum`
- [ ] Entité `AssessmentOwnership`
- [ ] Entité `InstituteExamPricing`
- [ ] Endpoint `POST /api/assessments` (PLATFORM_ADMIN → `isInternal=true`)
- [ ] Endpoint `POST /api/institutes/{id}/assessments` (INSTITUTE_ADMIN → `isInternal=false` + AssessmentOwnership OWNER auto)
- [ ] CRUD Level, Skill, Exam en sous-ressources d'Assessment
- [ ] Voter : propriétaire du test (plateforme ou institut) peut modifier
- [ ] Tests fonctionnels : création test interne/institut, ajout épreuves, pricing

### Entités
```
Assessment, Level, Skill, Exam, AssessmentOwnership, InstituteExamPricing
Enum : OwnershipTypeEnum
```

### Critères de validation
- `POST /api/assessments` → réservé PLATFORM_ADMIN, crée avec `isInternal=true`
- `POST /api/institutes/{id}/assessments` → réservé INSTITUTE_ADMIN, crée `isInternal=false` + ownership OWNER
- Un Exam doit avoir au moins 1 Skill
- Le Level d'un Exam doit appartenir au même Assessment
- `InstituteExamPricing` : un seul prix actif par (institut, exam)
- Un institut ne peut pas être OWNER et BUYER du même test
- Les tests PHPUnit passent

### Modèle recommandé : Sonnet

---

## Sprint 3 — Sessions et planification
**Objectif** : créer des sessions de test, planifier les épreuves, gérer le cycle de vie des sessions.

### Prérequis : Sprint 2 validé

### Livrables
- [ ] Entité `Session` avec state machine (`symfony/workflow` : DRAFT → OPEN → CLOSE → CANCELLED)
- [ ] Entité `ScheduledExam`
- [ ] Relation Session → Institute
- [ ] Relation ScheduledExam → User (examinateurs)
- [ ] Endpoint de transition de statut `PATCH /api/sessions/{id}/transition`
- [ ] Conditions de transition (StripeAccount activé, au moins 1 ScheduledExam, etc.)
- [ ] Liste publique des sessions OPEN
- [ ] Champs modifiables selon le statut
- [ ] Voters : INSTITUTE_ADMIN pour créer/modifier, PUBLIC pour lire sessions OPEN
- [ ] Tests fonctionnels : création session, planification épreuves, transitions

### Entités
```
Session, ScheduledExam
```

### Critères de validation
- `POST /api/institutes/{id}/sessions` → crée en DRAFT
- DRAFT → OPEN : échoue si pas de StripeAccount activé, pas de ScheduledExam, ou Assessment sans Skill
- OPEN → CLOSE : fonctionne
- `GET /api/sessions` → ne retourne que les sessions OPEN (publique)
- `GET /api/institutes/{id}/sessions` → retourne tous les statuts (INSTITUTE_ADMIN)
- Les examinateurs doivent être TEACHER ou STAFF de l'institut
- Impossible de modifier `start`/`end` si session OPEN
- Les tests PHPUnit passent

### Modèle recommandé : Opus (state machine + conditions de transition)

---

## Sprint 4 — Inscriptions
**Objectif** : permettre aux candidats de s'inscrire à une session et choisir leurs épreuves.

### Prérequis : Sprint 3 validé

### Livrables
- [ ] Entité `EnrollmentSession`
- [ ] Entité `EnrollmentExam`
- [ ] Endpoint `POST /api/sessions/{id}/enroll` (inscription candidat)
- [ ] Création automatique InstituteMembership CUSTOMER
- [ ] Vérification : session OPEN, places disponibles, `limitDateSubscribe` non dépassée, user vérifié
- [ ] Endpoint `GET /api/users/me/enrollments` (mes inscriptions)
- [ ] Endpoint `GET /api/sessions/{id}/enrollments` (INSTITUTE_ADMIN)
- [ ] Endpoint `PATCH /api/enrollment-exams/{id}` (saisie score par examinateur)
- [ ] Calcul automatique PASSED/FAILED selon `successScore`
- [ ] Annulation d'inscription `DELETE /api/enrollment-sessions/{id}`
- [ ] Tests fonctionnels : inscription, choix épreuves, saisie scores

### Entités
```
EnrollmentSession, EnrollmentExam
```

### Critères de validation
- `POST /api/sessions/{id}/enroll` → crée EnrollmentSession + EnrollmentExams pour les épreuves obligatoires
- Impossible de s'inscrire si session pas OPEN, plus de places, ou date dépassée
- Un user non vérifié ne peut pas s'inscrire
- Un user ne peut s'inscrire qu'une fois par session
- Seul l'examinateur assigné au ScheduledExam peut saisir un score
- Score >= successScore → PASSED, sinon → FAILED (automatique)
- Les tests PHPUnit passent

### Modèle recommandé : Opus (logique d'inscription multi-conditions)

---

## Sprint 5 — Facturation
**Objectif** : génération des factures conformes, cycle de vie DRAFT → ISSUED → PAID, avoirs.

### Prérequis : Sprint 4 validé

### Livrables
- [ ] Entité `Invoice` avec state machine (DRAFT → ISSUED → PAID / CANCELLED)
- [ ] Entité `InvoiceLine`
- [ ] Value Object `Counterparty`
- [ ] Enums : `InvoiceTypeEnum`, `InvoiceStatusEnum`, `BusinessTypeEnum`, `OperationCategoryEnum`
- [ ] Génération auto de la facture DRAFT à l'inscription (ENROLLMENT)
- [ ] Calcul des prix (priorité InstituteExamPricing > Exam.price)
- [ ] Endpoint `POST /api/invoices/{id}/issue` (DRAFT → ISSUED, génère numéro séquentiel)
- [ ] Numérotation séquentielle par institut (sans rupture)
- [ ] Immutabilité : blocage de toute modification si `status != DRAFT`
- [ ] Endpoint `POST /api/invoices/{id}/credit-note` (émet un avoir)
- [ ] Endpoint `GET /api/invoices/{id}/pdf` (génération PDF)
- [ ] Relation Invoice → AssessmentOwnership (pour les factures TEST_LICENSE)
- [ ] Tests fonctionnels : création facture, émission, avoir, immutabilité

### Entités
```
Invoice, InvoiceLine, Counterparty
Enums : InvoiceTypeEnum, InvoiceStatusEnum, BusinessTypeEnum, OperationCategoryEnum
```

### Critères de validation
- L'inscription (sprint 4) génère automatiquement une Invoice DRAFT avec les bonnes InvoiceLines
- `POST /api/invoices/{id}/issue` → attribue le numéro séquentiel, gèle tous les champs
- `PATCH /api/invoices/{id}` → 403 si `status != DRAFT`
- Les numéros sont séquentiels et sans rupture par institut
- `POST /api/invoices/{id}/credit-note` → crée un CREDIT_NOTE référençant l'original, passe l'original en CANCELLED
- Le PDF contient toutes les mentions légales obligatoires
- Les montants totalHT/totalTVA/totalTTC sont calculés correctement
- Les tests PHPUnit passent

### Modèle recommandé : Opus (conformité légale, immutabilité, numérotation)

---

## Sprint 6 — Paiements Stripe
**Objectif** : intégration Stripe complète (paiement, webhooks, remboursement).

### Prérequis : Sprint 5 validé

### Livrables
- [ ] Entité `Payment`
- [ ] Enums : `PaymentStatusEnum`, `PaymentMethodEnum`
- [ ] Intégration Stripe Connect pour les StripeAccount instituts
- [ ] Endpoint `POST /api/invoices/{id}/pay` → crée PaymentIntent Stripe, retourne `client_secret`
- [ ] Webhook `POST /api/webhooks/stripe` (vérification signature)
- [ ] Handler `payment_intent.succeeded` → Payment COMPLETED → Invoice PAID
- [ ] Handler `payment_intent.payment_failed` → Payment FAILED
- [ ] Handler `charge.refunded` → Payment REFUNDED
- [ ] Handler `account.updated` → StripeAccount.isActivated
- [ ] Process d'annulation : avoir + remboursement Stripe
- [ ] Process d'annulation de session CANCELLED → remboursement en masse
- [ ] Tests fonctionnels : paiement (mock Stripe), webhook, remboursement

### Entités
```
Payment
Enums : PaymentStatusEnum, PaymentMethodEnum
```

### Critères de validation
- `POST /api/invoices/{id}/pay` → retourne un `client_secret` Stripe
- Webhook `payment_intent.succeeded` → Payment COMPLETED + Invoice PAID si totalTTC couvert
- Webhook `payment_intent.payment_failed` → Payment FAILED, le candidat peut réessayer
- Plusieurs tentatives de paiement sur la même facture fonctionnent
- L'annulation d'une inscription avec facture PAID → avoir + refund Stripe
- L'annulation d'une session → remboursement de toutes les inscriptions payées
- Les tests PHPUnit passent (avec mock Stripe)

### Modèle recommandé : Opus (intégration Stripe, idempotence, webhooks)

---

## Sprint 7 — Achat de licence de test (B2B)
**Objectif** : permettre l'achat de tests entre instituts avec facturation B2B.

### Prérequis : Sprint 6 validé

### Livrables
- [ ] Endpoint `POST /api/institutes/{id}/ownerships` (demande d'achat BUYER)
- [ ] Génération automatique de la facture B2B (TEST_LICENSE)
- [ ] Mentions B2B obligatoires (SIREN, TVA, pénalités de retard, indemnité forfaitaire)
- [ ] Paiement via Stripe Connect (transfert inter-instituts)
- [ ] Tests fonctionnels : achat licence, facturation B2B, paiement

### Entités
```
Aucune nouvelle entité — utilise AssessmentOwnership, Invoice, Payment
```

### Critères de validation
- `POST /api/institutes/{id}/ownerships` → crée ownership BUYER + Invoice DRAFT (TEST_LICENSE)
- La facture B2B contient toutes les mentions légales (SIREN, TVA, pénalités...)
- Le paiement transite par Stripe Connect entre les deux comptes
- Un institut ne peut pas acheter un test dont il est déjà propriétaire
- Les tests PHPUnit passent

### Modèle recommandé : Sonnet (réutilise la logique des sprints 5-6)

---

## Résumé visuel

```
Sprint 0  ▶  Sprint 1  ▶  Sprint 2  ▶  Sprint 3  ▶  Sprint 4  ▶  Sprint 5  ▶  Sprint 6  ▶  Sprint 7
Fondations   Instituts    Catalogue    Sessions    Inscriptions  Facturation  Paiements    Licences B2B
                          de tests
Country      Institute    Assessment   Session     Enrollment    Invoice      Payment      Ownership
Language     Membership   Level        Scheduled   Enrollment    InvoiceLine               (BUYER flow)
User         StripeAcct   Skill        Exam        Exam          Counterparty
Auth JWT                  Exam
                          Ownership
                          Pricing
```

| Sprint | Entités | Estimation | Modèle |
|---|---|---|---|
| 0 - Fondations | User, Country, enums, VO | 2-3h | Sonnet |
| 1 - Instituts | Institute, Membership, Stripe | 1-2h | Sonnet + Opus (voters) |
| 2 - Catalogue | Assessment, Level, Skill, Exam, Ownership, Pricing | 2-3h | Sonnet |
| 3 - Sessions | Session, ScheduledExam | 2-3h | Opus |
| 4 - Inscriptions | EnrollmentSession, EnrollmentExam | 2-3h | Opus |
| 5 - Facturation | Invoice, InvoiceLine, Counterparty | 3-4h | Opus |
| 6 - Paiements | Payment + Stripe | 3-4h | Opus |
| 7 - Licences B2B | (flow B2B) | 1-2h | Sonnet |
| **Total** | | **~16-24h** | |
