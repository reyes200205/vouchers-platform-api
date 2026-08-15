---
name: 15-customers
description: "Use for customers, customer identity, customer state, duplicate detection, and customer-related financial rules in Mis vales."
user-invocable: true
---

# Customers

Use this skill for customer-centered business behavior.

## Guidance

- Track identity, status, and linkage to distributor or product.
- Distinguish final customers from distributors.
- Use CURP and other identifiers to prevent duplicate records.
- Preserve enough data for verification, onboarding, and future corrections.

## Design Rules

- A person should not receive multiple active opening credits if the business rule forbids it.
- Duplicate detection should use business identifiers, not only names.
- Sensitive customer data should be visible only to the roles that need it.

## Rule

Customer status can affect authorization, credit, reconciliation, and transfer rules, so identity must stay consistent across the system.