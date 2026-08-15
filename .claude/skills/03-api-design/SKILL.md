---
name: 03-api-design
description: "Use for API contracts, endpoints, payloads, response shapes, versioning, and integration rules in Mis vales."
user-invocable: true
---

# API Design

Use this skill for endpoint and contract design.

## Guidance

- Keep naming consistent and intention-revealing.
- Return predictable resource shapes.
- Separate validation errors from domain errors.
- Expose business status clearly so clients know whether they can act, wait, or escalate.

## Design Rules

- Prefer business identifiers and labels when the client needs to understand a record.
- Keep calculations in the server response derived from the financial engine, not duplicated by the browser.
- Use explicit messages or codes for authorization, validation, configuration, and reconciliation failures.
- Preserve backward compatibility when the contract feeds an operational workflow.

## Rule

The API should explain the domain clearly to clients and should not hide the reason a business action is blocked.