---
name: 19-performance
description: "Use for performance, scalability, query optimization, caching strategy, batch work, and bottleneck analysis in Mis vales."
user-invocable: true
---

# Performance

Use this skill for speed and scalability decisions.

## Guidance

- Optimize only after preserving correctness.
- Consider query patterns, indexes, caching and workload shape.
- Treat reconciliation, reports, and notifications as potential batch workloads.
- Cache only what can be safely invalidated and explained.

## Design Rules

- Performance improvements must preserve auditability and business meaning.
- If a query gets faster by omitting critical fields, that is a bad tradeoff.
- Heavy operations should be moved to background or batch processing when the workflow allows it.

## Rule

Performance changes must not distort financial accuracy, traceability, or rule interpretation.