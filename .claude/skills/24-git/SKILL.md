---
name: 24-git
description: "Use for Git workflow, branches, diffs, history, safe commits, release hygiene, and change tracking in Mis vales."
user-invocable: true
---

# Git

Use this skill for version control tasks.

## Guidance

- Keep changes small and reviewable.
- Inspect diffs before commit.
- Use history to understand intent.
- Avoid bundling unrelated business changes in the same commit.
- Keep changes aligned to one business flow or one rule change when possible.

## Design Rules

- The commit history should help explain why a financial rule changed.
- If a fix touches money, roles, or reconciliation, it should be easy to locate in history later.

## Rule

Every commit should tell one coherent business story and should be reviewable without guessing context.