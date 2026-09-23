# Long-Term Support (LTS) Policy

This document defines the Long-Term Support (LTS) policy for the SENZ Framework.

## LTS Definition

An **LTS release** is a framework version that receives extended security patches
and bug fixes for a minimum of 12 months after the next major version is released.

## Release Tiers

| Tier | Duration | Support Scope |
|------|----------|---------------|
| **Current** | Until next major release | Bug fixes, features, security |
| **LTS** | 12+ months after next major | Security fixes, critical bugs only |
| **Maintenance** | 6 months after LTS ends | Security patches only |
| **End-of-Life** | — | No support; upgrade required |

## Version Numbering

Following SemVer (Semantic Versioning):

- **Major** (X.y.z): Breaking changes. Only one major version is actively supported at a time.
- **Minor** (x.Y.z): New features, backwards-compatible.
- **Patch** (x.y.Z): Bug fixes, security patches, backwards-compatible.

### LTS Designation

- LTS releases are marked with an `lts` suffix in the branch name: `main-lts-1.x`
- LTS releases are announced in the CHANGELOG under a dedicated section
- At any time, only one major version line can be in LTS status

## Support Timeline Example

```
Version 1.0.0 (LTS)  ──────────────────────────────►  Version 2.0.0
Support: 12 months                   ^
                                      │
                              Version 2.0.0 released
                              (Current + LTS candidate)

Version 2.0.0 (LTS)  ──────────────────────────────►  Version 3.0.0
Support: 12 months
```

## Security Patch Policy

- Security patches are backported to all active versions (Current and LTS)
- Critical security fixes are released within 72 hours of disclosure
- Security advisories are published at `docs/security/advisories/`
- Users are notified via email and GitHub releases for critical patches

## Deprecation Policy

- Features marked as deprecated receive a deprecation warning for at least one minor release
- Deprecated features are removed in the next major version
- Migration guides are provided for all breaking changes
- Deprecation notices include: what is deprecated, why, and the replacement

## Branch Strategy

```
main                ← Current development (latest minor/major)
main-lts-1.x        ← LTS branch for v1.x (security + critical fixes only)
main-lts-2.x        ← LTS branch for v2.x (if applicable)
release/1.5.0       ← Temporary release branch (merged to main then deleted)
```

## Upgrade Path

Users should upgrade in the following order:

1. Stay on current minor version for 6+ months before upgrading major versions
2. LTS users should upgrade to the latest patch version before switching major versions
3. Non-LTS versions receive no updates after End-of-Life; migration guide required

## End-of-Life Process

When a version reaches EOL:
1. A deprecation notice is added to the documentation
2. A migration guide to the current version is published
3. The version remains available via Composer but receives no updates
4. Security vulnerabilities are still patched for 6 months after EOL

## Commitment

The SENZ Framework team commits to:
- Maintaining at least one LTS branch at all times
- Providing security patches for LTS versions for 12+ months
- Publishing clear upgrade paths between major versions
- Communicating EOL dates at least 6 months in advance
