---
name: 11-authorizations
description: "Use for approvals, permissions to move a record across states, and authorization workflows in Mis vales."
user-invocable: true
---

# Authorizations

Use this skill for approval flows and control points.

## Guidance

- Separate permission to view from permission to approve.
- Make transitions explicit and role-aware.
- Keep pending, approved, rejected, and escalated states visible.
- Use different authorization paths for edits, approvals, corrections, and manual reconciliation.

## Design Rules

- Authorization must depend on role, scope, and the type of action.
- Some actions may be visible to more roles than can execute them.
- A rejected action must preserve the reason and the responsible actor.

## Rule

Money-moving or credit-moving transitions require explicit authorization logic and an auditable decision trail.