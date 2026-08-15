---
name: 08-financial-engine
description: "Use for formulas, totals, commissions, insurance, penalties, bonuses, points, and all financial calculations in Mis vales."
user-invocable: true
---

# Financial Engine

Use this skill for the core calculation engine.

## Guidance

- Break totals into inputs and formulas.
- Support configurable commissions, insurance, bonuses, penalties and points.
- Keep calculations deterministic and auditable.
- Separate base amount, commission, seguro, points, recargos, and final amount.
- Make the explanation understandable for operations and audit.

## Design Rules

- The engine must support different results for anticipado, puntual, and fuera de tiempo scenarios.
- Any amount that depends on category, product, or relationship history must be taken from configuration or domain state.
- When the engine changes a result, it should be possible to explain why the number changed.
- The same input set must always yield the same output unless a versioned configuration changes the rule.

## Rule

Every amount must be derivable step by step from business inputs, and no opaque total should be accepted without a breakdown.