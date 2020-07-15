#!/bin/bash
./vendor/bin/phpstan analyse -l 6 src && \
  ./vendor/bin/phpcs -sw --standard=PSR2 src && \
  ./vendor/bin/phpunit
