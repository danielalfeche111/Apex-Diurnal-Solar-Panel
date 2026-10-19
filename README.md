# Apex Diurnal on Symfony and Docker

The site runs on Symfony 7.4, PHP 8.3, Doctrine, and MySQL 8. The existing public URLs, page layouts, styles, images, and customer flow remain in place. Product and inventory CRUD is in the existing admin Inventory Control area. Archived products leave the storefront and checkout while historical orders remain intact.

The Symfony scaffold includes `config/routes.yaml`, `config/routes/`, `config/packages/cache.yaml`, `config/preload.php`, `assets/`, `translations/`, `src/Repository/`, and `var/log/`. Existing frontend files remain in `public/` and the current PHP templates remain in `templates/`; the scaffold does not change the website's rendering or routes.

## Run the prepared Docker installation

The Docker MySQL volume in this workspace already contains the preserved database, and all four Doctrine migrations have run. Docker Desktop must be running. From the project root:

```powershell
docker desktop start
docker compose up -d --build
docker compose ps
```

Open the Symfony site at `http://127.0.0.1:8000/` and phpMyAdmin at `http://127.0.0.1:8081/`. Sign in to phpMyAdmin as `solar`, using `DB_PASSWORD` from the private, Git-ignored `.env` file, and select `solar_db`. The database is accessible only to the Docker network; both web ports bind to this computer's loopback address. The Docker MySQL named volume persists across container restarts. Do not run `docker compose down -v` unless you intend to delete that volume.

To stop the containers without deleting data:

```powershell
docker compose down
```

## Restore the preserved database on a new Docker volume

Copy the private, Git-ignored `var/backups/solar_db-portable.sql` securely into the same path on the new machine. Set unique `DB_PASSWORD`, `DB_ROOT_PASSWORD`, and `APP_SECRET` in a private `.env`, then run:

```powershell
docker compose up -d database
docker compose --profile import run --rm import
docker compose up -d --build app phpmyadmin
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
```

The import refuses a populated database and verifies 14 application tables, 504 records, and two triggers. For a fresh installation without the original customer and administrator records, skip the import step and run the migrations after starting the containers. See [database/README.md](database/README.md).

## Admin catalog

Open `http://127.0.0.1:8000/admin/inventory/index.php` after signing in as an administrator. **Add Product**, **Edit**, and **Archive/Restore** manage the MySQL catalog; **Adjust** updates physical stock. The editor records stock changes in `inventory_transactions`. Existing product cards retain their presentation, while storefront and checkout names, prices, and availability follow MySQL.

## Doctrine entities

`src/Entity/` contains 14 Doctrine ORM entities for all 164 columns in the application tables, including the foreign-key relationships, unique constraints, and indexes. Each entity has a matching `ServiceEntityRepository` in `src/Repository/`, registered through its `repositoryClass` mapping. `Product` and `Inventory` can be used through Doctrine's entity manager; a transactional write/read check covers their relationship. Existing DBAL-backed controllers and services continue to use the same SQL so the site behavior and URLs stay unchanged. Check the mappings with `docker compose exec app php bin/console doctrine:mapping:info` and `docker compose exec app php bin/console doctrine:schema:validate --skip-sync`.

The preserved MySQL schema has legacy timestamp definitions and some redundant indexes that Doctrine's schema comparator would rewrite. Keep schema changes in reviewed Doctrine migrations; do not apply `doctrine:schema:update --force` to the preserved database.

## Verification

The Docker import verified all original records and triggers. A fresh private backup of the current Docker database is in `var/backups/solar_db-current-2026-10-20.sql`. Doctrine reports all four migrations complete in `solar_db`; the containerized homepage, registration page, and phpMyAdmin login return HTTP 200. The full regression suite passed 494 assertions against an isolated MySQL test database before the Docker switch. The preservation check validates 55 original frontend assets, 21 unchanged template HTML blocks, and all 42 original public URLs; the inventory template was extended for CRUD. See [tests/README.md](tests/README.md).

Docker is the supported runtime. The retired standalone PHP, MySQL, and phpMyAdmin installation directories and `.env.local` have been removed. Keep `var/backups/` because it contains the original and current SQL exports.
