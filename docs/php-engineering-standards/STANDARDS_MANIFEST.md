# Maatify/category Standards Manifest

This file is the local resolver record for the repository's selective pinned
adoption. It records composition and provenance; the underlying standards and
profiles remain the source of truth for their own rules.

## Adoption metadata

- **Upstream repository:** `Maatify/php-engineering-standards`
- **Adoption commit:** `2fc57f9320f8a7f7147fb20abbcfa311fdf40c28`
- **Adoption date:** `2026-09-14` (source commit timestamp `2026-09-13T13:33:41+03:00`)
- **Floating upstream `main`:** not used
- **Adoption model:** Selective Pinned Adoption

## Pinned Adoption Control Set

These files are required to resolve and audit the active adoption:

- [`standards/STANDARDS_ADOPTION_STANDARD_AR.md`](standards/STANDARDS_ADOPTION_STANDARD_AR.md)
- [`standards/profiles/REPOSITORY_GOVERNANCE_PROFILE.md`](standards/profiles/REPOSITORY_GOVERNANCE_PROFILE.md)
- [`standards/profiles/BASE_MODULE_PROFILE.md`](standards/profiles/BASE_MODULE_PROFILE.md)
- [`standards/profiles/COMPOSER_PACKAGE_PROFILE.md`](standards/profiles/COMPOSER_PACKAGE_PROFILE.md)

All four files are copied from the adoption commit above. No unused profile
manifest is part of this control set.

## Active Profile Activations

| Profile | Profile version | Scope | Direct relationship |
|---|---:|---|---|
| [`repository-governance`](standards/profiles/REPOSITORY_GOVERNANCE_PROFILE.md) | `1.0.0` | `/` | no inheritance |
| [`base-module`](standards/profiles/BASE_MODULE_PROFILE.md) | `1.0.0` | `/` | extends `composer-package` |

The inherited `composer-package` profile is pinned in the Control Set and is
resolved for the `base-module` activation. Slim and Project-Aware Slim profiles
are not active and are not copied.

## Final Resolved Applicable Standards Set

The set below is the final result of the two-stage resolution required by the
pinned Adoption Standard: structural/transitive resolution produced the
candidate references, then canonical applicability was evaluated separately
for each active Profile/Scope against this repository's artifact facts. All
eight candidates apply; no non-applicable candidate is recorded here.

| Standard | Local path | Source Standard ID / Version | Resolved through |
|---|---|---|---|
| AI Collaboration Workflow | [`standards/ai/AI_COLLABORATION_WORKFLOW_AR.md`](standards/ai/AI_COLLABORATION_WORKFLOW_AR.md) | `std-ai-collaboration-workflow` / `6.0.0` | `repository-governance` |
| GitHub Phase Stack Workflow | [`standards/GITHUB_PHASE_STACK_WORKFLOW_AR.md`](standards/GITHUB_PHASE_STACK_WORKFLOW_AR.md) | `std-github-phase-stack-workflow` / `2.2.0` | `repository-governance` |
| Module Building Standard | [`standards/modules/MODULE_BUILDING_STANDARD.md`](standards/modules/MODULE_BUILDING_STANDARD.md) | `std-module-building` / `1.0.0` | `base-module` |
| Package Building Standard | [`standards/packages/PACKAGE_BUILDING_STANDARD.md`](standards/packages/PACKAGE_BUILDING_STANDARD.md) | `std-package-building` / `1.3.0` | inherited `composer-package` |
| Composer Package Standard | [`standards/packages/COMPOSER_PACKAGE_STANDARD.md`](standards/packages/COMPOSER_PACKAGE_STANDARD.md) | `std-composer-package` / `1.2.0` | inherited `composer-package` |
| CI Workflow Standard | [`standards/packages/CI_WORKFLOW_STANDARD.md`](standards/packages/CI_WORKFLOW_STANDARD.md) | `std-ci-workflow` / `1.1.0` | inherited `composer-package` |
| Library Presentation Standard | [`standards/packages/LIBRARY_PRESENTATION_STANDARD.md`](standards/packages/LIBRARY_PRESENTATION_STANDARD.md) | `std-library-presentation` / `1.0.1` | inherited `composer-package` |
| Testing Standard | [`standards/testing/TESTING_STANDARD.md`](standards/testing/TESTING_STANDARD.md) | `std-testing` / `1.1.0` | inherited `composer-package` |

### Auditable final profile resolution

```text
repository-governance (/)
└── AI Collaboration Workflow 6.0.0
└── GitHub Phase Stack Workflow 2.2.0

base-module (/)
├── Module Building Standard 1.0.0
└── composer-package (inherited)
    ├── Package Building Standard 1.3.0
    ├── Composer Package Standard 1.2.0
    ├── CI Workflow Standard 1.1.0
    ├── Library Presentation Standard 1.0.1
    └── Testing Standard 1.1.0
```

`STANDARDS_ADOPTION_STANDARD_AR.md` is part of the Control Set and is not an
engineering standard resolved by either active Profile.

## Additional Standards and exceptions

- **Explicit Additional Standards:** None.
- **Explicit Exceptions/Overrides:** None.

## Integrity and closure requirements

- Every retained pinned standard and profile is byte-for-byte equal to its
  upstream blob at the adoption commit.
- Relative links between retained files remain valid. Cross-references to
  non-retained upstream standards are checked against the exact adoption commit
  and do not add those standards to the local Adoption Set.
- Unused profiles, unused module standards, `docs/audits/`, and `docs/decisions/`
  are not copied into this repository's adoption set.
- Normal engineering tasks resolve from this manifest and the local pinned
  files; they do not require an upstream network request.
