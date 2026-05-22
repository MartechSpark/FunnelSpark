<?php if ( ! defined( 'ABSPATH' ) ) exit;

$funnel_id    = (int) ( $_GET['funnel_id'] ?? 0 );
$funnel_title = $funnel_id ? get_the_title( $funnel_id ) : 'Untitled Funnel';
$is_new       = ! $funnel_id;
$promo_shown  = ! get_user_meta( get_current_user_id(), 'fs_promo_dismissed', true );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html( $funnel_title ); ?> — FunnelSpark</title>
<?php
wp_enqueue_style( 'fs-fonts' );
wp_enqueue_style( 'fs-admin' );
wp_enqueue_style( 'fs-canvas' );
wp_print_styles();
?>
</head>
<body class="fs-editor-body">

<!-- ── Top Bar ── -->
<div class="fs-editor-topbar">
    <div class="fs-editor-topbar__left">
        <a href="<?php echo esc_url( admin_url('admin.php?page=funnelspark') ); ?>" class="fs-back-btn">← Funnels</a>
        <span class="fs-topbar-sep">|</span>
        <input type="text" id="fs-funnel-title" class="fs-title-input" value="<?php echo esc_attr( $funnel_title ); ?>" placeholder="Funnel Name">
    </div>
    <div class="fs-editor-topbar__center">
        <span class="fs-spark-wordmark">⚡ FunnelSpark</span>
    </div>
    <div class="fs-editor-topbar__right">
        <select id="fs-date-range" class="fs-select fs-select--sm">
            <option value="7daysAgo">Last 7 Days</option>
            <option value="30daysAgo" selected>Last 30 Days</option>
            <option value="90daysAgo">Last 90 Days</option>
        </select>
        <span id="fs-date-label" class="fs-date-label"></span>
        <button id="fs-load-ga4" class="fs-btn fs-btn--ghost fs-btn--sm" <?php echo ! FS_Settings::is_ga4_configured() ? 'disabled title="Configure GA4 in Settings"' : ''; ?>>
            📊 Load GA4 Data
        </button>
        <button id="fs-save-btn" class="fs-btn fs-btn--primary fs-btn--sm">Save</button>
        <span id="fs-save-status" class="fs-save-status"></span>
    </div>
</div>

<!-- ── Main Editor Layout ── -->
<div class="fs-editor-layout">

    <!-- Node Palette -->
    <div class="fs-palette">
        <div class="fs-palette__title">Funnel Steps</div>
        <div class="fs-palette__hint">Drag onto canvas</div>

        <div class="fs-palette-section">
            <div class="fs-palette-label">Acquisition</div>
            <div class="fs-palette-node" draggable="true" data-type="ad">📣 Ad / Traffic</div>
            <div class="fs-palette-node" draggable="true" data-type="landing">🎯 Landing Page</div>
            <div class="fs-palette-node" draggable="true" data-type="optin">📧 Opt-In Page</div>
        </div>

        <div class="fs-palette-section">
            <div class="fs-palette-label">Conversion</div>
            <div class="fs-palette-node" draggable="true" data-type="sales">💰 Sales Page</div>
            <div class="fs-palette-node" draggable="true" data-type="order">🛒 Order Form</div>
            <div class="fs-palette-node" draggable="true" data-type="upsell">⬆ Upsell</div>
            <div class="fs-palette-node" draggable="true" data-type="downsell">⬇ Downsell</div>
        </div>

        <div class="fs-palette-section">
            <div class="fs-palette-label">Post-Conversion</div>
            <div class="fs-palette-node" draggable="true" data-type="thankyou">✅ Thank You</div>
            <div class="fs-palette-node" draggable="true" data-type="webinar">🎙 Webinar</div>
            <div class="fs-palette-node" draggable="true" data-type="email">📬 Email Sequence</div>
        </div>

        <div class="fs-palette-section">
            <div class="fs-palette-label">Utility</div>
            <div class="fs-palette-node" draggable="true" data-type="page">📄 Generic Page</div>
            <div class="fs-palette-node" draggable="true" data-type="decision">🔀 Decision</div>
        </div>

        <div class="fs-palette-divider"></div>
        <div class="fs-palette__hint">Canvas Controls</div>
        <button id="fs-clear-canvas" class="fs-palette-action">🗑 Clear All</button>
        <button id="fs-zoom-in"  class="fs-palette-action">+ Zoom In</button>
        <button id="fs-zoom-out" class="fs-palette-action">− Zoom Out</button>
        <button id="fs-zoom-fit" class="fs-palette-action">⊡ Fit to Screen</button>
    </div>

    <!-- Canvas Area -->
    <div class="fs-canvas-wrap" id="fs-canvas-wrap">
        <svg class="fs-connections-svg" id="fs-connections-svg"></svg>
        <div class="fs-canvas" id="fs-canvas">
            <!-- Nodes injected by canvas.js -->
        </div>
        <div class="fs-canvas-empty" id="fs-canvas-empty">
            <div class="fs-canvas-empty__icon">🔥</div>
            <p>Drag funnel steps from the left panel onto the canvas</p>
            <p class="fs-canvas-empty__hint">Click two nodes to connect them with an arrow</p>
        </div>
    </div>

    <!-- Right Sidebar: Node Inspector + Promo -->
    <div class="fs-sidebar">

        <!-- Node Inspector -->
        <div class="fs-inspector" id="fs-inspector" style="display:none;">
            <div class="fs-inspector__title">Step Settings</div>

            <div class="fs-field">
                <label class="fs-label">Label</label>
                <input type="text" id="fs-node-label" class="fs-input" placeholder="e.g. Homepage Opt-In">
            </div>

            <!-- Page fields: all node types except Ad/Traffic -->
            <div id="fs-page-fields">
                <div class="fs-field">
                    <label class="fs-label">WordPress Page</label>
                    <select id="fs-page-picker" class="fs-input">
                        <option value="">— pick a page or enter URL below —</option>
                    </select>
                </div>
                <div class="fs-field">
                    <label class="fs-label">Page URL / Path</label>
                    <input type="text" id="fs-node-url" class="fs-input" placeholder="/lp/homepage-audit/">
                    <p class="fs-hint">Auto-filled when you pick a page above, or enter a custom path. Used to match GA4 data to this step.</p>
                </div>
            </div>

            <!-- Source field: Ad/Traffic nodes only -->
            <div id="fs-source-field" style="display:none;">
                <div class="fs-field">
                    <label class="fs-label">Traffic Source</label>
                    <select id="fs-node-source" class="fs-input">
                        <option value="">— select source —</option>
                    </select>
                    <p class="fs-hint" id="fs-source-hint"></p>
                </div>
            </div>

            <div class="fs-field">
                <label class="fs-label">Notes</label>
                <textarea id="fs-node-notes" class="fs-input fs-textarea" rows="3" placeholder="Optional notes about this step..."></textarea>
            </div>

            <div class="fs-field">
                <label class="fs-label fs-label--checkbox">
                    <input type="checkbox" id="fs-node-conversion">
                    Mark as conversion step
                </label>
                <p class="fs-hint">Shows Conversions and CVR on this step's badge when GA4 data is loaded.</p>
            </div>

            <button id="fs-update-node" class="fs-btn fs-btn--primary fs-btn--sm" style="width:100%;">Update Step</button>
            <button id="fs-delete-node" class="fs-btn fs-btn--danger fs-btn--sm" style="width:100%;margin-top:8px;">Delete Step</button>
        </div>

        <div class="fs-inspector-empty" id="fs-inspector-empty">
            <p>Click a funnel step to edit its settings, or click an arrow to delete it.</p>
        </div>

        <!-- Connection Inspector -->
        <div class="fs-inspector" id="fs-conn-inspector" style="display:none;">
            <div class="fs-inspector__title">Connection</div>
            <div style="margin-bottom:14px;font-size:13px;color:var(--fs-white);">
                <span id="fs-conn-from" style="font-weight:600;">—</span>
                <span style="margin:0 8px;color:var(--fs-muted);">→</span>
                <span id="fs-conn-to" style="font-weight:600;">—</span>
            </div>
            <button id="fs-delete-conn" class="fs-btn fs-btn--danger fs-btn--sm" style="width:100%;">Delete Connection</button>
        </div>

        <!-- GA4 Summary -->
        <div class="fs-ga4-summary" id="fs-ga4-summary" style="display:none;">
            <div class="fs-ga4-summary__title">📊 GA4 Summary</div>
            <div id="fs-ga4-summary-content"></div>
        </div>

        <!-- Promo Sidebar -->
        <?php $promo = FS_Promo::get(); ?>
        <div class="fs-promo" id="fs-promo">
            <div class="fs-promo__badge"><?php echo esc_html( $promo['badge'] ); ?></div>
            <div class="fs-promo__icon"><?php echo esc_html( $promo['icon'] ); ?></div>
            <h4 class="fs-promo__headline"><?php echo esc_html( $promo['headline'] ); ?></h4>
            <p class="fs-promo__body"><?php echo esc_html( $promo['body'] ); ?></p>
            <?php if ( ! empty( $promo['bullets'] ) ) : ?>
            <ul class="fs-promo__list">
                <?php foreach ( $promo['bullets'] as $bullet ) : ?>
                    <li><?php echo esc_html( $bullet ); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <a href="<?php echo esc_url( $promo['cta_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="fs-promo__cta">
                <?php echo esc_html( $promo['cta_text'] ); ?>
            </a>
            <p class="fs-promo__powered">
                <a href="<?php echo esc_url( $promo['powered_by_url'] ); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html( $promo['powered_by_text'] ); ?>
                </a>
            </p>
        </div>

    </div><!-- /.fs-sidebar -->

</div><!-- /.fs-editor-layout -->

<?php
wp_print_scripts();
wp_print_footer_scripts();
?>
</body>
</html>
