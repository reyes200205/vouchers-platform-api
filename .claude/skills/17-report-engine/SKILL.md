---
name: 17-report-engine
description: "Use for operational reports, accounting summaries, state of account outputs, delinquency views, and metric generation in Mis vales."
user-invocable: true
---

# Report Engine

Use this skill for reporting and summaries.

## Guidance

- Generate reports from auditable source data.
- Keep summaries tied to business definitions.
- Distinguish operational, managerial, and audit-facing reports.
- Make sure balances, morosas, and reconciliations all tie back to the same domain truth.

## Design Rules

- A report should be explainable from source records and the configured rule set.
- Exported reports should preserve the same business meaning as the on-screen version.
- If a report groups by branch, category, or distributor, the grouping must match the access scope.

## Rule

Reports must reconcile to the source records and rules, and should never invent a number the financial engine cannot reproduce.