# MODULE_BUILDING_STANDARD

## Standard Metadata

- **Standard ID:** `std-module-building`
- **Standard Version:** `1.0.0`
- **Standard Version Format:** `MAJOR.MINOR.PATCH`

This document defines the law for building any new standalone Base Module in the Maatify ecosystem.

---

## 1. Profile Relationships & Precedence

To ensure clarity in modular architecture, the following profile relationships are strictly enforced:

- **Base Module:** Exclusively designed as a reusable/extractable Composer Package.
- A Base Module is a reusable Package artifact located inside the Host repository. Extraction is a repository/distribution operation and MUST NOT require a Composer-contract, Runtime, namespace, or architecture rewrite.
- **Slim Module Wrapper:** An optional, highly specialized wrapper around **a single Base Module**. `MODULE_SLIM_BUILDING_STANDARD.md` owns its Admin HTTP/UI behavior; it must not duplicate core business logic.
- **Project-Aware Module:** Host-specific and non-extractable. `MODULE_PROJECT_AWARE_STANDARD.md` owns its Host-specific behavior; it never overrides Base Module rules unless explicitly named as an exception inside that profile.
- Non-extractable modules DO NOT implicitly expand Base. They must use the Project-Aware profile or establish a separate documented profile decision.
- All applicable Base Module rules, including those for Persistence and PDO, remain in effect when the module possesses Database behavior.

---

## 2. Standards Cross-References

This standard owns only the in-project Base Module artifact boundary, Host integration/isolation, profile relationships, and Base-specific extraction readiness. Generic package architecture and runtime mechanics are owned by `PACKAGE_BUILDING_STANDARD.md`; this Module Standard MUST NOT restate those mechanics.

The module MUST also adhere to:

- **Testing Architecture, Regression Protection & Evidence:** [TESTING_STANDARD.md](../testing/TESTING_STANDARD.md) owns the canonical testing strategy, behavior evidence, regression-protection rules, E2E/system test enforcement, and Consumer Verification Harness contract; this Standard defines Base Module applicability, while [PACKAGE_BUILDING_STANDARD.md](../packages/PACKAGE_BUILDING_STANDARD.md) defines Package-specific applicability.
- **Package Architecture & Runtime Mechanics:** [PACKAGE_BUILDING_STANDARD.md](../packages/PACKAGE_BUILDING_STANDARD.md) owns reusable Package structure, public contracts, persistence mechanics, validation, exceptions, query behavior, construction patterns, and domain architecture.
- **Other Module Profiles:** `MODULE_SLIM_BUILDING_STANDARD.md` and `MODULE_PROJECT_AWARE_STANDARD.md` own their respective profile-specific behavior; neither expands Base Module applicability.
- **Composer Metadata:** [COMPOSER_PACKAGE_STANDARD.md](../packages/COMPOSER_PACKAGE_STANDARD.md) owns dependency rules.
- **CI Checks:** [CI_WORKFLOW_STANDARD.md](../packages/CI_WORKFLOW_STANDARD.md) owns GitHub Actions workflow execution.
- **Library Presentation:** [LIBRARY_PRESENTATION_STANDARD.md](../packages/LIBRARY_PRESENTATION_STANDARD.md) owns READMEs, badges, and documentation formatting. The module must use the canonical root Package Reference; do not create a competing Module Reference.

---

## 3. Directory Structure, Boundaries, and The Module Contract

- The Base Module's source structure, Commands, Criteria, DTOs, Services, Repositories, and framework-neutral runtime contracts follow `PACKAGE_BUILDING_STANDARD.md`.
- Admin/UI wrappers belong to the Slim profile; Host-specific cross-module behavior belongs to Project-Aware. A Base Module MUST NOT absorb either profile to avoid extraction boundaries.
- The Base Module MUST remain a reusable Package artifact with a Host-independent public namespace and runtime boundary.
- The Module's Artifact Root MUST contain its own real, standalone-valid `composer.json` from the start. It MUST define an independent Composer package identity, production PSR-4 autoloading from `src/`, an independent namespace, and its direct runtime dependencies; it MUST NOT rely on the Host namespace or Host root `composer.json` as a substitute. The Host root Composer file MAY provide in-project autoloading while the Module is inside the Host, but does not replace the Artifact Root contract. [`COMPOSER_PACKAGE_STANDARD.md`](../packages/COMPOSER_PACKAGE_STANDARD.md) owns the detailed Composer rules.

---

## 4. Host Integration & Isolation Boundaries

- A Base Module MUST NOT depend at runtime on Host-owned tables, repositories, modules, or namespaces. Detailed external-identity, foreign-key, and join rules are owned by `PACKAGE_BUILDING_STANDARD.md`.
- Joins among the Base Module's own domain tables remain within its artifact boundary.

---

## 5. Dependency Injection

- Container wiring and project-specific bootstrap are the Host's responsibility. A Base Module MUST NOT ship Host-specific `Bootstrap/Bindings` or framework container configuration.
- Framework-neutral Factory, Builder, or Facade patterns are governed by the conditional rules in `PACKAGE_BUILDING_STANDARD.md`.

---

## 6. Conditional Database & Persistence Rules

Persistence, PDO, SQL, schema, migrations, transactions, query construction, hydration, ordering, pagination, and database testing are conditional on the Base Module's actual behavior and are governed by `PACKAGE_BUILDING_STANDARD.md` and the canonical Testing Standard. This document does not duplicate those mechanics.

The Host-isolation boundary in Section 4 remains in force. Project-Aware Host persistence responsibilities remain governed by the Project-Aware profile and do not expand Base Module scope.

---

## 7. Exception Rules

Exception hierarchy, markers, classifications, and propagation are owned by `PACKAGE_BUILDING_STANDARD.md` and apply to Base Modules when relevant.

---

## 8. Input Validation Rules

Public Command and query input validation is owned by `PACKAGE_BUILDING_STANDARD.md`. Base Modules MUST follow its canonical ID and date-input contracts rather than defining a competing validation policy here.

---

## 9. PHPStan Rules

PHPStan configuration, hydration/casting, and generic-annotation rules are owned by `PACKAGE_BUILDING_STANDARD.md`.

---

## 10. Decimal / Financial Rules

Decimal and financial-value rules are owned by `PACKAGE_BUILDING_STANDARD.md`.

---

## 11. Presentation vs Persistence Separation

The Package-level rule that persistence and query layers return unformatted values is owned by `PACKAGE_BUILDING_STANDARD.md` and applies to Base Modules when relevant.

---

## 12. Conditional Patterns (Translation & Analytics)

Translation is governed by the conditional pattern in `PACKAGE_BUILDING_STANDARD.md`. Pre-Aggregated Analytics remain conditional: when a Base Module owns such behavior, its Package Reference documents the applicable consistency, currency, idempotency, and transaction requirements. No Base Module is required to add analytics.

---

## 13. Base Module Completion Checklist

- [ ] The Base Module remains a Host-independent, reusable Package artifact with an independent public namespace.
- [ ] Its profile boundary is preserved: Admin/UI behavior is in Slim, and Host-specific cross-module behavior is in Project-Aware.
- [ ] It contains no Host-specific bootstrap/container configuration or runtime dependencies on Host tables, repositories, modules, or namespaces.
- [ ] The Artifact Root's standalone-valid `composer.json` satisfies Section 3; extraction to a separate repository/distribution does not require a new Composer contract or a Runtime, namespace, or architecture rewrite.
- [ ] Generic Package readiness, workflow, and practical consumer-example requirements in `PACKAGE_BUILDING_STANDARD.md` are satisfied.
- [ ] Its reproducible Consumer Verification Harness, as required by `TESTING_STANDARD.md`, consumes the Base Module's Artifact Root itself as an independent Composer dependency; the Host root is not a substitute.
- [ ] All cross-references to canonical Package, Composer, Testing, CI, Presentation, Slim, and Project-Aware standards are current.
