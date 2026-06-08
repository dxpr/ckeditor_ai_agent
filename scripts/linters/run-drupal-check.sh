#!/bin/bash

set -evo pipefail

if [ -z "$DRUPAL_RECOMMENDED_PROJECT" ]; then
  # Pin to a concrete Drupal 11 release for deterministic CI behavior.
  DRUPAL_RECOMMENDED_PROJECT=11.0.0
fi

# Install required libs for Drupal
GD_ENABLED=$(php -i | grep 'GD Support' | awk '{ print $4 }') || true

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
        - web/modules/contrib/ckeditor_ai_agent/tests (?)
    level: 5
    treatPhpDocTypesAsCertain: false
    ignoreErrors:
        # ProviderProxy uses __call magic method to delegate to wrapped plugin
        - '#Call to an undefined method Drupal\\\\ai\\\\Plugin\\\\ProviderProxy::#'
EOF

mkdir -p web/modules/contrib/

if [ ! -L "web/modules/contrib/ckeditor_ai_agent" ]; then
  ln -s /src web/modules/contrib/ckeditor_ai_agent
fi

# Install dependencies for ckeditor_ai_agent
composer require drupal/key --no-interaction

# Install markdownify with error handling since it's optional
if ! composer require drupal/markdownify --no-interaction; then
  echo "Warning: Could not install drupal/markdownify (optional dependency)"
fi

# Install AI module classes used by this module's proxy controller.
composer require 'drupal/ai:^1.2' --no-interaction

# Install PHPStan extensions for Drupal 11 and Drush for command analysis
composer require --dev phpstan/extension-installer:^1.4 phpstan/phpstan:^2.1.42 mglaman/phpstan-drupal:^2.0.11 phpstan/phpstan-deprecation-rules:^2.0 drush/drush:^13.7 --with-all-dependencies --no-interaction

# Verify AI module installed correctly and scanDirectories target exists.
if [ ! -d "web/modules/contrib/ai/src" ]; then
  echo "ERROR: AI module not found at web/modules/contrib/ai/src."
  echo "The composer require for drupal/ai may have failed or installed to an unexpected location."
  exit 1
fi

# Verify AI module's ProviderProxy uses __call (our ignoreErrors depend on this)
if ! grep -q 'function __call' web/modules/contrib/ai/src/Plugin/ProviderProxy.php 2>/dev/null; then
  echo "ERROR: AI module ProviderProxy.__call not found. The AI module API may have changed."
  echo "Review ignoreErrors in phpstan.neon and update accordingly."
  exit 1
fi

# Run phpstan
./vendor/bin/phpstan analyse --memory-limit=-1 -c phpstan.neon
