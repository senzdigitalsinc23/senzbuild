# Request for Comments (RFC) Process

This document defines the RFC process for the SENZ Framework. All significant changes
to the framework must go through this process to ensure quality, consistency, and
community input.

## What is an RFC?

An RFC (Request for Comments) is a design document that proposes a new feature,
change, or improvement to the framework. It serves as the primary mechanism for
community-driven development and technical decision-making.

## RFC Lifecycle

```
┌─────────┐    ┌─────────┐    ┌─────────┐    ┌─────────┐    ┌─────────┐
│  Draft  │ →  │ Review  │ →  │ Accept  │ →  │ Active  │ →  │  Done   │
└─────────┘    └─────────┘    └─────────┘    └─────────┘    └─────────┘
   DRAFT        REVIEW          ACCEPT         ACTIVE          DONE
```

| Stage | Description |
|-------|-------------|
| **Draft** | Author creates the RFC. Open for initial feedback. |
| **Review** | Core team reviews the proposal. Technical discussion and iteration. |
| **Accept** | RFC is accepted into the roadmap. Implementation begins. |
| **Active** | RFC is being implemented. Changes may still occur. |
| **Done** | RFC is merged and released. Labeled with target version. |

## Submitting an RFC

1. **Create a new file**: `docs/rfcs/YYYY-MM-DD-short-title.md`
2. **Use the template**: Copy `docs/rfcs/TEMPLATE.md` and fill in all sections
3. **Open a PR**: Submit the RFC as a pull request against the `develop` branch
4. **Tag appropriately**: Use `type: RFC` label on the PR

## RFC Categories

| Category | Description | Example |
|----------|-------------|---------|
| **Feature** | New capability or functionality | "Add GraphQL subscriptions" |
| **Improvement** | Enhancement to existing feature | "Improve query builder performance" |
| **Architecture** | Structural/design changes | "Introduce event-driven cache invalidation" |
| **Deprecation** | Remove or deprecate a feature | "Deprecate legacy Auth middleware" |
| **Process** | Change to development process | "Adopt semantic versioning policy" |

## RFC Template

See `docs/rfcs/TEMPLATE.md` for the standard template.

## Review Criteria

RFCs are evaluated against these criteria:

1. **Problem statement** — Is the problem clearly defined?
2. **Proposed solution** — Is the solution well-designed and justified?
3. **Backwards compatibility** — Does it break existing behavior?
4. **Performance impact** — What is the cost of this change?
5. **Security implications** — Are there any security concerns?
6. **Documentation** — Will this be documented properly?
7. **Testing** — How will this be tested?

## Timeline

- **Draft stage**: 1-2 weeks for community feedback
- **Review stage**: 2-4 weeks for core team review
- **Accept to Active**: Depends on priority and complexity
- **Active to Done**: Depends on implementation scope

## Roles

| Role | Responsibilities |
|------|-----------------|
| **Author** | Proposes the RFC, drives discussion, implements if accepted |
| **Reviewer** | Core team member who provides technical review |
| **Editor** | Ensures RFC format and quality standards |
| **Owner** | Final decision-maker for acceptance/rejection |

## Rules

1. RFCs must be discussed in the open (GitHub issues/PRs)
2. No RFC should be implemented without being in "Accepted" status
3. RFCs can be superseded by new versions (v2, v3, etc.)
4. Accepted RFCs should be tracked in `docs/rfcs/accepted/`
5. Rejected RFCs should note the reason for rejection

## Versioning

RFC versions follow semantic versioning:
- v0.x — Draft/experimental
- v1.0.0 — Accepted for implementation
- v2.0.0 — Significant revision after acceptance
- v3.0.0 — Major rewrite

## Contact

Questions about the RFC process? Open a discussion in the framework repository.
