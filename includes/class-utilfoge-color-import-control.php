<?php
/**
 * Custom Customizer Control: Color Import & Expansion.
 * Renders the full import UI directly inside the Customizer panel.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UTILFOGE_Color_Import_Control extends WP_Customize_Control {

	public $type = 'utilfoge-color-import';

	public function enqueue() {
		// Assets already enqueued by UTILFOGE_Color_Module::enqueue_customizer_assets()
	}

	public function render_content() {
		$saved = get_option( 'utilfoge_expanded_colors', array() );
		?>
		<div class="utilfoge-color-import-wrap" id="utilfoge-color-import-wrap">

			<!-- Header -->
			<div class="utilfoge-ci-header">
				<span class="utilfoge-ci-title">Color Import & Expansion</span>
				<span class="utilfoge-ci-subtitle">Import colors and generate lightness scales, color theory variants, and dark counterparts.</span>
			</div>

			<!-- Step 1: Paste Input -->
			<div class="utilfoge-ci-section">
				<label class="utilfoge-ci-label">Paste Colors</label>
				<textarea
					id="utilfoge-color-raw-input"
					class="utilfoge-ci-textarea"
					placeholder="Paste hex codes (#238b65, #0d3526), CSS variables (--accent: #b07c21;), or JSON ({&quot;red&quot;: &quot;#e95050&quot;})"
					rows="4"
				></textarea>
				<button type="button" id="utilfoge-parse-btn" class="utilfoge-ci-btn utilfoge-ci-btn-primary">
					Parse Colors
				</button>
				<div id="utilfoge-parse-error" class="utilfoge-ci-error" style="display:none;"></div>
			</div>

			<!-- Step 2: Active Color Palette (Synced with GP) -->
			<div class="utilfoge-ci-section">
				<label class="utilfoge-ci-label">GP Global Palette</label>
				<div id="utilfoge-color-palette" class="utilfoge-gc-palette">
					<!-- Swatches will be rendered here by JS -->
				</div>
			</div>

			<!-- Dark Mode Auto-Sync Status Banner -->
			<?php if ( class_exists( 'utilfoge_Dark_Mode' ) ) : ?>
			<div class="utilfoge-dark-sync-banner">
				<span class="utilfoge-dark-sync-icon">&#9790;</span>
				<span>Dark Mode auto-sync is <strong>active</strong>. Math-derived dark colors override defaults automatically.</span>
				<button type="button" id="utilfoge-resync-dark-btn" class="button" style="margin:0;padding:0 10px;height:24px;line-height:22px;font-size:11px;">Re-sync All</button>
			</div>
			<?php else : ?>
			<div class="utilfoge-dark-sync-banner utilfoge-dark-sync-off">
				<span class="utilfoge-dark-sync-icon">&#9790;</span>
				<span>Dark Mode module inactive. Dark colors managed manually.</span>
			</div>
			<?php endif; ?>

			<!-- Status -->
			<div id="utilfoge-ci-status" class="utilfoge-ci-status" style="display:none;"></div>

		</div>

		<!-- Mapping Wizard Modal (hidden by default) -->
		<div id="utilfoge-mapping-modal" class="utilfoge-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="utilfoge-modal-title">
			<div class="utilfoge-modal-box">

				<div class="utilfoge-modal-header">
					<h3 id="utilfoge-modal-title">Map Imported Colors to Variables</h3>
					<button type="button" id="utilfoge-modal-close" class="utilfoge-modal-close" aria-label="Close">&times;</button>
				</div>

				<div class="utilfoge-modal-body">

					<!-- Color Mapping Table -->
					<div class="utilfoge-mapping-intro">
						Assign each imported color to an existing variable or create a new one.
					</div>
					<div id="utilfoge-mapping-rows" class="utilfoge-mapping-table"></div>

					<!-- Global Options -->
					<div class="utilfoge-modal-options">
						<label class="utilfoge-ci-label">Generate Derivatives</label>
						<div class="utilfoge-options-grid">
							<label class="utilfoge-option-check">
								<input type="checkbox" id="utilfoge-opt-scale" checked>
								Lightness Scale (–10 to –90)
							</label>
							<label class="utilfoge-option-check">
								<input type="checkbox" id="utilfoge-opt-complementary">
								Complementary (–comp)
							</label>
							<label class="utilfoge-option-check">
								<input type="checkbox" id="utilfoge-opt-triadic">
								Triadic (–tri-a, –tri-b)
							</label>
							<label class="utilfoge-option-check">
								<input type="checkbox" id="utilfoge-opt-analogous">
								Analogous (–ana-a, –ana-b)
							</label>
							<label class="utilfoge-option-check">
								<input type="checkbox" id="utilfoge-opt-split-comp">
								Split-Complementary (–sc-a, –sc-b)
							</label>
							<label class="utilfoge-option-check">
								<input type="checkbox" id="utilfoge-opt-dark" checked>
								Auto Dark Counterpart
							</label>
						</div>
						<label class="utilfoge-option-check utilfoge-dark-sync-row">
							<input type="checkbox" id="utilfoge-opt-sync-dark" checked>
							<strong>Sync dark counterparts to Dark Mode panel</strong>
						</label>
					</div>

				</div>

				<div class="utilfoge-modal-footer">
					<button type="button" id="utilfoge-modal-cancel" class="utilfoge-ci-btn utilfoge-ci-btn-ghost">Cancel</button>
					<button type="button" id="utilfoge-modal-apply" class="utilfoge-ci-btn utilfoge-ci-btn-primary">Import & Apply</button>
				</div>

			</div>
		</div>

		<!-- Massive Menu Editor Modal -->
		<div id="utilfoge-editor-modal" class="utilfoge-modal-overlay" style="display:none;" role="dialog" aria-modal="true">
			<div class="utilfoge-modal-box utilfoge-editor-box">
				<div class="utilfoge-modal-header">
					<h3>Edit Color Variables</h3>
					<button type="button" id="utilfoge-editor-close" class="utilfoge-modal-close" aria-label="Close">&times;</button>
				</div>

				<div class="utilfoge-modal-body">
					<div class="utilfoge-editor-main">
						<div class="utilfoge-editor-fields">
							<div class="utilfoge-editor-field">
								<label class="utilfoge-ci-label">Variable Name</label>
								<input type="text" id="utilfoge-edit-name" class="utilfoge-ci-input" placeholder="e.g. accent-color" />
							</div>
							
							<div class="utilfoge-editor-field" style="margin-top: 15px;">
								<label class="utilfoge-ci-label">Color Value</label>
								<div id="utilfoge-react-color-picker-root" class="utilfoge-picker-container"></div>
								<input type="hidden" id="utilfoge-edit-hex" />
							</div>
						</div>

						<div class="utilfoge-editor-sidebar">
							<!-- Math Options -->
							<div class="utilfoge-modal-options">
								<label class="utilfoge-ci-label">Generate Derivatives</label>
								<div class="utilfoge-options-list">
									<label class="utilfoge-option-check"><input type="checkbox" id="utilfoge-edit-opt-scale"> Lightness Scale</label>
									<label class="utilfoge-option-check"><input type="checkbox" id="utilfoge-edit-opt-complementary"> Complementary</label>
									<label class="utilfoge-option-check"><input type="checkbox" id="utilfoge-edit-opt-triadic"> Triadic Variants</label>
									<label class="utilfoge-option-check"><input type="checkbox" id="utilfoge-edit-opt-analogous"> Analogous Variants</label>
									<label class="utilfoge-option-check"><input type="checkbox" id="utilfoge-edit-opt-split-comp"> Split-Complementary</label>
									<label class="utilfoge-option-check"><input type="checkbox" id="utilfoge-edit-opt-dark"> Auto Dark Counterpart</label>
								</div>
							</div>

							<div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #f0f0f1;">
								<button type="button" id="utilfoge-edit-delete" class="utilfoge-ci-btn utilfoge-ci-btn-danger" style="width:100%; justify-content:center;">Delete Variable</button>
							</div>
						</div>
					</div>

					<!-- Live Preview / Variables List -->
					<div class="utilfoge-editor-preview-wrap" style="margin-top:20px; border-top: 1px solid #e2e4e7; padding-top: 15px;">
						<label class="utilfoge-ci-label">Generated Variables (CSS Root)</label>
						<div id="utilfoge-editor-preview-list" class="utilfoge-editor-preview-list"></div>
					</div>
				</div>

				<div class="utilfoge-modal-footer">
					<span id="utilfoge-editor-status" style="font-size:11px;color:#00a32a;margin-right:auto;display:none;">Saved!</span>
					<button type="button" id="utilfoge-editor-cancel" class="utilfoge-ci-btn utilfoge-ci-btn-ghost">Close</button>
					<button type="button" id="utilfoge-editor-apply" class="utilfoge-ci-btn utilfoge-ci-btn-primary">Apply & Save</button>
				</div>
			</div>
		</div>
		<?php
	}
}
