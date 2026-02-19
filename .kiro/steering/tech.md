# Technology Stack

## Architecture

API REST monolithique avec séparation en couches (Entity, Repository, Service, Controller/Processor). API Platform génère les endpoints REST automatiquement à partir des entités Doctrine annotées.

## Core Technologies

- **Language** : PHP 8.4
- **Framework** : Symfony 7
- **API** : API Platform 4.2 (REST/JSON-LD auto-généré)
- **ORM** : Doctrine ORM avec attributs PHP 8
- **Database** : PostgreSQL 16
- **Runtime** : FrankenPHP ou PHP-FPM

## Key Libraries

- `api-platform/core` : génération API REST, filtres, sérialisation, pagination
- `lexik/jwt-authentication-bundle` : authentification JWT
- `symfony/workflow` : state machines (Session, Invoice, Payment)
- `stripe/stripe-php` : intégration paiements Stripe
- `atgp/factur-x` : génération PDF Factur-X (e-invoicing)
- `doctrine/doctrine-fixtures-bundle` : fixtures de données
- `nelmio/cors-bundle` : CORS pour le frontend

## Development Standards

### Type Safety
- PHP 8.4 strict types (`declare(strict_types=1)`)
- Typed properties, return types, union types
- Enums PHP 8.1+ natifs pour tous les types énumérés

### Code Quality
- PHP-CS-Fixer (PSR-12)
- PHPStan niveau 8
- Pas de `mixed` sauf contrainte framework

### Testing
- PHPUnit + API Platform Test Client
- Tests fonctionnels sur chaque endpoint (HTTP status, JSON structure, autorisation)
- Fixtures de test par sprint

## Development Environment

### Required Tools
- Docker + Docker Compose
- PHP 8.4 (via container)
- Composer 2
- Symfony CLI (optionnel)

### Common Commands
```bash
# Dev: docker compose up -d
# Build: docker compose build
# Test: docker compose exec php bin/phpunit
# Migrations: docker compose exec php bin/console doctrine:migrations:migrate
# Fixtures: docker compose exec php bin/console doctrine:fixtures:load
# Cache clear: docker compose exec php bin/console cache:clear
```

## Key Technical Decisions

- **API Platform plutôt que controllers manuels** : 80% des endpoints sont du CRUD déclaratif, API Platform excelle pour ça
- **Doctrine attributs PHP 8 plutôt que XML/YAML** : co-localisation entité + mapping + API Platform dans un seul fichier
- **JWT plutôt que sessions** : API stateless, adapté au multi-client (web, mobile)
- **Symfony Workflow plutôt que logique manuelle** : state machines auditables et déclaratives pour Session, Invoice, Payment
- **Soft delete via filtre Doctrine** : champ `deletedAt`, exclu automatiquement des requêtes
- **Value Objects Doctrine embeddables** : Address, Price, Counterparty sont des embeddables Doctrine, pas des entités séparées

---
_Document standards and patterns, not every dependency_
