---
name: 06-relaciones
description: "Use for relation generation, relation balance, product allocation, payment summaries, and state-of-account outputs in Mis vales."
user-invocable: true
---

# Relaciones

Use this skill for relation generation and settlement.

## Guidance

- A relation should be explainable from product, dates, points, bonuses and penalties.
- Every relation needs traceability to its corte and reference.
- The relation should behave like the state of account for the distributor.

## Design Rules

- The relation must explain how the amount was built: product, commission, seguro, points, recargos, and timing.
- Each relation should have a unique reference for reconciliation and follow-up.
- If the relation changes because configuration changes, the engine should know whether the new version applies to future or active accounts.
- The output should be understandable by operations without needing to inspect the raw formula.

## Rule

The relation is the main financial document of the system, and every payment, reconciliation, or adjustment must map back to it.