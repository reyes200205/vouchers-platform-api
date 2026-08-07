---
name: 05-credit-engine
description: "Use for credit limits, credit availability, product assignment, credit status changes, and tolerance rules in Mis vales."
user-invocable: true
---

# Credit Engine

Use this skill for all credit-related decisions.

## Guidance

- Track available, used, and reserved credit.
- Apply rules by customer, distributor, product, or state.
- Make credit changes explicit and auditable.
- Evaluate whether the active relation or vale history changes the available amount.
- Respect the first-vale rule when the configured policy says the first disbursement has a cap.

## Design Rules

- Credit must always be computed from current state and configuration.
- If a credit increases while active vale balances exist, the engine must consider the current outstanding amount.
- Decisions should distinguish between available credit, authorized credit, and risk capacity.
- A customer cannot receive a second active opening credit if the business rule forbids it.

## Rule

Credit must be computed from state plus configuration, never guessed or inferred from stale assumptions.