# Gradient Variables Module

## Overview
Allows users to create, manage, and implement CSS gradients as native WordPress variables (`--wp--preset--gradient--[slug]`). This integrates gradients seamlessly into the GenerateBlocks editor and provides advanced usage features like gradient text and gradient borders.

## Key Features
1. **Customizer Palette UI**:
   - A fully featured gradient builder built natively in the Customizer.
   - Allows users to configure Linear, Radial, and Conic gradients with multiple stops and angle controls.

2. **Native Gutenberg Integration**:
   - Injects the configured gradients into `theme.json` via the `wp_theme_json_data_theme` hook.
   - Gradients appear natively in the Block Editor's Color Panel (under the Gradients tab).

3. **Advanced CSS Generation**:
   - **Gradient Text**: Generates a utility class `.has-[slug]-gradient-text` that uses `-webkit-background-clip: text` to apply the gradient to typography.
   - **Gradient Borders**: Generates a utility class `.has-[slug]-gradient-border`. Uses advanced CSS masks (`-webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0); -webkit-mask-composite: xor;`) combined with `::before` pseudo-elements to ensure border radius is respected when drawing gradient borders.

4. **Integration with GP Global Colors (Read-Only)**:
   - To keep the user experience cohesive, the gradient palette is injected natively as a read-only visual reference below GeneratePress's "Global Colors" Customizer panel.

5. **Architecture / Files**:
   - `includes/class-wpids-gradient-module.php`: Handles Customizer settings, CSS generation, and `theme.json` injections.
   - `includes/class-wpids-gradient-control.php`: Customizer UI PHP skeleton.
   - `assets/js/wpids-gradient-module.js`: Full JS application driving the interactive gradient builder UI.
   - `assets/css/wpids-gradient-module.css`: Styling for the Customizer UI.
