# BookStore Co. — Drupal 11 traditional storefront

This package contains the custom Drupal module and Twig theme for the BookStore classroom application. It is an overlay for a Composer-managed `drupal/recommended-project` installation. 

## Requirements

- Drupal 11, PHP 8.3+, PostgreSQL 16+, `php-pgsql`, `php-bcmath`, `php-gd`, `php-xml`, `php-mbstring`, `php-curl`, `php-zip`, `php-intl`.
- PostgreSQL `pg_trgm` extension in the Drupal database. `pgcrypto` and `gen_random_uuid()` for the legacy schema.
- The theme loads fixed Bootswatch Lux 5.3.8 and Bootstrap JS 5.3.8 from jsDelivr at runtime; browser access to that CDN is required. For offline/isolated deployments, vendor these two assets locally and change `bookstore_lux.libraries.yml`.


## Install on a fresh non-Docker Drupal site

Create and install Drupal 11 with PHP 8.3+, PostgreSQL 16+ and PHP PostgreSQL/bcmath extensions. Enable `pg_trgm` in the same database first. Copy this package's custom module and theme into the respective `web/` paths.
