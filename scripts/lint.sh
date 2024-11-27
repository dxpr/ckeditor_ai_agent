#!/bin/bash
set -e

echo "Running PHPCBF to automatically fix coding standards..."
docker compose run --rm drupal-lint-auto-fix

echo "Running Drupal lint..."
docker compose run --rm drupal-lint

echo "Running Drupal deprecation checks..."
docker compose run --rm drupal-check 