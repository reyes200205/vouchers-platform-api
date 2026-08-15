---
name: 23-debug
description: "Use for debugging calculations, workflows, API issues, database problems, UI bugs, and audit mismatches in Mis vales."
user-invocable: true
---

# Debug

Use this skill for troubleshooting.

## Guidance

- Reproduce the problem.
- Isolate the affected layer.
- Compare expected versus actual domain behavior.
- Trace the symptom back to the controlling rule or configuration.
- Validate with the smallest possible scenario that still shows the failure.

## Debug Questions

- Is the problem in the frontend, API, domain, database, or configuration?
- Did a role, branch, or state change alter the expected behavior?
- Is the number wrong because the formula changed or because the input changed?

## Rule

Debug from symptom to controlling rule, not from guess to guess, and stop when you can prove the actual controlling input.