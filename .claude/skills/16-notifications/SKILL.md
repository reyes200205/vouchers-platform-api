---
name: 16-notifications
description: "Use for notifications, alerts, reminders, and event-driven communications in Mis vales."
user-invocable: true
---

# Notifications

Use this skill for business event notifications.

## Guidance

- Notify by event, role, and workflow state.
- Keep notification triggers configurable.
- Make notifications actionable so the recipient knows what happened and what to do next.
- Support different audiences like coordinator, verifier, branch manager, general manager, cajera, and distributor.

## Design Rules

- Notifications should reflect the business event, not a hardcoded timer.
- The same event may notify different roles with different message details.
- A notification should be traceable to the record and the state change that triggered it.

## Rule

Notifications should reflect the business event, not a hardcoded timer, and should always point to an actionable next step.