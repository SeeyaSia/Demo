# Canvas Bootstrap

Canvas Bootstrap integrates Bootstrap 5 components into Drupal’s Canvas editor, allowing site builders to use familiar Bootstrap UI elements as native Canvas components.

This module provides ready-to-use Canvas components such as buttons, containers, rows, and layout helpers that map directly to Bootstrap classes, without writing code.

## Features

- Native Canvas components powered by Bootstrap 5
- Predefined components (Button, Container, Row, Columns, etc.)
- Variant options (primary, secondary, success, etc.)
- Size options (sm, default, lg)
- Outline button support
- Fully compatible with Canvas component metadata
- No JavaScript required
- Uses your site’s active Bootstrap theme styling

## Use cases

- Build Bootstrap-based layouts visually using Canvas
- Allow editors to create consistent UI elements
- Reduce the need for custom Twig or Paragraphs
- Rapid prototyping for Bootstrap-themed Drupal sites

## Requirements

- Drupal 11 or higher
- Canvas module (required)
- A Bootstrap 5–based front-end theme

## Installation

Install like any Drupal module:
`composer require drupal/canvas_bootstrap`

## Usage
- Once enabled:
- Open the Canvas editor
- Look for the Canvas Bootstrap category in the Components Library
- Drag and drop components like Bootstrap Button
- Configure variants, size, and options from the component settings panel
- No configuration pages are required.

## Notes
This module does not ship Bootstrap CSS.

Styling is provided by your active Bootstrap-based theme.

Components automatically inherit front-end styles inside Canvas.

Maintainers
- Ahmad Abbad ([@ahmadabbad](https://www.drupal.org/u/ahmad-abbad))
