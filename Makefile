build: psalm phpcs phpunit
package: psalm phpcs phpunit box

psalm:
	./vendor/bin/psalm --use-baseline resources/pslam-baseline

phpcs:
	./vendor/bin/phpcs -sw --standard=PSR2 src

phpunit:
	XDEBUG_MODE=coverage ./vendor/bin/phpunit

box:
	box compile

.DEFAULT_GOAL := build
