=== Utility for GeneratePress ===
Contributors: wpids
Tags: generatepress, generateblocks, dark mode, fluid typography, gradient
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.01
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Ultimate utility for GeneratePress & GenerateBlocks. Adds dark mode, fluid typography, gradients, editor sync, and settings migration.

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

= How does the Dynamic Dark Mode contrast boosting work? =
Our Dark Mode module uses a mathematical HSL color math engine. Instead of creating redundant CSS classes, it dynamically overrides the original global variables inside the `.dark` selector. The "Contrast Boosting" feature automatically desaturates bright colors and enhances background-to-text contrast ratios to guarantee optimal legibility and prevent eye strain.

= Does Fluid Typography support custom scale ratios? =
Yes! The Fluid Typography Wizard automatically calculates responsive text scaling using the modern CSS `clamp()` function. You can set custom minimum/maximum viewports and scale ratios, completely eliminating the need for tedious manual media queries.

= How does the Gradient Builder sync with Gutenberg? =
Our advanced Gradient Builder provides a drag-and-drop Color Stop slider using the native WordPress React ColorPicker. It automatically registers the resulting custom CSS gradients and seamlessly injects them directly into Gutenberg's block settings and native palettes via `theme.json` hooks.

= How does the GenerateBlocks Export/Import work? =
Our Export/Import module natively supports both **GeneratePress** settings (including custom Elements) and **GenerateBlocks Pro** assets (including Local Patterns, Global Styles, SVGs, custom icons, and displays). It bundles everything into a secure package and automatically rebuilds the CSS styles cache on import to ensure your pages look perfect instantly.

= Will this plugin slow down my Gutenberg editor? =
No. The Editor CSS Sync enqueues your styles using native WordPress editor styles hooks. This ensures they only load inside the active editor iframe frame with zero page overhead or blocking external scripts.

= What makes this the most underrated helper tool? =
Most utilities only focus on one minor feature. **Utility for GeneratePress** combines fluid typography scaling, an advanced math-based dark mode engine, dynamic theme.json gradient injection, and a robust migration engine into a single unified design system that runs with zero performance overhead.

== Screenshots ==

1. Utility for GeneratePress Dashboard - Easily toggle and manage your modular design extensions.
2. Dynamic Dark Mode Wizard - Real-time dark mode palette configuration inside the Customizer.
3. Contrast Boosting Settings - Auto-desaturate vibrant colors and boost reading legibility.
4. Fluid Typography Panel - Automatically calculate responsive typography scaling on the fly.
5. Fluid Viewport Configuration - Customize min/max viewports and scaling ratios.
6. Advanced Drag-and-Drop Gradient Builder - Craft beautiful custom CSS gradients.
7. Gutenberg Palette Integration - Syncing custom gradient variables directly into block editor controls.
8. Secure Export / Import Module - Easily backup or migrate your elements, styles, and configurations.
9. Seamless Element Dashboard Injection - Quick migration buttons integrated into native CPT dashboards.
10. Gutenberg Editor CSS Sync - Real-time WYSIWYG sync of your styles inside the editor canvas.
11. Unified Admin Branding - Perfectly matches the native premium GeneratePress look and feel.
12. HSL Color Math Engine Options - Configure custom alpha opacity and HSL auto-derivatives.
13. Mobile Responsive Design Preview - Beautiful fluid typography scaling on mobile viewports.
14. System Status and Diagnostics - Clean inline indicators reflecting your active modular setup.

== Changelog ==

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
