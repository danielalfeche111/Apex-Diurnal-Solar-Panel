# Database setup and preserved data

The Symfony application uses Doctrine ORM, DBAL, and Doctrine Migrations with MySQL 8. It has **14 application tables** and two order-history triggers. The schema and migrations are tracked in `database/schema.sql`, `database/triggers.sql`, and `migrations/`; all application tables are mapped in `src/Entity/`. The baseline migration is safe to run after restoring an existing database; it creates missing tables and triggers without dropping existing records. The next migration inserts the four catalog products and two inventory records only if absent. The third migration adds the `Commercial` RFQ facility choice where missing, and the fourth adds two missing indexes. None of these migrations creates a customer or administrator account.

The original site's complete database was exported to the private, Git-ignored file `var/backups/solar_db-portable.sql`: **14 tables, 504 records, and two triggers**. The original customer accounts, password hashes, IDs, carts, orders, RFQs, and inventory values are in that backup. It contains personal data and must be copied securely for deployment; cloning the source repository alone cannot reproduce those records. The backup was restored and verified in the Docker MySQL volume. A separate current backup, `var/backups/solar_db-current-2026-10-20.sql`, includes the present Docker data and Doctrine migration metadata.

## Existing data with Docker Compose

From the project root, with Docker Desktop/Engine running:

```sh
docker compose up -d database
docker compose --profile import run --rm import
docker compose up -d --build app phpmyadmin
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
```

The import reads `var/backups/solar_db-portable.sql` into the empty `solar_db` MySQL volume, then checks for 14 application tables, 504 records, and two triggers. It refuses to import into a populated database. The application is served from `public/` on `http://127.0.0.1:8000`; phpMyAdmin is available at `http://127.0.0.1:8081`. No external database installation is required with Docker.

On this workspace the Docker volume has already been imported and migrated. On subsequent starts use `docker compose up -d --build`; do not repeat the import. Log into Docker phpMyAdmin as `solar` using `DB_PASSWORD` from the private, Git-ignored project-root `.env`.

If the Docker volume is already populated, skip the import and run the migration command. Back up existing data before modifying a populated deployment. Keep `DB_PASSWORD`, `DB_ROOT_PASSWORD`, and `APP_SECRET` in a local `.env` or deployment secret manager rather than using the example defaults outside local development.

## Fresh database with Docker Compose

```sh
docker compose up -d --build
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
```

This creates the empty schema, its triggers, the four original storefront products, and two opening-stock records. It deliberately does not create default login credentials. Customers can register through the site; an administrator account must be provisioned explicitly for a fresh deployment.

## Verification

On the imported database, all four Doctrine migrations completed while the original 504 records and two triggers remained. On a separate empty database, they produced 14 application tables, two triggers, four products, two inventory rows, and zero user/admin accounts. Doctrine also creates its own migration metadata table.

The original backup is never served from the web root. Keep the web server document root set to `public/`.
