=== Utility for GeneratePress ===
Contributors: wpids
Tags: generatepress, generateblocks, design tokens, dark mode, fluid typography
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.03
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Enhance GeneratePress and GenerateBlocks with design tokens, customizer sync, dark mode, fluid typography, and site migration tools.

== Description ==

**Utility for GeneratePress** is the complete **GeneratePress Utility** and **GenerateBlocks** design system companion for WordPress developers, agencies, and site builders. It bridges the gap between the **GeneratePress Customizer** and **GenerateBlocks Pro Design Tokens** and **Global Styles**, while adding native **Color Management**, automatic **Color Shades**, a **Gradient Palette** builder, **Fluid Typography**, **Dark Mode**, and one-click **GP Export / GP Import** and **GenerateBlocks Export / Import** tools — with zero frontend bloat.

Whether you use the free GeneratePress theme, **GP Premium**, **GenerateBlocks**, or **GenerateBlocks Pro**, every module integrates directly into the native WordPress Customizer and Gutenberg block editor.

= 🔗 Customizer Sync & Design Token Sync (New!) =

Keep your **GeneratePress Customizer** and **GenerateBlocks Pro Design Tokens** (`2.8.0+`) in sync without duplicate setup:

* **Two-Way GP Global Colors ↔ Design Tokens Sync**: Saving the GeneratePress Customizer automatically creates and updates matching color **Design Tokens** under the `GeneratePress` category in GenerateBlocks Pro. Updating those token values in the GenerateBlocks Design Tokens panel syncs changes right back to your GeneratePress Global Colors.
* **Color Shades & Variants Token Sync**: Automatically exposes all generated **Color Shades**, tints (`10–90`), and color harmony tokens from **Color Management** under the `Variants` category in GenerateBlocks Design Tokens.
* **Fluid Typography Token Sync**: Automatically registers your `--utilfoge-step-*` `clamp()` scale as `unit` **Design Tokens** scoped to `fontSize` under the `Fluid Typography` category for one-click selection inside GenerateBlocks typography controls.
* **One-Click "Sync Now" & Safe Checksum Architecture**: Trigger a manual sync anytime from **Appearance > Utility**. Built on GenerateBlocks Pro's native `:root` checksum validation and snapshot history — user-created tokens are never overwritten or deleted.
* **Gutenberg Editor CSS Sync**: Automatically enqueues your active Child Theme stylesheet and Customizer Additional CSS inside the Gutenberg block editor for a true WYSIWYG editing experience (works with or without GenerateBlocks Pro).

= 🎨 Color Management, Color Shades & Harmony Variants =

Build a complete design system palette directly from your **GeneratePress Global Colors**:

* **Automatic Color Shades & Tints (`10–90`)**: Generate full 9-step lightness scales (`--slug-10` through `--slug-90`) from any base color in your palette.
* **Mathematical Color Harmonies**: Generate Complementary, Triadic, Analogous, and Split-Complementary color variables with one click.
* **Bulk CSS Variable Import**: Paste any `:root` CSS block to parse, map, and import colors straight into GeneratePress Global Colors and **Design Tokens**.

= 🌈 Gradient Palette & Native theme.json Presets =

* **Visual Drag-and-Drop Gradient Palette Builder**: Create linear and radial CSS gradients using the native WordPress React ColorPicker with full alpha transparency support.
* **Native Gutenberg & theme.json Integration**: Automatically injects custom gradients as native WordPress presets (`--wp--preset--gradient--*`) plus `.has-{slug}-gradient-text` and `.has-{slug}-gradient-border` utility classes with dark mode variant support.

= 📐 Fluid Typography & Responsive clamp() Wizard =

* **Zero-Media-Query Fluid Typography**: Automatically calculate responsive font sizes using CSS `clamp()` based on your base size, min/max viewport widths, and modular scale ratios (Minor Third, Major Third, Perfect Fourth, Golden Ratio, or custom).
* **Automatic Heading & Token Mapping**: Maps `--utilfoge-step--1` through `--utilfoge-step-8` to `body` and `h1–h6`, and syncs them directly to **GenerateBlocks Pro Design Tokens**.

= 🌙 Dynamic Dark Mode & HSL Contrast Engine =

* **Zero-Duplication CSS Variable Override**: Overrides your existing GeneratePress Global Colors inside the `.dark` scope so every block using `var(--contrast)`, `var(--base)`, or **Design Tokens** adapts to Dark Mode automatically.
* **HSL Contrast Boosting & Auto-Desaturation**: Automatically calculates eye-friendly dark counterparts for your entire color palette.
* **Dark Mode Logo Replacement**: Dedicated Customizer controls for desktop, mobile header, and GP Premium sticky navigation dark mode logos with zero-FOUC early script execution.

= 📦 GP Export / GP Import & GenerateBlocks Export / Import =

A complete backup and migration toolkit for **GeneratePress** and **GenerateBlocks**:

* **GP Export & GP Import**: Export and import GeneratePress theme settings, Global Colors, Typography, and customizer configurations.
* **GP Elements Export/Import**: Backup and migrate custom **GeneratePress Elements** (`gp_elements` — Block, Header, Hook, and Layout Elements) across sites.
* **GenerateBlocks Export & GenerateBlocks Import**: Migrate **Global Styles**, **Design Tokens** (`gb_style_tokens`), **Style Categories**, **Local Patterns**, **Asset Library SVGs & Icons**, **Overlay Panels**, **Display Conditions**, **Forms**, and **Editor Access Profiles** with automatic CSS cache rebuilding.
* **GeneratePress Brand Tool `theme.json` Import**: Import palettes and typography directly from `theme.json` files.

= ⚡ Performance-First Philosophy =

Zero bloat, zero external framework dependencies. Every module is individually toggleable under **Appearance > Utility**, uses native WordPress React components in the admin, and outputs ultra-clean CSS variables on the frontend.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/utility-for-generatepress` directory, or install the plugin through the WordPress Plugins screen directly.
2. Activate **Utility for GeneratePress** through the **Plugins** screen in WordPress.
3. Navigate to **Appearance > Utility** to toggle modules, run **Customizer Sync**, or use the **GP / GenerateBlocks Export & Import** tools.

== Frequently Asked Questions ==

= How does Customizer Sync and Design Token Sync work with GenerateBlocks Pro? =
When **GenerateBlocks Pro 2.8.0+** is active, the **Customizer Sync** module connects the GeneratePress Customizer with GenerateBlocks Design Tokens. Your **GeneratePress Global Colors** sync two-way with the `GeneratePress` token category, while **Color Shades / Variants** (`--slug-10` to `--slug-90`, harmonies) and **Fluid Typography** steps (`--utilfoge-step-*`) sync one-way into the `Variants` and `Fluid Typography` categories. If GenerateBlocks Pro is not installed, Customizer Sync continues to work as an Editor CSS Sync tool for Gutenberg.

= Does this plugin require GeneratePress Premium or GenerateBlocks Pro? =
No! It only requires the free **GeneratePress** theme. However, it deeply integrates with **GP Premium** (Elements, Sticky Navigation Dark Mode Logo) and **GenerateBlocks / GenerateBlocks Pro** (Design Tokens, Global Styles, Local Patterns, Forms, and Asset Library migration) when they are active.

= How do Color Management and Color Shades work? =
Inside the Customizer, the **Color Management** module lets you select any GeneratePress Global Color (or import `:root` CSS variables) and automatically generate 9-step **Color Shades** and tints (`10` through `90`), harmony variants, and dark mode counterparts. All generated variables are immediately available in Gutenberg and synced to GenerateBlocks Design Tokens.

= Can I use this for GP Export, GP Import, and GP Elements Export/Import? =
Yes! The built-in **Export / Import** module lets you perform a selective or full **GP Export** and **GP Import** — including GeneratePress theme settings, Global Colors, Fluid Typography, and **GP Elements Export/Import** (`gp_elements`) — in a single JSON file.

= Does it support GenerateBlocks Export, GenerateBlocks Import, and Global Styles? =
Yes! You can export and import **GenerateBlocks Global Styles**, **Design Tokens**, **Style Categories**, **Local Patterns**, **SVG Shapes & Icons**, **Display Conditions**, **Overlay Panels**, **Forms**, and **Editor Access Profiles** with automatic dynamic CSS cache regeneration on import.

= How does the Dynamic Dark Mode work with Global Colors and Design Tokens? =
Instead of creating separate dark classes for every block, our Dark Mode engine overrides your original CSS variables (`--contrast`, `--base`, `--accent`, etc.) inside the `.dark` selector. Any GeneratePress element or GenerateBlocks block styled with Global Colors or **Design Tokens** automatically switches to its dark counterpart with zero extra configuration.

= How does the Gradient Palette builder integrate with Gutenberg? =
The **Gradient Palette** module provides a drag-and-drop multi-stop gradient builder in the Customizer and automatically registers your presets into WordPress's native `theme.json` palette (`--wp--preset--gradient--*`) as well as `.has-{slug}-gradient-text` and `.has-{slug}-gradient-border` utility classes.

== Screenshots ==

1. Utility for GeneratePress Dashboard - Toggle modules, run Customizer Sync, and manage GP & GenerateBlocks Export/Import.
2. Customizer Sync & Design Tokens - Automatic synchronization of GP Global Colors, Color Variants, and Fluid Typography into GenerateBlocks Pro Design Tokens.
3. Color Management & Color Shades - Generate 10–90 tints/shades and color harmony variants in the Customizer.
4. Dynamic Dark Mode Wizard - Real-time dark mode palette and logo configuration inside the Customizer.
5. Contrast Boosting Settings - Auto-desaturate vibrant colors and boost reading legibility in Dark Mode.
6. Fluid Typography Panel - Automatically calculate responsive `clamp()` typography scaling on the fly.
7. Fluid Viewport Configuration - Customize min/max viewports and modular scale ratios.
8. Advanced Drag-and-Drop Gradient Palette Builder - Craft custom linear and radial CSS gradients.
9. Gutenberg Palette Integration - Custom gradient variables and Design Tokens inside block editor controls.
10. GP & GenerateBlocks Export / Import Module - Backup or migrate GP Elements, Global Styles, Design Tokens, Patterns, and settings.
11. Seamless Element Dashboard Injection - Quick export/import buttons integrated into native GP & GB admin screens.
12. Gutenberg Editor CSS Sync - Real-time WYSIWYG sync of your Customizer and Child Theme styles inside the editor canvas.
13. HSL Color Math Engine Options - Configure alpha opacity and HSL auto-derivatives.
14. Mobile Responsive Design Preview - Fluid typography scaling across desktop, tablet, and mobile viewports.

== Changelog ==

= 1.1.03 =
* Feature: New Customizer Sync module (evolves Editor CSS Sync). Syncs GeneratePress Global Colors two-way with GenerateBlocks Pro Design Tokens (2.8.0+), and exposes Color Management variants and Fluid Typography steps as tokens.
* Feature: Added manual 'Sync Now' action and live sync status feedback directly on the Utility dashboard card.
* Tweak: Expanded color token scope to include borderTopColor, borderRightColor, borderBottomColor, and borderLeftColor for complete GenerateBlocks Pro border color picker support.
* Tweak: Context-aware module hint displaying 'Sync customizer with design tokens.' when GenerateBlocks Pro is active, or a non-intrusive hint when it is not.
* Tweak: Repositioned 'Appearance > Utility' submenu directly above Theme Editor and below all GeneratePress / GP Premium items.
* Compliance: Achieved 100% clean pass on automated WordPress.org Plugin Check (synced Stable tag, standard English short description, removed redundant Tested up to from PHP header).
* Compatibility: Replaced deprecated ReactDOM.unmountComponentAtNode with createRoot() and root.unmount() for React 19 forward compatibility.
* Cleanup: Removed deprecated Elite dashboard navigation link and cleaned up legacy utilgp-* files.
* Docs: Comprehensive documentation and discovery overhaul for Design Tokens, Customizer Sync, Global Styles, and GP / GenerateBlocks Export & Import.

= 1.1.02 =
* Compatibility: Fully tested and certified compatible with WordPress 7.1.
* Compatibility: Added full Export/Import support for GenerateBlocks Pro 2.7.0 and 2.8.0, including Editor Access Profiles (`gb_access_profile`), Control Sets (`gb_access_set`), Design Tokens (`gb_style_tokens`), and Style Categories.
* Compatibility: Added GeneratePress Premium 2.5.6 sticky navigation logo dark mode support via `generate_sticky_navigation_logo_output` filter.
* Performance: Optimized SVG Asset Library import with `autoload = false` to eliminate autoload overhead.
* Tweak: Raised minimum supported WordPress version to 6.7 following GenerateBlocks 2.5.0 core standards.

= 1.1.01 =
* Feature: Added Dark Mode Logo support (Desktop and mobile headers).
* Tweak: Changed default Dark Mode floating toggle position to Left.
* Feature: Added Export/Import support for GenerateBlocks Pro 2.6.1 Forms Custom Post Type (`gblocks_form`) and form integrations option data.

= 1.1.0 =
* Stable Release: Consolidation of all major modules into a production-ready stable build.
* Feature: Added powerful Export/Import backup and migration module with native support for GenerateBlocks Pro settings, elements, global styles, and SVG libraries.
* Security: Backported strict radial gradient shape whitelisting and position regex validation to prevent any potential CSS injection.
* UI: Updated Settings Dashboard to inherit native '--wp-admin-theme-color' for a flawless WordPress integration.
* Fixed: Removed invisible UTF-8 BOM bytes that caused Customizer saving/publishing failures.

= 1.0.18 =
* Security: Implemented strict shape whitelisting (circle, ellipse) and regex-based position validation for radial gradients.

= 1.0.17 =
* Maintenance: Cleaned up legacy translation strings and finalized prefix migration to 'utilfoge_'.

= 1.0.0 =
* Initial release of the GeneratePress utility suite.
