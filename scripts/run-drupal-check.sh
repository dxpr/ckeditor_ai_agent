#!/bin/bash
set -vo pipefail

DRUPAL_RECOMMENDED_PROJECT=${DRUPAL_RECOMMENDED_PROJECT:-11.x-dev}
DRUPAL_CHECK_TOOL="mglaman/drupal-check"

# Create Drupal project if it doesn't exist
if [ ! -d "/drupal" ]; then
  composer create-project drupal/recommended-project=$DRUPAL_RECOMMENDED_PROJECT drupal --no-interaction --stability=dev --ignore-platform-reqs
fi

cd drupal
mkdir -p web/modules/contrib/

# Symlink ckeditor_ai_agent if not already linked
if [ ! -L "web/modules/contrib/ckeditor_ai_agent" ]; then
  ln -s /src web/modules/contrib/ckeditor_ai_agent
fi

# Install drupal-check if not already installed
if [ ! -f "./vendor/bin/drupal-check" ]; then
  composer config allow-plugins.tbachert/spi true
  composer require $DRUPAL_CHECK_TOOL --dev --ignore-platform-reqs
fi

# Run drupal-check with stderr redirected
./vendor/bin/drupal-check --drupal-root . -ad web/modules/contrib/ckeditor_ai_agent 2>/dev/null
