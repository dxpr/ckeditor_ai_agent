#!/bin/bash
set -vo pipefail

DRUPAL_RECOMMENDED_PROJECT=${DRUPAL_RECOMMENDED_PROJECT:-11.x-dev}

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

# Create a phpstan.neon configuration if it doesn't exist
if [ ! -f "phpstan.neon" ]; then
  cat > phpstan.neon << 'EOF'
parameters:
  level: 2
  paths:
    - web/modules/contrib/ckeditor_ai_agent
  excludePaths:
    - web/modules/contrib/ckeditor_ai_agent/tests (?)
  drupal:
    drupal_root: .
EOF
fi

# Run phpstan directly since drupal-check is not compatible with Drupal 11
./vendor/bin/phpstan analyse --no-progress --memory-limit=256M
