---
name: 01-laravel
description: "Use for Laravel 12 conventions, backend structure, controllers, requests, services, jobs, events, and framework-level decisions."
user-invocable: true
---

# Laravel

Use this skill for backend implementation decisions in Laravel.

## Guidance

- Prefer thin controllers and explicit services.
- Keep domain logic out of routes and views.
- Validate input before invoking business rules.

## Rule

Laravel should orchestrate the domain, not hide it.