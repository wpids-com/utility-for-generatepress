# Color Management Module

## Overview
An advanced color configuration center that acts as an "enhancer" for GeneratePress's native Global Colors. It provides tools for mass importing, math-based color derivatives, and dynamic UI syncing.

## Key Features (Including V2 Blueprint Architecture)
1. **Color Import & Mapping Wizard**:
   - Parses raw hex lists, JSON, and CSS variables.
   - Features a wizard to map imported colors to existing GP Global Color slugs or to create new ones.

2. **Full GP Palette Sync (Two-Way)**:
   - The UI actively listens to changes in GP's `generate_settings[global_colors]`.
   - Any color added natively via GP's React picker is instantly reflected in our module as a circle swatch.
   - Any change made in our module is instantly injected and saved to GP's native database source.

3. **Color Math Engine (Derivatives)**:
   - Generates a **Lightness Scale**: 9 steps from `--slug-10` to `--slug-90`.
   - Generates **Color Theory Variants**: Complementary, Triadic, Analogous, and Split Complementary.
   - Calculates **Auto Dark Counterparts** for Dark Mode syncing.

4. **Massive Menu Editor**:
   - Clicking a color swatch opens an advanced editor.
   - Allows renaming, hex editing, and toggling mathematical derivatives.
   - Provides live preview rendering of the generated variables with 1-click "Copy CSS" buttons.

5. **Priority 9997 CSS Injection**:
   - All generated derivative CSS variables are injected very early into the `:root` pseudo-class in the `<head>`, ensuring they are available immediately to all child components.

6. **Architecture / Files**:
   - `includes/class-wpids-color-module.php`: Core module handling AJAX math expansion, Customizer integration, and CSS generation.
   - `includes/class-wpids-color-math.php`: The engine responsible for HSL/RGB conversion, lightness shifting, and theory calculations.
   - `includes/class-wpids-color-import-control.php`: The HTML skeleton for the Customizer control.
   - `assets/js/wpids-color-module.js`: The frontend script driving the two-way GP sync, math previews, and modal interactions.
   - `assets/css/wpids-color-module.css`: Styles for the palette grid and massive menu modal.
