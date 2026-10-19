# Migration regression checks

With the Docker installation running, run the asset, markup, and native-route checks in the app container:

```powershell
docker compose exec app php tests/run.php --static-only
docker compose exec app php bin/console lint:container
```

The full HTTP regression suite was run against a separate disposable MySQL database before the Docker switch. To repeat it, provide a disposable loopback MySQL instance and run the suite with any PHP 8.2+ CLI that has `curl` and `pdo_mysql` installed:

```powershell
$env:TEST_DATABASE_URL = "mysql://test_user:<password>@127.0.0.1:3309/migration_test?charset=utf8mb4"
php tests\run.php
Remove-Item Env:TEST_DATABASE_URL
```

Composer dependencies must be installed. The runner accepts only a loopback database host and a name equal to `migration_test` or beginning with `migration_test_`. It creates that database when the account has permission, runs Doctrine migrations, and passes the selected URL explicitly to the Symfony CLI and its own HTTP server. It never points the application at the preserved Docker `solar_db` database.

The suite creates unique customer and administrator fixtures, then checks the persisted effects of the original workflows: authentication, session keepalive, profiles, password changes, carts, checkout totals and receipts, bookings, commercial RFQs, order ownership, administrator CSRF checks, product CRUD with storefront and checkout visibility, stock and order status changes, quote assignments, and calendar updates. It also checks original URLs, directory aliases, assets, and private-file protection.

Fixtures remain in the disposable database for inspection. Each run writes a server log under `var/test-http-*` and stops its own test server. Browser JavaScript and responsive layout should also be inspected manually when visual changes are made.
