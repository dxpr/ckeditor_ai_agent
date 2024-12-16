#!/bin/bash
set -vo pipefail

DRUPAL_RECOMMENDED_PROJECT=${DRUPAL_RECOMMENDED_PROJECT:-10.3.x-dev}
DRUPAL_CHECK_TOOL="mglaman/drupal-check"

# Create Drupal project if it doesn't exist
if [ ! -d "/drupal" ]; then
  composer create-project drupal/recommended-project=$DRUPAL_RECOMMENDED_PROJECT drupal --no-interaction --stability=dev
fi

cd drupal
mkdir -p web/modules/contrib/

# Symlink ckeditor_ai_agent if not already linked
if [ ! -L "web/modules/contrib/ckeditor_ai_agent" ]; then
  ln -s /src web/modules/contrib/ckeditor_ai_agent
fi

# Install the statistic modules if D11 (removed from core).
if [[ $DRUPAL_RECOMMENDED_PROJECT == 11.* ]]; then
  composer require drupal/statistics
fi

# Install drupal-check only if not already installed
if [ ! -f "./vendor/bin/drupal-check" ]; then
  composer require $DRUPAL_CHECK_TOOL --dev
fi

# Run drupal-check
./vendor/bin/drupal-check --drupal-root . -ad web/modules/contrib/ckeditor_ai_agent
