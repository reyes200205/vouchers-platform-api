---
name: 21-testing
description: "Use for unit, integration, feature, regression, and calculation testing of Mis vales business behavior."
user-invocable: true
---

# Testing

Use this skill for validation and regression control.

## Guidance

- Test financial formulas, edge cases and state transitions.
- Cover expected, boundary and failure scenarios.
- Add scenarios for anticipado, puntual, late payment, bad reference, and manual correction when relevant.
- Protect historical behavior when configuration changes.

## Design Rules

- Test the business rule, not just the implementation detail.
- Any rule that affects credit, money, reconciliation, or approval should have at least one regression test.
- If configuration changes the outcome, test both the baseline and the altered configuration.

## Rule

If a rule matters financially or operationally, it needs a test that proves the system still behaves correctly.