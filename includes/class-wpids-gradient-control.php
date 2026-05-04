<?php
/**
 * Gradient Control — GP React Color style UI.
 *
 * Renders the palette grid and inline editor shell.
 * All dynamic rendering is handled by wpids-gradient-module.js.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPIDS_Gradient_Control extends WP_Customize_Control {

	public $type = 'wpids_gradient';

	public function render_content() {
		?>
		<div class="wpids-gc-wrap">

			<?php if ( $this->label ) : ?>
			<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
			<?php endif; ?>

			<!-- ── PALETTE VIEW ── -->
			<div class="wpids-gc-palette" id="wpids-gc-palette">
				<!-- Gradient swatches rendered by JS -->
			</div>

			<!-- ── EDITOR VIEW (hidden until user clicks a swatch or +) ── -->
			<div class="wpids-gc-editor" id="wpids-gc-editor" style="display:none;">

				<div class="wpids-gc-editor-header">
					<button type="button" class="wpids-gc-back-btn" id="wpids-gc-back">
						<span class="dashicons dashicons-arrow-left-alt2"></span>
					</button>
					<input type="text" class="wpids-gc-name-input" id="wpids-gc-name"
						placeholder="<?php esc_attr_e( 'Gradient name…', 'generatepress-utility' ); ?>">
					<button type="button" class="wpids-gc-delete-btn" id="wpids-gc-delete"
						title="<?php esc_attr_e( 'Delete gradient', 'generatepress-utility' ); ?>">
						<span class="dashicons dashicons-trash"></span>
					</button>
				</div>

				<!-- Gradient live preview bar -->
				<div class="wpids-gc-preview-bar" id="wpids-gc-preview-bar"></div>

				<!-- Type + Angle row -->
				<div class="wpids-gc-row">
					<div class="wpids-gc-field">
						<label><?php esc_html_e( 'Type', 'generatepress-utility' ); ?></label>
						<select id="wpids-gc-type" class="wpids-gc-select">
							<option value="linear"><?php esc_html_e( 'Linear', 'generatepress-utility' ); ?></option>
							<option value="radial"><?php esc_html_e( 'Radial', 'generatepress-utility' ); ?></option>
							<option value="conic"><?php esc_html_e( 'Conic', 'generatepress-utility' ); ?></option>
						</select>
					</div>
					<div class="wpids-gc-field" id="wpids-gc-angle-wrap">
						<label><?php esc_html_e( 'Angle', 'generatepress-utility' ); ?></label>
						<div class="wpids-gc-angle-group">
							<input type="number" id="wpids-gc-angle" class="wpids-gc-num" min="0" max="360" value="135">
							<span>°</span>
						</div>
					</div>
				</div>

				<!-- Color stops list -->
				<div class="wpids-gc-stops" id="wpids-gc-stops">
					<!-- Each stop rendered by JS:
					     [color swatch] [────slider────] [position input] [×] -->
				</div>

				<button type="button" class="button wpids-gc-add-stop" id="wpids-gc-add-stop">
					<?php esc_html_e( '+ Add Color Stop', 'generatepress-utility' ); ?>
				</button>

				<!-- Utility class reference -->
				<div class="wpids-gc-utility-hint" id="wpids-gc-utility-hint" style="display:none;">
					<span class="dashicons dashicons-info-outline"></span>
					<span id="wpids-gc-hint-text"></span>
				</div>

				<!-- Actions -->
				<div class="wpids-gc-actions">
					<button type="button" class="button button-primary wpids-gc-save-btn" id="wpids-gc-save">
						<?php esc_html_e( 'Save', 'generatepress-utility' ); ?>
					</button>
					<span class="wpids-gc-save-status" id="wpids-gc-status"></span>
				</div>

			</div><!-- /.wpids-gc-editor -->

		<!-- ── BORDER SETTINGS (global, below palette) ── -->
		<div class="wpids-gb-settings" id="wpids-gb-settings">
			<span class="wpids-gb-settings-title"><?php esc_html_e( 'Gradient Border Settings', 'generatepress-utility' ); ?></span>

			<!-- Radius Preset -->
			<div class="wpids-gc-field">
				<label for="wpids-gb-radius-preset"><?php esc_html_e( 'Border Radius', 'generatepress-utility' ); ?></label>
				<select id="wpids-gb-radius-preset" class="wpids-gc-select">
					<option value="sharp"><?php esc_html_e( 'Sharp (0)', 'generatepress-utility' ); ?></option>
					<option value="rounded"><?php esc_html_e( 'Rounded (8px)', 'generatepress-utility' ); ?></option>
					<option value="pill"><?php esc_html_e( 'Pill (9999px)', 'generatepress-utility' ); ?></option>
					<option value="custom"><?php esc_html_e( 'Custom', 'generatepress-utility' ); ?></option>
				</select>
			</div>

			<!-- Custom Radius Inputs (hidden unless Custom selected) -->
			<div class="wpids-gb-custom-radius" id="wpids-gb-custom-radius" style="display:none;">
				<div class="wpids-gb-radius-header">
					<label><?php esc_html_e( 'Border Radius', 'generatepress-utility' ); ?></label>
					<div class="wpids-gb-radius-controls">
						<select id="wpids-gb-radius-unit" class="wpids-gb-unit-select">
							<option value="px">px</option>
							<option value="rem">rem</option>
						</select>
						<button type="button" id="wpids-gb-link-sides" class="wpids-gb-link-btn is-linked" title="<?php esc_attr_e( 'Link all sides', 'generatepress-utility' ); ?>">
							<span class="dashicons dashicons-admin-links"></span>
						</button>
					</div>
				</div>
				<div class="wpids-gb-radius-grid">
					<div class="wpids-gb-radius-field">
						<input type="number" id="wpids-gb-r-tl" class="wpids-gb-r-input" min="0" placeholder="0">
						<label><?php esc_html_e( 'Top Left', 'generatepress-utility' ); ?></label>
					</div>
					<div class="wpids-gb-radius-field">
						<input type="number" id="wpids-gb-r-tr" class="wpids-gb-r-input" min="0" placeholder="0">
						<label><?php esc_html_e( 'Top Right', 'generatepress-utility' ); ?></label>
					</div>
					<div class="wpids-gb-radius-field">
						<input type="number" id="wpids-gb-r-bl" class="wpids-gb-r-input" min="0" placeholder="0">
						<label><?php esc_html_e( 'Bottom Left', 'generatepress-utility' ); ?></label>
					</div>
					<div class="wpids-gb-radius-field">
						<input type="number" id="wpids-gb-r-br" class="wpids-gb-r-input" min="0" placeholder="0">
						<label><?php esc_html_e( 'Bottom Right', 'generatepress-utility' ); ?></label>
					</div>
				</div>
			</div>

			<!-- Border Usage Instructions -->
			<div class="wpids-gb-usage">
				<p class="wpids-gb-usage-title">
					<span class="dashicons dashicons-info-outline"></span>
					<?php esc_html_e( 'How to use gradient border:', 'generatepress-utility' ); ?>
				</p>
				<p><?php esc_html_e( 'Add one of these classes to the block\'s "Additional CSS Class" field:', 'generatepress-utility' ); ?></p>
				<div class="wpids-gb-class-list" id="wpids-gb-class-list-border">
					<span class="wpids-gc-empty"><?php esc_html_e( 'No gradients saved yet.', 'generatepress-utility' ); ?></span>
				</div>
			</div>
		</div><!-- /.wpids-gb-settings -->

		<!-- ── TEXT SETTINGS ── -->
		<div class="wpids-gb-settings" id="wpids-gt-settings">
			<span class="wpids-gb-settings-title"><?php esc_html_e( 'Gradient Text Settings', 'generatepress-utility' ); ?></span>
			<div class="wpids-gb-usage">
				<p class="wpids-gb-usage-title">
					<span class="dashicons dashicons-info-outline"></span>
					<?php esc_html_e( 'How to use gradient text:', 'generatepress-utility' ); ?>
				</p>
				<p><?php esc_html_e( 'Add one of these classes to the block\'s "Additional CSS Class" field:', 'generatepress-utility' ); ?></p>
				<div class="wpids-gb-class-list" id="wpids-gb-class-list-text">
					<span class="wpids-gc-empty"><?php esc_html_e( 'No gradients saved yet.', 'generatepress-utility' ); ?></span>
				</div>
			</div>
		</div><!-- /.wpids-gt-settings -->


		</div><!-- /.wpids-gc-wrap -->
		<?php
	}
}
