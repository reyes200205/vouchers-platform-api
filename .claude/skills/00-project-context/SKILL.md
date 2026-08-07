---
name: 00-project-context
description: "Use for the global Mis vales project context, business philosophy, rule interpretation from documents, and cross-module decisions."
user-invocable: true
---

# Project Context

Use this skill for the high-level business context of Mis vales.

## What This Skill Holds

- The central business model of vales in cash for distributors.
- The operational meaning of relations, credits, payments, and reconciliations.
- The distinction between fixed business rules and configurable behavior.
- The role of documents, examples, and approvals in defining the system.

## Core Ideas

- Vales en efectivo para distribuidoras.
- El negocio gira alrededor de relaciones, créditos, conciliaciones y estado de cuenta.
- Todo debe ser configurable y auditable.
- The same person may appear in more than one workflow, but permissions must still be explicit.

## Operating Questions

- What is the source of truth for the rule?
- Is this rule global, by sucursal, by categoría, or by product?
- Does the rule apply only to new records or also to active records?
- What evidence should remain after the action?

## Rule

Always anchor answers in the business document or the configured rule set, and if the document is ambiguous, flag the ambiguity instead of inventing a rule.