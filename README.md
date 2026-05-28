
## HOWTO release

phpcs include view lib index.php inc.smarty.php inc.startup.php cron.email_import.php
./vendor/bin/pest
composer validate
git tag _version_ // (just number, like 1.5.13)
commit
git push origin _version_ // (just number, like 1.5.13)

git push