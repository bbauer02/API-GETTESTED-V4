# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

GETTESTED is a language testing platform API for managing language proficiency assessments (TOEIC, DELF, DALF, JLPT, etc.). The project is currently in its **design/planning phase** — domain specifications and class diagrams are defined, but source code implementation has not started yet.

All domain documentation is written in French.

## Design Documents

- `description-metier.md` — Business domain specifications describing entity relationships and roles
- `diagramme.puml` — PlantUML class diagram defining the full entity model

## Planned Technology Stack

Based on PhpStorm configuration and the companion API-GETTESTED project:
- **PHP 8.4** with **Symfony** framework
- **API Platform** for REST API generation from entity definitions
- **Doctrine ORM** with PHP 8 attributes for entity mapping
- **PostgreSQL** database
- **Docker Compose** for local development

## Domain Model Architecture

### Role System (Two-Level)
- **Platform level** (`PlatformRoleEnum`): ADMIN (full control), USER (standard)
- **Institute level** (`InstituteRoleEnum`): ADMIN, TEACHER, STAFF, CUSTOMER — managed through `InstituteMembership` join entity between `User` and `Institute`

### Key Entity Relationships
- `Assessment` (test type) → has `Exam` instances (individual test components) → linked to `Level` and `Skill`
- `Assessment` supports parent/child hierarchy (test inheritance)
- `Session` groups `ScheduledExam` entries with registration deadlines and seat limits
- `EnrollmentSession` tracks user registration and payment status for a session
- `AssessmentOwnership` links `Institute` to `Assessment` with OWNER/BUYER relationship — institutes can sell tests to other institutes
- `InstituteExamPricing` allows per-institute custom pricing for exams

### Value Objects
- `Address` — embedded address with Country reference
- `Counterparty` — used in invoicing (seller/buyer)

### Question System (Inheritance)
- Abstract `Question` base with subtypes: `MCQQuestion`, `FillBlankQuestion`, `HighlightQuestion`
- Questions link to `Skill` and `Media` (IMAGE, AUDIO, YOUTUBE)
- `Subject` groups questions for a `ScheduledExam`

### Payment & Invoicing
- `Payment` with status tracking (PENDING, COMPLETED, FAILED)
- `Invoice` with `InvoiceLine` items, linked to `EnrollmentSession`
- Stripe integration via `StripeAccount` per institute

### Entities Marked as Done in Diagram
User, Institute, Assessment, Level, Exam, Skill, Session, EnrollmentSession, ScheduledExam, AssessmentOwnership, InstituteMembership, StripeAccount, Address, CivilityEnum, GenderEnum, OwnershipTypeEnum, PlatformRoleEnum, InstituteRoleEnum

### Entities Not Yet Done
Payment, Invoice, InvoiceLine, Counterparty, InstituteExamPricing, EnrollmentExam, Question (and subtypes), Choice, Media, Subject
