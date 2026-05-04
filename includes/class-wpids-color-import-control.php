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

			<!-- Step 2: Active Color Palette (Synced with GP) -->
			<div class="wpids-ci-section">
				<label class="wpids-ci-label">GP Global Palette</label>
				<div id="wpids-color-palette" class="wpids-gc-palette">
					<!-- Swatches will be rendered here by JS -->
				</div>
			</div>

			<!-- Dark Mode Auto-Sync Status Banner -->
			<?php if ( class_exists( 'WPIDS_Dark_Mode' ) ) : ?>
			<div class="wpids-dark-sync-banner">
				<span class="wpids-dark-sync-icon">&#9790;</span>
				<span>Dark Mode auto-sync is <strong>active</strong>. Math-derived dark colors override defaults automatically.</span>
				<button type="button" id="wpids-resync-dark-btn" class="wpids-ci-btn wpids-ci-btn-ghost" style="margin:0;padding:3px 10px;font-size:11px;">Re-sync All</button>
			</div>
			<?php else : ?>
			<div class="wpids-dark-sync-banner wpids-dark-sync-off">
				<span class="wpids-dark-sync-icon">&#9790;</span>
				<span>Dark Mode module inactive. Dark colors managed manually.</span>
			</div>
			<?php endif; ?>

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

		<!-- Massive Menu Editor Modal -->
		<div id="wpids-editor-modal" class="wpids-modal-overlay" style="display:none;" role="dialog" aria-modal="true">
			<div class="wpids-modal-box wpids-editor-box">
				<div class="wpids-modal-header">
					<h3>Edit Color Variables</h3>
					<button type="button" id="wpids-editor-close" class="wpids-modal-close" aria-label="Close">&times;</button>
				</div>

				<div class="wpids-modal-body">
					<div class="wpids-editor-row">
						<div class="wpids-editor-field">
							<label class="wpids-ci-label">Name</label>
							<input type="text" id="wpids-edit-name" class="wpids-ci-input" />
						</div>
						<div class="wpids-editor-field">
							<label class="wpids-ci-label">Color</label>
							<div id="wpids-react-color-picker-root"></div>
							<input type="hidden" id="wpids-edit-hex" />
						</div>
						<div class="wpids-editor-field" style="flex:0; display:flex; align-items:flex-end;">
							<button type="button" id="wpids-edit-delete" class="wpids-ci-btn wpids-ci-btn-danger" title="Delete from GP">Delete</button>
						</div>
					</div>

					<!-- Math Options -->
					<div class="wpids-modal-options" style="margin-top:16px;">
						<label class="wpids-ci-label">Generate Derivatives</label>
						<div class="wpids-options-grid">
							<label class="wpids-option-check"><input type="checkbox" id="wpids-edit-opt-scale"> Lightness Scale (–10 to –90)</label>
							<label class="wpids-option-check"><input type="checkbox" id="wpids-edit-opt-complementary"> Complementary (–comp)</label>
							<label class="wpids-option-check"><input type="checkbox" id="wpids-edit-opt-triadic"> Triadic (–tri-a, –tri-b)</label>
							<label class="wpids-option-check"><input type="checkbox" id="wpids-edit-opt-analogous"> Analogous (–ana-a, –ana-b)</label>
							<label class="wpids-option-check"><input type="checkbox" id="wpids-edit-opt-split-comp"> Split-Complementary (–sc-a, –sc-b)</label>
							<label class="wpids-option-check"><input type="checkbox" id="wpids-edit-opt-dark"> Auto Dark Counterpart</label>
						</div>
					</div>

					<!-- Live Preview / Variables List -->
					<div class="wpids-editor-preview-wrap" style="margin-top:16px;">
						<label class="wpids-ci-label">Generated Variables (Click to copy CSS)</label>
						<div id="wpids-editor-preview-list" class="wpids-editor-preview-list"></div>
					</div>
				</div>

				<div class="wpids-modal-footer">
					<span id="wpids-editor-status" style="font-size:11px;color:#00a32a;margin-right:auto;display:none;">Saved!</span>
					<button type="button" id="wpids-editor-cancel" class="wpids-ci-btn wpids-ci-btn-ghost">Close</button>
					<button type="button" id="wpids-editor-apply" class="wpids-ci-btn wpids-ci-btn-primary">Apply & Save</button>
				</div>
			</div>
		</div>
		<?php
	}
}
