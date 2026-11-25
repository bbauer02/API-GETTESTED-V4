# Spécificités

Ce fichier décrit les relations entre les objets du diagramme de classes présent dans le fichier `diagramme.puml`.

## Objet `User`

Cet objet permet de décrire un utilisateur de la plateforme.

### Identification et authentification

- `email` : identifiant unique de connexion (UserIdentifier Symfony Security). Sert à la fois de login et d'adresse de contact.
- `password` : mot de passe hashé
- `isVerified` : indique si l'adresse email a été vérifiée (par lien de confirmation)
- `emailVerifiedAt` : date de vérification de l'email
- `isActive` : permet de désactiver/bloquer un compte sans le supprimer. Un compte inactif ne peut pas se connecter.

### Informations personnelles

- `avatar` : chemin vers la photo de profil
- `civility` : civilité (`CivilityEnum` : M, MME, MLLE, AUTRE)
- `gender` : genre (`GenderEnum` : MASCULIN, FEMININ, AUTRE, NON_SPECIFIE)
- `firstname`, `lastname` : prénom et nom
- `phone` : numéro de téléphone (format national)
- `phoneCountryCode` : indicatif téléphonique international (ex: "+33", "+81")
- `birthday` : date de naissance
- `address` : adresse complète (ValueObject `Address`)
- `nativeCountry` : pays d'origine (type `Country`)
- `nationality` : nationalité (type `Country`)
- `firstlanguage` : langue maternelle
- `previousRegistrationNumber` : numéro d'inscription antérieur (pour les candidats ayant déjà passé des tests)

### Rôle plateforme

`platformRole` permet de déterminer si l'utilisateur est un Administrateur ou un simple utilisateur de la plateforme.

* `ADMIN` (Pleins pouvoir sur la plateforme)
* `USER` (Les utilisateurs standards)

### Horodatage

- `createdAt` : date de création du compte
- `updatedAt` : date de dernière modification

### Règles métier

Seuls les utilisateurs `ADMIN` ont le droit de créer des tests (`Assessment`) et les Administrateurs d'instituts. Les tests créés par `ADMIN`, sont achetables par les instituts qui pourront les exploiter ou exploitable, mais en payant des frais.
Les tests créés par un institut sont achetables par les autres instituts qui pourront les exploiter.

Un utilisateur dont `isActive=false` ne peut pas se connecter ni effectuer d'opérations. Un utilisateur dont `isVerified=false` peut se connecter mais avec des fonctionnalités restreintes (il ne peut pas s'inscrire à une session ni créer d'institut tant que l'email n'est pas vérifié).


## Objet `Institute`

Un Institut représente une organisation qui va créer des sessions de test de langue.

Le personnel travaillant dans un institut est constitué :

* Les enseignants : `TEACHER`
* Les gestionnaires : `ADMIN`
* Le personnel : `STAFF`

Lorsqu'un utilisateur va s'inscrire sur la plateforme, il aura le choix :

- De rejoindre une session de test : L'utilisateur devient alors un client `CUSTOMER` de l'institut.
- De créer un nouvel institut et d'en devenir le gestionnaire `ADMIN`.

## Objet `InstituteMembership`

C'est dans cet Objet que l'on va pouvoir récupérer pour un institut l'ensemble des utilisateurs et leur droit d'accès.


## Objet `StripeAccount`

C'est dans cet Objet que l'on trouvera des informations **Stripe**

## Objet `Assessment`

Un `Assessment` est un test comme le TOIEC, le DELF, le DALF ou le JLPT. Un test pour être enfant d'un autre test et hérite donc de ses niveaux.
Un test `Assessment` interne est un test dont le créateur est le gestionnaire de la plateforme gettested.

1. Quand `isInternal=true` : C'est un test dont le propriétaire est la plateforme.
2. Quand `isInternal=false` : on sait que le propriétaire est un institut (via `AssessmentOwnership(OWNER)`).



## Objet `Level`

Les tests possèdent des niveaux de difficulté comme A1, B1, B2 ou N1, N2…

## Objet `Exam`

Les tests `Assessment`, sont constitués d'un ensemble d'épreuves `Exam`.
Les épreuves sont aussi liées à un niveau.
Une épreuve peut être écrite ou orale (`isWritten`).
Lorsque les candidats s'inscrivent à une session de test, il y a des épreuves obligatoires et des épreuves optionnelles.
Pour réussir une épreuve il faut atteindre un score minimal définit dans : `successScore`.

Les épreuves possèdent un prix.
La somme des prix des exams permet de définir le tarif d'une session de test. L'utilisateur peut choisir des épreuves facultatives.
Le créateur d'un test doit définir le tarif de chaque épreuves.

Par contre un institut qui exploite le test d'un autre institut sous contrat (voir `TestContract`) pourra définir son propre tarif de vente aux candidats, défini par l'objet : `InstituteExamPricing`.

Chaque épreuve a un type (`examType`) : compréhension écrite, compréhension orale, expression écrite, expression orale ou autre. Les examinateurs du centre de passation ne notent que les épreuves d'**expression orale**.
Le prix prioritaire sera : le prix définit par l'organisateur de la session. Si absent alors se sera le prix définit par le créateur de la plateforme.



## Objet `Skill`

Un test valide un ensemble de compétences. (Savoir lire les Hiragana, savoir conjuguer au futur…).
Les épreuves d'un test servent à valider ces compétences.
Plus tard, nous allons avoir des questions qui seront aussi associées à des compétences. Ainsi, pour une épreuve, nous ne choisirons que des questions validant les compétences d'une épreuve.

## Objet `Session`

Une session de test est définie par une date de début et de fin.
Une date limite pour s'inscrire. Et un nombre de places limité.
`Validation` possede plusieurs valeur :

* DRAFT => Un brouillon, la session n'est pas publiée.
* OPEN => Inscription OUVERTE.
* LOCKED => Inscriptions VERROUILLÉES : verrouillage manuel, ou automatique à l'expiration de la date limite d'inscription.
* VALIDATED => Validation définitive par l'organisateur, possible uniquement depuis LOCKED. Elle vaut **commande auprès du propriétaire du test** (écran de rappel du contrat et du total TTC ; date et identité de la personne qui valide). Plus aucune désinscription n'est possible.
* CANCELLED => Session Annulée, les inscris sont remboursées ou les demandes de paiement annulée. Une session VALIDATED ne peut être annulée que pour **cas de force majeure**, par l'ADMIN de l'organisateur, avec un motif, tant que les copies n'ont pas été transmises au propriétaire : la commande est annulée sans facture et le propriétaire est notifié.

Le propriétaire du test est notifié dès la création d'une session de son test, même non validée.
La suite (copies, correction, résultats, attestations) est suivie par la commande (`SessionOrder`) : voir `SPEC/contrats-correction-attestations/design.md`.

## Objet `EnrollmentSession`

Cet objet représente une inscription à une session.
Il est associé à `User` et à `Session`.


## Objet `ScheduledExam`

Lorsque l'on organise une session, le détail d'organisation des épreuves est défini par l'objet `ScheduledExam`.
La date de départ, la localisation, la salle.
Une épreuve est liée à 1 ou plusieurs examinateurs.
Les examinateurs (membres du centre de passation) saisissent une note et valident des compétences **uniquement sur les épreuves d'expression orale**.
**Seul le propriétaire du test** déclenche la correction des copies, contre-évalue les épreuves orales, valide les résultats et édite les attestations de résultat.

## Objet `AssessmentOwnership`

Les instituts peuvent créer des tests complets. Ils deviennent alors le propriétaire du test qu'ils ont créé `OwnershipTypeEnum->OWNER`.
Un test n'est **pas vendu** à d'autres instituts : il est exploité sous **contrat** (`TestContract`) entre le propriétaire et le centre de passation. Le contrat fixe un prix par épreuve, une durée, s'il est renouvelable, et ouvre le droit d'organiser des sessions du test.
La relation `OwnershipTypeEnum->BUYER` est dépréciée et remplacée par le contrat. La plateforme est propriétaire des tests internes et suit le même principe.

## Objet `InstituteExamPricing`

Lorsque les instituts exploitent un test dont ils ne sont pas le propriétaire, ils peuvent définir un prix de vente personnalisé pour chaque épreuve du test (prix facturé aux candidats). Ce prix est indépendant du prix du contrat, que le propriétaire facture à l'institut.
Dans le cas où un prix personnalisé n'est pas défini dans `InstituteExamPricing`, le prix défini dans l'épreuve sera pris en compte.

---

# Facturation et Paiements

Cette section décrit le système de facturation conforme aux normes européennes (Directive TVA 2006/112/CE) et françaises (Art. 242 nonies A du CGI, loi anti-fraude NF525).

## Objet `Counterparty` (ValueObject)

Représente une partie prenante (vendeur ou acheteur) sur une facture. Contient toutes les **mentions légales obligatoires** pour la facturation B2B en France :

- `name` : raison sociale
- `address` : adresse complète (via `Address`)
- `vatNumber` : numéro de TVA intracommunautaire
- `siren` : numéro SIREN (9 chiffres) — obligatoire en B2B depuis juillet 2024
- `siret` : numéro SIRET (14 chiffres, niveau établissement)
- `legalForm` : forme juridique (SAS, SARL, EURL...)
- `shareCapital` : capital social (ex: "10 000 EUR")
- `rcsCity` : ville du Registre du Commerce et des Sociétés

**Note B2C** : pour les factures d'inscription candidat, seuls `name` et `address` sont requis côté acheteur. Les champs SIREN, forme juridique, etc. sont facultatifs.

## Objet `Invoice`

Représente une facture ou un avoir. Une fois émise (statut `ISSUED`), la facture est **immutable** conformément à la loi anti-fraude NF525. Aucune modification ni suppression n'est autorisée.

### Champs principaux

- `invoiceNumber` : numéro séquentiel, unique, sans rupture de séquence (obligatoire Art. 242 nonies A). Format recommandé : `{PREFIX_INSTITUT}-{ANNEE}-{SEQUENCE}` (ex: `LI-2026-0042`)
- `invoiceDate` : date d'émission
- `serviceDate` : date de réalisation de la prestation (si différente de la date d'émission)
- `seller` / `buyer` : parties prenantes (type `Counterparty`)
- `invoiceType` : `INVOICE` (facture) ou `CREDIT_NOTE` (avoir/facture d'avoir)
- `businessType` : `ENROLLMENT` (inscription candidat, B2C) ou `TEST_LICENSE` (achat de licence de test, B2B)
- `operationCategory` : `SERVICE`, `GOODS` ou `BOTH` — mention obligatoire depuis juillet 2024
- `paymentDueDate` : date d'échéance de paiement
- `paymentTerms` : conditions de paiement (ex: "30 jours nets")
- `earlyPaymentDiscount` : conditions d'escompte ou "Néant"
- `latePaymentPenaltyRate` : taux de pénalité de retard (ex: 0.10 pour 10%)
- `fixedRecoveryIndemnity` : indemnité forfaitaire de recouvrement (40 EUR, imposé par la loi en B2B)
- `totalHT`, `totalTVA`, `totalTTC` : montants totaux
- `currency` : devise (EUR)
- `status` : état de la facture (voir cycle de vie ci-dessous)
- `creditedInvoice` : si c'est un avoir, référence vers la facture originale corrigée/annulée
- `pdfPath` : chemin vers l'archive PDF/Factur-X immutable (conservation 10 ans)

### Cycle de vie (`InvoiceStatusEnum`)

```
DRAFT  ──────→  ISSUED  ──────→  PAID
                   │
                   └──────→  CANCELLED (uniquement via émission d'un avoir)
```

- **DRAFT** : brouillon, modifiable, pas encore de valeur fiscale. Le numéro séquentiel n'est pas encore attribué.
- **ISSUED** : émise, **tous les champs sont gelés**. Le numéro séquentiel est attribué, le PDF Factur-X est généré et archivé.
- **PAID** : la somme des paiements `COMPLETED` associés couvre le montant TTC.
- **CANCELLED** : annulée par l'émission d'un avoir (`CREDIT_NOTE`). La facture reste en base de données, jamais supprimée.

### Règles de numérotation

La numérotation doit respecter trois principes stricts (CGI Annexe II, Art. 242 nonies A) :

1. **Unicité** : pas deux factures avec le même numéro
2. **Séquence chronologique** : les numéros suivent l'ordre chronologique
3. **Continuité sans rupture** : aucun trou dans la séquence

Chaque institut possède sa propre série de numérotation. La remise à zéro est autorisée en début d'exercice fiscal.

## Objet `InvoiceLine`

Représente une ligne de détail sur une facture. Chaque ligne correspond généralement à une épreuve (`Exam`).

- `label` : intitulé de la ligne (ex: "TOEIC Listening - Niveau B2")
- `description` : description complémentaire
- `quantity` : quantité
- `unitPriceHT` : prix unitaire hors taxes
- `tvaRate` : taux de TVA applicable (ex: 20%, 5.5%)
- `tvaAmount` : montant de TVA calculé
- `totalHT` : total HT de la ligne (`quantity × unitPriceHT`)
- `totalTTC` : total TTC de la ligne (`totalHT + tvaAmount`)

La ventilation de la TVA par taux est obligatoire sur la facture.

## Objet `Payment`

Représente une tentative de paiement. **Une facture peut avoir plusieurs paiements** (échec puis nouvel essai, paiement partiel, remboursement).

- `amount` : montant du paiement
- `currency` : devise
- `status` : état du paiement (`PaymentStatusEnum`)
- `date` : date du paiement
- `paymentMethod` : méthode de paiement (`STRIPE`, `BANK_TRANSFER`, `OTHER`)
- `stripePaymentIntentId` : identifiant Stripe pour la réconciliation
- `refundedPayment` : si c'est un remboursement (`REFUNDED`), lien vers le paiement original

### Cycle de vie (`PaymentStatusEnum`)

```
PENDING  ──────→  COMPLETED  ──────→  REFUNDED
    │
    └──────→  FAILED
```

- **PENDING** : paiement initié, en attente de confirmation Stripe
- **COMPLETED** : paiement confirmé par Stripe
- **FAILED** : paiement échoué (carte refusée, fonds insuffisants...)
- **REFUNDED** : remboursement effectué (lié au paiement original via `refundedPayment`)

---

# Process métier de facturation

## Process 1 : Inscription d'un candidat à une session (ENROLLMENT — B2C)

Ce process décrit le parcours de facturation lorsqu'un candidat s'inscrit à une session de test.

**Acteurs** : Candidat (User/CUSTOMER), Institut organisateur

**Prérequis** : La session est en statut `OPEN`, le nombre de places disponibles est > 0.

### Étapes

1. **Inscription** : le candidat s'inscrit à la session. Un objet `EnrollmentSession` est créé avec la `registrationDate`. Le candidat choisit les épreuves obligatoires et optionnelles (`EnrollmentExam`).

2. **Création de la facture** : une `Invoice` est créée en statut `DRAFT` avec :
   - `businessType` = `ENROLLMENT`
   - `seller` = `Counterparty` de l'institut organisateur
   - `buyer` = `Counterparty` du candidat (mentions simplifiées : nom + adresse)
   - Les `InvoiceLine` sont générées à partir des épreuves choisies. Le prix de chaque ligne est déterminé par ordre de priorité :
     1. `InstituteExamPricing` de l'institut organisateur (si défini et actif)
     2. `Price` de l'épreuve (`Exam.price`) par défaut

3. **Émission** : le candidat confirme. La facture passe en `ISSUED` :
   - Le numéro séquentiel est attribué (série de l'institut)
   - Le PDF Factur-X est généré et archivé (`pdfPath`)
   - Les champs sont désormais gelés

4. **Paiement** : un `Payment` est créé en statut `PENDING` avec le `stripePaymentIntentId`.
   - **Succès** → `Payment` passe en `COMPLETED` → `Invoice` passe en `PAID`
   - **Échec** → `Payment` passe en `FAILED` → le candidat peut réessayer (un nouveau `Payment` est créé)

5. **Annulation / Remboursement** (si applicable) :
   - Un avoir (`Invoice` de type `CREDIT_NOTE`) est émis, référençant la facture originale via `creditedInvoice`
   - La facture originale passe en `CANCELLED`
   - Un remboursement Stripe est initié → nouveau `Payment` en `REFUNDED` lié au paiement original via `refundedPayment`

## Process 2 : Redevance d'exploitation de test (TEST_LICENSE — B2B)

> Remplace l'ancien « achat de licence de test ». Détail complet : `SPEC/contrats-correction-attestations/design.md`.

Ce process décrit la facturation du propriétaire d'un test (institut ou plateforme) au centre de passation qui exploite ce test sous contrat.

**Acteurs** : propriétaire du test (vendeur), institut organisateur (acheteur)

### Étapes

1. **Contrat** : le propriétaire propose un contrat (prix HT et TVA par épreuve, période, reconduction tacite aux mêmes prix si le contrat est renouvelable) ; l'organisateur l'accepte (date + identité). Le contrat en vigueur ouvre le droit d'organiser des sessions.
2. **Commande** : à la validation définitive de la session (statut LOCKED → VALIDATED), une commande est créée : pour chaque épreuve, prix du contrat en vigueur × nombre d'inscrits actifs à l'épreuve. Les absents sont facturés ; les désinscrits n'apparaissent pas ; plus aucune désinscription n'est possible ensuite.
3. **Facturation** : la facture est créée et émise **à l'édition des attestations de résultat par le propriétaire**, c'est-à-dire quand les résultats sont disponibles :
   - `businessType` = `TEST_LICENSE`
   - `seller` = `Counterparty` du propriétaire (ou de la plateforme), `buyer` = `Counterparty` de l'institut organisateur (mentions B2B complètes)
   - `operationCategory` = `SERVICE`
   - `paymentTerms`, `latePaymentPenaltyRate`, `fixedRecoveryIndemnity` repris du contrat
   - une `InvoiceLine` par épreuve de la commande
4. **Unicité** : une seule facture par commande ; une réédition d'attestation ne refacture pas. Une session organisée par le propriétaire lui-même ne donne lieu à aucune facture.
5. **Paiement** : par virement (V1), puis via Stripe Connect (V2).
6. **Commission plateforme** : au paiement de la facture du propriétaire, la plateforme facture au propriétaire une commission (`PLATFORM_COMMISSION`) calculée sur le HT de cette facture. Aucune commission sur un test interne de la plateforme.

Deux factures distinctes coexistent donc pour une même session : organisateur → candidat (prix de vente de l'organisateur, process 1) et propriétaire → organisateur (prix du contrat, process 2).

## Obligations e-invoicing (Facture électronique)

| Échéance | Obligation |
|---|---|
| **Sept. 2026** | Toutes les entreprises : réception obligatoire des e-factures. GE et ETI : émission obligatoire. |
| **Sept. 2027** | PME et TPE : émission obligatoire. |

- **B2B** (`TEST_LICENSE`) : soumis à l'e-invoicing via une PDP (Plateforme de Dématérialisation Partenaire). Format Factur-X (PDF/A-3 + XML CII), UBL 2.1 ou CII.
- **B2C** (`ENROLLMENT`) : non soumis à l'e-invoicing, mais soumis au **e-reporting** (transmission des données de transaction au fisc, jusqu'à 3 envois par mois).

---

## Objet `Country`

Données de référence représentant un pays. Chaque pays possède :

- `code` : code ISO 3166-1 alpha-2 (ex: "FR", "JP", "US") — identifiant unique
- `alpha3` : code ISO 3166-1 alpha-3 (ex: "FRA", "JPN", "USA")
- `nameOriginal` : nom du pays dans sa langue d'origine (ex: "日本")
- `nameEn` : nom en anglais (ex: "Japan")
- `nameFr` : nom en français (ex: "Japon")
- `flag` : emoji drapeau ou URL vers l'image du drapeau
- `demonymFr` : gentilé en français (ex: "Japonais")
- `demonymEn` : gentilé en anglais (ex: "Japanese")

Un pays est lié à une ou plusieurs langues parlées (`Language`).

`Country` est utilisé par :
- `Address.country` : pays de l'adresse
- `User.nativeCountry` : pays d'origine de l'utilisateur
- `User.nationality` : nationalité de l'utilisateur

Les données sont chargées par fixtures (norme ISO 3166-1). Seul un ADMIN plateforme peut ajouter ou modifier un pays.

## Objet `Language`

Données de référence représentant une langue. Chaque langue possède :

- `code` : code ISO 639-1 (ex: "fr", "ja", "en") — identifiant unique
- `nameOriginal` : nom de la langue dans sa propre écriture (ex: "日本語")
- `nameEn` : nom en anglais (ex: "Japanese")
- `nameFr` : nom en français (ex: "Japonais")

Une langue peut être parlée dans plusieurs pays (relation ManyToMany via `Country`).

`Language` est utilisé par `User.firstlanguage` pour référencer la langue maternelle de l'utilisateur.

Les données sont chargées par fixtures (norme ISO 639-1). Seul un ADMIN plateforme peut ajouter ou modifier une langue.
