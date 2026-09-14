<div align="center">

# Maatify Category

![Maatify.dev](https://www.maatify.dev/assets/img/img/maatify_logo_white.svg)

[![Maatify Ecosystem](https://img.shields.io/badge/Maatify-Ecosystem-blueviolet)](https://github.com/Maatify)
[![PHP](https://img.shields.io/badge/PHP-8.4%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-2F9E44.svg)](phpstan.neon)
[![Changelog](https://img.shields.io/badge/Changelog-View-blue.svg)](CHANGELOG.md)
[![Package Reference](https://img.shields.io/badge/Reference-Read-blue.svg)](CATEGORY_PACKAGE_REFERENCE.md)
[![Security Policy](https://img.shields.io/badge/Security-Policy-blue.svg)](SECURITY.md)
[![Contributing Guide](https://img.shields.io/badge/Contributing-Guide-blue.svg)](CONTRIBUTING.md)

Framework-neutral hierarchical categories, contents, extensible Host-defined
content fields, a Category-owned Image Role registry, and Category-owned image assignments for reusable PHP
applications.

**Version:** `v1.0.0-rc.1`

**Release status:** Release Candidate (Pre-Stable)

**Install after publication:** `composer require maatify/php-category:1.0.0-rc.1`

No Stable compatibility or supported Stable line is claimed. Before the first
Stable release, the Consumer Verification Harness and at least two independent
Real Host validations must use this exact RC.

</div>

---

## Package Summary

`maatify/php-category` provides typed Category domain/application contracts, PDO
adapters, MySQL schema, hierarchy invariants, lifecycle operations, ordering,
and exact-scope references to host Media Asset identities and Host-defined
content values,
and separate management and visible query/list behavior. It is independent of
Catalog, Product, Admin, Slim, HTTP, permissions, and presentation layers.

## Key Features

- Typed immutable Category and Category Content DTOs.
- Typed extensible Category Content Field DTOs with text, HTML, and JSON formats.
- Typed Category Image Role DTOs with immutable globally unique keys and typed lifecycle status.
- Typed Category Image Assignment DTOs with exact language/platform/role scopes
  and an explicit default marker.
- Stable immutable Category codes and content identities.
- Parent movement with complete cycle prevention.
- Category and Content create, full-form update, typed inline field updates,
  soft-delete, and restore lifecycle mutations, plus Category status and
  display-order mutations.
- Image Assignment assign, exact-scope ordering, explicit default assignment,
  reversible remove/restore lifecycle; stable identity remains reserved after
  removal.
- Image Role create, status update, soft-delete, restore, and bounded management
  reads; role keys remain permanently reserved after soft deletion.
- Content Field create, atomic value/format full-form update, typed inline value
  update, exact-scope ordering, soft-delete, restore, management reads, and
  exact consumer reads; field keys remain Host-defined.
- Shared Persistence transaction and row-locking contracts for
  hierarchy/lifecycle invariants; the Host owns outer transaction boundaries.
- Shared `maatify/persistence` Ordering API for root and nested scopes.
- MySQL recursive ancestor visibility filtering for query/list reads.
- Deterministic, bounded consumer visibility lists and management reads with
  separate typed criteria; management criteria expose explicit status and
  deleted-state controls while consumer criteria do not.
- No associative arrays as public or domain contracts.

## Public Runtime API

The package exposes twenty-eight typed mutation Commands, immutable Category,
Content, Image Role, Image Assignment, and Content Field DTOs, six bounded
criteria DTOs, five enums, a unified `CategoryFacade` with five domain APIs,
typed service and repository contracts, and framework-neutral PDO adapters.
The complete constructor and method inventory is maintained in the
[Category Package Reference](CATEGORY_PACKAGE_REFERENCE.md).

The internal `findByCode()` mutation-support lookup remains separate from the
management query port. The Stage 3 management surface now provides exact public
`getByCode()`, shared Persistence pagination for management lists, and
Category-owned SQL search by Category code. Search does not delegate to a
Persistence search engine; the Host still owns language fallback and policy.
The Stage 4 mutation surface retains full-form updates and adds typed Content
`name`/`description` and Content Field `value` operations for inline editing;
Content Field `format` and `value` remain one atomic invariant.

## Query and list behavior

Management reads and consumer visibility reads are separate contracts.
Management criteria can select status and deleted state; consumer criteria
cannot bypass active/non-deleted ancestor visibility. Every unpaginated list is
bounded to at most 100 rows. Category lists use `display_order, id`; Content
lists use `language_code, id`; Image Role lists use `role_key, id`; Image
Assignment and Content Field lists use exact scopes and deterministic
`display_order, id` ordering within each scope. Management list APIs also
expose canonical `maatify/persistence` pagination results. The package owns
Category search in its SQL/query layer and does not implement a local search or
pagination engine.
For paginated Image Assignment and Content Field management lists, the default
sort key is the explicit `business_order` key; `category_id` retains its direct
Category ID sorting semantics.
Management Image Assignment criteria can independently omit the Role filter,
match the exact NULL Role, or match one concrete Role while retaining exact
language/platform filtering.

## Category Content model

Category is the structural entity; its table does not duplicate `name` or
`description`. Optional Category Content stores those fields in one unified
content table. Use `language_code: null` for ordinary unlocalized content, or a
non-NULL language code for localized content. The schema enforces one NULL row
per Category and one row per non-NULL language code. The package does not select
fallback content; the Host owns semantic language validation and locale policy.

## Category Image Assignment model

Category owns a direct relation to host-provided Media Asset identities. Each
assignment has an immutable stable identity of
`(category_id, media_asset_id, role_id, language_code, platform)`, with
`role_id`, `language_code`, and `platform` each nullable and every exact
combination supported. NULL is an exact scope value, not fallback; an absent
Role is the generic/unclassified scope. New role-scoped assignments require an
active, non-deleted Role; existing assignments become invisible to consumers
while their Role is inactive or soft-deleted, but remain available to
management reads. Empty strings are invalid, no platform enum is hardcoded, and
the same Media Asset may be used in another scope. Default is an explicit
property of Category Image Assignment within one exact scope; zero or one
default is allowed and no automatic fallback or promotion exists. The default
is independent from `display_order`; soft-deleting a default clears it and
restoring the assignment leaves it non-default. Category does not own Media,
Platform, or Language lifecycle and creates no foreign key to those host
concepts.

## Image Assignment consumer workflow

The Host owns upload and Media/Storage lifecycle. After upload returns a
mediaAssetId, pass only that ID to Category. Use one exact typed scope for both
assignment and consumer reads:

    use Maatify\Category\ImageAssignment\Assignment\Command\CreateCategoryImageAssignmentCommand;
    use Maatify\Category\ImageAssignment\CategoryImageAssignmentScopeDTO;
    use Maatify\Category\ImageAssignment\Ordering\Command\UpdateCategoryImageAssignmentDisplayOrderCommand;
    use Maatify\Category\ImageAssignment\Default\Command\SetCategoryImageAssignmentDefaultCommand;
    use Maatify\Category\ImageAssignment\Lifecycle\Command\SoftDeleteCategoryImageAssignmentCommand;
    use Maatify\Category\ImageAssignment\Lifecycle\Command\RestoreCategoryImageAssignmentCommand;

    $images = $category->images();
    $scope = new CategoryImageAssignmentScopeDTO('en-US', 'web');

    $assignmentId = $images->assign(
        new CreateCategoryImageAssignmentCommand($categoryId, $mediaAssetId, $scope),
    );
    $images->reorder(new UpdateCategoryImageAssignmentDisplayOrderCommand($assignmentId, 1));
    $images->setDefault(new SetCategoryImageAssignmentDefaultCommand($assignmentId));
    $visibleAssignments = $images->listVisibleForCategory($categoryId, $scope);
    $images->remove(new SoftDeleteCategoryImageAssignmentCommand($assignmentId));
    $images->restore(new RestoreCategoryImageAssignmentCommand($assignmentId));

NULL language, platform, or Role values are exact scope dimensions and never
fall back. A Role ID is optional and is added to the scope only for a
Role-scoped assignment backed by an active, non-deleted Category Image Role.

## Category Image Role model

Category owns the Image Role registry. `role_key` is an immutable, globally
unique Host-defined key and remains reserved after soft deletion. Roles have
typed `active`/`inactive` status independent from `deleted_at`; management reads
support get-by-ID, get-by-key, bounded lists, explicit status, and deleted-state
filters. Role semantics, cardinality, and media policy remain Host-owned.

Consumer reads require an exact scope, exclude deleted assignments, and hide
assignments when any Category ancestor is inactive or deleted. Management reads
can omit scope filtering or request an exact scope, including explicit NULL for
Role, language, and platform. Omitting the scope filter is distinct from
requesting the generic/unclassified NULL Role scope.

## Category Content Field model

Category Content Fields are arbitrary Host-defined key/value records separate
from the fixed `CategoryContent` name/description model. Their stable identity
is `(category_id, field_key, language_code, platform)`, including soft-deleted
rows. `NULL` language/platform values are exact neutral dimensions, not
fallbacks; all four scope combinations are supported. Values use exact
lowercase `text`, `html`, or `json` formats and are stored without HTML
sanitization or rendering. The database enforces the format case exactly.
The Host owns the meaning of `field_key`, WYSIWYG policy, rendering, and JSON
semantics. Consumer reads require the exact scope and complete ancestor
visibility.

## Requirements

- PHP 8.4 or a compatible later PHP 8.x release, as constrained by Composer.
- Composer.
- MySQL 8.0.16 or later, required by the package storage contract because
  enforced `CHECK` constraints are part of the schema behavior.

## Installation

Install the `v1.0.0-rc.1` Release Candidate with Composer:

```bash
composer require maatify/php-category:1.0.0-rc.1
```

A branch, repository checkout, local path, or VCS source is not a published RC
and does not substitute for exact-version Consumer Verification Harness or
Real Host validation.

## Quick Usage

For a complete Host workflow covering all five Domain APIs, exact scopes,
management reads, pagination, lifecycle, and external Media assignments, see
the [Consumer Usage Guide](docs/USAGE_GUIDE.md).

The Host provides the existing `PDO` connection and
`Maatify\SharedCommon\Contracts\ClockInterface`. Category owns timestamps as
values, but does not own timezone policy or normalize timestamps to UTC. The
Factory wires the complete framework-neutral application once; each facade
accessor exposes one domain API.

```php
use Maatify\Category\Factory\CategoryFactory;
use Maatify\Category\Content\Mutation\Command\CreateCategoryContentCommand;
use Maatify\Category\Lifecycle\Command\CreateCategoryCommand;

// $pdo and $clock are supplied by the host application.
$category = CategoryFactory::create($pdo, $clock);

$categoryId = $category->categories()->create(
    new CreateCategoryCommand('clothing'),
);
$category->contents()->create(
    new CreateCategoryContentCommand($categoryId, null, 'Clothing', null),
);

$visibleCategory = $category->categories()->getById($categoryId);
```

The Category package owns the syntactic and storage validation of non-NULL
`language_code` values required by its contract and Runtime. The host application owns dependency
injection, semantic language validation, fallback/locale policy, HTTP response
envelopes, and presentation formatting.

## Documentation

- [Consumer Usage Guide](docs/USAGE_GUIDE.md)
- [Category Package Reference](CATEGORY_PACKAGE_REFERENCE.md)
- [Category schema](schema/category.sql)
- [Schema notes](schema/README.md)
- [Changelog](CHANGELOG.md)
- [Contributing](CONTRIBUTING.md)
- [Security policy](SECURITY.md)

The standalone consumer verification builds a clean temporary Composer project
from the package VCS source and exercises installation, optimized PSR-4
autoloading, platform checks, schema installation, and host-isolation checks.

## Quality Status

Run the package checks from the repository root:

```bash
composer validate --strict
composer dump-autoload --optimize --strict-psr
composer check-platform-reqs
composer audit --no-interaction --abandoned=fail
composer analyse
find src tests -type f -name '*.php' -exec php -l {} \;
composer test:unit
composer test:integration
composer test
```

Integration tests require `CATEGORY_TEST_DSN`, `CATEGORY_TEST_DB_USER`, and
`CATEGORY_TEST_DB_PASSWORD` and run against the same required MySQL runtime
version: 8.0.16 or later.

For local Integration or full-suite runs, copy `env.testing.example` to
`env.testing`, set the local MySQL credentials, and create the configured
database before running PHPUnit. `tests/bootstrap.php` loads this ignored file
as local defaults without overriding environment variables supplied by CI.

Example local setup:

```bash
cp env.testing.example env.testing
composer test:integration
composer test
```

This Release Candidate is pre-Stable; RC readiness does not mean Stable
readiness.

## License

MIT. See [LICENSE](LICENSE).

## Author

Engineered by **Mohamed Abdulalim** ([@megyptm](https://github.com/megyptm))<br>
Backend Lead & Technical Architect<br>
[https://www.maatify.dev](https://www.maatify.dev)

---

<div align="center">

[Built with ❤️ by Maatify.dev — Unified Ecosystem for Modern PHP Libraries](https://www.maatify.dev)

</div>
