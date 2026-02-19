# Project Structure

## Organization Philosophy

Architecture en couches suivant les conventions Symfony avec API Platform. Chaque entité est auto-suffisante : le fichier Entity contient le mapping Doctrine, les attributs API Platform, les validations et les groupes de sérialisation.

## Directory Patterns

### Entities
**Location** : `src/Entity/`
**Purpose** : Entités Doctrine avec attributs API Platform, validations Symfony, groupes de sérialisation
**Example** : `src/Entity/User.php` — contient `#[ORM\Entity]`, `#[ApiResource]`, `#[Assert\...]`, `#[Groups]`

### Enums
**Location** : `src/Enum/`
**Purpose** : Enums PHP 8.1+ natives (backed enums string)
**Example** : `src/Enum/PlatformRoleEnum.php`

### Value Objects
**Location** : `src/Entity/Embeddable/`
**Purpose** : Doctrine Embeddables (Address, Price, Counterparty)
**Example** : `src/Entity/Embeddable/Address.php`

### Repositories
**Location** : `src/Repository/`
**Purpose** : Requêtes Doctrine personnalisées
**Example** : `src/Repository/UserRepository.php`

### State Processors
**Location** : `src/State/`
**Purpose** : Logique métier pour les opérations API Platform (create, update, delete custom)
**Example** : `src/State/EnrollmentProcessor.php`

### Security Voters
**Location** : `src/Security/Voter/`
**Purpose** : Logique d'autorisation par entité
**Example** : `src/Security/Voter/SessionVoter.php`

### Workflow
**Location** : `config/packages/workflow.yaml`
**Purpose** : Définition des state machines (Session, Invoice, Payment, EnrollmentExam)

### Event Subscribers
**Location** : `src/EventSubscriber/`
**Purpose** : Listeners pour les événements Doctrine, Workflow et Stripe webhooks
**Example** : `src/EventSubscriber/StripeWebhookSubscriber.php`

### Services
**Location** : `src/Service/`
**Purpose** : Services métier (facturation, pricing, numérotation)
**Example** : `src/Service/InvoiceService.php`

### Tests
**Location** : `tests/`
**Purpose** : Tests fonctionnels PHPUnit avec API Platform Test Client
**Example** : `tests/Api/UserTest.php`

### Fixtures
**Location** : `src/DataFixtures/`
**Purpose** : Données de test et données de référence (Country, Language)
**Example** : `src/DataFixtures/CountryFixtures.php`

### Migrations
**Location** : `migrations/`
**Purpose** : Migrations Doctrine auto-générées

### Docker
**Location** : racine
**Purpose** : `docker-compose.yml`, `Dockerfile`, `.env`

## Naming Conventions

- **Fichiers Entity** : PascalCase singulier (`User.php`, `EnrollmentSession.php`)
- **Fichiers Enum** : PascalCase + Enum suffix (`PlatformRoleEnum.php`)
- **Fichiers Repository** : PascalCase + Repository suffix (`UserRepository.php`)
- **Fichiers Voter** : PascalCase + Voter suffix (`SessionVoter.php`)
- **Fichiers Test** : PascalCase + Test suffix (`UserTest.php`)
- **Tables BDD** : snake_case auto-généré par Doctrine (`enrollment_session`)
- **Propriétés** : camelCase (`firstName`, `registrationDate`)
- **Routes API** : kebab-case pluriel (`/api/enrollment-sessions`)

## Import Organization

```php
// 1. PHP natif
use DateTimeImmutable;
// 2. Symfony / Doctrine / API Platform
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\Validator\Constraints as Assert;
// 3. Bundles tiers
use Lexik\Bundle\JWTAuthenticationBundle\...;
// 4. Application
use App\Entity\User;
use App\Enum\PlatformRoleEnum;
```

## Code Organization Principles

- **Une entité = un fichier complet** : mapping + API + validation + sérialisation
- **Pas de logique métier dans les entités** : la logique va dans les Services ou State Processors
- **Voters pour l'autorisation** : jamais de `if ($user->getRole() === ...)` dans les controllers
- **State machines pour les cycles de vie** : pas de setter direct sur les champs de statut

---
_Document patterns, not file trees. New files following patterns shouldn't require updates_
