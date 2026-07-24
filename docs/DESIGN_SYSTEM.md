# GalaxyOne V1 — Design System

Version: 1.0  
Status: Phase 3 Foundation

## 1. Purpose

This design system defines the presentation foundation for GalaxyOne V1.

It provides reusable visual tokens, responsive layout rules, accessible controls, and WooCommerce styling guidance. It does not contain product, pricing, delivery, reward, checkout, campaign, or administrative business rules.

## 2. Presentation Ownership

- Elementor Pro owns page layout, editable content, landing pages, and static sections.
- GalaxyOne Child owns styling, UI refinement, responsive behavior, and visual component rules.
- WooCommerce owns commerce templates and interaction behavior.
- GalaxyOne Core owns all business logic.

The child theme must not calculate prices, determine delivery availability, unlock offers, validate rewards, or modify WooCommerce business behavior.

## 3. Design Principles

- Mobile-first: the smallest supported viewport is 320 px wide.
- Clear hierarchy: customers should identify headings, prices, actions, and messages quickly.
- Consistent controls: equivalent actions use the same visual treatment.
- Accessible interaction: keyboard focus, readable contrast, and touch-friendly controls are required.
- Low visual weight: motion and decoration must not delay or obscure primary actions.
- Reusable tokens: colors, spacing, typography, and component shapes are centralized in CSS custom properties.

## 4. Color Tokens

| Token | Value | Use |
|---|---:|---|
| `--galaxy-color-primary` | `#165dff` | Primary actions and links |
| `--galaxy-color-primary-strong` | `#0d47c7` | Hover and active emphasis |
| `--galaxy-color-primary-soft` | `#e8f0ff` | Informational backgrounds |
| `--galaxy-color-text` | `#172033` | Main text |
| `--galaxy-color-text-muted` | `#5f6b7f` | Supporting text |
| `--galaxy-color-surface` | `#ffffff` | Main surface |
| `--galaxy-color-surface-muted` | `#f5f7fb` | Page background and subdued surfaces |
| `--galaxy-color-border` | `#d9e0ea` | Control and card borders |
| `--galaxy-color-success` | `#137a48` | Success state |
| `--galaxy-color-warning` | `#9a5b00` | Warning state |
| `--galaxy-color-danger` | `#b42318` | Error state |

## 5. Typography

The default interface font stack is:

```text
Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif
