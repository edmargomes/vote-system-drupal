#!/usr/bin/env bash
set -euo pipefail

CYAN='\033[0;36m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

SITE_URL="http://voting-system.lndo.site"
WIKI_URL="https://github.com/edmargomes/vote-system-drupal/wiki"

step() { echo -e "\n${CYAN}[$1/5] $2${NC}"; }
done_msg() { echo -e "  ${GREEN}done${NC}"; }

# ── 1. .env ──────────────────────────────────────────────────────────────────
step 1 "Creating .env ..."
if [ ! -f .env ]; then
  cp .env.example .env
  # Replace the placeholder hash salt with a real random value.
  HASH_SALT=$(openssl rand -base64 64 | tr -d '\n/+=')
  sed -i "s/change-me-to-a-random-string/${HASH_SALT}/" .env
  echo "  .env created with a generated hash salt"
else
  echo "  .env already exists — skipping"
fi
done_msg

# ── 2. Lando start ───────────────────────────────────────────────────────────
step 2 "Starting Lando environment ..."
lando start
done_msg

# ── 3. Composer install ──────────────────────────────────────────────────────
step 3 "Installing PHP dependencies ..."
lando composer install --no-interaction --prefer-dist
done_msg

# ── 4. Database import ───────────────────────────────────────────────────────
step 4 "Importing database dump ..."
lando db-import dump/voting_system.sql
done_msg

# ── 5. Drupal deploy ─────────────────────────────────────────────────────────
step 5 "Running Drupal deploy ..."
lando drush deploy -y
done_msg

# ── Done ─────────────────────────────────────────────────────────────────────
ADMIN_URL=$(lando drush uli --no-browser 2>/dev/null | tr -d '[:space:]')

echo -e "\n${GREEN}Setup complete!${NC}\n"
echo -e "  Site   ${CYAN}${SITE_URL}${NC}"
echo -e "  Admin  ${CYAN}${ADMIN_URL}${NC}"
echo -e "  Docs   ${CYAN}${WIKI_URL}${NC}"
echo ""
