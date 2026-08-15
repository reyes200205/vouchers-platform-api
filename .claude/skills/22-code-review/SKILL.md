---
name: 22-code-review
description: "Use for reviewing code changes, spotting regressions, missing tests, business rule drift, and security gaps in Mis vales."
user-invocable: true
---

# Code Review

Use this skill for reviewing changes.

## Guidance

- Check for hardcoded rules, missing tests and bad assumptions.
- Prioritize correctness and safety over style.
- Check whether the change respects roles, branches, and active credit state.
- Verify that any calculation change is explainable from configuration.

## Review Questions

- Does this change alter a configured rule or invent a new one?
- Does this preserve audit evidence?
- Would this still work when the configuration changes?
- Does this create a silent access or authorization gap?

## Rule

Review changes against the business rule, not only the diff, and challenge anything that weakens traceability or correctness.