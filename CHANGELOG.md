# Changelog

All notable changes to this package will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

**RC publication preparation:** target `v1.0.0-rc.1` after umbrella PR #48
merges. Tagging, release, and distribution publication remain a separate
owner-approved release action. The RC has not been tagged, published, or made
externally Composer-resolvable; no release date is assigned. The Consumer
Verification Harness and at least two independent Real Host validations must
use that same published RC before the first Stable release.

These unreleased notes describe package work prepared for the target
`v1.0.0-rc.1`; they do not describe a published RC or set a release date.

### Added

- Initial standalone extraction of the reusable Category and optional Category
  Content domain/application contracts.
- MySQL schema, PDO repositories, shared Persistence transaction runner, and real-engine
  Integration tests for hierarchy, lifecycle, ordering, and visibility.
- Root Package Reference and standards-aligned Composer/CI configuration.
- Added the practical [Consumer Usage Guide](docs/USAGE_GUIDE.md) for factory
  setup, the five Domain APIs, exact scopes, lifecycle, pagination, and
  end-to-end Host workflows.
- Expanded management query APIs with shared Persistence pagination,
  Category-owned SQL search by code, public management `getByCode()`, and
  unit, MySQL Integration, and standalone consumer coverage through the
  public Facade APIs.
- Added typed Content `name` and `description` inline updates and typed
  Content Field value inline updates that preserve the atomic format/value
  invariant.
- Added the Image Assignment consumer workflow with explicit `assign()`,
  `reorder()`, and `remove()` operations, typed exact scope input, and
  lifecycle/default/order/read coverage through the public Facade APIs.
- Completed public consumer-surface verification, stale API/alias review,
  package-boundary checks, and standalone external Composer consumer
  verification against the v1 runtime.

### Changed

- Removed Category-owned UTC timestamp normalization. Hosts provide
  `Maatify\SharedCommon\Contracts\ClockInterface` and its timezone; Category
  persists timestamp values as supplied and hydrates them using that Host Clock
  timezone.
- Reconciled package documentation with the selective pinned standards adoption
  and the current v1 runtime contract.
- Replaced the former command-service composition with a thin `CategoryFacade`,
  five Domain APIs, split domain services, domain-owned read contracts and PDO
  adapters, and a framework-neutral package-level `CategoryFactory`.
- Documented separate management and consumer visibility reads, bounded
  unpaginated lists, deterministic ordering, and management query behavior
  while keeping Search Category-owned and SQL-based.
- Corrected the pagination sort-key contract: Image Assignment and Content
  Field default to the explicit `business_order` key while
  `category_id` retains direct Category ID semantics.
- Kept existing full-form mutation operations intact while exposing typed
  partial operations for safe inline editing. Category and Image Role retain
  their existing dedicated operations; Image Assignment exposes consumer-oriented
  `assign()`, `reorder()`, and `remove()` operations with typed
  default and restore mutations. No generic `updateField()` contract or
  Media/Storage workflow was added.
- Prepared release-facing documentation for target `v1.0.0-rc.1` as part of RC
  publication preparation; the RC remains unpublished and has no release date.
- Corrected the previous content model to unified Category Content,
  supporting one unlocalized (`language_code = NULL`) row and localized rows
  under a database-enforced logical identity.
- Added first-class Category Image Assignments with exact nullable
  language/platform scopes, stable NULL-safe identity, independent shared
  ordering, soft-delete/restore lifecycle, management reads, and ancestor-aware
  consumer reads. Category stores only the host-provided Media Asset identity;
  Media lifecycle remains outside the package.
- Added explicit Category Image Assignment defaults within exact
  (category_id, role_id, language_code, platform) scopes. Each scope allows
  zero or one active default, with shared-transaction Set/Clear mutations and a
  conditional generated MySQL uniqueness identity; no automatic fallback or
  promotion exists.
- Added the package-owned Category Image Role registry with immutable globally
  unique keys, typed active/inactive status, soft-delete/restore lifecycle,
  permanent key reservation, management reads, and Role-aware exact Image
  Assignment identity, ordering, and consumer visibility.
- Added first-class Host-defined Category Content Fields with exact nullable
  language/platform scopes, typed text/html/json formats, LONGTEXT storage,
  NULL-safe identity reserved across soft deletion, independent shared ordering,
  management reads, and exact ancestor-aware consumer reads.
- Migrated Category orchestration from its local transaction implementation to
  the published `maatify/persistence:^1.3` transaction contract. Hosts provide
  `PdoTransactionRunner` with the same PDO used by Category persistence;
  caller-owned outer transactions remain Host-owned.
