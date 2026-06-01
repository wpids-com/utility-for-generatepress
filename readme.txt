=== Utility for GeneratePress ===
Contributors: wpids
Tags: generatepress, generateblocks, dark mode, fluid typography, gradient
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The ultimate underrated helper tool for GeneratePress & GenerateBlocks. Empower your site with Dark Mode, Fluid Typography, advanced Color Management, custom CSS Gradients, and seamless Export/Import backups.

== Description ==

Unlock the full power of your WordPress website with the most **underrated helper tool for GeneratePress & GenerateBlocks**. Designed specifically for developers, designers, and site builders, this plugin bridges gaps and adds powerhouse features to the GP ecosystem with absolute performance and zero overhead.

Whether you are using GeneratePress Premium or the free theme, this lightweight utility integrates perfectly into the Customizer and block editor to deliver a native-feel administration dashboard.

= 🚀 Main Features =

* **Dynamic Dark Mode**: Turn your site into a modern, eye-friendly experience. Built with mathematical HSL auto-derivatives, contrast-boosting logic, and real-time customizer previewing. Completely overrides original global variables in `.dark` scope for a clean CSS footprint.
* **Fluid Typography Wizard**: Automatically calculate and generate responsive typography using the CSS `clamp()` function. Easily configure dynamic scale ratios to eliminate tedious media queries.
* **Advanced Color & Gradient Management**: Overhauled gradient builder that lets you easily create, drag-and-drop, and manage custom CSS gradient variables. Fully integrates with Gutenberg and theme.json native palettes.
* **Seamless Export / Import Module**: Backup, migrate, and transfer all your **GeneratePress** settings, custom **GP Elements**, and full **GenerateBlocks** layouts (including Global Styles, Local Patterns, SVG shapes, icons, display conditions, and overlay panels) with automated CSS cache-rebuilding.
* **Editor CSS Sync**: Achieve a true WYSIWYG experience in Gutenberg. Automatically enqueues and synchronizes your Child Theme or Customizer CSS directly into the editor canvas.

= ⚡ Performance-First Philosophy =
We hate bloat as much as you do. This plugin enqueues minimal vanilla CSS and JS, utilizes native WordPress Gutenberg components, and does not rely on heavy external frameworks, keeping your Google PageSpeed scores at 100%.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/utility-for-generatepress` directory, or install through the WordPress Plugins screen.
2. Activate the plugin.
3. Navigate to **Appearance > Utility** in your WordPress dashboard to toggle your desired modules.

== Frequently Asked Questions ==

= Does this require GeneratePress Premium? =
No! It works beautifully with the free version of GeneratePress. However, it is built to complement both the free and premium ecosystems perfectly.

= How does the GenerateBlocks Export/Import work? =
Our Export/Import module natively supports **GenerateBlocks**. When you run an export, it bundles all your local patterns, global styles, asset library SVGs, and overlays. When imported on a new site, it automatically re-syncs all block styles and refreshes the CSS cache so your design loads perfectly instantly.

= What makes this the most underrated helper tool? =
Most utilities only focus on one minor aspect. **Utility for GeneratePress** combines typography, advanced math-based dark mode, theme.json gradient injection, and a robust migration engine in a single lightweight package that operates under a unified design system.

= Will this plugin slow down my Gutenberg editor? =
No. The Editor CSS Sync enqueues your styles via native WordPress editor styles hooks, ensuring they only load inside the Gutenberg frame without any blocking scripts.

== Changelog ==

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
