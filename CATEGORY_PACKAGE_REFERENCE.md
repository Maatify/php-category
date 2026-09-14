# Category Package Reference

`maatify/php-category` is the canonical, framework-neutral package for reusable
hierarchical categories, optional Category Content, extensible Category Content
Fields, a Category-owned Image Role registry, and Category-owned Image
Assignments. This file is the package's
single stable contract reference. Detailed implementation notes belong under
`docs/` and must link back here.

For the practical Host workflow through `CategoryFactory`, the facade, and its
five Domain APIs, see the [Consumer Usage Guide](docs/USAGE_GUIDE.md).

## Scope and boundaries

The package owns Category, Category Content, Category Content Fields, the Image
Role registry, and direct Image Assignment relationship behavior. It does not
own Catalog identity, Product, Pricing, Inventory, Media, HTTP, framework
integration, permissions, presentation, or dependency-injection bindings.

The package is host-agnostic:

- Host-owned identities are accepted as validated scalar IDs and are never
  joined to or constrained by package-owned tables.
- Internal Category and Content relationships use package-owned foreign
  keys only.
- Image Assignments reference host-provided Media Asset identities without a
  Media, Platform, or Language foreign key or lifecycle dependency.
- Image Roles use package-owned identities and lifecycle state, while the Host
  defines role keys and their semantics.
- Content Fields store host-defined `field_key`/value pairs without owning the
  semantic meaning of a key. Their exact nullable language/platform scopes and
  lifecycle are package-owned; the Host owns key semantics, HTML sanitization,
  rendering, and any JSON schema validation.
- Category owns the syntactic and storage validation of non-NULL `language_code`
  values required by its contract, including the constraints enforced by the
  Runtime.
- The Host owns semantic language validation, such as confirming that a language
  is supported or known, together with fallback and locale policy.
- Public read contracts use typed DTOs and collections, while mutation
  contracts use typed Commands; neither uses associative arrays.

## Current standards provenance

The current normative standards adoption is the repository-local selective
pinning recorded in [`docs/php-engineering-standards/STANDARDS_MANIFEST.md`](docs/php-engineering-standards/STANDARDS_MANIFEST.md).
Its exact adoption commit is `639bbdb7c70c1d6db9e8d5cfef93b23fb926afd3`.
The manifest, not a floating upstream branch or a historical roadmap claim,
resolves the active and inherited profiles for this package.

## Domain Content model

Category is the structural entity. Its identity, stable `code`, hierarchy,
status, ordering, timestamps, and soft-deletion lifecycle are independent from
human-readable content; `name` and `description` are not copied into the
Category table.

Category Content is the package's localized name/description persistence
concept. Its
`(category_id, language_code)` identity supports both forms:

- `language_code = NULL` is ordinary, unlocalized Category Content.
- A non-NULL `language_code` is localized Content for that language.

The database enforces at most one unlocalized row per Category and at most one
row for each non-NULL language code. The Package applies only syntactic/storage
validation; the Host owns semantic language availability, fallback, and locale
policy. The Package performs no implicit fallback.

### Category Image Assignment model

Category owns a direct, soft-deletable assignment relation to an external
`mediaAssetId`. The immutable stable identity is
`(category_id, media_asset_id, role_id, language_code, platform)`, including
soft-deleted rows. `role_id`, `language_code`, and `platform` are nullable exact
scope dimensions; every combination is supported, and NULL Role means the
generic/unclassified scope. New role-scoped assignments require an active,
non-deleted Role; existing assignments are hidden from consumer reads while the
Role is inactive or deleted, but remain visible to management reads. Empty
strings are invalid, and no platform enum or fallback is defined. The same
Media Asset may be used in different scopes. Ordering is independent for each
Category and exact scope. Default is an explicit property of Category Image
Assignment within one exact scope; zero or one default is allowed and no
automatic fallback or promotion exists. The default is independent from
`display_order`; soft-deleting a default clears it and restoring the assignment
leaves it non-default. The `isDefault` value is exposed by visible, management,
and mutation-support Image Assignment hydration.

### Category Image Role model

Image Roles are package-owned registry records with an immutable, globally
unique `role_key`. `status` is typed `active`/`inactive` and independent from
soft deletion. Role keys remain permanently reserved after soft deletion;
restore preserves the same Role identity and status. The management API
provides get-by-ID, get-by-key, and bounded list reads with explicit status and
deleted-state criteria. Role semantics, cardinality, and media policy remain
Host-owned.

### Category Content Field model

Category Content Fields are a separate extensible value model. Their immutable
identity is `(category_id, field_key, language_code, platform)`, including
soft-deleted rows. All four exact nullable scope combinations are supported:
NULL/NULL, language/NULL, NULL/platform, and language/platform. Empty strings
are invalid, no fallback is performed, and the Host owns the semantic meaning
of each key.

Each field declares an exact lowercase `text`, `html`, or `json` format and
stores its value as `LONGTEXT`. The database format column uses the
case-sensitive `utf8mb4_bin` collation, and the package validates JSON syntax
when the declared format is `json`; HTML sanitization/rendering and JSON schema
validation remain Host responsibilities. Display order is independent for each
Category and exact scope, with deterministic `display_order, id` reads and
explicit reorder commands.

## Runtime API

The production namespace is `Maatify\Category\`.

The public application entry point is `Maatify\Category\Factory\CategoryFactory`.
The Host supplies its existing `PDO` connection and
`Maatify\SharedCommon\Contracts\ClockInterface`; the Factory returns one
`Maatify\Category\Facade\Contract\CategoryFacadeInterface`. The facade exposes domain APIs through
`categories()`, `contents()`, `contentFields()`, `imageRoles()`, and
`images()` (Image Assignments). Domain APIs preserve the existing consumer,
management, transaction, ordering, and timestamp behavior.

### Status

`Maatify\Category\Lifecycle\Enum\CategoryStatusEnum` is a string-backed enum with:

- `active`
- `inactive`

Status is independent from soft deletion.

`Maatify\Category\Common\Enum\CategoryDeletedStateEnum` explicitly selects
`non_deleted`, `include_deleted`, or `deleted_only` for management reads.

### DTOs

The immutable record DTOs are:

- `CategoryIdDTO`
- `CategoryDTO`
- `CategoryContentDTO`
- `CategoryCollectionDTO`
- `CategoryContentCollectionDTO`
- `CategoryImageRoleDTO`
- `CategoryImageRoleCollectionDTO`
- `CategoryImageRoleListCriteriaDTO`
- `CategoryImageAssignmentScopeDTO`
- `CategoryImageAssignmentRoleFilterDTO`
- `CategoryImageAssignmentDTO`
- `CategoryImageAssignmentCollectionDTO`
- `CategoryContentFieldScopeDTO`
- `CategoryContentFieldDTO`
- `CategoryContentFieldCollectionDTO`
- `CategoryListCriteriaDTO`
- `CategoryContentListCriteriaDTO`
- `CategoryImageAssignmentListCriteriaDTO`
- `CategoryContentFieldListCriteriaDTO`
- `CategoryVisibleListCriteriaDTO`

### Commands

- `CreateCategoryCommand`
- `MoveCategoryCommand`
- `SoftDeleteCategoryCommand`
- `RestoreCategoryCommand`
- `UpdateCategoryStatusCommand`
- `UpdateCategoryDisplayOrderCommand`
- `CreateCategoryContentCommand`
- `UpdateCategoryContentCommand`
- `UpdateCategoryContentNameCommand`
- `UpdateCategoryContentDescriptionCommand`
- `SoftDeleteCategoryContentCommand`
- `RestoreCategoryContentCommand`
- `CreateCategoryImageRoleCommand`
- `UpdateCategoryImageRoleStatusCommand`
- `SoftDeleteCategoryImageRoleCommand`
- `RestoreCategoryImageRoleCommand`
- `CreateCategoryImageAssignmentCommand`
- `UpdateCategoryImageAssignmentDisplayOrderCommand`
- `SetCategoryImageAssignmentDefaultCommand`
- `ClearCategoryImageAssignmentDefaultCommand`
- `SoftDeleteCategoryImageAssignmentCommand`
- `RestoreCategoryImageAssignmentCommand`
- `CreateCategoryContentFieldCommand`
- `UpdateCategoryContentFieldCommand`
- `UpdateCategoryContentFieldValueCommand`
- `UpdateCategoryContentFieldDisplayOrderCommand`
- `SoftDeleteCategoryContentFieldCommand`
- `RestoreCategoryContentFieldCommand`

Every public DTO and collection DTO is immutable and implements
`JsonSerializable`. Collections also retain typed `IteratorAggregate` behavior.
Date-time fields serialize as RFC 3339 strings; enum fields serialize using
their backing values.

Commands and DTOs validate their input/domain invariants. `CategoryDTO`,
`CategoryContentDTO`, `CategoryImageAssignmentDTO`, and
`CategoryContentFieldDTO` require canonical positive identities. A Category
cannot use itself as its parent. `UpdateCategoryContentCommand` is the full-form
Content update. The typed `UpdateCategoryContentNameCommand` and
`UpdateCategoryContentDescriptionCommand` support independent inline edits while
preserving the other Content field and the logical identity
`(category_id, language_code)`. Category mutation Commands do not expose `code`,
so the stable Category code remains immutable after creation.
`UpdateCategoryContentFieldCommand` is the atomic full-form `format`/`value`
update; `UpdateCategoryContentFieldValueCommand` supports an inline value edit
by preserving the current format and delegating both fields through that same
atomic update. No generic or string-based field update exists.

### Services and contracts

- `CategoryFacadeInterface` is the single package entry point. Its accessors
  return the five domain APIs: `categories()`, `contents()`,
  `contentFields()`, `imageRoles()`, and `images()`. The `images()` accessor is
  intentionally the Image Assignment API; it does not own Media.
- `Maatify\Category\Factory\CategoryFactory::create(PDO $pdo, ClockInterface $clock):
  CategoryFacadeInterface` is the framework-neutral host-wiring entry point.
  It builds all PDO adapters, one shared transaction runner, and one shared
  ordering manager around the supplied primitives.
- `CategoryServiceInterface`/`CategoryService` own Category mutation
  orchestration and Category consumer/management reads only.
- `ContentServiceInterface`/`ContentService` own Category Content mutation
  orchestration and Content consumer/management reads only.
- `ContentFieldServiceInterface`/`ContentFieldService` own Category Content
  Field mutation orchestration and Field consumer/management reads only.
- `ImageRoleServiceInterface`/`ImageRoleService` own Image Role lifecycle and
  management reads only.
- `ImageAssignmentServiceInterface`/`ImageAssignmentService` own Image
  Assignment lifecycle, exact-scope ordering/default behavior, and
  consumer/management reads only.
- Each domain API is a thin public delegation boundary over its matching
  domain service. Business orchestration is not duplicated in the facade,
  Factory, or API wrappers.
- `CategoryCommandRepositoryInterface` is the Category write port.
- `CategoryContentCommandRepositoryInterface` is the Content
  lifecycle write port.
- `CategoryImageAssignmentCommandRepositoryInterface` is the Image Assignment
  lifecycle and ordering write port.
- `CategoryContentFieldCommandRepositoryInterface` is the Content Field
  lifecycle and exact-scope ordering write port.
- `CategoryImageRoleCommandRepositoryInterface` is the Image Role lifecycle
  write port.
- `CategoryQueryReaderInterface` is the Category mutation-support read port.
  Its `findById()` includes soft-deleted rows; `findActiveById()` excludes
  them; explicit `ForUpdate` methods lock Category rows inside the application
  transaction. Content, Content Field, Image Role, and Image Assignment each
  expose their own mutation-support read port in their domain boundary.
- `CategoryReadQueryInterface` is the dedicated visible Category read port.
  Content, Content Field, and Image Assignment each expose a separate visible
  read port in their own domain boundary.
- Consumer visibility list methods accept only the typed
  `CategoryVisibleListCriteriaDTO`, which bounds each call to 1–100 rows. It
  exposes no status or deleted-state override, preserving consumer visibility
  semantics and the complete ancestor rule.
- `CategoryManagementReadQueryInterface` is the dedicated management Category
  read port. Content, Content Field, Image Role, and Image Assignment each
  expose a separate management read port in their own domain boundary.
- `Maatify\Persistence\Pdo\Transaction\TransactionRunnerInterface` defines
  the shared transaction boundary used by the domain services. The Factory
  wires `PdoTransactionRunner` with the same PDO instance used by Category
  repositories and shared Ordering operations.

### Complete public runtime inventory

The following inventory is generated from the current `src/` tree and is the
current v1 public API surface. Concrete PDO adapters are public host-wiring classes;
their public methods implement the corresponding contracts below.

#### Commands and constructors

```text
CreateCategoryCommand(string $code, string|int|null $parentId = null, CategoryStatusEnum $status = ACTIVE)
MoveCategoryCommand(string|int $categoryId, string|int|null $parentId)
UpdateCategoryStatusCommand(string|int $categoryId, CategoryStatusEnum $status)
UpdateCategoryDisplayOrderCommand(string|int $categoryId, int $displayOrder)
SoftDeleteCategoryCommand(string|int $categoryId)
RestoreCategoryCommand(string|int $categoryId)

CreateCategoryContentCommand(string|int $categoryId, ?string $languageCode, string $name, ?string $description)
UpdateCategoryContentCommand(string|int $contentId, string $name, ?string $description)
UpdateCategoryContentNameCommand(string|int $contentId, string $name)
UpdateCategoryContentDescriptionCommand(string|int $contentId, ?string $description)
SoftDeleteCategoryContentCommand(string|int $contentId)
RestoreCategoryContentCommand(string|int $contentId)

CreateCategoryImageRoleCommand(string $roleKey, CategoryImageRoleStatusEnum $status = ACTIVE)
UpdateCategoryImageRoleStatusCommand(string|int $roleId, CategoryImageRoleStatusEnum $status)
SoftDeleteCategoryImageRoleCommand(string|int $roleId)
RestoreCategoryImageRoleCommand(string|int $roleId)

CreateCategoryImageAssignmentCommand(string|int $categoryId,
                                     string|int $mediaAssetId,
                                     ?CategoryImageAssignmentScopeDTO $scope = null)
UpdateCategoryImageAssignmentDisplayOrderCommand(string|int $assignmentId,
                                                 int $displayOrder)
SetCategoryImageAssignmentDefaultCommand(string|int $assignmentId)
ClearCategoryImageAssignmentDefaultCommand(string|int $assignmentId)
SoftDeleteCategoryImageAssignmentCommand(string|int $assignmentId)
RestoreCategoryImageAssignmentCommand(string|int $assignmentId)

CreateCategoryContentFieldCommand(string|int $categoryId, string $fieldKey,
                                  ?string $languageCode,
                                  ?string $platform,
                                  CategoryContentFieldFormatEnum $format,
                                  string $value)
UpdateCategoryContentFieldCommand(string|int $fieldId,
                                  CategoryContentFieldFormatEnum $format,
                                  string $value)
UpdateCategoryContentFieldValueCommand(string|int $fieldId, string $value)
UpdateCategoryContentFieldDisplayOrderCommand(string|int $fieldId,
                                              int $displayOrder)
SoftDeleteCategoryContentFieldCommand(string|int $fieldId)
RestoreCategoryContentFieldCommand(string|int $fieldId)
```

Commands are `final readonly` and implement `JsonSerializable`. Category code,
content `categoryId`, and content `languageCode` are not mutable through
an update command.

#### DTOs and criteria constructors

```text
CategoryIdDTO(string|int $value, string $field = 'id')
CategoryDTO(int $id, ?int $parentId, string $code, CategoryStatusEnum $status,
            int $displayOrder, DateTimeImmutable $createdAt,
            DateTimeImmutable $updatedAt, ?DateTimeImmutable $deletedAt)
CategoryContentDTO(int $id, int $categoryId, ?string $languageCode,
                   string $name, ?string $description,
                   DateTimeImmutable $createdAt,
                   DateTimeImmutable $updatedAt,
                   ?DateTimeImmutable $deletedAt)
CategoryImageRoleDTO(int $id, string $roleKey, CategoryImageRoleStatusEnum $status,
                     DateTimeImmutable $createdAt, DateTimeImmutable $updatedAt,
                     ?DateTimeImmutable $deletedAt)
CategoryImageRoleCollectionDTO(array $items)
CategoryImageRoleListCriteriaDTO(?CategoryImageRoleStatusEnum $status = null,
                                 CategoryDeletedStateEnum $deletedState = NON_DELETED,
                                 int $maxResults = 100)
CategoryImageAssignmentScopeDTO(?string $languageCode = null,
                                ?string $platform = null,
                                string|int|null $roleId = null)
CategoryImageAssignmentRoleFilterDTO::omitted()
CategoryImageAssignmentRoleFilterDTO::exactNull()
CategoryImageAssignmentRoleFilterDTO::forRole(string|int $roleId)
CategoryImageAssignmentDTO(int $id, int $categoryId, int $mediaAssetId,
                           ?string $languageCode, ?string $platform,
                           int $displayOrder, DateTimeImmutable $createdAt,
                           DateTimeImmutable $updatedAt,
                           ?DateTimeImmutable $deletedAt, ?int $roleId = null,
                           bool $isDefault = false)
CategoryContentFieldScopeDTO(?string $languageCode = null,
                             ?string $platform = null)
CategoryContentFieldDTO(int $id, int $categoryId, string $fieldKey,
                        ?string $languageCode, ?string $platform,
                        CategoryContentFieldFormatEnum $format, string $value,
                        int $displayOrder, DateTimeImmutable $createdAt,
                        DateTimeImmutable $updatedAt,
                        ?DateTimeImmutable $deletedAt)
CategoryCollectionDTO(array $items)
CategoryContentCollectionDTO(array $items)
CategoryImageAssignmentCollectionDTO(array $items)
CategoryContentFieldCollectionDTO(array $items)
CategoryListCriteriaDTO(?CategoryStatusEnum $status = null,
                        CategoryDeletedStateEnum $deletedState = NON_DELETED,
                        int $maxResults = 100,
                        ?string $search = null)
CategoryContentListCriteriaDTO(?int $categoryId = null,
                                   CategoryDeletedStateEnum $deletedState = NON_DELETED,
                                   int $maxResults = 100)
CategoryVisibleListCriteriaDTO(int $maxResults = 100)
CategoryImageAssignmentListCriteriaDTO(?int $categoryId = null,
                                       ?CategoryImageAssignmentScopeDTO $scope = null,
                                       CategoryDeletedStateEnum $deletedState = NON_DELETED,
                                       int $maxResults = 100,
                                       ?CategoryImageAssignmentRoleFilterDTO $roleFilter = null)
CategoryContentFieldListCriteriaDTO(?int $categoryId = null,
                                    ?string $fieldKey = null,
                                    ?CategoryContentFieldScopeDTO $scope = null,
                                    CategoryDeletedStateEnum $deletedState = NON_DELETED,
                                    int $maxResults = 100)
```

All DTOs and collections are `final readonly` and `JsonSerializable`;
collections also implement typed `IteratorAggregate` and `Countable`. The six
criteria DTOs reject limits outside `1..100`.

#### Enums

```text
CategoryStatusEnum: ACTIVE = 'active', INACTIVE = 'inactive'
CategoryDeletedStateEnum: NON_DELETED = 'non_deleted',
                          INCLUDE_DELETED = 'include_deleted',
                          DELETED_ONLY = 'deleted_only'
CategoryContentFieldFormatEnum: TEXT = 'text', HTML = 'html', JSON = 'json'
CategoryImageRoleStatusEnum: ACTIVE = 'active', INACTIVE = 'inactive'
CategoryImageAssignmentRoleFilterModeEnum: OMITTED = 'omitted',
                                           EXACT_NULL = 'exact_null',
                                           CONCRETE = 'concrete'
```

#### Public contracts and method signatures

```text
CategoryFacadeInterface [Maatify\Category\Facade\Contract; src/Facade/Contract]
  categories(): CategoryApiInterface
  contents(): ContentApiInterface
  contentFields(): ContentFieldApiInterface
  imageRoles(): ImageRoleApiInterface
  images(): ImageAssignmentApiInterface

CategoryApiInterface [Maatify\Category\Api; src/Category/Api]
  create(CreateCategoryCommand): int
  move(MoveCategoryCommand): void
  softDelete(SoftDeleteCategoryCommand): void
  restore(RestoreCategoryCommand): void
  updateStatus(UpdateCategoryStatusCommand): void
  updateDisplayOrder(UpdateCategoryDisplayOrderCommand): void
  getById(int): CategoryDTO
  listRootCategories(CategoryVisibleListCriteriaDTO = new CategoryVisibleListCriteriaDTO()): CategoryCollectionDTO
  listChildren(int, CategoryVisibleListCriteriaDTO = new CategoryVisibleListCriteriaDTO()): CategoryCollectionDTO
  getByIdForManagement(int, CategoryDeletedStateEnum = NON_DELETED): CategoryDTO
  getByCode(string, CategoryDeletedStateEnum = NON_DELETED): CategoryDTO
  listForManagement(CategoryListCriteriaDTO): CategoryCollectionDTO
  listRootCategoriesForManagement(CategoryListCriteriaDTO): CategoryCollectionDTO
  listChildrenForManagement(int, CategoryListCriteriaDTO): CategoryCollectionDTO
  paginateForManagement(CategoryListCriteriaDTO, PageRequest): PageResult<CategoryDTO>
  paginateRootCategoriesForManagement(CategoryListCriteriaDTO, PageRequest): PageResult<CategoryDTO>
  paginateChildrenForManagement(int, CategoryListCriteriaDTO, PageRequest): PageResult<CategoryDTO>

ContentApiInterface
  create(CreateCategoryContentCommand): int
  update(UpdateCategoryContentCommand): void
  updateName(UpdateCategoryContentNameCommand): void
  updateDescription(UpdateCategoryContentDescriptionCommand): void
  softDelete(SoftDeleteCategoryContentCommand): void
  restore(RestoreCategoryContentCommand): void
  listVisibleForCategory(int, CategoryVisibleListCriteriaDTO = new CategoryVisibleListCriteriaDTO()): CategoryContentCollectionDTO
  getByIdForManagement(int, CategoryDeletedStateEnum = NON_DELETED): CategoryContentDTO
  listForManagement(CategoryContentListCriteriaDTO): CategoryContentCollectionDTO
  paginateForManagement(CategoryContentListCriteriaDTO, PageRequest): PageResult<CategoryContentDTO>

ContentFieldApiInterface
  create(CreateCategoryContentFieldCommand): int
  update(UpdateCategoryContentFieldCommand): void
  updateValue(UpdateCategoryContentFieldValueCommand): void
  updateDisplayOrder(UpdateCategoryContentFieldDisplayOrderCommand): void
  softDelete(SoftDeleteCategoryContentFieldCommand): void
  restore(RestoreCategoryContentFieldCommand): void
  listVisibleForCategory(int, CategoryContentFieldScopeDTO, CategoryVisibleListCriteriaDTO = new CategoryVisibleListCriteriaDTO()): CategoryContentFieldCollectionDTO
  getByIdForManagement(int, CategoryDeletedStateEnum = NON_DELETED): CategoryContentFieldDTO
  listForManagement(CategoryContentFieldListCriteriaDTO): CategoryContentFieldCollectionDTO
  paginateForManagement(CategoryContentFieldListCriteriaDTO, PageRequest): PageResult<CategoryContentFieldDTO>

ImageRoleApiInterface
  create(CreateCategoryImageRoleCommand): int
  updateStatus(UpdateCategoryImageRoleStatusCommand): void
  softDelete(SoftDeleteCategoryImageRoleCommand): void
  restore(RestoreCategoryImageRoleCommand): void
  getByIdForManagement(int, CategoryDeletedStateEnum = NON_DELETED): CategoryImageRoleDTO
  getByKeyForManagement(string, CategoryDeletedStateEnum = NON_DELETED): CategoryImageRoleDTO
  listForManagement(CategoryImageRoleListCriteriaDTO): CategoryImageRoleCollectionDTO
  paginateForManagement(CategoryImageRoleListCriteriaDTO, PageRequest): PageResult<CategoryImageRoleDTO>

ImageAssignmentApiInterface [images()]
  assign(CreateCategoryImageAssignmentCommand): int
  reorder(UpdateCategoryImageAssignmentDisplayOrderCommand): void
  setDefault(SetCategoryImageAssignmentDefaultCommand): void
  clearDefault(ClearCategoryImageAssignmentDefaultCommand): void
  remove(SoftDeleteCategoryImageAssignmentCommand): void
  restore(RestoreCategoryImageAssignmentCommand): void
  listVisibleForCategory(int, CategoryImageAssignmentScopeDTO, CategoryVisibleListCriteriaDTO = new CategoryVisibleListCriteriaDTO()): CategoryImageAssignmentCollectionDTO
  getByIdForManagement(int, CategoryDeletedStateEnum = NON_DELETED): CategoryImageAssignmentDTO
  listForManagement(CategoryImageAssignmentListCriteriaDTO): CategoryImageAssignmentCollectionDTO
  paginateForManagement(CategoryImageAssignmentListCriteriaDTO, PageRequest): PageResult<CategoryImageAssignmentDTO>

CategoryCommandRepositoryInterface
  create(CreateCategoryCommand, DateTimeImmutable): int
  move(MoveCategoryCommand, DateTimeImmutable): bool
  softDelete(SoftDeleteCategoryCommand, DateTimeImmutable): bool
  restore(RestoreCategoryCommand, DateTimeImmutable): bool
  updateStatus(UpdateCategoryStatusCommand, DateTimeImmutable): bool
  updateDisplayOrder(UpdateCategoryDisplayOrderCommand, DateTimeImmutable): bool

CategoryContentCommandRepositoryInterface
  create(CreateCategoryContentCommand, DateTimeImmutable): int
  update(UpdateCategoryContentCommand, DateTimeImmutable): bool
  softDelete(SoftDeleteCategoryContentCommand, DateTimeImmutable): bool
  restore(RestoreCategoryContentCommand, DateTimeImmutable): bool

CategoryImageRoleCommandRepositoryInterface
  create(CreateCategoryImageRoleCommand, DateTimeImmutable): int
  updateStatus(UpdateCategoryImageRoleStatusCommand, DateTimeImmutable): bool
  softDelete(SoftDeleteCategoryImageRoleCommand, DateTimeImmutable): bool
  restore(RestoreCategoryImageRoleCommand, DateTimeImmutable): bool

CategoryImageAssignmentCommandRepositoryInterface
  create(CreateCategoryImageAssignmentCommand, DateTimeImmutable): int
  updateDisplayOrder(UpdateCategoryImageAssignmentDisplayOrderCommand, DateTimeImmutable): bool
  setDefault(SetCategoryImageAssignmentDefaultCommand, DateTimeImmutable): bool
  clearDefault(ClearCategoryImageAssignmentDefaultCommand, DateTimeImmutable): bool
  softDelete(SoftDeleteCategoryImageAssignmentCommand, DateTimeImmutable): bool
  restore(RestoreCategoryImageAssignmentCommand, DateTimeImmutable): bool

CategoryContentFieldCommandRepositoryInterface
  create(CreateCategoryContentFieldCommand, DateTimeImmutable): int
  update(UpdateCategoryContentFieldCommand, DateTimeImmutable): bool
  updateDisplayOrder(UpdateCategoryContentFieldDisplayOrderCommand, DateTimeImmutable): bool
  softDelete(SoftDeleteCategoryContentFieldCommand, DateTimeImmutable): bool
  restore(RestoreCategoryContentFieldCommand, DateTimeImmutable): bool

CategoryReadQueryInterface [Category domain]
  findVisibleById(int): ?CategoryDTO
  listVisibleRootCategories(CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO()): CategoryCollectionDTO
  listVisibleChildren(int $parentId, CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO()): CategoryCollectionDTO

CategoryContentReadQueryInterface
  listVisibleContents(int $categoryId, CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO()): CategoryContentCollectionDTO

CategoryContentFieldReadQueryInterface
  listVisibleContentFields(int $categoryId, CategoryContentFieldScopeDTO $scope, CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO()): CategoryContentFieldCollectionDTO

CategoryImageAssignmentReadQueryInterface
  listVisibleImageAssignments(int $categoryId, CategoryImageAssignmentScopeDTO $scope, CategoryVisibleListCriteriaDTO $criteria = new CategoryVisibleListCriteriaDTO()): CategoryImageAssignmentCollectionDTO

CategoryManagementReadQueryInterface [Category domain]
  findById(int, CategoryDeletedStateEnum): ?CategoryDTO
  findByCode(string, CategoryDeletedStateEnum): ?CategoryDTO
  listCategories(CategoryListCriteriaDTO): CategoryCollectionDTO
  listRootCategories(CategoryListCriteriaDTO): CategoryCollectionDTO
  listChildren(int, CategoryListCriteriaDTO): CategoryCollectionDTO
  paginateCategories(CategoryListCriteriaDTO, PageRequest): PageResult<CategoryDTO>
  paginateRootCategories(CategoryListCriteriaDTO, PageRequest): PageResult<CategoryDTO>
  paginateChildren(int, CategoryListCriteriaDTO, PageRequest): PageResult<CategoryDTO>

CategoryContentManagementReadQueryInterface
  findContentById(int, CategoryDeletedStateEnum): ?CategoryContentDTO
  listContents(CategoryContentListCriteriaDTO): CategoryContentCollectionDTO
  paginateContents(CategoryContentListCriteriaDTO, PageRequest): PageResult<CategoryContentDTO>

CategoryImageRoleManagementReadQueryInterface
  findImageRoleById(int, CategoryDeletedStateEnum): ?CategoryImageRoleDTO
  findImageRoleByKey(string, CategoryDeletedStateEnum): ?CategoryImageRoleDTO
  listImageRoles(CategoryImageRoleListCriteriaDTO): CategoryImageRoleCollectionDTO
  paginateImageRoles(CategoryImageRoleListCriteriaDTO, PageRequest): PageResult<CategoryImageRoleDTO>

CategoryImageAssignmentManagementReadQueryInterface
  findImageAssignmentById(int, CategoryDeletedStateEnum): ?CategoryImageAssignmentDTO
  listImageAssignments(CategoryImageAssignmentListCriteriaDTO): CategoryImageAssignmentCollectionDTO
  paginateImageAssignments(CategoryImageAssignmentListCriteriaDTO, PageRequest): PageResult<CategoryImageAssignmentDTO>

CategoryContentFieldManagementReadQueryInterface
  findContentFieldById(int, CategoryDeletedStateEnum): ?CategoryContentFieldDTO
  listContentFields(CategoryContentFieldListCriteriaDTO): CategoryContentFieldCollectionDTO
  paginateContentFields(CategoryContentFieldListCriteriaDTO, PageRequest): PageResult<CategoryContentFieldDTO>

CategoryQueryReaderInterface [internal mutation-support port]
  findById(int): ?CategoryDTO
  findByCode(string): ?CategoryDTO
  findActiveById(int): ?CategoryDTO
  findActiveByIdForUpdate(int): ?CategoryDTO
  findByIdForUpdate(int): ?CategoryDTO
  hasNonDeletedChildrenForUpdate(int): bool

CategoryContentQueryReaderInterface
  findContentById(int): ?CategoryContentDTO
  findContentByIdForUpdate(int): ?CategoryContentDTO

CategoryImageRoleQueryReaderInterface
  findImageRoleByIdForUpdate(int): ?CategoryImageRoleDTO

CategoryImageAssignmentQueryReaderInterface
  findImageAssignmentById(int): ?CategoryImageAssignmentDTO
  findImageAssignmentByIdForUpdate(int): ?CategoryImageAssignmentDTO

CategoryContentFieldQueryReaderInterface
  findContentFieldById(int): ?CategoryContentFieldDTO
  findContentFieldByIdForUpdate(int): ?CategoryContentFieldDTO

TransactionRunnerInterface (from maatify/persistence)
  run(callable $callback): mixed
```

The internal mutation-support `findByCode()` remains separate from the public
management read port. The management surface now exposes exact `getByCode()`;
its deleted-state argument preserves the same explicit non-deleted,
include-deleted, and deleted-only semantics as management get-by-ID.

#### Services and PDO adapters

```text
CategoryFactory::create(PDO $pdo, ClockInterface $clock): CategoryFacadeInterface
CategoryService(CategoryCommandRepositoryInterface,
                CategoryQueryReaderInterface,
                CategoryReadQueryInterface,
                CategoryManagementReadQueryInterface,
                TransactionRunnerInterface,
                ClockInterface)
ContentService(CategoryContentCommandRepositoryInterface,
               CategoryQueryReaderInterface,
               CategoryContentQueryReaderInterface,
               CategoryContentReadQueryInterface,
               CategoryContentManagementReadQueryInterface,
               TransactionRunnerInterface,
               ClockInterface)
ContentFieldService(CategoryContentFieldCommandRepositoryInterface,
                    CategoryQueryReaderInterface,
                    CategoryContentFieldQueryReaderInterface,
                    CategoryContentFieldReadQueryInterface,
                    CategoryContentFieldManagementReadQueryInterface,
                    TransactionRunnerInterface,
                    ClockInterface)
ImageRoleService(CategoryImageRoleCommandRepositoryInterface,
                 CategoryImageRoleQueryReaderInterface,
                 CategoryImageRoleManagementReadQueryInterface,
                 TransactionRunnerInterface,
                 ClockInterface)
ImageAssignmentService(CategoryImageAssignmentCommandRepositoryInterface,
                       CategoryQueryReaderInterface,
                       CategoryImageRoleQueryReaderInterface,
                       CategoryImageAssignmentQueryReaderInterface,
                       CategoryImageAssignmentReadQueryInterface,
                       CategoryImageAssignmentManagementReadQueryInterface,
                       TransactionRunnerInterface,
                       ClockInterface)

PdoCategoryCommandRepository(PDO, ScopedOrderingManager)
PdoCategoryContentCommandRepository(PDO)
PdoCategoryImageRoleCommandRepository(PDO)
PdoCategoryImageAssignmentCommandRepository(PDO, ScopedOrderingManager)
PdoCategoryContentFieldCommandRepository(PDO, ScopedOrderingManager)
PdoCategoryQueryReader(PDO, ClockInterface)
PdoCategoryReadQuery(PDO, ClockInterface)
PdoCategoryManagementReadQuery(PDO, ClockInterface)
PdoCategoryContentQueryReader(PDO, ClockInterface)
PdoCategoryContentReadQuery(PDO, ClockInterface)
PdoCategoryContentManagementReadQuery(PDO, ClockInterface)
PdoCategoryContentFieldQueryReader(PDO, ClockInterface)
PdoCategoryContentFieldReadQuery(PDO, ClockInterface)
PdoCategoryContentFieldManagementReadQuery(PDO, ClockInterface)
PdoCategoryImageRoleQueryReader(PDO, ClockInterface)
PdoCategoryImageRoleManagementReadQuery(PDO, ClockInterface)
PdoCategoryImageAssignmentQueryReader(PDO, ClockInterface)
PdoCategoryImageAssignmentReadQuery(PDO, ClockInterface)
PdoCategoryImageAssignmentManagementReadQuery(PDO, ClockInterface)
PdoTransactionRunner(PDO) [Host wiring from maatify/persistence]
```

The concrete adapters implement the public contracts listed above and contain
no Host framework/container bindings.

Management Category lists accept `CategoryListCriteriaDTO`, apply an optional
status filter, explicit `CategoryDeletedStateEnum`, and optional Category-owned
SQL search against `code`. Their unpaginated form remains bounded to at most
100 rows per call. Management Content lists accept
`CategoryContentListCriteriaDTO`, optionally filter by Category, apply an
explicit deleted state, and use the same bound. Category lists are ordered by
`display_order, id`; Content lists are ordered by `language_code, id`.
Image Role lists are ordered by `role_key, id`.
Every management list API also has a paginated counterpart returning the
canonical `PageResult<T>` from `maatify/persistence`. The package does not
implement a local pagination engine: the shared `PdoPaginator` owns page
normalization, counts, limits, offsets, sorting, and pagination metadata, while
the package owns SQL, filters, Category search, and row mapping. Language fallback remains Host-owned. Content collections may contain
the single NULL-language row together with zero or more
language-specific rows; Category queries never join an unrestricted Content
collection in a way that multiplies Category rows.
Management Image Assignment lists accept `CategoryImageAssignmentListCriteriaDTO`,
apply exact nullable language/platform scope predicates only when a scope object
is supplied, and are ordered by Category, exact scope, `display_order, id`.
For paginated Content Field and Image Assignment management lists, the default
`sortBy` is the explicit `business_order` key, which represents that complete
business ordering; `sortBy=category_id` remains a direct Category ID sort
with the shared `id` tie-breaker.
The management criteria also support three independent Role-filter states
through `CategoryImageAssignmentRoleFilterDTO`: omitted (all Roles), exact
NULL Role, or one concrete Role. When the explicit Role filter is absent, the
`scope->roleId` value remains an exact Role predicate. Visible Image
Assignment lists require an exact `CategoryImageAssignmentScopeDTO`, exclude
deleted rows, and apply complete ancestor visibility plus active/non-deleted
Role visibility with no fallback. Role-scoped assignment creation requires the
Role to exist, be active, and be non-deleted.
Management Image Role reads accept `CategoryImageRoleListCriteriaDTO` and
provide get-by-ID/get-by-key and bounded status/deleted-state filtering.
Management Content Field lists accept `CategoryContentFieldListCriteriaDTO`,
apply an optional exact nullable scope predicate, and are ordered by Category,
exact scope, and `display_order, id`. Visible Content Field lists
require an exact `CategoryContentFieldScopeDTO`, exclude deleted rows, and
apply complete ancestor visibility with no fallback. In management criteria,
`scope = null` means no scope filter; `new CategoryContentFieldScopeDTO()`
means exact NULL/NULL scope.
Consumer Category lists use the same maximum of 100 through their separate
criteria DTO; root and child lists use `display_order, id`, and Content
lists use `language_code, id`. The bound is applied by the persistence query
with a typed integer parameter; paginated management queries use the shared
Persistence pagination contract and do not change the consumer visibility or
deleted/status semantics.

### Content parent-state contract

The current Runtime does not couple Content lifecycle mutations to the
parent Category's status after the required parent-existence check. The
mutation-support names `findActiveById()` and `findActiveByIdForUpdate()` mean
non-deleted Category lifecycle state; they do not mean
`CategoryStatusEnum::ACTIVE`.

- `contents()->create()` requires the Category to exist and have
  `deleted_at IS NULL`. A Category with status `INACTIVE` is valid; a
  soft-deleted Category is rejected.
- `contents()->update()` depends on the Content's own non-deleted lifecycle
  and is allowed when the parent Category is inactive or soft-deleted.
- `contents()->softDelete()` depends on the Content's own non-deleted
  lifecycle and is allowed when the parent Category is inactive or
  soft-deleted.
- `contents()->restore()` depends on the Content row existing in its
  soft-deleted lifecycle and is allowed when the parent Category is inactive
  or soft-deleted.

These semantics are proven against the real MySQL schema by
`CategoryPdoIntegrationTest::testContentMutationsFollowParentLifecycleStateContractOnMySql`.
They are the v1 contract; no parent-state redesign is implied.

### Image Assignment parent-state contract

`images()->assign()` requires a Category that exists and is not
soft-deleted; `CategoryStatusEnum::INACTIVE` is allowed. After an Image
Assignment is created, ordering, soft-delete, and restore operations depend on
the assignment's own lifecycle. An Image Assignment is not a Category child
and therefore does not prevent Category soft-delete.

### Image Assignment consumer workflow

The Host owns upload and Media/Storage lifecycle. After the Host completes its
upload workflow and receives a `mediaAssetId`, it passes only that ID to
Category:

```php
$scope = new CategoryImageAssignmentScopeDTO('en-US', 'web');
$images = $category->images();

$assignmentId = $images->assign(
    new CreateCategoryImageAssignmentCommand($categoryId, $mediaAssetId, $scope),
);

$visibleAssignments = $images->listVisibleForCategory($categoryId, $scope);
$images->reorder(new UpdateCategoryImageAssignmentDisplayOrderCommand($assignmentId, 1));
$images->setDefault(new SetCategoryImageAssignmentDefaultCommand($assignmentId));
$images->clearDefault(new ClearCategoryImageAssignmentDefaultCommand($assignmentId));
$images->remove(new SoftDeleteCategoryImageAssignmentCommand($assignmentId));
$images->restore(new RestoreCategoryImageAssignmentCommand($assignmentId));
```

Use the same `CategoryImageAssignmentScopeDTO` for assignment and exact
consumer reads. `NULL` language, platform, or Role values are exact scope
dimensions and never fall back. Add a Role ID only when the Host needs a
Role-scoped assignment and has an active, non-deleted Category Image Role.
`remove()` is a reversible soft delete; it clears `isDefault`, does not
promote another assignment, and `restore()` leaves the assignment
non-default. Ordering and default selection are independent operations.

Content Field creation requires a non-deleted parent Category. Field value,
ordering, soft-delete, and restore mutations depend on the field's own
lifecycle and are not blocked by an inactive or soft-deleted parent Category.
Visible field reads additionally require every Category in the complete
ancestor path to be active and non-deleted.

## Business invariants

- Category `code` is immutable and unique among all stored identities.
- Category Content logical identity `(category_id, language_code)` is
  immutable and unique, including the database-enforced single NULL-language
  identity per Category. Full-form updates change `name` and `description`
  together; typed `updateName()` and `updateDescription()` operations preserve
  the other field by locking the current row and delegating the same full-form
  write.
- Content creation rejects an existing identity, including a soft-deleted
  row; restoration reuses that same identity.
- Parent movement rejects direct self-parenting and every indirect cycle,
  including `A → B → C → A`.
- Soft delete is rejected while a Category has non-deleted children.
- Image Role `role_key` is immutable and globally unique, including after soft
  deletion; restoration preserves the Role identity and status.
- Image Assignment identity `(category_id, media_asset_id, role_id,
  language_code, platform)` is immutable and unique, including soft-deleted
  rows; the same Media Asset may be assigned in other exact scopes.
- Image Assignment ordering is independent per Category and exact
  language/platform/Role scope. NULL Role is distinct from every concrete Role.
- Default is an explicit property of Category Image Assignment within one exact
  scope (category_id, role_id, language_code, platform); zero or one active
  default is allowed. Set/Clear are explicit mutations; creation never
  auto-selects a default, and changing display_order does not change it.
  Soft-deleting a default clears it in the same mutation, restoration leaves it
  non-default, and no fallback or promotion occurs.
- Content Field identity `(category_id, field_key, language_code, platform)` is
  immutable and unique, including soft-deleted rows; NULL scopes use
  NULL-safe database identity values.
- Content Field keys support all four exact nullable language/platform scopes;
  the same key may exist in different scopes without fallback between them.
- Content Field formats are exact lowercase `text`, `html`, and `json`; declared JSON values
  must be syntactically valid, while Host HTML/semantic validation is outside
  the package.
- Content Field full-form updates change `format` and `value` atomically. The
  typed `updateValue()` operation preserves the current format and uses that
  same atomic update, so no independent format/value mutation can violate the
  format invariant.
- Content Field ordering is independent per Category and exact scope, uses
  explicit reorder commands, and is stable across soft delete/restore.
- Restore reuses the same Category, Content, Image Role, Image Assignment, or
  Content Field identity.
- Every mutation updates `updated_at` using the Host-provided `ClockInterface`.
  Category owns timestamps as values, but does not own timezone policy; the Host
  provides the Clock and its timezone.
- Hierarchy/lifecycle checks and writes execute inside a real transaction with
  the required row locks.

## Query visibility contract

Management query methods expose stored Category, Content, Image Role, and Image
Assignment state for
management/use-case consumers. They do not apply consumer ancestor visibility
rules. Management reads provide Category get-by-ID/get-by-code, bounded
all/root/child lists plus paginated counterparts, and the same paginated
management surfaces for Content, Image Roles, Image Assignments, and Content
Fields. Deleted records are returned only when the caller explicitly selects
`include_deleted` or `deleted_only`. Category search remains within the
selected status and deleted-state scope, and `getByCode()` applies the
requested deleted state exactly.

Visible query methods:

- Read one Category by identity.
- List root Categories.
- List direct children by `parent_id`.
- Read Category Contents.
- Read Image Assignments for an exact language/platform/Role scope.
- Read Content Fields for an exact language/platform scope.

They exclude soft-deleted and inactive Categories. A descendant is hidden when
any ancestor in its complete parent path is inactive or soft-deleted. Query
methods return typed DTOs and do not select a language or apply fallback. Their
criteria cannot opt out of inactive/deleted filtering.
Image Assignment and Content Field reads exclude soft-deleted rows and never
fall back between exact scopes. Role-scoped Image Assignment reads also require
an active, non-deleted Role. Management `scope = null` means no
language/platform scope filter. An explicit `CategoryImageAssignmentScopeDTO`
filters its language/platform dimensions exactly; without an explicit
`roleFilter`, its `roleId` remains an exact Role predicate, so
`new CategoryImageAssignmentScopeDTO()` means exact NULL Role/NULL
language/NULL platform. To keep language/platform exact while omitting the
Role predicate, pass `CategoryImageAssignmentRoleFilterDTO::omitted()`. Use
`CategoryImageAssignmentRoleFilterDTO::exactNull()` for an explicit NULL Role
filter or `::forRole($roleId)` for one concrete Role. Image Role reads are
management-only and are exposed by `imageRoles()`; they are not part of
`categories()` or any consumer visibility API.

## Persistence contract

The canonical schema is [`schema/category.sql`](schema/category.sql). It owns
exactly five tables:

- `maa_category_categories`
- `maa_category_category_contents`
- `maa_category_category_image_roles`
- `maa_category_category_image_assignments`
- `maa_category_category_content_fields`

The schema uses InnoDB, `utf8mb4`, `ON DELETE RESTRICT`, `ON UPDATE RESTRICT`,
stable unique keys, status/language/platform/Role `CHECK` enforcement, and
package-owned self-parent triggers. A stored generated language identity maps
NULL to one uniqueness value, so MySQL enforces both the single unlocalized
row and the per-language uniqueness. Content Fields use generated
NULL-normalized language/platform identities for their own immutable
four-part identity and generated exact-scope ordering keys for locking and
ordering. Image Assignments use generated NULL-normalized Role/language/platform
identities for their immutable five-part identity and generated exact-scope
ordering keys. Image Assignments also persist `is_default` as `TINYINT(1)` with
an enforced `0/1` check. A conditional generated `default_scope_identity` is
non-NULL only for an active default and has a unique key, so MySQL enforces
zero or one active default per exact scope without preventing multiple
non-default or soft-deleted rows. Image Assignment `role_id` has an internal
restrictive foreign key to the Image Role registry; there are no Host-table
foreign keys. Image
Role `status` and Content Field `format` use case-sensitive `utf8mb4_bin`
column collations, and `JSON_VALID(value)` is enforced for exact lowercase
`json` rows.
MySQL 8.0.16 or later is required because
earlier MySQL 8 releases accepted but did not enforce `CHECK` constraints.

Timestamps are application-managed values supplied by the Host's
`Maatify\SharedCommon\Contracts\ClockInterface`. PDO repositories persist the
supplied `DateTimeImmutable` wall-clock value without converting it to UTC or
generating `now()` themselves. Read adapters receive the same Clock and hydrate
stored `DATETIME` values with `$clock->getTimezone()`. Category owns timestamps
as values, but does not own timezone policy; the Host provides the Clock and its
timezone.

Display-order creation and mutations consume the stable `maatify/persistence`
Ordering API, including nullable root scopes and atomic `updated_at` mutation.
Creation locks the target scope inside the package transaction before asking
the API for `MAX(display_order) + 1`. The package does not implement a local
ordering or pagination substitute.

Default assignment and clearing use the same shared transaction runner and PDO
instance. The write adapter locks every row in the target generated exact
scope, revalidates the active target, clears any current active default, and
then sets the requested target. This scope lock and the conditional generated
unique key provide application- and database-level protection against
concurrent default changes.

Each domain service wraps its orchestrated mutations with the shared
`TransactionRunnerInterface`. `CategoryFactory` provides
`PdoTransactionRunner` using the same PDO instance supplied to every Category
repository and Ordering operation. When the Host already owns a transaction on
that PDO, the shared runner participates without committing or rolling it back;
outer transaction ownership remains with the Host. Category does not provide a
local transaction implementation.

Repository adapters classify selected database failures. Known duplicate-key
failures are translated into the relevant package-owned `AlreadyExistsException`;
other database failures that are not explicitly classified may propagate as
`PDOException`. Package-owned storage/hydration validation failures use the
appropriate `CategoryPersistenceException` hierarchy.

## Composer and platform contract

The package is `maatify/php-category`, type `library`, under the
`Maatify\Category\` PSR-4 namespace. Its direct runtime requirements are PHP
`^8.4`, `ext-mbstring`, `ext-pdo`, `ext-pdo_mysql`, `maatify/exceptions:^1.0`,
`maatify/persistence:^1.3`, and `maatify/shared-common:^1.0`. Development
tools are declared separately in `require-dev`; the reusable library does not
commit `composer.lock`.

The package's storage contract is MySQL `8.0.16+` with InnoDB, `utf8mb4`, and
enforced `CHECK` constraints.

## Exceptions

The package marker is `CategoryExceptionInterface`. Named exceptions are used
for distinct failure semantics:

- `CategoryInvalidArgumentException`
- `CategoryNotFoundException`
- `CategoryContentNotFoundException`
- `CategoryContentAlreadyExistsException`
- `CategoryContentFieldNotFoundException`
- `CategoryContentFieldAlreadyExistsException`
- `CategoryCodeAlreadyExistsException`
- `CategoryCycleException`
- `CategoryHasNonDeletedChildrenException`
- `CategoryPersistenceException`

They use the stable hierarchy from `maatify/exceptions`.

Named factories exposed by the package are:

- `CategoryInvalidArgumentException::emptyField()`, `fieldTooLong()`,
  `invalidId()`, `nonPositiveId()`, `invalidDisplayOrder()`, `selfParent()`,
  `invalidListLimit()`, and `invalidJsonValue()`.
- `CategoryNotFoundException::withId()` and
  `CategoryContentNotFoundException::withId()`.
- `CategoryCodeAlreadyExistsException::withCode()` and
  `CategoryContentAlreadyExistsException::withIdentity()`.
- `CategoryContentFieldNotFoundException::withId()` and
  `CategoryContentFieldAlreadyExistsException::withIdentity()`.
- `CategoryCycleException::forMove()` and
  `CategoryHasNonDeletedChildrenException::withId()`.
- `CategoryPersistenceException::queryFailed()`,
  `invalidAutoIncrementIdentity()`, `invalidContentAutoIncrementIdentity()`,
  `invalidStorageValue()`, and `unexpectedColumnType()`.

## Verification contract

The package must pass, where applicable:

- `composer validate --strict`
- latest-compatible and lowest-supported dependency resolution
- `composer check-platform-reqs`
- fail-closed `composer audit --abandoned=fail`
- PHP syntax validation
- PHPStan level max with zero errors
- Unit tests
- Full PHPUnit suite
- Real MySQL Integration tests with cleanup and repeatability coverage
- Workflow syntax validation
- Standalone external Composer consumer verification through `CategoryFactory`,
  the `CategoryFacade`, and all five Domain APIs
- Public `src/` inventory reconciliation and stale API/alias sweep

The integration suite is configured with `CATEGORY_TEST_DSN`,
`CATEGORY_TEST_DB_USER`, and `CATEGORY_TEST_DB_PASSWORD`. For local runs,
copy `env.testing.example` to the ignored `env.testing`; the PHPUnit bootstrap
loads those values as defaults and preserves any externally injected values,
including CI's isolated MySQL configuration.

## Non-goals and staged work

- Catalog, Product, Pricing, Inventory, and Media composition.
- HTTP/API routes, controllers, middleware, permissions, Twig, and JavaScript.
- Presentation serialization and response envelopes.
- Host language fallback.
- A separate Catalog entity or Catalog identity.
