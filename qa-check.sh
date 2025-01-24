#!/bin/bash

echo "Checking PHP files for snake_case in our code..."
find ./src -type f -name "*.php" -exec grep -l "_[a-z]" {} \; | while read file; do
  echo "=== $file ==="
  grep -n "_[a-z]" "$file" | grep -v "__" # Exclude PHP magic methods
done

# Specific checks for known config keys in our src directory
echo "\nChecking for specific config keys that should be camelCase..."
KEYS_TO_CHECK=(
  "basic_settings"
  "advanced_settings"
  "performance_settings"
  "behavior_settings"
  "moderation_settings"
  "prompt_settings"
  "api_key"
  "max_output_tokens"
  "max_input_tokens"
  "time_out_duration"
  "retry_attempts"
  "debug_mode"
  "stream_content"
  "show_error_duration"
  "context_size"
  "editor_context_ratio"
  "endpoint_url"
  "content_scope"
  "disable_flags"
)

for key in "${KEYS_TO_CHECK[@]}"; do
  echo "\nSearching for: $key"
  find ./src -type f -exec grep -l "$key" {} \;
done

# Check schema files specifically
echo "\nChecking schema files..."
find ./config -type f -name "*.schema.yml" -exec cat {} \; | grep -n "_"

# Check YAML files for snake_case
echo "\nChecking YAML files..."
find . -type f -name "*.yml" -not -path "*/node_modules/*" -not -path "*/vendor/*" -not -path "*/js/*" -exec grep -l "_" {} \;

# Check JavaScript files for inconsistent casing
echo "\nChecking JavaScript files for inconsistent casing..."
find . -type f -name "*.js" -not -path "*/node_modules/*" -not -path "*/vendor/*" -not -path "*/js/*" -exec grep -l "_" {} \;

# Special check for form element IDs and names
echo "\nChecking form element IDs and names..."
find ./src -type f -name "*.php" -exec grep -l "basic_settings\|advanced_settings\|performance_settings\|behavior_settings\|moderation_settings\|prompt_settings" {} \;

# Check for common prefixes/suffixes that should be camelCase
echo "\nChecking for common patterns..."
PATTERNS=(
  "_settings"
  "_duration"
  "_mode"
  "_size"
  "_ratio"
  "_url"
  "_scope"
  "_flags"
  "_key"
)

for pattern in "${PATTERNS[@]}"; do
  echo "\nSearching for pattern: $pattern"
  find . -type f -not -path "*/vendor/*" -not -path "*/node_modules/*" -not -path "*/js/*" -exec grep -l "$pattern" {} \;
done

# Check test files
echo "\nChecking test files..."
find . -type f -name "*Test.php" -exec grep -l "_" {} \;