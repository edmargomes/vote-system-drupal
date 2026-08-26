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

## Documentation

- [Running Tests & Code Quality](https://github.com/edmargomes/vote-system-drupal/wiki/Running-Tests)
- [API Endpoints Reference](https://github.com/edmargomes/vote-system-drupal/wiki/Developer-Endpoints)
- [Authentication](https://github.com/edmargomes/vote-system-drupal/wiki/Developer-Authentication)
- [Vote Flow](https://github.com/edmargomes/vote-system-drupal/wiki/Developer-Vote-Flow)
- [Entity Schema](https://github.com/edmargomes/vote-system-drupal/wiki/Developer-Entity-Schema)
- [Service Layer](https://github.com/edmargomes/vote-system-drupal/wiki/Developer-Service-Layer)
- [Admin Guide](https://github.com/edmargomes/vote-system-drupal/wiki/Admin-Getting-Started)
