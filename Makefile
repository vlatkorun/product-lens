.PHONY: analyse fix test test-unit test-integration

analyse:
	php -d memory_limit=1G vendor/bin/phpstan analyse

fix:
	php vendor/bin/php-cs-fixer fix

test:
	php vendor/bin/phpunit

test-unit:
	php vendor/bin/phpunit --testsuite Unit

test-integration:
	php vendor/bin/phpunit --testsuite Integration
