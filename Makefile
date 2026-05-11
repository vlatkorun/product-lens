.PHONY: analyse fix

analyse:
	php vendor/bin/phpstan analyse

fix:
	php vendor/bin/php-cs-fixer fix
