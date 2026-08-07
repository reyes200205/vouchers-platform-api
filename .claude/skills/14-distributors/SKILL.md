---
name: 14-distributors
description: "Use for distributors, distributor state, distributor credit behavior, branch ownership, and relationship visibility in Mis vales."
user-invocable: true
---

# Distributors

Use this skill for distributor management and behavior.

## Guidance

- Track status, credit, relation history and payment behavior.
- Preserve distributor-specific parameters when they differ from the default.
- Keep the coordinator, branch, and distributor relationship visible.
- Distinguish what a branch can see from what the general manager can see.

## Design Rules

- A distributor can belong to one branch operationally while higher roles can still see the full network.
- Category changes should affect financial treatment only through configuration.
- Distributor corrections made by a verifier must be auditable.

## Rule

Distributor state can change the financial workflow and must be considered by credit, relation, and authorization decisions.