# Utility for GeneratePress

**Utility for GeneratePress** is a complete **GeneratePress Utility** and **GenerateBlocks** design system companion for WordPress. It connects the **GeneratePress Customizer** with **GenerateBlocks Pro Design Tokens** and **Global Styles**, while adding **Color Management**, automatic **Color Shades**, a **Gradient Palette** builder, **Fluid Typography**, **Dark Mode**, and **GP / GenerateBlocks Export & Import** migration tools.

## ✨ Key Features

- **Customizer Sync & Design Token Sync**: Two-way synchronization between GeneratePress Global Colors and GenerateBlocks Pro Design Tokens (`2.8.0+`), plus one-way token export for Color Shades/Variants and Fluid Typography `clamp()` steps, and Gutenberg Editor CSS Sync for Child Theme & Additional CSS.
- **Color Management & Color Shades**: Automatically generate 9-step lightness scales (`10–90`), Complementary/Triadic/Analogous harmonies, and bulk-import `:root` CSS variables into GeneratePress Global Colors.
- **Gradient Palette**: Visual drag-and-drop linear and radial gradient builder with native `theme.json` preset registration (`--wp--preset--gradient--*`) and text/border gradient utility classes.
- **Fluid Typography**: Dynamic responsive scaling using CSS `clamp()` with modular scale ratios and a real-time visualization wizard.
- **Dynamic Dark Mode**: High-performance dark mode switching with mathematical HSL contrast boosting, `.dark` CSS variable overrides, and desktop/mobile/sticky dark mode logo replacement.
- **GP Export / GP Import & GenerateBlocks Export / Import**: Backup and migrate GeneratePress settings, GP Elements (`gp_elements`), GenerateBlocks Global Styles, Design Tokens, Style Categories, Local Patterns, Asset Library SVGs/Icons, Forms, Display Conditions, and Overlay Panels.

## 🚀 Installation

1. Download the [latest release](https://github.com/wpids-com/utility-for-generatepress/releases).
2. Upload the `utility-for-generatepress` folder to your `/wp-content/plugins/` directory (or upload the `.zip` via **Plugins > Add New > Upload Plugin**).
3. Activate the plugin in the WordPress Admin and navigate to **Appearance > Utility**.

## 🛠 Requirements

- **Theme**: [GeneratePress](https://wordpress.org/themes/generatepress/) (Active)
- **WordPress**: 6.7+
- **PHP**: 7.4+

## 📄 License

This project is licensed under the GPL-2.0 License.

---
*Created and maintained by [WPIDS](https://wpids.com).*
