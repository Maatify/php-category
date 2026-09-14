# Contributing — maatify/php-category

## Scope

Keep this repository framework-neutral and focused on the reusable Category,
Category Content, Content Field, Image Role, and Image Assignment capabilities.
Their domain APIs, thin package Facade, and framework-neutral Factory are
package-owned boundaries. Do not add Catalog, Product, Admin/Slim, presentation,
or Host-specific integrations to this package.

The stable package contract is [CATEGORY_PACKAGE_REFERENCE.md](CATEGORY_PACKAGE_REFERENCE.md).
Detailed architecture notes belong under `docs/` and must link back to that
reference.

## Ways to contribute

- Report a reproducible bug or propose a focused documentation improvement.
- Submit a narrowly scoped change with the required Unit and real-engine
  Integration coverage when behavior changes in any owned capability.
- Discuss architecture or public-contract changes before implementation when
  they affect package boundaries, dependencies, schema, or compatibility.

## Local verification

From the repository root:

```bash
composer validate --strict
composer dump-autoload --optimize --strict-psr
composer check-platform-reqs
composer audit --no-interaction --abandoned=fail
composer analyse
composer test:unit
composer test
```

The CI standalone-consumer check creates a clean temporary Composer project,
installs this package from its VCS source, verifies optimized PSR-4 loading and
platform requirements, installs the package schema in isolated MySQL, and
executes the consumer script without Host-owned files or dependencies.

Integration verification uses real MySQL 8.0.16 or later and requires:

```bash
export CATEGORY_TEST_DSN='mysql:host=127.0.0.1;port=3306;dbname=category_test;charset=utf8mb4'
export CATEGORY_TEST_DB_USER='category_test'
export CATEGORY_TEST_DB_PASSWORD='secret'
composer test:integration
```

The package does not commit `composer.lock`; Composer may create it temporarily
during local verification. Remove it before committing.

## Pull Request expectations

- Keep each PR within one clearly stated scope and follow the Phase Stack
  workflow in the pinned engineering standards.
- Do not add unrelated refactors, Host integrations, or framework bindings.
- Describe the starting branch, changed files, verification results, and any
  remaining limitation or deferred work.
- Do not merge directly to `main`; the repository owner approves the final
  merge.

## Change requirements

- Preserve public typed contracts and naming suffixes.
- Add or update Unit and real-engine Integration coverage for behavior changes.
- Keep PHPStan at level max with zero baseline or hidden suppressions.
- Update `CATEGORY_PACKAGE_REFERENCE.md` only when the stable package contract
  changes; put detailed architecture notes under `docs/`.

## Security and architecture discussions

Do not report vulnerabilities in a public issue. Use the private reporting route
described in [SECURITY.md](SECURITY.md).

Architecture changes that affect package boundaries, public contracts,
Composer dependencies, schema, persistence behavior, or host isolation require
an explicit discussion and an approved decision before implementation.
