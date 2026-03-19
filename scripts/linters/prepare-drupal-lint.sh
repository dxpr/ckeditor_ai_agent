#!/bin/bash

if [ -z "$TARGET_DRUPAL_CORE_VERSION" ]; then
  TARGET_DRUPAL_CORE_VERSION=11
fi

echo "$COMPOSER_HOME: $COMPOSER_HOME"
echo "TARGET_DRUPAL_CORE_VERSION: $TARGET_DRUPAL_CORE_VERSION"

# Ensure COMPOSER_HOME directory exists.
mkdir -p "$COMPOSER_HOME"

# Create/overwrite global composer.json to trust required plugins up-front.
echo '{
    "config": {
        "allow-plugins": {
            "dealerdirect/phpcodesniffer-composer-installer": true,
            "drupal/coder": true,
            "phpcompatibility/php-compatibility": true
        }
    }
}' > "$COMPOSER_HOME/composer.json"

# Install PHPCS tooling with a PHPCompatibility-compatible phpcs major version.
composer global require \
  drupal/coder \
  phpcompatibility/php-compatibility \
  squizlabs/php_codesniffer:^3 \
  dealerdirect/phpcodesniffer-composer-installer \
  --with-all-dependencies

export PATH="$PATH:$COMPOSER_HOME/vendor/bin"

composer global show -P
phpcs -i

# Ensure PHPCompatibility is always discoverable even if plugin hooks are skipped.
phpcs --config-set installed_paths "$COMPOSER_HOME/vendor/drupal/coder/coder_sniffer,$COMPOSER_HOME/vendor/phpcompatibility/php-compatibility,$COMPOSER_HOME/vendor/sirbrillig/phpcs-variable-analysis,$COMPOSER_HOME/vendor/slevomat/coding-standard"

# Configure PHPCS settings
phpcs --config-set colors 1
phpcs --config-set ignore_warnings_on_exit 1
phpcs --config-set drupal_core_version "$TARGET_DRUPAL_CORE_VERSION"

phpcs --config-show
