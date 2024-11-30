#!/bin/bash

source scripts/prepare-drupal-lint.sh

phpcbf --standard=Drupal \
  --extensions=php,module,inc,install,test,profile,theme,info,txt,md,yml \
  --ignore=src/Service/UploadHandler.php,node_modules,ckeditor_ai_agent/vendor \
  .

phpcbf --standard=DrupalPractice \
  --extensions=php,module,inc,install,test,profile,theme,info,txt,md,yml \
  --ignore=src/Service/UploadHandler.php,node_modules,ckeditor_ai_agent/vendor \
  .
