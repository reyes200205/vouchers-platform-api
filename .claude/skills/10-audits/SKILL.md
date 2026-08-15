---
name: 10-audits
description: "Use for audit trails, event history, before-after values, evidence, and traceability in Mis vales."
user-invocable: true
---

# Audits

Use this skill for any sensitive history tracking.

## Guidance

- Record actor, timestamp, entity, before, after and reason.
- Capture changes to money, credit, status and authorization.
- Capture the role and context under which the user acted.
- Preserve evidence when the action came from verification, correction, or manual reconciliation.

## Design Rules

- Audit entries should explain the business reason, not only the technical diff.
- If a correction was made, preserve the original and the corrected values.
- Important events should remain searchable by distributor, branch, user, and time.

## Rule

If a state change matters financially or operationally, it must be auditable and recoverable from history.