# Category Consumer Usage Guide

This guide is for a Host application consuming `maatify/php-category` through its
public PHP API. It complements the [Category Package Reference](../CATEGORY_PACKAGE_REFERENCE.md):
the reference is the complete contract inventory, while this guide shows the
normal Host workflow and the decisions that must remain explicit.

## What the package provides

`maatify/php-category` owns these five domain areas:

- Categories: hierarchy, status, display order, and lifecycle.
- Category Content: unlocalized and localized name/description records.
- Content Fields: Host-defined text, HTML, or JSON values in exact scopes.
- Image Roles: a Category-owned registry of Host-defined role keys.
- Image Assignments: links from a Category to Host-provided Media Asset IDs.

The package is framework-neutral. It does not provide HTTP routes, controllers,
permissions, UI behavior, language fallback, Media upload/storage, or
presentation serialization. A Host should keep those concerns in its own
application layer.

## Installation and prerequisites

The canonical Composer package name is `maatify/php-category`. The target
`v1.0.0-rc.1` is still unpublished and not externally Composer-resolvable; use
the command only after an approved release is actually available from a
Composer source:

```bash
composer require maatify/php-category
```

The runtime contract requires PHP `^8.4`, `ext-mbstring`, `ext-pdo`,
`ext-pdo_mysql`, MySQL `8.0.16+`, `maatify/exceptions:^1.0`,
`maatify/persistence:^1.3`, and `maatify/shared-common:^1.0`. Install and run
the package-owned MySQL schema using the Host's deployment process before
calling the factory.

The Host supplies:

- a configured `PDO` connected to the package schema;
- a `Maatify\SharedCommon\Contracts\ClockInterface` implementation;
- semantic language validation and locale/fallback policy;
- permissions, transaction boundaries, HTTP, and presentation;
- Media/Storage upload and lifecycle, when images are used.

The package validates IDs, required strings, lengths, formats, exact nullable
scope values, and its own database invariants. It does not validate whether a
language or platform is meaningful to the Host.

## Factory and facade

Create one facade from the same `PDO` and Host clock used by the application:

```php
use DateTimeZone;
use Maatify\Category\Factory\CategoryFactory;
use Maatify\SharedCommon\Infrastructure\SystemClock;
use PDO;

$pdo = new PDO($dsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$clock = new SystemClock(new DateTimeZone('Africa/Cairo'));

$category = CategoryFactory::create($pdo, $clock);
```

`CategoryFactory::create(PDO $pdo, ClockInterface $clock)` returns a
`CategoryFacadeInterface`. The facade exposes exactly these five domain APIs:

```php
$category->categories();     // CategoryApiInterface
$category->contents();       // ContentApiInterface
$category->contentFields();  // ContentFieldApiInterface
$category->imageRoles();     // ImageRoleApiInterface
$category->images();         // ImageAssignmentApiInterface
```

The `images()` API manages Category Image Assignments only. It is not a Media
upload, storage, URL, binary, or transformation API.

## Categories

### Mutations

Use typed Commands for every mutation:

```php
use Maatify\Category\Hierarchy\Command\MoveCategoryCommand;
use Maatify\Category\Lifecycle\Command\CreateCategoryCommand;
use Maatify\Category\Lifecycle\Command\RestoreCategoryCommand;
use Maatify\Category\Lifecycle\Command\SoftDeleteCategoryCommand;
use Maatify\Category\Lifecycle\Command\UpdateCategoryStatusCommand;
use Maatify\Category\Ordering\Command\UpdateCategoryDisplayOrderCommand;
use Maatify\Category\Lifecycle\Enum\CategoryStatusEnum;

$categoryId = $category->categories()->create(
    new CreateCategoryCommand('news'),
);

$childId = $category->categories()->create(
    new CreateCategoryCommand('technology', $categoryId, CategoryStatusEnum::ACTIVE),
);

$category->categories()->move(new MoveCategoryCommand($childId, null));
$category->categories()->updateStatus(
    new UpdateCategoryStatusCommand($childId, CategoryStatusEnum::INACTIVE),
);
$category->categories()->updateDisplayOrder(
    new UpdateCategoryDisplayOrderCommand($childId, 2),
);
$category->categories()->softDelete(new SoftDeleteCategoryCommand($childId));
$category->categories()->restore(new RestoreCategoryCommand($childId));
```

Category codes are immutable after creation and must be unique. Creating a
child requires an existing, non-deleted parent; the parent's
`CategoryStatusEnum` may be `INACTIVE`. Moving a category requires a
non-deleted source and, when a new parent is supplied, a non-deleted target;
the target may also be `INACTIVE`. These mutation checks are soft-delete
checks, not status checks, and direct or indirect cycles are still rejected.
Soft deletion is blocked while the category has non-deleted children. A
category's status and display order are independent typed mutations.

### Consumer reads

Consumer reads expose only active, non-deleted categories whose complete
ancestor path is also active and non-deleted:

```php
use Maatify\Category\Query\DTO\CategoryVisibleListCriteriaDTO;

$visible = $category->categories()->getById($categoryId);
$roots = $category->categories()->listRootCategories(
    new CategoryVisibleListCriteriaDTO(maxResults: 50),
);
$children = $category->categories()->listChildren(
    $categoryId,
    new CategoryVisibleListCriteriaDTO(maxResults: 50),
);
```

`getById()` and the two visible list methods never return inactive, deleted,
or descendant categories hidden by an inactive/deleted ancestor. The visible
criteria only controls the bounded `maxResults` value (`1..100`); it cannot
disable visibility rules.

### Management reads

Management reads are separate and can explicitly select status and deleted
state:

```php
use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\Query\DTO\CategoryListCriteriaDTO;
use Maatify\Category\Lifecycle\Enum\CategoryStatusEnum;

$managed = $category->categories()->getByIdForManagement(
    $categoryId,
    CategoryDeletedStateEnum::INCLUDE_DELETED,
);
$byCode = $category->categories()->getByCode('news');
$all = $category->categories()->listForManagement(
    new CategoryListCriteriaDTO(
        status: CategoryStatusEnum::ACTIVE,
        maxResults: 100,
        search: 'new',
    ),
);
$managedRoots = $category->categories()->listRootCategoriesForManagement(
    new CategoryListCriteriaDTO(),
);
$managedChildren = $category->categories()->listChildrenForManagement(
    $categoryId,
    new CategoryListCriteriaDTO(),
);
```

The management API also has `paginateForManagement()`,
`paginateRootCategoriesForManagement()`, and
`paginateChildrenForManagement()`. Category `search` is a substring search
owned by Category and applied to `code`; the package escapes `%` and `_` as
search text. `getByCode()` is an exact code lookup. Management reads can use
`NON_DELETED`, `INCLUDE_DELETED`, or `DELETED_ONLY`.

## Category Content

Content stores the name and nullable description independently from the
Category row. Its immutable identity is `(category_id, language_code)`:

- `language_code: null` is the one unlocalized Content record for a Category.
- a non-null code is one localized Content record for that exact code.
- an empty string is invalid and is not equivalent to `null`.

The Host decides which language codes are supported and which locale to select.
The package performs no fallback and does not replace a missing localized row
with the unlocalized row. The visible Content read returns all visible Content
records for the Category, ordered by `language_code, id`; it has no language
filter. The Host selects the DTO whose `languageCode` matches its chosen
locale, if one exists.

### Mutations and reads

The complete Content mutation surface is:

```php
use Maatify\Category\Content\Mutation\Command\CreateCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\RestoreCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\SoftDeleteCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentDescriptionCommand;
use Maatify\Category\Content\Mutation\Command\UpdateCategoryContentNameCommand;
use Maatify\Category\Content\Query\DTO\CategoryContentListCriteriaDTO;

$unlocalizedContentId = $category->contents()->create(
    new CreateCategoryContentCommand($categoryId, null, 'News', null),
);
$englishContentId = $category->contents()->create(
    new CreateCategoryContentCommand($categoryId, 'en-US', 'News', 'Latest stories'),
);

$category->contents()->update(
    new UpdateCategoryContentCommand($englishContentId, 'News today', 'Updated description'),
);
$category->contents()->updateName(
    new UpdateCategoryContentNameCommand($englishContentId, 'News today'),
);
$category->contents()->updateDescription(
    new UpdateCategoryContentDescriptionCommand($englishContentId, null),
);
$category->contents()->softDelete(
    new SoftDeleteCategoryContentCommand($englishContentId),
);
$category->contents()->restore(
    new RestoreCategoryContentCommand($englishContentId),
);

$visibleContent = $category->contents()->listVisibleForCategory($categoryId);
$managedContent = $category->contents()->getByIdForManagement($englishContentId);
$contentList = $category->contents()->listForManagement(
    new CategoryContentListCriteriaDTO(categoryId: $categoryId),
);
```

The Content management API also provides `paginateForManagement()`. Visible
Content is returned only for a visible Category and excludes deleted Content.
There is no visible `getById()` method; use `listVisibleForCategory()` for
consumer reads and `getByIdForManagement()` when an administrative identity
lookup is intended.

Content creation requires a non-deleted Category; an inactive Category is not a
visible parent but is still a valid parent for package mutation rules. After a
Content record exists, its own update, soft-delete, and restore lifecycle is
addressed by the Content ID.

## Content Fields

Content Fields are Host-defined key/value records. Their identity is the exact
four-part combination `(category_id, field_key, language_code, platform)`.
`language_code` and `platform` are independent nullable dimensions, so all four
combinations are valid. A `null` dimension is an exact `NULL` value, not a
fallback instruction.

Supported formats are `TEXT`, `HTML`, and `JSON`:

- `TEXT` is stored as text.
- `HTML` is stored as text; the Host owns sanitization and rendering.
- `JSON` must be syntactically valid JSON; the package does not apply a JSON
  schema, semantic validation, or rendering.

The Host owns the meaning of `fieldKey`, the supported language/platform values,
and any presentation policy.

### Mutations and reads

```php
use Maatify\Category\ContentField\CategoryContentFieldScopeDTO;
use Maatify\Category\ContentField\CategoryContentFieldFormatEnum;
use Maatify\Category\ContentField\Mutation\Command\CreateCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\RestoreCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\SoftDeleteCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\UpdateCategoryContentFieldCommand;
use Maatify\Category\ContentField\Mutation\Command\UpdateCategoryContentFieldValueCommand;
use Maatify\Category\ContentField\Ordering\Command\UpdateCategoryContentFieldDisplayOrderCommand;
use Maatify\Category\ContentField\Query\DTO\CategoryContentFieldListCriteriaDTO;

$fieldId = $category->contentFields()->create(
    new CreateCategoryContentFieldCommand(
        $categoryId,
        'badge_config',
        'en-US',
        'web',
        CategoryContentFieldFormatEnum::JSON,
        '{"enabled":true}',
    ),
);

$category->contentFields()->update(
    new UpdateCategoryContentFieldCommand(
        $fieldId,
        CategoryContentFieldFormatEnum::JSON,
        '{"enabled":false}',
    ),
);
$category->contentFields()->updateValue(
    new UpdateCategoryContentFieldValueCommand($fieldId, '{"enabled":true}'),
);
$category->contentFields()->updateDisplayOrder(
    new UpdateCategoryContentFieldDisplayOrderCommand($fieldId, 2),
);
$category->contentFields()->softDelete(
    new SoftDeleteCategoryContentFieldCommand($fieldId),
);
$category->contentFields()->restore(
    new RestoreCategoryContentFieldCommand($fieldId),
);

$exactScope = new CategoryContentFieldScopeDTO('en-US', 'web');
$visibleFields = $category->contentFields()->listVisibleForCategory(
    $categoryId,
    $exactScope,
);
$managedField = $category->contentFields()->getByIdForManagement($fieldId);
$managedFields = $category->contentFields()->listForManagement(
    new CategoryContentFieldListCriteriaDTO(
        categoryId: $categoryId,
        scope: $exactScope,
    ),
);
```

The Content Field management API also provides `paginateForManagement()`. A
visible field read always requires an explicit exact
`CategoryContentFieldScopeDTO`; omitting a scope is not a request for fallback.
Visible fields also require a complete visible Category ancestor path and
exclude deleted fields. The `format` and `value` mutation is atomic; use
`updateValue()` when changing only the value while preserving the stored
format.

## Image Roles

Image Roles are a package-owned registry used to qualify assignments. The Host
defines the role key's meaning and any cardinality or UI policy. The package
does not ship predefined roles and does not decide whether a role means
`hero`, `gallery`, or something else.

Role keys are immutable and remain reserved after soft deletion. Role status
(`ACTIVE` or `INACTIVE`) is separate from soft deletion. The role API is
management-only:

```php
use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;
use Maatify\Category\ImageRole\Lifecycle\Command\CreateCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\RestoreCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\SoftDeleteCategoryImageRoleCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\UpdateCategoryImageRoleStatusCommand;
use Maatify\Category\ImageRole\Lifecycle\Enum\CategoryImageRoleStatusEnum;
use Maatify\Category\ImageRole\Query\DTO\CategoryImageRoleListCriteriaDTO;

$galleryRoleId = $category->imageRoles()->create(
    new CreateCategoryImageRoleCommand('gallery'),
);
$category->imageRoles()->updateStatus(
    new UpdateCategoryImageRoleStatusCommand(
        $galleryRoleId,
        CategoryImageRoleStatusEnum::INACTIVE,
    ),
);
$category->imageRoles()->softDelete(
    new SoftDeleteCategoryImageRoleCommand($galleryRoleId),
);
$category->imageRoles()->restore(
    new RestoreCategoryImageRoleCommand($galleryRoleId),
);
$category->imageRoles()->updateStatus(
    new UpdateCategoryImageRoleStatusCommand(
        $galleryRoleId,
        CategoryImageRoleStatusEnum::ACTIVE,
    ),
);

$role = $category->imageRoles()->getByKeyForManagement('gallery');
$roles = $category->imageRoles()->listForManagement(
    new CategoryImageRoleListCriteriaDTO(
        status: CategoryImageRoleStatusEnum::ACTIVE,
        deletedState: CategoryDeletedStateEnum::NON_DELETED,
    ),
);
```

The role API also provides `getByIdForManagement()`,
`paginateForManagement()`, and status/deleted-state criteria. A role-scoped
Image Assignment can be created only while the referenced Role is active and
non-deleted. Restore preserves the Role's status, so the explicit
`updateStatus(..., CategoryImageRoleStatusEnum::ACTIVE)` above is required
before using this Role for a new assignment. If a Role later becomes inactive
or soft-deleted, existing assignments remain management-visible but disappear
from consumer Image Assignment reads until the Role is active and non-deleted
again.

## Image Assignments and the external Media workflow

The Host-owned workflow is:

```text
Host upload/storage -> external Media Asset ID -> Category Image Assignment
```

The upload and Media lifecycle happen outside this package. Category receives
only the scalar `mediaAssetId`; it stores no URL, binary data, MIME processing,
storage state, Media foreign key, or Media lifecycle state. The Host may use
the same Media Asset ID in more than one Category or exact assignment scope.

An assignment's immutable identity is:

```text
(category_id, media_asset_id, role_id, language_code, platform)
```

`role_id`, `language_code`, and `platform` are all nullable exact dimensions.
`role_id: null` means the generic/unclassified assignment scope. A null
`language_code` or `platform` is also an exact `NULL` value. The package never
falls back between generic, localized, platform, or role scopes.

### Assign, order, default, remove, and restore

```php
use Maatify\Category\ImageAssignment\Assignment\Command\CreateCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\CategoryImageAssignmentScopeDTO;
use Maatify\Category\ImageAssignment\Default\Command\ClearCategoryImageAssignmentDefaultCommand;
use Maatify\Category\ImageAssignment\Default\Command\SetCategoryImageAssignmentDefaultCommand;
use Maatify\Category\ImageAssignment\Lifecycle\Command\RestoreCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Lifecycle\Command\SoftDeleteCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Ordering\Command\UpdateCategoryImageAssignmentDisplayOrderCommand;

// The ID is returned by the Host's Media/Storage workflow, not by Category.
$mediaAssetId = 700;
$genericScope = new CategoryImageAssignmentScopeDTO();
$localizedWebScope = new CategoryImageAssignmentScopeDTO('en-US', 'web');
$galleryScope = new CategoryImageAssignmentScopeDTO('en-US', 'web', $galleryRoleId);

$genericAssignmentId = $category->images()->assign(
    new CreateCategoryImageAssignmentCommand(
        $categoryId,
        $mediaAssetId,
        $genericScope,
    ),
);
$localizedAssignmentId = $category->images()->assign(
    new CreateCategoryImageAssignmentCommand(
        $categoryId,
        $mediaAssetId,
        $localizedWebScope,
    ),
);
$galleryAssignmentId = $category->images()->assign(
    new CreateCategoryImageAssignmentCommand(
        $categoryId,
        701,
        $galleryScope,
    ),
);

$category->images()->reorder(
    new UpdateCategoryImageAssignmentDisplayOrderCommand($genericAssignmentId, 1),
);
$category->images()->setDefault(
    new SetCategoryImageAssignmentDefaultCommand($genericAssignmentId),
);
$category->images()->clearDefault(
    new ClearCategoryImageAssignmentDefaultCommand($genericAssignmentId),
);
$category->images()->setDefault(
    new SetCategoryImageAssignmentDefaultCommand($localizedAssignmentId),
);

$category->images()->remove(
    new SoftDeleteCategoryImageAssignmentCommand($localizedAssignmentId),
);
$category->images()->restore(
    new RestoreCategoryImageAssignmentCommand($localizedAssignmentId),
);
```

`assign()`, `reorder()`, `setDefault()`, `clearDefault()`, `remove()`, and
`restore()` are separate typed operations. Assignment identity is never changed
by ordering, default, or lifecycle mutations. Assigning does not automatically
make a row the default. Defaults are zero-or-one inside one exact
`(category_id, role_id, language_code, platform)` scope, and setting a new
default explicitly clears the current active default in that same scope.
Ordering is independent from default state. Removing a default clears it;
there is no automatic promotion, and restoring the assignment does not make it
default again.

### Exact consumer reads

```php
use Maatify\Category\Query\DTO\CategoryVisibleListCriteriaDTO;

$genericImages = $category->images()->listVisibleForCategory(
    $categoryId,
    new CategoryImageAssignmentScopeDTO(),
    new CategoryVisibleListCriteriaDTO(maxResults: 50),
);
$localizedImages = $category->images()->listVisibleForCategory(
    $categoryId,
    new CategoryImageAssignmentScopeDTO('en-US', 'web'),
);
$roleImages = $category->images()->listVisibleForCategory(
    $categoryId,
    new CategoryImageAssignmentScopeDTO('en-US', 'web', $galleryRoleId),
);
```

Every visible Image Assignment read matches all three nullable dimensions
exactly and excludes deleted assignments, hidden Category ancestor paths, and
assignments whose Role is inactive or deleted. For example, requesting
`new CategoryImageAssignmentScopeDTO('en-US')` matches
`language_code = 'en-US'` and `platform IS NULL`; it does not fall back to
`platform = 'web'`. The visible collection includes `isDefault`, but a default
is meaningful only within the exact scope used for that read.

### Management reads and Role filtering

Management Image Assignment reads are broader and can inspect lifecycle state:

```php
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentListCriteriaDTO;
use Maatify\Category\ImageAssignment\Query\DTO\CategoryImageAssignmentRoleFilterDTO;
use Maatify\Category\Common\Enum\CategoryDeletedStateEnum;

$assignment = $category->images()->getByIdForManagement($genericAssignmentId);
$deletedAssignment = $category->images()->getByIdForManagement(
    $genericAssignmentId,
    CategoryDeletedStateEnum::INCLUDE_DELETED,
);
$roleAssignments = $category->images()->listForManagement(
    new CategoryImageAssignmentListCriteriaDTO(
        categoryId: $categoryId,
        scope: new CategoryImageAssignmentScopeDTO('en-US', 'web'),
        roleFilter: CategoryImageAssignmentRoleFilterDTO::forRole($galleryRoleId),
    ),
);
$genericAssignments = $category->images()->listForManagement(
    new CategoryImageAssignmentListCriteriaDTO(
        categoryId: $categoryId,
        scope: new CategoryImageAssignmentScopeDTO(),
        roleFilter: CategoryImageAssignmentRoleFilterDTO::exactNull(),
    ),
);
$allRolesInExactScope = $category->images()->listForManagement(
    new CategoryImageAssignmentListCriteriaDTO(
        categoryId: $categoryId,
        scope: new CategoryImageAssignmentScopeDTO('en-US', 'web'),
        roleFilter: CategoryImageAssignmentRoleFilterDTO::omitted(),
    ),
);
```

For management criteria, `scope: null` means no language/platform filter. A
non-null `scope` means exact language/platform matching; its default Role
behavior is concrete Role when `roleId` is non-null and exact `NULL` Role when
`roleId` is null. Pass `CategoryImageAssignmentRoleFilterDTO::omitted()` to
keep the language/platform scope exact while omitting the Role predicate. The
three Role filter factories are `omitted()`, `exactNull()`, and `forRole()`.
Management deleted state defaults to `NON_DELETED` and can be changed to
`INCLUDE_DELETED` or `DELETED_ONLY`.

The management API also provides `paginateForManagement()`. It is the right
choice for administrative tables or batch screens; UI/DataTables response
mapping remains a Host responsibility.

## Management pagination

Pagination uses the shared `maatify/persistence` contract:

```php
use Maatify\Persistence\Pdo\Pagination\PageRequest;

$page = $category->categories()->paginateForManagement(
    new CategoryListCriteriaDTO(search: 'news'),
    new PageRequest(
        page: 1,
        perPage: 20,
        sortBy: 'code',
        sortDirection: 'DESC',
    ),
);

foreach ($page->data as $managedCategory) {
    // $managedCategory is a CategoryDTO.
}
```

`PageRequest` accepts `int|string|null` for `page` and `perPage`. The paginator
normalizes invalid page input to `1`, clamps `perPage` to `1..100`, uses the
API default for invalid sort input, and resets a page beyond the available
range to page `1`. An empty result is represented by page `1`,
`totalPages = 0`, and no navigation flags.

Every `PageResult` exposes:

```text
data, page, perPage, total, filtered, totalPages,
hasNext, hasPrevious, sortBy, sortDirection
```

`total` is the count before Category's optional code search but after the
other management criteria. `filtered` is the count after the search and all
criteria. APIs without a separate search stage currently report the same
criteria count in both fields. `toArray()` and JSON serialization use a
`data` array and a nested `pagination` object with snake-case keys.

The public sort keys and defaults are:

| Management API | Default | Exact public keys |
| --- | --- | --- |
| Categories | `display_order` ASC | `display_order`, `code`, `id`, `created_at` |
| Content | `language_code` ASC | `language_code`, `category_id`, `id`, `created_at` |
| Content Fields | `business_order` ASC | `business_order`, `category_id`, `field_key`, `display_order`, `id`, `created_at` |
| Image Roles | `role_key` ASC | `role_key`, `status`, `id`, `created_at` |
| Image Assignments | `business_order` ASC | `business_order`, `category_id`, `display_order`, `media_asset_id`, `id`, `created_at` |

The package owns domain filters, Category code search, SQL, and DTO mapping.
`maatify/persistence` owns pagination normalization, count execution, bounded
limit/offset, sort whitelist enforcement, and pagination metadata. The package
does not provide a second local pagination or search engine.

## DTOs, collections, and errors

All public mutation inputs are typed Commands. Public reads return typed DTOs,
collections, or `PageResult` values. Collections are immutable,
`Countable`, `IteratorAggregate`, and `JsonSerializable`; consume them with
`count()`, `isEmpty()`, `foreach`, or `json_encode()`.

The main DTOs expose:

- `CategoryDTO`: `id`, `parentId`, `code`, `status`, `displayOrder`, timestamps,
  and `deletedAt`.
- `CategoryContentDTO`: `id`, `categoryId`, `languageCode`, `name`,
  `description`, timestamps, and `deletedAt`.
- `CategoryContentFieldDTO`: `id`, `categoryId`, `fieldKey`,
  `languageCode`, `platform`, `format`, `value`, `displayOrder`, timestamps,
  and `deletedAt`.
- `CategoryImageRoleDTO`: `id`, `roleKey`, `status`, timestamps, and
  `deletedAt`.
- `CategoryImageAssignmentDTO`: `id`, `categoryId`, `mediaAssetId`, `roleId`,
  `languageCode`, `platform`, `displayOrder`, timestamps, `deletedAt`, and
  `isDefault`.

Typical package-owned failures include:

- `CategoryInvalidArgumentException` for invalid IDs, empty/oversized values,
  invalid display orders, limits, or JSON syntax.
- `CategoryNotFoundException` for missing Categories and
  `CategoryContentNotFoundException`, `CategoryContentFieldNotFoundException`,
  `CategoryImageRoleNotFoundException`, or
  `CategoryImageAssignmentNotFoundException` for their respective records.
- `CategoryCodeAlreadyExistsException`,
  `CategoryContentAlreadyExistsException`, and
  `CategoryContentFieldAlreadyExistsException` for duplicate immutable
  identities.
- `CategoryCycleException` and
  `CategoryHasNonDeletedChildrenException` for hierarchy violations.
- `CategoryImageRoleUnavailableException` when a new role-scoped assignment
  names an inactive or deleted Role.
- `CategoryPersistenceException` for package-owned storage/hydration failures.

Repository adapters classify selected database failures. In particular,
known duplicate-key failures are translated into the relevant package-owned
`AlreadyExistsException`; other database failures that are not explicitly
classified may propagate as `PDOException`. Package-owned storage and hydration
validation failures use `CategoryPersistenceException`. The Host should map
these outcomes to its own transport or UI policy without changing the package
contracts.

## Transactions and the Host clock

`CategoryFactory` creates one shared `PdoTransactionRunner` using the supplied
PDO and gives the same persistence context to the domain services and ordering
operations. Each typed mutation is transactional at the package service level.

If the Host already owns an outer transaction on that same PDO, the package
runner participates without committing or rolling back the outer transaction.
The Host remains responsible for the outer `beginTransaction()`, commit, and
rollback decisions.

Every mutation receives its timestamp from the supplied
`ClockInterface`. Category does not call a global clock, normalize values to
UTC, or choose a timezone. Reads hydrate stored timestamps using the timezone
of that same Host clock.

## Complete Host workflows

### 1. Basic taxonomy: Category -> Content -> visible read

```php
use Maatify\Category\Content\Mutation\Command\CreateCategoryContentCommand;
use Maatify\Category\Factory\CategoryFactory;
use Maatify\Category\Lifecycle\Command\CreateCategoryCommand;
use Maatify\Category\Query\DTO\CategoryVisibleListCriteriaDTO;
use Maatify\SharedCommon\Infrastructure\SystemClock;
use DateTimeZone;

$category = CategoryFactory::create($pdo, new SystemClock(new DateTimeZone('Africa/Cairo')));

$newsId = $category->categories()->create(new CreateCategoryCommand('news'));
$category->contents()->create(
    new CreateCategoryContentCommand($newsId, null, 'News', null),
);

$news = $category->categories()->getById($newsId);
$visibleContents = $category->contents()->listVisibleForCategory(
    $news->id,
    new CategoryVisibleListCriteriaDTO(maxResults: 20),
);

foreach ($visibleContents as $content) {
    echo $content->name;
}
```

The result is visible only because the Category is active, non-deleted, and has
a complete visible ancestor path. `listVisibleForCategory()` returns all
visible Content rows for the Category, ordered by `language_code, id`; it does
not accept a language filter. If the Host needs a localized value, it selects
the DTO whose `languageCode` matches its chosen locale. The package does not
choose a fallback.

### 2. CMS: localized Content -> fields -> management and consumer reads

```php
use Maatify\Category\Content\Mutation\Command\CreateCategoryContentCommand;
use Maatify\Category\Content\Query\DTO\CategoryContentListCriteriaDTO;
use Maatify\Category\ContentField\CategoryContentFieldFormatEnum;
use Maatify\Category\ContentField\CategoryContentFieldScopeDTO;
use Maatify\Category\ContentField\Mutation\Command\CreateCategoryContentFieldCommand;
use Maatify\Category\ContentField\Query\DTO\CategoryContentFieldListCriteriaDTO;
use Maatify\Category\Query\DTO\CategoryVisibleListCriteriaDTO;

$localizedId = $category->contents()->create(
    new CreateCategoryContentCommand(
        $newsId,
        'en-US',
        'News in English',
        'A localized description',
    ),
);
$category->contentFields()->create(
    new CreateCategoryContentFieldCommand(
        $newsId,
        'badge_config',
        'en-US',
        'web',
        CategoryContentFieldFormatEnum::JSON,
        '{"enabled":true}',
    ),
);

$consumerContent = $category->contents()->listVisibleForCategory(
    $newsId,
    new CategoryVisibleListCriteriaDTO(maxResults: 20),
);
$consumerFields = $category->contentFields()->listVisibleForCategory(
    $newsId,
    new CategoryContentFieldScopeDTO('en-US', 'web'),
);

$managedContent = $category->contents()->listForManagement(
    new CategoryContentListCriteriaDTO(categoryId: $newsId),
);
$managedFields = $category->contentFields()->listForManagement(
    new CategoryContentFieldListCriteriaDTO(
        categoryId: $newsId,
        scope: new CategoryContentFieldScopeDTO('en-US', 'web'),
    ),
);
```

The consumer reads use the exact requested field scope. The management reads
can inspect records independently of consumer visibility and can use the
corresponding deleted-state criteria and pagination methods when an
administrative screen needs them.

### 3. Media: Host Media ID -> Role -> Assignment -> order/default/read/lifecycle

```php
use Maatify\Category\ImageAssignment\Assignment\Command\CreateCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\CategoryImageAssignmentScopeDTO;
use Maatify\Category\ImageAssignment\Default\Command\SetCategoryImageAssignmentDefaultCommand;
use Maatify\Category\ImageAssignment\Lifecycle\Command\RestoreCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Lifecycle\Command\SoftDeleteCategoryImageAssignmentCommand;
use Maatify\Category\ImageAssignment\Ordering\Command\UpdateCategoryImageAssignmentDisplayOrderCommand;
use Maatify\Category\ImageRole\Lifecycle\Command\CreateCategoryImageRoleCommand;

// 1. Outside Category, the Host uploads/stores the file and obtains this ID.
$mediaAssetId = 700;

// 2. Inside Category: create a Host-defined Role and exact assignment.
$heroRoleId = $category->imageRoles()->create(
    new CreateCategoryImageRoleCommand('hero'),
);
$heroScope = new CategoryImageAssignmentScopeDTO('en-US', 'web', $heroRoleId);
$assignmentId = $category->images()->assign(
    new CreateCategoryImageAssignmentCommand($newsId, $mediaAssetId, $heroScope),
);

$category->images()->reorder(
    new UpdateCategoryImageAssignmentDisplayOrderCommand($assignmentId, 1),
);
$category->images()->setDefault(
    new SetCategoryImageAssignmentDefaultCommand($assignmentId),
);

$visibleHeroImages = $category->images()->listVisibleForCategory(
    $newsId,
    new CategoryImageAssignmentScopeDTO('en-US', 'web', $heroRoleId),
);

$category->images()->remove(
    new SoftDeleteCategoryImageAssignmentCommand($assignmentId),
);
// The removed row is absent from visible reads and has no default.
$category->images()->restore(
    new RestoreCategoryImageAssignmentCommand($assignmentId),
);
// Restoring exposes it as non-default; setDefault() is explicit if needed.
```

The Host Media/Storage integration is intentionally Host code and is not a
Category dependency. Category receives and returns only the scalar assignment identity
and stored assignment fields; the Host resolves the Media Asset ID to URLs,
binary content, transformations, and lifecycle state in its own module.

## Public API map

The facade's five APIs are deliberately small and explicit:

| Facade method | Public API | Main consumer operations | Main management operations |
| --- | --- | --- | --- |
| `categories()` | `CategoryApiInterface` | `getById`, `listRootCategories`, `listChildren` | lifecycle, `getByCode`, lists, pagination |
| `contents()` | `ContentApiInterface` | `listVisibleForCategory` | full/partial mutations, lookup, lists, pagination |
| `contentFields()` | `ContentFieldApiInterface` | exact-scope `listVisibleForCategory` | full/partial mutations, lookup, lists, pagination |
| `imageRoles()` | `ImageRoleApiInterface` | none; roles are management-only | create/status/lifecycle, lookup, lists, pagination |
| `images()` | `ImageAssignmentApiInterface` | exact-scope `listVisibleForCategory` | assign/order/default/lifecycle, lookup, lists, pagination |

For the exact Commands, DTO constructors, criteria fields, exception hierarchy,
and complete method signatures, use the [Category Package Reference](../CATEGORY_PACKAGE_REFERENCE.md).
