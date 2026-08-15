---
name: 07-conciliaciones
description: "Use for payment reconciliation, reference matching, deposits, manual adjustments, and account settlement in Mis vales."
user-invocable: true
---

# Conciliaciones

Use this skill for matching payments to relations.

## Guidance

- Match by reference, amount, date and method.
- Support partial and multiple payments when business rules allow it.
- Preserve evidence of reconciliation.
- Allow manual correction only through an authorized workflow.
- Treat unmatched payments as visible business items, not discarded noise.

## Design Rules

- A bad reference must not silently match the wrong relation.
- If a payment cannot be matched automatically, the system should preserve it as unmatched and visible.
- Manual reconciliation should leave evidence, who authorized it, and why it was needed.
- A mismatch between bank amount and relation amount should remain explainable, not hidden.

## Rule

Every matched payment must be reproducible from evidence and configuration, and every exception must remain auditable.