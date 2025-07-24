PHP_FILES := src/ tests/ # Ajusta a tus carpetas
REPORT_DIR=build/reports
COVERAGE_DIR=build/coverage

.PHONY: lint test

# Ejecuta los tests unitarios
test:
	mkdir -p $(REPORT_DIR)
	vendor/bin/phpunit  --log-junit $(REPORT_DIR)/test-report.xml

format:
	PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix src/  --config=php-cs.dist
	PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix test/  --config=php-cs.dist

lint:
	mkdir -p $(REPORT_DIR)
	PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix src/ --dry-run --format=checkstyle > $(REPORT_DIR)/lint-report.xml
	PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix test/ --dry-run --format=checkstyle >> $(REPORT_DIR)/lint-report.xml
# --config=.php-cs-fixer.php

coverage:
	mkdir -p $(REPORT_DIR)
	php -d xdebug.mode=coverage vendor/bin/phpunit --coverage-clover $(REPORT_DIR)/coverage-report.xml --coverage-filter src/

coverage-view:
	mkdir -p $(COVERAGE_DIR)
	php -d zend_extension=xdebug.so -d xdebug.mode=coverage vendor/bin/phpunit --coverage-html ${REPORT_DIR}/coverage/ --coverage-filter src/
	
# Ejecuta análisis de estilo de código (linting)
static-analysis:
	mkdir -p $(REPORT_DIR)
	php -d zend_extension=xdebug.so vendor/bin/psalm --no-cache --no-progress --output-format=xml > $(REPORT_DIR)/static-analysis-report.xml

sast:
	mkdir -p $(REPORT_DIR)
	vendor/bin/psalm --no-cache --taint-analysis  --no-progress --output-format=xml > $(REPORT_DIR)/sast-report.xml

# Formatea automáticamente el código
#format:
#	vendor/bin/php-cs-fixer fix $(PHP_FILES)

