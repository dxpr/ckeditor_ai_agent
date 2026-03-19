#!/bin/bash

set -vo pipefail

if [ -z "$DRUPAL_RECOMMENDED_PROJECT" ]; then
  # Pin to a concrete Drupal 11 release for deterministic CI behavior.
  DRUPAL_RECOMMENDED_PROJECT=11.0.0
fi

# Install required libs for Drupal
GD_ENABLED=$(php -i | grep 'GD Support' | awk '{ print $4 }')

if [ "$GD_ENABLED" != 'enabled' ]; then
  apk update && \
  apk add libpng libpng-dev libjpeg-turbo-dev libwebp-dev zlib-dev libxpm-dev gd tree rsync && docker-php-ext-install gd
fi

# Create project in a temporary directory inside the container
INSTALL_DIR="/drupal_install_tmp"
composer create-project drupal/recommended-project=$DRUPAL_RECOMMENDED_PROJECT "$INSTALL_DIR" --no-interaction

cd "$INSTALL_DIR"

# Allow specific plugins needed by dependencies before requiring them.
composer config --no-plugins allow-plugins.tbachert/spi true --no-interaction
composer config --no-plugins allow-plugins.phpstan/extension-installer true --no-interaction

# Create phpstan.neon config file
cat <<EOF > phpstan.neon
parameters:
    paths:
        - web/modules/contrib/ckeditor_ai_agent
    scanDirectories:
        - web/modules/contrib/ai
    excludePaths:
        - web/modules/contrib/ckeditor_ai_agent/node_modules (?)
    # Set the analysis level (0-9)
    level: 5
EOF

mkdir -p web/modules/contrib/

if [ ! -L "web/modules/contrib/ckeditor_ai_agent" ]; then
  ln -s /src web/modules/contrib/ckeditor_ai_agent
fi

# Install dependencies for ckeditor_ai_agent
composer require drupal/key

# Install markdownify with error handling since it's optional
if ! composer require drupal/markdownify; then
  echo "Warning: Could not install drupal/markdownify (optional dependency)"
fi

# Install AI module classes used by this module's proxy controller.
composer require 'drupal/ai:^1.2' --no-interaction

# Install PHPStan extensions for Drupal 11 and Drush for command analysis
composer require --dev phpstan/extension-installer:^1.4 phpstan/phpstan:^2.1.42 mglaman/phpstan-drupal:^2.0.11 phpstan/phpstan-deprecation-rules:^2.0 drush/drush:^13.7 --with-all-dependencies --no-interaction

# Run phpstan
./vendor/bin/phpstan analyse --memory-limit=-1 -c phpstan.neon
