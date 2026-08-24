# voting_system — Drupal 11 Voting Module

A custom Drupal 11 module implementing a poll/voting system. The REST API is built manually (no JSON:API or GraphQL), with custom token authentication, single-vote-per-user enforcement via a database unique constraint, and per-question result visibility control.

## Requirements

- [Lando](https://lando.dev/) (local development environment)
- PHP 8.3
- Drupal 11
- MySQL 8.0

## Setup

```bash
# 1. Clone the repository
git clone <repo-url>
cd vote-system-drupal

# 2. Start the Lando environment
lando start

# 3. Install PHP dependencies
lando composer install

# 4. Import the database dump
lando db-import dump/voting_system.sql

# 5. Clear caches
lando drush cr
```

The site will be available at **http://voting-system.lndo.site**.

## Running Tests

```bash
# Unit tests only (fast — no Drupal bootstrap)
lando test-unit

# Kernel tests (entity persistence, DB constraints)
lando test-kernel

# API contract tests (HTTP status codes, JSON schema)
lando test-contract

# Integration tests (full end-to-end flows)
lando test-integration

# All test suites
lando phpunit
```

## Code Quality

```bash
# Check Drupal coding standards
lando phpcs

# Auto-fix style issues
lando phpcbf

# Static analysis (level 5)
lando phpstan
```

GrumPHP runs `phpcs`, `phpstan`, and the Unit test suite automatically on every `git commit`.

## Coverage Reports

```bash
# Generate full coverage report
lando coverage

# Unit tests coverage only (faster)
lando coverage-unit
```

The HTML report is served at **http://coverage.voting-system.lndo.site**.

## API Quick Reference

All endpoints (except auth) require `Authorization: Bearer {token}`.

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/v1/auth/token` | Obtain an access token |
| `GET`  | `/api/v1/questions` | List active questions |
| `GET`  | `/api/v1/questions/{uuid}` | Question detail with options |
| `POST` | `/api/v1/questions/{uuid}/vote` | Cast a vote |
| `GET`  | `/api/v1/admin/questions/{uuid}/results` | Full results (admin only) |

**Auth request:**
```json
POST /api/v1/auth/token
{ "username": "editor", "password": "secret" }
```

**Vote request:**
```json
POST /api/v1/questions/{uuid}/vote
Authorization: Bearer <token>
{ "option_uuid": "6ba7b810-9dad-11d1-80b4-00c04fd430c8" }
```

Duplicate votes return **HTTP 409 Conflict**.

## Module Structure

```
web/modules/custom/voting_system/
├── voting_system.info.yml
├── voting_system.module
├── voting_system.install
├── voting_system.routing.yml
├── voting_system.permissions.yml
├── voting_system.services.yml
├── voting_system.links.menu.yml
├── config/
│   ├── install/voting_system.settings.yml
│   └── schema/voting_system.schema.yml
└── src/
    ├── Entity/          # VotingQuestion, VotingOption, VotingVote
    ├── Controller/Api/  # AuthController, QuestionApiController, VoteApiController, AdminResultsController
    ├── Controller/Admin/
    ├── Service/         # AuthTokenService, VotingService, QuestionService, ResultService, VotingLogger
    ├── Form/
    ├── Exception/
    ├── Validator/
    └── EventSubscriber/
```

## Architectural Decisions

| Decision | Choice | Reason |
|----------|--------|--------|
| Public identifier | UUID | Portability; avoids ID collisions across environments |
| Authentication | Custom token | No contrib dependency; auditable and revocable |
| `user_id` on vote | Extracted from token | Never from the request body — prevents identity spoofing |
| Duplicate vote | HTTP 409 Conflict | Semantically correct; 400 would be wrong |
| Concurrency | DB transaction + unique constraint | More efficient than SELECT + INSERT; race-condition proof |
| Images | Managed File | Native Drupal file system integration |
| Results on vote | Returned in same response | Avoids an extra round-trip when `show_results=true` |
