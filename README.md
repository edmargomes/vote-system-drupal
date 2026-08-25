# voting_system — Drupal 11 Voting Module

A custom Drupal 11 module implementing a poll/voting system. The REST API is built manually (no JSON:API or GraphQL), with custom token authentication, single-vote-per-user enforcement via a database unique constraint, and per-question result visibility control.

## Quick Start

```bash
git clone <repo-url>
cd vote-system-drupal
lando setup-project
```

That's it. The command starts Lando, creates your `.env` (with a generated hash salt), installs PHP dependencies, imports the initial database, and runs `drush deploy`.

When it finishes you'll see:

```
  Site  http://voting-system.lndo.site
  Docs  https://github.com/edmargomes/vote-system-drupal/wiki
```

For a full setup guide and admin tutorials see the [project wiki](https://github.com/edmargomes/vote-system-drupal/wiki).

---

## Requirements

- [Lando](https://lando.dev/) (local development environment)
- PHP 8.4
- Drupal 11
- MySQL 8.4

---

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

For the full testing guide see [Running Tests](https://github.com/edmargomes/vote-system-drupal/wiki/Running-Tests) in the wiki.

## Code Quality

```bash
# Check Drupal coding standards
lando phpcs

# Auto-fix style issues
lando phpcbf

# Static analysis (level 5)
lando phpstan
```

GrumPHP runs `phpcs` and `phpstan` automatically on every `git commit`.

## Coverage Reports (local only)

```bash
# Generate full coverage report (HTML + Clover)
lando coverage

# Unit tests coverage only (faster)
lando coverage-unit
```

The HTML report is served at **http://coverage.voting-system.lndo.site**.

## API Quick Reference

All endpoints require `Authorization: Basic base64(username:password)`.

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET`  | `/api/v1/questions` | List active questions |
| `GET`  | `/api/v1/questions/{uuid}` | Question detail with options |
| `POST` | `/api/v1/questions/{uuid}/vote` | Cast a vote |
| `GET`  | `/api/v1/admin/questions/{uuid}/results` | Full results (admin only) |

**Vote request:**
```json
POST /api/v1/questions/{uuid}/vote
Authorization: Basic <base64(username:password)>
{ "option_uuid": "6ba7b810-9dad-11d1-80b4-00c04fd430c8" }
```

Duplicate votes return **HTTP 409 Conflict**.

## Module Structure

```
web/modules/custom/vs_core/
├── vs_core.info.yml
├── vs_core.module
├── vs_core.install
├── vs_core.routing.yml
├── vs_core.permissions.yml
├── vs_core.services.yml
├── vs_core.links.menu.yml
├── config/
│   ├── install/vs_core.settings.yml
│   └── schema/vs_core.schema.yml
├── src/
│   ├── Controller/Api/    # QuestionApiController, VoteApiController, AdminResultsController
│   ├── Entity/            # VotingQuestion, VotingOption, VotingVote (+ interfaces)
│   ├── EventSubscriber/   # VotingRequestSubscriber
│   ├── Exception/         # DuplicateVoteException, VotingDisabledException, VotingNotFoundException
│   ├── Form/              # VotingSettingsForm
│   ├── Service/           # VotingService, QuestionService, ResultService, VotingCacheService, VotingLogger
│   ├── Validator/         # VotePayloadValidator
└── tests/src/
    ├── Unit/              # Fast tests — no Drupal bootstrap
    ├── Kernel/            # Entity persistence and DB constraints
    └── Functional/Api/
        ├── Contract/      # HTTP contract tests (status, schema, headers)
        └── Integration/   # End-to-end flow tests
```
