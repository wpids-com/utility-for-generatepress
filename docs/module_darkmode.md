# Dark Mode Module

## Overview
A comprehensive Dark Mode solution for GeneratePress. It provides a toggle for users on the frontend, automatically shifts colors based on CSS variables, and swaps logo/images when dark mode is active.

## Key Features
1. **Frontend Toggle & System Preference**:
   - Injects a toggle button in the site header or footer (configurable).
   - Uses `localStorage` to remember user preference.
   - Respects OS-level preferences (`prefers-color-scheme: dark`) on first visit.
   - Toggles a `.dark` class on the `<html>` element.

2. **Color Synchronization**:
   - Every GP Global Color has a designated "Dark Counterpart" configured via Customizer.
   - When `.dark` is active on `<html>`, the CSS variables (`--[slug]`) are overridden by their dark counterparts (`--[slug]__dark__`).

3. **Image & Logo Swapping**:
   - Replaces the site logo natively by hooking into GP's `generate_logo` and block editor rendering.
   - Provides a `.has-dark-image` utility where two images can be overlaid and swapped based on the active mode using CSS `opacity`.

4. **Architecture / Files**:
   - `includes/class-wpids-dark-mode-module.php`: Handles PHP hooks, Customizer settings for dark counterparts, and CSS generation.
   - `assets/js/wpids-dark-mode.js`: Frontend script for handling the toggle logic, system preferences, and `localStorage`.
   - `assets/css/wpids-dark-mode.css`: Frontend and backend styles for the toggle and transition effects.
