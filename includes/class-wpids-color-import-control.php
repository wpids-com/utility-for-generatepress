<?php
/**
 * Custom Customizer Control: Color Import & Expansion.
 * Renders the full import UI directly inside the Customizer panel.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPIDS_Color_Import_Control extends WP_Customize_Control {

	public $type = 'wpids-color-import';

	public function enqueue() {
		// Assets already enqueued by WPIDS_Color_Module::enqueue_customizer_assets()
	}

	public function render_content() {
		$saved = get_option( 'wpids_expanded_colors', array() );
		?>
		<div class="wpids-color-import-wrap" id="wpids-color-import-wrap">

			<!-- Header -->
			<div class="wpids-ci-header">
				<span class="wpids-ci-title">Color Import & Expansion</span>
				<span class="wpids-ci-subtitle">Import colors and generate lightness scales, color theory variants, and dark counterparts.</span>
			</div>

			<!-- Step 1: Paste Input -->
			<div class="wpids-ci-section">
				<label class="wpids-ci-label">Paste Colors</label>
				<textarea
					id="wpids-color-raw-input"
					class="wpids-ci-textarea"
					placeholder="Paste hex codes (#238b65, #0d3526), CSS variables (--accent: #b07c21;), or JSON ({&quot;red&quot;: &quot;#e95050&quot;})"
					rows="4"
				></textarea>
				<button type="button" id="wpids-parse-btn" class="wpids-ci-btn wpids-ci-btn-primary">
					Parse Colors
				</button>
				<div id="wpids-parse-error" class="wpids-ci-error" style="display:none;"></div>
			</div>

			<!-- Step 2: Saved Color Sets -->
			<div class="wpids-ci-section">
				<label class="wpids-ci-label">Active Color Sets</label>
				<div id="wpids-saved-sets">
					<?php if ( empty( $saved ) ) : ?>
						<div class="wpids-ci-empty">No color sets yet. Import colors above to get started.</div>
					<?php else : ?>
						<?php foreach ( $saved as $i => $set ) : ?>
							<?php $this->render_saved_set( $set, $i ); ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>

			<!-- Status -->
			<div id="wpids-ci-status" class="wpids-ci-status" style="display:none;"></div>

		</div>

		<!-- Mapping Wizard Modal (hidden by default) -->
		<div id="wpids-mapping-modal" class="wpids-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="wpids-modal-title">
			<div class="wpids-modal-box">

				<div class="wpids-modal-header">
					<h3 id="wpids-modal-title">Map Imported Colors to Variables</h3>
					<button type="button" id="wpids-modal-close" class="wpids-modal-close" aria-label="Close">&times;</button>
				</div>

				<div class="wpids-modal-body">

					<!-- Color Mapping Table -->
					<div class="wpids-mapping-intro">
						Assign each imported color to an existing variable or create a new one.
					</div>
					<div id="wpids-mapping-rows" class="wpids-mapping-table"></div>

					<!-- Global Options -->
					<div class="wpids-modal-options">
						<label class="wpids-ci-label">Generate Derivatives</label>
						<div class="wpids-options-grid">
							<label class="wpids-option-check">
								<input type="checkbox" id="wpids-opt-scale" checked>
								Lightness Scale (–10 to –90)
							</label>
							<label class="wpids-option-check">
								<input type="checkbox" id="wpids-opt-complementary">
								Complementary (–comp)
							</label>
							<label class="wpids-option-check">
								<input type="checkbox" id="wpids-opt-triadic">
								Triadic (–tri-a, –tri-b)
							</label>
							<label class="wpids-option-check">
								<input type="checkbox" id="wpids-opt-analogous">
								Analogous (–ana-a, –ana-b)
							</label>
							<label class="wpids-option-check">
								<input type="checkbox" id="wpids-opt-split-comp">
								Split-Complementary (–sc-a, –sc-b)
							</label>
							<label class="wpids-option-check">
								<input type="checkbox" id="wpids-opt-dark" checked>
								Auto Dark Counterpart
							</label>
						</div>
						<label class="wpids-option-check wpids-dark-sync-row">
							<input type="checkbox" id="wpids-opt-sync-dark" checked>
							<strong>Sync dark counterparts to Dark Mode panel</strong>
						</label>
					</div>

				</div>

				<div class="wpids-modal-footer">
					<button type="button" id="wpids-modal-cancel" class="wpids-ci-btn wpids-ci-btn-ghost">Cancel</button>
					<button type="button" id="wpids-modal-apply" class="wpids-ci-btn wpids-ci-btn-primary">Import & Apply</button>
				</div>

			</div>
		</div>

		<!-- Preview Modal -->
		<div id="wpids-preview-modal" class="wpids-modal-overlay" style="display:none;" role="dialog" aria-modal="true">
			<div class="wpids-modal-box wpids-preview-box">
				<div class="wpids-modal-header">
					<h3>CSS Variables Preview</h3>
					<button type="button" id="wpids-preview-close" class="wpids-modal-close">&times;</button>
				</div>
				<div class="wpids-modal-body">
					<div id="wpids-preview-content" class="wpids-preview-content"></div>
				</div>
				<div class="wpids-modal-footer">
					<button type="button" id="wpids-preview-copy" class="wpids-ci-btn wpids-ci-btn-ghost">Copy CSS</button>
					<button type="button" id="wpids-preview-close-btn" class="wpids-ci-btn wpids-ci-btn-primary">Done</button>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_saved_set( $set, $index ) {
		$slug      = $set['slug'] ?? '';
		$hex       = $set['hex'] ?? '#000000';
		$var_count = count( $set['variables'] ?? array() );
		?>
		<div class="wpids-saved-set" data-index="<?php echo esc_attr( $index ); ?>">
			<div class="wpids-set-swatch" style="background-color:<?php echo esc_attr( $hex ); ?>;"></div>
			<div class="wpids-set-info">
				<span class="wpids-set-slug">--<?php echo esc_html( $slug ); ?></span>
				<span class="wpids-set-count"><?php echo esc_html( $var_count ); ?> variables</span>
			</div>
			<div class="wpids-set-actions">
				<button type="button" class="wpids-set-preview" data-index="<?php echo esc_attr( $index ); ?>" title="Preview variables">&#128065;</button>
				<button type="button" class="wpids-set-delete" data-index="<?php echo esc_attr( $index ); ?>" title="Remove">&times;</button>
			</div>
		</div>
		<?php
	}
}
