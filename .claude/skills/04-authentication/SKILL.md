---
name: 04-authentication
description: "Use for authentication, identity verification, session handling, device context, and access boundaries in Mis vales."
user-invocable: true
---

# Authentication

Use this skill for identity and access entry points.

## Guidance

- Distinguish authentication from authorization.
- Protect session and token handling.
- Keep login and recovery flows auditable.
- Record device context when the workflow needs it for audit or approval.
- Make the active role explicit if a user can act as more than one role.

## Design Rules

- Authentication must tell us who the user is, from where they connected, and under which role they are acting.
- Sensitive actions should not rely only on login state; they also need authorization checks.
- Account recovery and password resets should be auditable and revocable.

## Rule

Identity must be established before sensitive business actions are allowed, and the session must be traceable enough for audit.