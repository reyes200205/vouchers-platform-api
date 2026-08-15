---
name: 13-products
description: "Use for products, product rules, product amounts, term definitions, and product-specific financial behavior in Mis vales."
user-invocable: true
---

# Products

Use this skill for product definition and product-driven rules.

## Guidance

- Track product name, amount, term and configurable behaviors.
- Keep product exceptions explicit.
- Keep product amount, term, category, and behavior normalized in configuration or domain state.
- If a product drives the relation formula, make that dependency explicit.

## Design Rules

- The product catalog should support business constraints like multiples of 100 if the rule requires it.
- A product can influence commission, points, limit, or payment timing only through an explicit rule.
- Changes to a product must be auditable if active relations depend on it.

## Rule

Product rules drive the downstream financial calculation and must remain readable to business, not just to developers.