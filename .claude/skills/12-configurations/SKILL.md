---
name: 12-configurations
description: "Use for configurable business rules, percentages, dates, limits, penalties, formulas, and versioned parameters in Mis vales."
user-invocable: true
---

# Configurations

Use this skill for values that the business can change without modifying code.

## Guidance

- Keep corte dates configurable.
- Keep payment windows configurable.
- Keep percentages and penalties configurable.
- Keep product-specific exceptions configurable.
- Keep branch, category, and role-sensitive settings configurable when the business depends on them.
- Preserve historical versions when a configuration can affect active relations.

## Design Rules

- If a configuration applies to only one product, distributor category, or date window, model that scope explicitly.
- Configuration changes should not silently recalculate historical results unless the business explicitly wants that.
- The engine should know whether to use the current configuration or the original one that existed when the record was created.

## Rule

If the business can negotiate it or adjust it operationally, configure it instead of hardcoding it.