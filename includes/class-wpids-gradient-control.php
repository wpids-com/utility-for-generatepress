<?php
/**
 * Custom Customizer Control: Gradient Variables Manager.
 * Renders the gradient list + "Add Gradient" button in Customizer.
 * The full gradient builder opens in a modal.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPIDS_Gradient_Control extends WP_Customize_Control {

	public $type = 'wpids-gradient';

	public function render_content() {
		$saved = get_option( 'wpids_gradient_variables', array() );
		?>
		<div class="wpids-grad-wrap" id="wpids-grad-wrap">

			<div class="wpids-grad-header">
				<span class="wpids-ci-title">Gradient Variables</span>
				<span class="wpids-ci-subtitle">Create CSS gradient variables that can be used anywhere via <code>var(--your-gradient)</code>. An entry-point shortcut also appears in the GP Global Colors panel.</span>
			</div>

			<!-- Saved Gradients List -->
			<div class="wpids-ci-section">
				<div id="wpids-grad-list">
					<?php if ( empty( $saved ) ) : ?>
						<div class="wpids-ci-empty">No gradient variables yet. Click the button below to create one.</div>
					<?php else : ?>
						<?php foreach ( $saved as $i => $g ) : ?>
							<?php $this->render_saved_gradient( $g, $i ); ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>

			<button type="button" id="wpids-grad-add-btn" class="wpids-ci-btn wpids-ci-btn-primary">
				+ Add Gradient Variable
			</button>

			<div id="wpids-grad-status" class="wpids-ci-status" style="display:none;"></div>

		</div>

		<!-- ═══════════════════════════════════════════════
		     GRADIENT BUILDER MODAL
		     ═══════════════════════════════════════════════ -->
		<div id="wpids-grad-modal" class="wpids-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="wpids-grad-modal-title">
			<div class="wpids-modal-box wpids-grad-modal-box">

				<div class="wpids-modal-header">
					<h3 id="wpids-grad-modal-title">Gradient Builder</h3>
					<button type="button" id="wpids-grad-modal-close" class="wpids-modal-close">&times;</button>
				</div>

				<div class="wpids-modal-body wpids-grad-body">

					<!-- Left: Controls -->
					<div class="wpids-grad-controls">

						<!-- Name & Slug -->
						<div class="wpids-grad-field-row">
							<div class="wpids-grad-field">
								<label>Name</label>
								<input type="text" id="wpids-grad-name" placeholder="Hero Gradient" />
							</div>
							<div class="wpids-grad-field">
								<label>CSS Variable (slug)</label>
								<div class="wpids-slug-wrap">
									<span class="wpids-slug-prefix">--</span>
									<input type="text" id="wpids-grad-slug" placeholder="hero-gradient" />
								</div>
							</div>
						</div>

						<!-- Type + Angle/Shape -->
						<div class="wpids-grad-field-row">
							<div class="wpids-grad-field">
								<label>Type</label>
								<select id="wpids-grad-type">
									<option value="linear">Linear</option>
									<option value="radial">Radial</option>
									<option value="conic">Conic</option>
								</select>
							</div>
							<div class="wpids-grad-field" id="wpids-grad-angle-wrap">
								<label>Angle (deg)</label>
								<div class="wpids-angle-row">
									<input type="range" id="wpids-grad-angle-range" min="0" max="360" value="135" />
									<input type="number" id="wpids-grad-angle-num" min="0" max="360" value="135" />
								</div>
							</div>
							<div class="wpids-grad-field" id="wpids-grad-radial-wrap" style="display:none;">
								<label>Shape</label>
								<select id="wpids-grad-shape">
									<option value="ellipse">Ellipse</option>
									<option value="circle">Circle</option>
								</select>
							</div>
							<div class="wpids-grad-field" id="wpids-grad-at-wrap" style="display:none;">
								<label>Position</label>
								<select id="wpids-grad-at">
									<option value="center">Center</option>
									<option value="top">Top</option>
									<option value="bottom">Bottom</option>
									<option value="left">Left</option>
									<option value="right">Right</option>
									<option value="top left">Top Left</option>
									<option value="top right">Top Right</option>
									<option value="bottom left">Bottom Left</option>
									<option value="bottom right">Bottom Right</option>
								</select>
							</div>
						</div>

						<!-- Color Stops -->
						<div class="wpids-grad-field">
							<label>Color Stops</label>
							<div id="wpids-grad-stops" class="wpids-grad-stops-list">
								<!-- Stops rendered by JS -->
							</div>
							<button type="button" id="wpids-grad-add-stop" class="wpids-ci-btn wpids-ci-btn-ghost wpids-grad-add-stop-btn">
								+ Add Stop
							</button>
						</div>

						<!-- Dark Mode -->
						<div class="wpids-grad-field wpids-grad-dark-section">
							<label>Dark Mode</label>
							<label class="wpids-option-check">
								<input type="checkbox" id="wpids-grad-dark-auto" checked>
								Auto-compute dark gradient (per-stop dark counterpart)
							</label>
							<div id="wpids-grad-dark-preview-wrap" style="margin-top:8px; display:none;">
								<div class="wpids-grad-dark-label">Dark version preview:</div>
								<div id="wpids-grad-dark-preview" class="wpids-grad-preview-strip"></div>
							</div>
						</div>

					</div>

					<!-- Right: Live Preview -->
					<div class="wpids-grad-preview-panel">
						<div class="wpids-grad-preview-label">Live Preview</div>
						<div id="wpids-grad-preview" class="wpids-grad-preview-strip wpids-grad-preview-large"></div>
						<div class="wpids-grad-preview-css-wrap">
							<label>CSS Output</label>
							<code id="wpids-grad-css-output" class="wpids-grad-css-output"></code>
						</div>
					</div>

				</div>

				<div class="wpids-modal-footer">
					<button type="button" id="wpids-grad-cancel" class="wpids-ci-btn wpids-ci-btn-ghost">Cancel</button>
					<button type="button" id="wpids-grad-save" class="wpids-ci-btn wpids-ci-btn-primary">Save Gradient</button>
				</div>

			</div>
		</div>
		<?php
	}

	private function render_saved_gradient( $g, $index ) {
		$slug = $g['slug'] ?? '';
		$name = $g['name'] ?? $slug;
		$type = ucfirst( $g['type'] ?? 'linear' );

		// Build preview CSS
		$preview_css = WPIDS_Gradient_Module::build_gradient_css( $g );
		?>
		<div class="wpids-grad-saved-row" data-index="<?php echo esc_attr( $index ); ?>">
			<div class="wpids-grad-swatch" style="background:<?php echo esc_attr( $preview_css ); ?>;"></div>
			<div class="wpids-set-info">
				<span class="wpids-set-slug">--<?php echo esc_html( $slug ); ?></span>
				<span class="wpids-set-count"><?php echo esc_html( $name ); ?> &middot; <?php echo esc_html( $type ); ?></span>
			</div>
			<div class="wpids-set-actions">
				<button type="button" class="wpids-grad-edit" data-index="<?php echo esc_attr( $index ); ?>" title="Edit">&#9998;</button>
				<button type="button" class="wpids-grad-delete" data-index="<?php echo esc_attr( $index ); ?>" title="Delete">&times;</button>
			</div>
		</div>
		<?php
	}
}
