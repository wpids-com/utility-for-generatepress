<?php
/**
 * Gradient Control — GP React Color style UI.
 *
 * Renders the palette grid and inline editor shell.
 * All dynamic rendering is handled by utilgp-gradient-module.js.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UTILGP_Gradient_Control extends WP_Customize_Control {

	public $type = 'utilgp_gradient';

	public function render_content() {
		?>
		<div class="utilgp-gc-wrap">

			<?php if ( $this->label ) : ?>
			<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
			<?php endif; ?>

			<!-- ── PALETTE VIEW ── -->
			<div class="utilgp-gc-palette" id="utilgp-gc-palette">
				<!-- Gradient swatches rendered by JS -->
			</div>

			<!-- ── EDITOR VIEW (hidden until user clicks a swatch or +) ── -->
			<div class="utilgp-gc-editor" id="utilgp-gc-editor" style="display:none;">

				<div class="utilgp-gc-editor-header">
					<button type="button" class="utilgp-gc-back-btn" id="utilgp-gc-back">
						<span class="dashicons dashicons-arrow-left-alt2"></span>
					</button>
					<input type="text" class="utilgp-gc-name-input" id="utilgp-gc-name"
						placeholder="<?php esc_attr_e( 'Gradient name…', 'utility-for-generatepress' ); ?>">
					<button type="button" class="utilgp-gc-delete-btn" id="utilgp-gc-delete"
						title="<?php esc_attr_e( 'Delete gradient', 'utility-for-generatepress' ); ?>">
						<span class="dashicons dashicons-trash"></span>
					</button>
				</div>

				<!-- Gradient live preview bar -->
				<div class="utilgp-gc-preview-bar" id="utilgp-gc-preview-bar"></div>

				<!-- Type + Angle row -->
				<div class="utilgp-gc-row">
					<div class="utilgp-gc-field">
						<label><?php esc_html_e( 'Type', 'utility-for-generatepress' ); ?></label>
						<select id="utilgp-gc-type" class="utilgp-gc-select">
							<option value="linear"><?php esc_html_e( 'Linear', 'utility-for-generatepress' ); ?></option>
							<option value="radial"><?php esc_html_e( 'Radial', 'utility-for-generatepress' ); ?></option>
							<option value="conic"><?php esc_html_e( 'Conic', 'utility-for-generatepress' ); ?></option>
						</select>
					</div>
					<div class="utilgp-gc-field" id="utilgp-gc-angle-wrap">
						<label><?php esc_html_e( 'Angle', 'utility-for-generatepress' ); ?></label>
						<div class="utilgp-gc-angle-group">
							<input type="number" id="utilgp-gc-angle" class="utilgp-gc-num" min="0" max="360" value="135">
							<span>°</span>
						</div>
					</div>
				</div>

				<!-- Color stops list -->
				<div class="utilgp-gc-stops" id="utilgp-gc-stops">
					<!-- Each stop rendered by JS:
					     [color swatch] [────slider────] [position input] [×] -->
				</div>

				<button type="button" class="button utilgp-gc-add-stop" id="utilgp-gc-add-stop">
					<?php esc_html_e( '+ Add Color Stop', 'utility-for-generatepress' ); ?>
				</button>

				<!-- Utility class reference -->
				<div class="utilgp-gc-utility-hint" id="utilgp-gc-utility-hint" style="display:none;">
					<span class="dashicons dashicons-info-outline"></span>
					<span id="utilgp-gc-hint-text"></span>
				</div>

				<!-- Actions -->
				<div class="utilgp-gc-actions">
					<button type="button" class="button button-primary utilgp-gc-save-btn" id="utilgp-gc-save">
						<?php esc_html_e( 'Save', 'utility-for-generatepress' ); ?>
					</button>
					<span class="utilgp-gc-save-status" id="utilgp-gc-status"></span>
				</div>

			</div><!-- /.utilgp-gc-editor -->

		<!-- ── BORDER SETTINGS (global, below palette) ── -->
		<div class="utilgp-gb-settings" id="utilgp-gb-settings">
			<span class="utilgp-gb-settings-title"><?php esc_html_e( 'Gradient Border Settings', 'utility-for-generatepress' ); ?></span>

			<!-- Radius Preset -->
			<div class="utilgp-gc-field">
				<label for="utilgp-gb-radius-preset"><?php esc_html_e( 'Border Radius', 'utility-for-generatepress' ); ?></label>
				<select id="utilgp-gb-radius-preset" class="utilgp-gc-select">
					<option value="sharp"><?php esc_html_e( 'Sharp (0)', 'utility-for-generatepress' ); ?></option>
					<option value="rounded"><?php esc_html_e( 'Rounded (8px)', 'utility-for-generatepress' ); ?></option>
					<option value="pill"><?php esc_html_e( 'Pill (9999px)', 'utility-for-generatepress' ); ?></option>
					<option value="custom"><?php esc_html_e( 'Custom', 'utility-for-generatepress' ); ?></option>
				</select>
			</div>

			<!-- Custom Radius Inputs (hidden unless Custom selected) -->
			<div class="utilgp-gb-custom-radius" id="utilgp-gb-custom-radius" style="display:none;">
				<div class="utilgp-gb-radius-header">
					<label><?php esc_html_e( 'Border Radius', 'utility-for-generatepress' ); ?></label>
					<div class="utilgp-gb-radius-controls">
						<select id="utilgp-gb-radius-unit" class="utilgp-gb-unit-select">
							<option value="px">px</option>
							<option value="rem">rem</option>
						</select>
						<button type="button" id="utilgp-gb-link-sides" class="utilgp-gb-link-btn is-linked" title="<?php esc_attr_e( 'Link all sides', 'utility-for-generatepress' ); ?>">
							<span class="dashicons dashicons-admin-links"></span>
						</button>
					</div>
				</div>
				<div class="utilgp-gb-radius-grid">
					<div class="utilgp-gb-radius-field">
						<input type="number" id="utilgp-gb-r-tl" class="utilgp-gb-r-input" min="0" placeholder="0">
						<label><?php esc_html_e( 'Top Left', 'utility-for-generatepress' ); ?></label>
					</div>
					<div class="utilgp-gb-radius-field">
						<input type="number" id="utilgp-gb-r-tr" class="utilgp-gb-r-input" min="0" placeholder="0">
						<label><?php esc_html_e( 'Top Right', 'utility-for-generatepress' ); ?></label>
					</div>
					<div class="utilgp-gb-radius-field">
						<input type="number" id="utilgp-gb-r-bl" class="utilgp-gb-r-input" min="0" placeholder="0">
						<label><?php esc_html_e( 'Bottom Left', 'utility-for-generatepress' ); ?></label>
					</div>
					<div class="utilgp-gb-radius-field">
						<input type="number" id="utilgp-gb-r-br" class="utilgp-gb-r-input" min="0" placeholder="0">
						<label><?php esc_html_e( 'Bottom Right', 'utility-for-generatepress' ); ?></label>
					</div>
				</div>
			</div>

			<!-- Border Usage Instructions -->
			<div class="utilgp-gb-usage">
				<p class="utilgp-gb-usage-title">
					<span class="dashicons dashicons-info-outline"></span>
					<?php esc_html_e( 'How to use gradient border:', 'utility-for-generatepress' ); ?>
				</p>
				<p><?php esc_html_e( 'Add one of these classes to the block\'s "Additional CSS Class" field:', 'utility-for-generatepress' ); ?></p>
				<div class="utilgp-gb-class-list" id="utilgp-gb-class-list-border">
					<span class="utilgp-gc-empty"><?php esc_html_e( 'No gradients saved yet.', 'utility-for-generatepress' ); ?></span>
				</div>
			</div>
		</div><!-- /.utilgp-gb-settings -->

		<!-- ── TEXT SETTINGS ── -->
		<div class="utilgp-gb-settings" id="utilgp-gt-settings">
			<span class="utilgp-gb-settings-title"><?php esc_html_e( 'Gradient Text Settings', 'utility-for-generatepress' ); ?></span>
			<div class="utilgp-gb-usage">
				<p class="utilgp-gb-usage-title">
					<span class="dashicons dashicons-info-outline"></span>
					<?php esc_html_e( 'How to use gradient text:', 'utility-for-generatepress' ); ?>
				</p>
				<p><?php esc_html_e( 'Add one of these classes to the block\'s "Additional CSS Class" field:', 'utility-for-generatepress' ); ?></p>
				<div class="utilgp-gb-class-list" id="utilgp-gb-class-list-text">
					<span class="utilgp-gc-empty"><?php esc_html_e( 'No gradients saved yet.', 'utility-for-generatepress' ); ?></span>
				</div>
			</div>
		</div><!-- /.utilgp-gt-settings -->


		</div><!-- /.utilgp-gc-wrap -->
		<?php
	}
}
