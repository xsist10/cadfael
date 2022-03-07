build: psalm phpcs phpunit

psalm:
	./vendor/bin/psalm --use-baseline resources/pslam-baseline

phpcs:
	./vendor/bin/phpcs -sw --standard=PSR2 src

phpunit:
	./vendor/bin/phpunit


.DEFAULT_GOAL := build
