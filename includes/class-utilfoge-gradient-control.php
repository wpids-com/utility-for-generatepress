<?php
/**
 * Gradient Control — GP React Color style UI.
 *
 * Renders the palette grid and inline editor shell.
 * All dynamic rendering is handled by utilfoge-gradient-module.js.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UTILFOGE_Gradient_Control extends WP_Customize_Control {

	public $type = 'utilfoge_gradient';

	public function render_content() {
		?>
		<div class="utilfoge-gc-wrap">

			<?php if ( $this->label ) : ?>
			<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
			<?php endif; ?>

			<!-- ── PALETTE VIEW ── -->
			<div class="utilfoge-gc-palette" id="utilfoge-gc-palette">
				<!-- Gradient swatches rendered by JS -->
			</div>

			<!-- ── EDITOR VIEW (hidden until user clicks a swatch or +) ── -->
			<div class="utilfoge-gc-editor" id="utilfoge-gc-editor" style="display:none;">

				<div class="utilfoge-gc-editor-header">
					<button type="button" class="utilfoge-gc-back-btn" id="utilfoge-gc-back">
						<span class="dashicons dashicons-arrow-left-alt2"></span>
					</button>
					<input type="text" class="utilfoge-gc-name-input" id="utilfoge-gc-name"
						placeholder="<?php esc_attr_e( 'Gradient name…', 'utility-for-generatepress' ); ?>">
					<button type="button" class="utilfoge-gc-delete-btn" id="utilfoge-gc-delete"
						title="<?php esc_attr_e( 'Delete gradient', 'utility-for-generatepress' ); ?>">
						<span class="dashicons dashicons-trash"></span>
					</button>
				</div>

				<!-- Gradient live preview bar -->
				<div class="utilfoge-gc-preview-bar" id="utilfoge-gc-preview-bar"></div>

				<!-- Type + Angle row -->
				<div class="utilfoge-gc-row">
					<div class="utilfoge-gc-field">
						<label><?php esc_html_e( 'Type', 'utility-for-generatepress' ); ?></label>
						<select id="utilfoge-gc-type" class="utilfoge-gc-select">
							<option value="linear"><?php esc_html_e( 'Linear', 'utility-for-generatepress' ); ?></option>
							<option value="radial"><?php esc_html_e( 'Radial', 'utility-for-generatepress' ); ?></option>
							<option value="conic"><?php esc_html_e( 'Conic', 'utility-for-generatepress' ); ?></option>
						</select>
					</div>
					<div class="utilfoge-gc-field" id="utilfoge-gc-angle-wrap">
						<label><?php esc_html_e( 'Angle', 'utility-for-generatepress' ); ?></label>
						<div class="utilfoge-gc-angle-group">
							<input type="number" id="utilfoge-gc-angle" class="utilfoge-gc-num" min="0" max="360" value="135">
							<span>°</span>
						</div>
					</div>
				</div>

				<!-- Color stops list -->
				<div class="utilfoge-gc-stops" id="utilfoge-gc-stops">
					<!-- Each stop rendered by JS:
					     [color swatch] [────slider────] [position input] [×] -->
				</div>

				<button type="button" class="button utilfoge-gc-add-stop" id="utilfoge-gc-add-stop">
					<?php esc_html_e( '+ Add Color Stop', 'utility-for-generatepress' ); ?>
				</button>

				<!-- Utility class reference -->
				<div class="utilfoge-gc-utility-hint" id="utilfoge-gc-utility-hint" style="display:none;">
					<span class="dashicons dashicons-info-outline"></span>
					<span id="utilfoge-gc-hint-text"></span>
				</div>

				<!-- Actions -->
				<div class="utilfoge-gc-actions">
					<button type="button" class="button button-primary utilfoge-gc-save-btn" id="utilfoge-gc-save">
						<?php esc_html_e( 'Save', 'utility-for-generatepress' ); ?>
					</button>
					<span class="utilfoge-gc-save-status" id="utilfoge-gc-status"></span>
				</div>

			</div><!-- /.utilfoge-gc-editor -->

		<!-- ── BORDER SETTINGS (global, below palette) ── -->
		<div class="utilfoge-gb-settings" id="utilfoge-gb-settings">
			<span class="utilfoge-gb-settings-title"><?php esc_html_e( 'Gradient Border Settings', 'utility-for-generatepress' ); ?></span>

			<!-- Radius Preset -->
			<div class="utilfoge-gc-field">
				<label for="utilfoge-gb-radius-preset"><?php esc_html_e( 'Border Radius', 'utility-for-generatepress' ); ?></label>
				<select id="utilfoge-gb-radius-preset" class="utilfoge-gc-select">
					<option value="sharp"><?php esc_html_e( 'Sharp (0)', 'utility-for-generatepress' ); ?></option>
					<option value="rounded"><?php esc_html_e( 'Rounded (8px)', 'utility-for-generatepress' ); ?></option>
					<option value="pill"><?php esc_html_e( 'Pill (9999px)', 'utility-for-generatepress' ); ?></option>
					<option value="custom"><?php esc_html_e( 'Custom', 'utility-for-generatepress' ); ?></option>
				</select>
			</div>

			<!-- Custom Radius Inputs (hidden unless Custom selected) -->
			<div class="utilfoge-gb-custom-radius" id="utilfoge-gb-custom-radius" style="display:none;">
				<div class="utilfoge-gb-radius-header">
					<label><?php esc_html_e( 'Border Radius', 'utility-for-generatepress' ); ?></label>
					<div class="utilfoge-gb-radius-controls">
						<select id="utilfoge-gb-radius-unit" class="utilfoge-gb-unit-select">
							<option value="px">px</option>
							<option value="rem">rem</option>
						</select>
						<button type="button" id="utilfoge-gb-link-sides" class="utilfoge-gb-link-btn is-linked" title="<?php esc_attr_e( 'Link all sides', 'utility-for-generatepress' ); ?>">
							<span class="dashicons dashicons-admin-links"></span>
						</button>
					</div>
				</div>
				<div class="utilfoge-gb-radius-grid">
					<div class="utilfoge-gb-radius-field">
						<input type="number" id="utilfoge-gb-r-tl" class="utilfoge-gb-r-input" min="0" placeholder="0">
						<label><?php esc_html_e( 'Top Left', 'utility-for-generatepress' ); ?></label>
					</div>
					<div class="utilfoge-gb-radius-field">
						<input type="number" id="utilfoge-gb-r-tr" class="utilfoge-gb-r-input" min="0" placeholder="0">
						<label><?php esc_html_e( 'Top Right', 'utility-for-generatepress' ); ?></label>
					</div>
					<div class="utilfoge-gb-radius-field">
						<input type="number" id="utilfoge-gb-r-bl" class="utilfoge-gb-r-input" min="0" placeholder="0">
						<label><?php esc_html_e( 'Bottom Left', 'utility-for-generatepress' ); ?></label>
					</div>
					<div class="utilfoge-gb-radius-field">
						<input type="number" id="utilfoge-gb-r-br" class="utilfoge-gb-r-input" min="0" placeholder="0">
						<label><?php esc_html_e( 'Bottom Right', 'utility-for-generatepress' ); ?></label>
					</div>
				</div>
			</div>

			<!-- Border Usage Instructions -->
			<div class="utilfoge-gb-usage">
				<p class="utilfoge-gb-usage-title">
					<span class="dashicons dashicons-info-outline"></span>
					<?php esc_html_e( 'How to use gradient border:', 'utility-for-generatepress' ); ?>
				</p>
				<p><?php esc_html_e( 'Add one of these classes to the block\'s "Additional CSS Class" field:', 'utility-for-generatepress' ); ?></p>
				<div class="utilfoge-gb-class-list" id="utilfoge-gb-class-list-border">
					<span class="utilfoge-gc-empty"><?php esc_html_e( 'No gradients saved yet.', 'utility-for-generatepress' ); ?></span>
				</div>
			</div>
		</div><!-- /.utilfoge-gb-settings -->

		<!-- ── TEXT SETTINGS ── -->
		<div class="utilfoge-gb-settings" id="utilfoge-gt-settings">
			<span class="utilfoge-gb-settings-title"><?php esc_html_e( 'Gradient Text Settings', 'utility-for-generatepress' ); ?></span>
			<div class="utilfoge-gb-usage">
				<p class="utilfoge-gb-usage-title">
					<span class="dashicons dashicons-info-outline"></span>
					<?php esc_html_e( 'How to use gradient text:', 'utility-for-generatepress' ); ?>
				</p>
				<p><?php esc_html_e( 'Add one of these classes to the block\'s "Additional CSS Class" field:', 'utility-for-generatepress' ); ?></p>
				<div class="utilfoge-gb-class-list" id="utilfoge-gb-class-list-text">
					<span class="utilfoge-gc-empty"><?php esc_html_e( 'No gradients saved yet.', 'utility-for-generatepress' ); ?></span>
				</div>
			</div>
		</div><!-- /.utilfoge-gt-settings -->


		</div><!-- /.utilfoge-gc-wrap -->
		<?php
	}
}
