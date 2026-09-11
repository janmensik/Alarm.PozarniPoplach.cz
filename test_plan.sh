#!/bin/bash
set -e
composer global require squizlabs/php_codesniffer
export PATH="$PATH:$HOME/.config/composer/vendor/bin"
phpcbf include view index.php cron.email_import.php inc.smarty.php inc.startup.php tests || true
./vendor/bin/pest
