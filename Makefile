PHP_FILES := src/ tests/ # Ajusta a tus carpetas
REPORT_DIR=build/reports
COVERAGE_DIR=build/coverage

.PHONY: lint test

# Ejecuta los tests unitarios
test:
	mkdir -p $(REPORT_DIR)
	vendor/bin/phpunit --configuration .ci/phpunit.xml.dist --display-warnings --display-deprecations --log-junit $(REPORT_DIR)/test-report.xml

format:
	PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix --config=.ci/php-cs.dist

lint:
	mkdir -p $(REPORT_DIR)
	PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix src/Shared --config=.ci/php-cs.dist
	PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix src/Bootstrap --config=.ci/php-cs.dist

coverage:
	mkdir -p $(REPORT_DIR)
	php -d memory_limit=1G -d xdebug.mode=coverage vendor/bin/phpunit --configuration .ci/phpunit.xml.dist --coverage-html $(REPORT_DIR)/coverage-html --coverage-clover $(REPORT_DIR)/coverage-report.xml --coverage-filter src/

coverage-view:
	mkdir -p $(COVERAGE_DIR)
	php -d memory_limit=1G -d zend_extension=xdebug.so -d xdebug.mode=coverage vendor/bin/phpunit --configuration .ci/phpunit.xml.dist --coverage-html ${REPORT_DIR}/coverage/ --coverage-filter src/

# Ejecuta análisis de estilo de código (linting)
static-analysis:
	mkdir -p $(REPORT_DIR)
	php -d zend_extension=xdebug.so vendor/bin/psalm --config=.ci/psalm.xml --no-cache --no-progress --output-format=xml > $(REPORT_DIR)/static-analysis-report.xml

sast:
	mkdir -p $(REPORT_DIR)
	vendor/bin/psalm --config=.ci/psalm.xml --no-cache --taint-analysis --no-progress --output-format=xml > $(REPORT_DIR)/sast-report.xml

