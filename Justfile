set windows-shell := ["powershell.exe", "-NoLogo", "-Command"]

coverage_min := env_var_or_default("COVERAGE_MIN", "84")

# List available recipes
default:
    @just --list

# Validate composer.json
composer-validate:
    composer validate

# Fix code style
cbf:
    php vendor/bin/phpcbf

# Run static analysis
stan:
    php vendor/bin/phpstan --memory-limit=-1

# Check for unused and misplaced dependencies
require-check:
    php vendor/bin/composer-dependency-analyser

# Check for security vulnerabilities
security-check:
    composer audit

# Run tests
test:
    php vendor/bin/phpunit

# Run PHPUnit coverage gate
coverage:
    mkdir -p var/coverage
    php vendor/bin/phpunit --coverage-clover var/coverage.xml
    php vendor/bin/coverage-check var/coverage.xml {{coverage_min}}

# Check dependencies between layers
deptrac:
    php vendor/bin/deptrac analyse

# Lint markdown, don't look at externally sourced files
markdown:
    markdownlint README.md

# Deploy to the configured server
deploy:
    php vendor/bin/dep deploy

# Build and push multi-arch Docker image to GHCR
docker-build:
    docker buildx build --platform linux/amd64,linux/arm64 -t ghcr.io/andrewmy/re-notifier:latest --push .

# Deploy Docker stack
deploy-docker:
    php vendor/bin/dep deploy-docker reservoir

# Full CI flow
ci: composer-validate cbf require-check coverage stan deptrac security-check
