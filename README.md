# voting_system — Drupal 11 Voting Module

A custom Drupal 11 module implementing a poll/voting system with a manually built REST API, HTTP Basic Authentication, and single-vote enforcement via a database unique constraint.

## Quick Start

```bash
git clone <repo-url>
cd vote-system-drupal
lando setup-project
```

That's it. The command starts Lando, creates your `.env` (with a generated hash salt), installs PHP dependencies, imports the initial database, and runs `drush deploy`. At the end it prints the site URL and a one-time admin login link.

**Requires:** [Lando](https://lando.dev/)

## Tests

All 142 tests pass. The full suite takes ~40 minutes (Contract and Admin suites boot a full Drupal install).

| Suite | Tests | Notes |
|-------|------:|-------|
| Unit | 28 | No Drupal bootstrap — runs in < 1 s |
| Kernel | 19 | Entity persistence and DB constraints |
| Contract | 30 | HTTP contract per endpoint |
| Integration | 4 | End-to-end API flows |
| Admin | 61 | Full browser-level functional tests |
| **Total** | **142** | |

### Coverage (Unit suite)

| Metric | Covered | Total | % |
|--------|--------:|------:|--:|
| Lines | 268 | 1 114 | 24.1% |
| Methods | 25 | 100 | 25.0% |

Coverage is measured on the Unit suite only — run `lando coverage` for the full report served at `http://coverage.voting-system.lndo.site`.

## Documentation

- [Running Tests & Code Quality](https://github.com/edmargomes/vote-system-drupal/wiki/Running-Tests)
- [API Endpoints Reference](https://github.com/edmargomes/vote-system-drupal/wiki/Developer-Endpoints)
- [Authentication](https://github.com/edmargomes/vote-system-drupal/wiki/Developer-Authentication)
- [Vote Flow](https://github.com/edmargomes/vote-system-drupal/wiki/Developer-Vote-Flow)
- [Entity Schema](https://github.com/edmargomes/vote-system-drupal/wiki/Developer-Entity-Schema)
- [Service Layer](https://github.com/edmargomes/vote-system-drupal/wiki/Developer-Service-Layer)
- [Admin Guide](https://github.com/edmargomes/vote-system-drupal/wiki/Admin-Getting-Started)
