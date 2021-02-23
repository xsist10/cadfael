build: psalm phpcs phpunit

psalm:
	./vendor/bin/psalm

phpcs:
	./vendor/bin/phpcs -sw --standard=PSR2 src

phpunit:
	./vendor/bin/phpunit


.DEFAULT_GOAL := build