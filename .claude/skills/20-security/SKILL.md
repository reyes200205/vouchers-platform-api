---
name: 20-security
description: "Use for authentication boundaries, authorization, sensitive data handling, and security controls in Mis vales."
user-invocable: true
---

# Security

Use this skill for security-sensitive behavior.

## Guidance

- Protect financial data, identities and references.
- Enforce least privilege and explicit access control.
- Separate read access, write access, approval access, and export access.
- Keep evidence and logs protected from tampering.

## Design Rules

- Sensitive personal data must only be visible to the roles that need it.
- A low-privilege role must not be able to alter financial history.
- If a workflow exposes an action, it should still be blocked unless the authorization rule passes.

## Rule

Security must protect both data and financial actions, and should leave enough evidence to investigate misuse.