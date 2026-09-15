# Category schema

The canonical stable contract is [CATEGORY_PACKAGE_REFERENCE.md](../CATEGORY_PACKAGE_REFERENCE.md).

This directory contains the package-owned persistence contract for Categories
and Category Contents, extensible Category Content Fields, plus Category Image
Assignments and the Category Image Role registry.

The package requires MySQL 8.0.16 or later. This minimum is part of the storage
contract because the database schema relies on enforced `CHECK` constraints.
Integration verification uses the same required runtime version.

## Included tables

- `maa_category_categories`
- `maa_category_category_contents`
- `maa_category_category_image_roles`
- `maa_category_category_content_fields`
- `maa_category_category_image_assignments`

Apply [category.sql](category.sql). It creates exactly the five tables and the
package-owned self-parent triggers:

- `trg_maa_category_categories_parent_not_self_ai`
- `trg_maa_category_categories_parent_not_self_bu`

The schema contains no Host, Catalog, Product, Pricing, Inventory, or Media
tables. Timestamps are supplied by the Host through
`Maatify\SharedCommon\Contracts\ClockInterface` and stored as the values that
Clock provides; Category does not choose or normalize a timezone. Soft deletion
uses nullable `deleted_at`. Internal foreign keys use `RESTRICT` for
delete and update operations. Category creation obtains the next positive
`display_order` for the nullable `parent_id` scope through the shared
`maatify/persistence` Ordering API inside the application transaction.

Category Image Roles are package-owned registry records with immutable,
globally unique `role_key` values and typed exact-lowercase `active`/`inactive`
status stored with `utf8mb4_bin` and enforced by a binary `CHECK`. A
soft-deleted key remains reserved and restoration preserves the same identity.
Role semantics and media policy remain Host-owned.

Category Image Assignments are owned by Category and store only a validated
host-provided `media_asset_id`; there is deliberately no Media, Platform, or
Language foreign key. The exact scope is `(role_id, language_code, platform)`,
where every nullable dimension is a real value and not a fallback request.
`role_id` is an internal optional foreign key to the Role registry; NULL is the
generic/unclassified scope. The stable identity
`(category_id, media_asset_id, role_id, language_code, platform)` remains unique
across soft deletion through generated NULL-safe identity columns. The
generated `ordering_scope` lets the application use the shared Ordering API
independently for each Category and exact Role/language/platform scope.
Role-scoped assignments require an active, non-deleted Role on creation;
inactive or deleted Roles hide existing assignments from consumer reads while
management reads retain them. Default is an explicit property of Category Image
Assignment within one exact scope (category_id, role_id, language_code,
platform); zero or one active default is allowed and no automatic fallback or
promotion exists. is_default is constrained to 0/1, and a conditional
generated default_scope_identity is unique only for active default rows, so
multiple non-default and soft-deleted rows remain allowed. Default mutation is
explicit through Set/Clear commands, independent of display_order; soft
deletion clears it and restoration leaves the assignment non-default.

Category Content creation, content updates, soft deletion, and restoration
are exposed through the public Content Domain API (`CategoryFacade::contents()`);
consumers do not need direct SQL for that lifecycle. The `(category_id, language_code)` identity remains
unique and immutable. `language_code = NULL` represents unlocalized Content; a
non-NULL value represents localized Content. A stored generated identity column
makes NULL a single database uniqueness value, so MySQL enforces at most one
unlocalized row per Category as well as one row per non-NULL language code.
Empty-string language codes are rejected by the schema and are not an
alternative to NULL.

Category Content Fields are Host-defined key/value records with immutable
`(category_id, field_key, language_code, platform)` identity. The four exact
scope combinations are supported independently; management criteria distinguish
an omitted scope filter from an exact NULL/NULL scope, while consumer reads
require one exact scope and never fallback. `format` is exactly lowercase
`text`, `html`, or `json` through its case-sensitive `utf8mb4_bin` column
collation; values use `LONGTEXT`, JSON syntax is enforced for exact lowercase
`json` fields, and
Category does not sanitize, render, or interpret Host-defined keys. Field
ordering is independent per Category and exact scope through the shared
`maatify/persistence` Ordering API. The generated NULL-safe identity remains
unique across soft deletion.
