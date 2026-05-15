<?php
/**
 * Custom Customizer Control: Color Import & Expansion.
 * Renders the full import UI directly inside the Customizer panel.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UTILGP_Color_Import_Control extends WP_Customize_Control {

	public $type = 'utilgp-color-import';

	public function enqueue() {
		// Assets already enqueued by UTILGP_Color_Module::enqueue_customizer_assets()
	}

	public function render_content() {
		$saved = get_option( 'utilgp_expanded_colors', array() );
		?>
		<div class="utilgp-color-import-wrap" id="utilgp-color-import-wrap">

			<!-- Header -->
			<div class="utilgp-ci-header">
				<span class="utilgp-ci-title">Color Import & Expansion</span>
				<span class="utilgp-ci-subtitle">Import colors and generate lightness scales, color theory variants, and dark counterparts.</span>
			</div>

			<!-- Step 1: Paste Input -->
			<div class="utilgp-ci-section">
				<label class="utilgp-ci-label">Paste Colors</label>
				<textarea
					id="utilgp-color-raw-input"
					class="utilgp-ci-textarea"
					placeholder="Paste hex codes (#238b65, #0d3526), CSS variables (--accent: #b07c21;), or JSON ({&quot;red&quot;: &quot;#e95050&quot;})"
					rows="4"
				></textarea>
				<button type="button" id="utilgp-parse-btn" class="utilgp-ci-btn utilgp-ci-btn-primary">
					Parse Colors
				</button>
				<div id="utilgp-parse-error" class="utilgp-ci-error" style="display:none;"></div>
			</div>

			<!-- Step 2: Active Color Palette (Synced with GP) -->
			<div class="utilgp-ci-section">
				<label class="utilgp-ci-label">GP Global Palette</label>
				<div id="utilgp-color-palette" class="utilgp-gc-palette">
					<!-- Swatches will be rendered here by JS -->
				</div>
			</div>

			<!-- Dark Mode Auto-Sync Status Banner -->
			<?php if ( class_exists( 'UTILGP_Dark_Mode' ) ) : ?>
			<div class="utilgp-dark-sync-banner">
				<span class="utilgp-dark-sync-icon">&#9790;</span>
				<span>Dark Mode auto-sync is <strong>active</strong>. Math-derived dark colors override defaults automatically.</span>
				<button type="button" id="utilgp-resync-dark-btn" class="utilgp-ci-btn utilgp-ci-btn-ghost" style="margin:0;padding:3px 10px;font-size:11px;">Re-sync All</button>
			</div>
			<?php else : ?>
			<div class="utilgp-dark-sync-banner utilgp-dark-sync-off">
				<span class="utilgp-dark-sync-icon">&#9790;</span>
				<span>Dark Mode module inactive. Dark colors managed manually.</span>
			</div>
			<?php endif; ?>

			<!-- Status -->
			<div id="utilgp-ci-status" class="utilgp-ci-status" style="display:none;"></div>

		</div>

		<!-- Mapping Wizard Modal (hidden by default) -->
		<div id="utilgp-mapping-modal" class="utilgp-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="utilgp-modal-title">
			<div class="utilgp-modal-box">

				<div class="utilgp-modal-header">
					<h3 id="utilgp-modal-title">Map Imported Colors to Variables</h3>
					<button type="button" id="utilgp-modal-close" class="utilgp-modal-close" aria-label="Close">&times;</button>
				</div>

				<div class="utilgp-modal-body">

					<!-- Color Mapping Table -->
					<div class="utilgp-mapping-intro">
						Assign each imported color to an existing variable or create a new one.
					</div>
					<div id="utilgp-mapping-rows" class="utilgp-mapping-table"></div>

					<!-- Global Options -->
					<div class="utilgp-modal-options">
						<label class="utilgp-ci-label">Generate Derivatives</label>
						<div class="utilgp-options-grid">
							<label class="utilgp-option-check">
								<input type="checkbox" id="utilgp-opt-scale" checked>
								Lightness Scale (–10 to –90)
							</label>
							<label class="utilgp-option-check">
								<input type="checkbox" id="utilgp-opt-complementary">
								Complementary (–comp)
							</label>
							<label class="utilgp-option-check">
								<input type="checkbox" id="utilgp-opt-triadic">
								Triadic (–tri-a, –tri-b)
							</label>
							<label class="utilgp-option-check">
								<input type="checkbox" id="utilgp-opt-analogous">
								Analogous (–ana-a, –ana-b)
							</label>
							<label class="utilgp-option-check">
								<input type="checkbox" id="utilgp-opt-split-comp">
								Split-Complementary (–sc-a, –sc-b)
							</label>
							<label class="utilgp-option-check">
								<input type="checkbox" id="utilgp-opt-dark" checked>
								Auto Dark Counterpart
							</label>
						</div>
						<label class="utilgp-option-check utilgp-dark-sync-row">
							<input type="checkbox" id="utilgp-opt-sync-dark" checked>
							<strong>Sync dark counterparts to Dark Mode panel</strong>
						</label>
					</div>

				</div>

				<div class="utilgp-modal-footer">
					<button type="button" id="utilgp-modal-cancel" class="utilgp-ci-btn utilgp-ci-btn-ghost">Cancel</button>
					<button type="button" id="utilgp-modal-apply" class="utilgp-ci-btn utilgp-ci-btn-primary">Import & Apply</button>
				</div>

			</div>
		</div>

		<!-- Massive Menu Editor Modal -->
		<div id="utilgp-editor-modal" class="utilgp-modal-overlay" style="display:none;" role="dialog" aria-modal="true">
			<div class="utilgp-modal-box utilgp-editor-box">
				<div class="utilgp-modal-header">
					<h3>Edit Color Variables</h3>
					<button type="button" id="utilgp-editor-close" class="utilgp-modal-close" aria-label="Close">&times;</button>
				</div>

				<div class="utilgp-modal-body">
					<div class="utilgp-editor-row">
						<div class="utilgp-editor-field">
							<label class="utilgp-ci-label">Name</label>
							<input type="text" id="utilgp-edit-name" class="utilgp-ci-input" />
						</div>
						<div class="utilgp-editor-field">
							<label class="utilgp-ci-label">Color</label>
							<div id="utilgp-react-color-picker-root"></div>
							<input type="hidden" id="utilgp-edit-hex" />
						</div>
						<div class="utilgp-editor-field" style="flex:0; display:flex; align-items:flex-end;">
							<button type="button" id="utilgp-edit-delete" class="utilgp-ci-btn utilgp-ci-btn-danger" title="Delete from GP">Delete</button>
						</div>
					</div>

					<!-- Math Options -->
					<div class="utilgp-modal-options" style="margin-top:16px;">
						<label class="utilgp-ci-label">Generate Derivatives</label>
						<div class="utilgp-options-grid">
							<label class="utilgp-option-check"><input type="checkbox" id="utilgp-edit-opt-scale"> Lightness Scale (–10 to –90)</label>
							<label class="utilgp-option-check"><input type="checkbox" id="utilgp-edit-opt-complementary"> Complementary (–comp)</label>
							<label class="utilgp-option-check"><input type="checkbox" id="utilgp-edit-opt-triadic"> Triadic (–tri-a, –tri-b)</label>
							<label class="utilgp-option-check"><input type="checkbox" id="utilgp-edit-opt-analogous"> Analogous (–ana-a, –ana-b)</label>
							<label class="utilgp-option-check"><input type="checkbox" id="utilgp-edit-opt-split-comp"> Split-Complementary (–sc-a, –sc-b)</label>
							<label class="utilgp-option-check"><input type="checkbox" id="utilgp-edit-opt-dark"> Auto Dark Counterpart</label>
						</div>
					</div>

					<!-- Live Preview / Variables List -->
					<div class="utilgp-editor-preview-wrap" style="margin-top:16px;">
						<label class="utilgp-ci-label">Generated Variables (Click to copy CSS)</label>
						<div id="utilgp-editor-preview-list" class="utilgp-editor-preview-list"></div>
					</div>
				</div>

				<div class="utilgp-modal-footer">
					<span id="utilgp-editor-status" style="font-size:11px;color:#00a32a;margin-right:auto;display:none;">Saved!</span>
					<button type="button" id="utilgp-editor-cancel" class="utilgp-ci-btn utilgp-ci-btn-ghost">Close</button>
					<button type="button" id="utilgp-editor-apply" class="utilgp-ci-btn utilgp-ci-btn-primary">Apply & Save</button>
				</div>
			</div>
		</div>
		<?php
	}
}
