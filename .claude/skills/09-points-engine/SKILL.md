---
name: 09-points-engine
description: "Use for point accumulation, point penalties, point-to-cash conversion, and performance incentives in Mis vales."
user-invocable: true
---

# Points Engine

Use this skill for point systems.

## Guidance

- Points must be configurable by product, corte or payment timing.
- Point deductions should be explicit when payment is late or outside policy.
- The value of a point must be configurable and understandable for the business.
- If points can be redeemed or converted, that conversion must be versioned or effective-dated.
- Point behavior should reflect payment discipline, not arbitrary manual decisions.

## Design Rules

- Points should be linked to business events that can be audited.
- A late payment can reduce, suspend, or deny points depending on the configured rule.
- If the business changes the value of a point, historical redemptions should remain explainable.

## Rule

Points are part of the financial truth and must be traceable, configurable, and reproducible from the rule set.