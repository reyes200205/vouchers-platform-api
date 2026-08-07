---
name: 18-database
description: "Use for MySQL schema design, indexes, transactions, constraints, historical data, and persistence modeling in Mis vales."
user-invocable: true
---

# Database

Use this skill for persistence and schema design.

## Guidance

- Model integrity with keys, constraints and transactions.
- Optimize queries without breaking correctness.
- Preserve historical values when financial audit or versioning requires them.
- Use precision that matches the monetary and points rules.

## Design Rules

- The schema should make invalid states hard to store.
- Foreign keys and constraints should protect critical domain relationships.
- If a record can be corrected, the schema or audit layer should preserve what changed.
- Transaction boundaries should wrap the full financial effect, not just one table write.

## Rule

The database must preserve the financial truth of the domain and support reconstruction of critical history.