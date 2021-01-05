#!/bin/bash
./vendor/bin/psalm && \
  ./vendor/bin/phpcs -sw --standard=PSR2 src && \
  ./vendor/bin/phpunit
