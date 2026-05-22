<?php if ( ! defined( 'ABSPATH' ) ) exit;

$funnels = get_posts([
    'post_type'      => 'fs_funnel',
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'orderby'        => 'modified',
    'order'          => 'DESC',
]);
?>
<div class="fs-wrap">

    <div class="fs-header">
        <div class="fs-header__brand">
            <span class="fs-spark">⚡</span>
            <div>
                <h1 class="fs-header__title">FunnelSpark</h1>
                <p class="fs-header__sub">Visual Funnel Builder &amp; GA4 Tracker</p>
            </div>
        </div>
        <div class="fs-header__actions">
            <a href="<?php echo esc_url( admin_url('admin.php?page=funnelspark-new') ); ?>" class="fs-btn fs-btn--primary">+ New Funnel</a>
            <a href="<?php echo esc_url( admin_url('admin.php?page=funnelspark-settings') ); ?>" class="fs-btn fs-btn--ghost">⚙ Settings</a>
        </div>
    </div>

    <?php if ( ! FS_Settings::is_ga4_configured() ) : ?>
    <div class="fs-notice fs-notice--warning">
        ⚠ <strong>Connect GA4</strong> to see live conversion data on your funnels.
        <a href="<?php echo esc_url( admin_url('admin.php?page=funnelspark-settings') ); ?>">Set up GA4 →</a>
    </div>
    <?php endif; ?>

    <?php if ( empty( $funnels ) ) : ?>
    <div class="fs-empty-state">
        <div class="fs-empty-state__icon">🔥</div>
        <h2>Build Your First Funnel</h2>
        <p>Map your customer journey from first click to conversion — and see exactly where leads drop off with live GA4 data.</p>
        <a href="<?php echo esc_url( admin_url('admin.php?page=funnelspark-new') ); ?>" class="fs-btn fs-btn--primary fs-btn--lg">Create Your First Funnel</a>
    </div>
    <?php else : ?>

    <div class="fs-funnel-grid" id="fs-funnel-grid">
        <?php foreach ( $funnels as $funnel ) :
            $updated  = get_post_meta( $funnel->ID, '_fs_updated', true );
            $canvas   = json_decode( get_post_meta( $funnel->ID, '_fs_canvas', true ), true );
            $steps    = count( $canvas['nodes'] ?? [] );
        ?>
        <div class="fs-funnel-card" data-id="<?php echo esc_attr( $funnel->ID ); ?>">
            <div class="fs-funnel-card__preview">
                <?php echo funnelspark_mini_preview( $canvas ); ?>
            </div>
            <div class="fs-funnel-card__body">
                <h3 class="fs-funnel-card__title"><?php echo esc_html( $funnel->post_title ); ?></h3>
                <p class="fs-funnel-card__meta">
                    <?php echo esc_html( $steps ); ?> step<?php echo $steps !== 1 ? 's' : ''; ?>
                    <?php if ( $updated ) : ?>
                        · Updated <?php echo esc_html( human_time_diff( strtotime( $updated ) ) ); ?> ago
                    <?php endif; ?>
                </p>
            </div>
            <div class="fs-funnel-card__actions">
                <a href="<?php echo esc_url( admin_url('admin.php?page=funnelspark-editor&funnel_id=' . $funnel->ID) ); ?>" class="fs-btn fs-btn--sm fs-btn--primary">Edit</a>
                <button class="fs-btn fs-btn--sm fs-btn--ghost fs-duplicate-btn" data-id="<?php echo esc_attr( $funnel->ID ); ?>">Copy</button>
                <button class="fs-btn fs-btn--sm fs-btn--danger fs-delete-btn" data-id="<?php echo esc_attr( $funnel->ID ); ?>">Delete</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>

</div>

<?php
function funnelspark_mini_preview( $canvas ) {
    if ( empty( $canvas['nodes'] ) ) {
        return '<div class="fs-mini-preview fs-mini-preview--empty"><span>No steps yet</span></div>';
    }
    $nodes = array_slice( $canvas['nodes'], 0, 5 );
    $html  = '<div class="fs-mini-preview">';
    foreach ( $nodes as $i => $node ) {
        $type  = esc_attr( $node['type'] ?? 'page' );
        $label = esc_html( wp_trim_words( $node['label'] ?? 'Step', 3 ) );
        $html .= '<div class="fs-mini-node fs-mini-node--' . $type . '">' . $label . '</div>';
        if ( $i < count($nodes) - 1 ) $html .= '<div class="fs-mini-arrow">→</div>';
    }
    if ( count( $canvas['nodes'] ) > 5 ) {
        $html .= '<div class="fs-mini-arrow">→</div><div class="fs-mini-node fs-mini-node--more">+' . ( count($canvas['nodes']) - 5 ) . '</div>';
    }
    $html .= '</div>';
    return $html;
}
