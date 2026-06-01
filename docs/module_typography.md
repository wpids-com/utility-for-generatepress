# Fluid Typography Module

## Overview
The Fluid Typography module enhances GeneratePress by replacing static font sizes with fully responsive `clamp()` functions. It automatically calculates the minimum and maximum font sizes based on viewport widths, ensuring typography scales smoothly across all devices.

## Key Features
1. **Viewport Settings Configuration**:
   - Allows users to set Minimum and Maximum Viewport widths (e.g., 320px to 1200px) in the Customizer.
   - Provides a Base Font Size setting to anchor relative EM/REM conversions.

2. **GeneratePress Integration**:
   - Hooks into `generate_typography_css` and `generate_typography_default_fonts`.
   - Modifies the native GP Typography loop. Whenever a user sets a font size, it is treated as the *Maximum Size*. The system then calculates the *Minimum Size* (using a user-defined Scale Ratio) and outputs a `clamp()` CSS string instead of a static `px` or `em`.

3. **Gutenberg Editor Sync**:
   - Injects the generated fluid typography logic into `theme.json` via the `wp_theme_json_data_theme` hook.
   - This ensures the block editor perfectly mirrors the frontend fluid typography without compiling separate editor CSS.

4. **Architecture / Files**:
   - `includes/class-wpids-typography-module.php`: Core logic for calculating clamps and modifying GP settings.
   - Settings are stored in the main `UTILFOGE_utility_options` array.

## Core Formula
`clamp( MinSize, (MinSize + (MaxSize - MinSize) * ((100vw - MinViewport) / (MaxViewport - MinViewport))), MaxSize )`
