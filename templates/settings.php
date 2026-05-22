<?php if ( ! defined( 'ABSPATH' ) ) exit;

$connected    = FS_Settings::is_ga4_configured();
$has_creds    = ! empty( FS_Settings::get( 'ga4_client_id' ) ) && ! empty( FS_Settings::get( 'ga4_client_secret' ) );
$redirect_uri = FS_GA4_Client::get_redirect_uri();
$auth_url     = $has_creds ? FS_GA4_Client::get_auth_url() : '';
?>
<div class="fs-wrap">

    <div class="fs-header">
        <div class="fs-header__brand">
            <span class="fs-spark">⚡</span>
            <div>
                <h1 class="fs-header__title">FunnelSpark Settings</h1>
                <p class="fs-header__sub">Connect your GA4 property for live conversion data</p>
            </div>
        </div>
    </div>

    <div class="fs-settings-layout">

        <?php if ( isset( $_GET['fs_ga4_status'] ) && $_GET['fs_ga4_status'] === 'connected' ) : ?>
            <div class="fs-notice fs-notice--success" style="max-width:680px;margin-bottom:16px;">
                Google Analytics connected successfully.
            </div>
        <?php endif; ?>

        <?php if ( isset( $_GET['fs_ga4_error'] ) ) : ?>
            <div class="fs-notice fs-notice--error" style="max-width:680px;margin-bottom:16px;">
                Connection failed: <?php echo esc_html( urldecode( $_GET['fs_ga4_error'] ) ); ?>
            </div>
        <?php endif; ?>

        <div class="fs-card" style="max-width:680px;">

            <div id="fs-settings-notice"></div>

            <div class="fs-field">
                <label class="fs-label" for="ga4_property_id">GA4 Property ID <span class="fs-required">*</span></label>
                <input type="text" id="ga4_property_id" class="fs-input" value="<?php echo esc_attr( FS_Settings::get( 'ga4_property_id' ) ); ?>" placeholder="e.g. 123456789">
                <p class="fs-hint">GA4 → Admin → Property Settings. Numbers only — not the Measurement ID (G-XXXXXXXX).</p>
            </div>

            <div class="fs-field">
                <label class="fs-label" for="ga4_client_id">OAuth Client ID <span class="fs-required">*</span></label>
                <input type="text" id="ga4_client_id" class="fs-input" value="<?php echo esc_attr( FS_Settings::get( 'ga4_client_id' ) ); ?>" placeholder="e.g. 123456789-abc123.apps.googleusercontent.com">
            </div>

            <div class="fs-field">
                <label class="fs-label" for="ga4_client_secret">OAuth Client Secret <span class="fs-required">*</span></label>
                <input type="password" id="ga4_client_secret" class="fs-input" value="<?php echo esc_attr( FS_Settings::get( 'ga4_client_secret' ) ); ?>" placeholder="GOCSPX-…" autocomplete="new-password">
            </div>

            <div class="fs-field">
                <button id="fs-save-settings" class="fs-btn fs-btn--primary">Save Credentials</button>
                <span id="fs-settings-saving" style="display:none;margin-left:12px;color:#8BA3A9;font-size:13px;">Saving…</span>
            </div>

        </div>

        <div class="fs-card" style="max-width:680px;margin-top:20px;">
            <h3 class="fs-card__title">Google Analytics Connection</h3>

            <?php if ( $connected ) : ?>
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                    <span style="color:#34d399;font-weight:600;">● Connected</span>
                    <button id="fs-disconnect-ga4" class="fs-btn fs-btn--ghost fs-btn--sm">Disconnect</button>
                </div>

                <div id="fs-property-info" style="margin-top:16px;">
                    <p id="fs-property-loading" style="font-size:12px;color:var(--fs-muted);">Loading property info…</p>
                    <div id="fs-property-details" style="display:none;">
                        <table style="width:100%;border-collapse:collapse;font-size:13px;">
                            <tr>
                                <td style="padding:4px 0 4px;color:var(--fs-muted);font-size:11px;width:120px;text-transform:uppercase;letter-spacing:.05em;">Property</td>
                                <td id="fs-prop-name" style="color:var(--fs-white);font-weight:600;"></td>
                            </tr>
                            <tr>
                                <td style="padding:4px 0;color:var(--fs-muted);font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Property ID</td>
                                <td id="fs-prop-id" style="color:var(--fs-muted);font-family:monospace;font-size:12px;"></td>
                            </tr>
                        </table>
                        <div id="fs-prop-streams"></div>
                    </div>
                    <p id="fs-property-error" style="display:none;font-size:12px;color:#f87171;margin:8px 0 0;"></p>
                </div>

                <div id="fs-disconnect-notice" style="margin-top:12px;"></div>

            <?php elseif ( $has_creds ) : ?>
                <p class="fs-hint" style="margin-bottom:14px;">Credentials saved. Authorize FunnelSpark to read your GA4 data.</p>
                <a href="<?php echo esc_url( $auth_url ); ?>" class="fs-btn fs-btn--primary">Connect Google Analytics</a>

            <?php else : ?>
                <p class="fs-hint">Save your OAuth Client ID and Client Secret above, then connect.</p>
            <?php endif; ?>
        </div>

        <div class="fs-card" style="max-width:680px;margin-top:20px;">
            <h3 class="fs-card__title">Setup Guide</h3>
            <ol class="fs-list">
                <li>Go to <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud Console</a> → create or select a project</li>
                <li>Enable the <strong>Google Analytics Data API</strong></li>
                <li>Go to <strong>APIs &amp; Services → OAuth consent screen</strong> → configure it (External or Internal)</li>
                <li>Go to <strong>Credentials → Create Credentials → OAuth 2.0 Client ID</strong> → type: <em>Web application</em></li>
                <li>Under <strong>Authorized redirect URIs</strong> add exactly:<br>
                    <code style="background:rgba(255,110,78,.1);color:#FF6E4E;padding:3px 8px;border-radius:4px;font-size:12px;word-break:break-all;"><?php echo esc_html( $redirect_uri ); ?></code>
                </li>
                <li>Copy the <strong>Client ID</strong> and <strong>Client Secret</strong> into the fields above and save</li>
                <li>Click <strong>Connect Google Analytics</strong> and authorize with your Google account</li>
                <li>In GA4 → Admin → Account Access Management → confirm that the Google account you used has at least <strong>Viewer</strong> access</li>
                <li>Copy your <strong>Property ID</strong> from GA4 → Admin → Property Settings</li>
            </ol>
        </div>

        <div class="fs-card" style="max-width:680px;margin-top:20px;">
            <h3 class="fs-card__title">🗄 Data &amp; Privacy</h3>
            <p class="fs-hint" style="margin-bottom:16px;">
                By default, your funnels and settings are kept in the database when the plugin is deactivated or deleted — so reinstalling picks up exactly where you left off.
            </p>
            <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;">
                <input type="checkbox" id="delete_on_uninstall" style="margin-top:3px;flex-shrink:0;"
                    <?php checked( FS_Settings::get( 'delete_on_uninstall' ), '1' ); ?> value="1">
                <span>
                    <strong style="color:#f87171;">Delete all data when the plugin is uninstalled</strong><br>
                    <span class="fs-hint">If checked, deleting the plugin from the WordPress admin will permanently remove all funnels, settings, and cached data. This cannot be undone.</span>
                </span>
            </label>
            <div style="margin-top:14px;">
                <button id="fs-save-data-settings" class="fs-btn fs-btn--ghost fs-btn--sm">Save preference</button>
                <span id="fs-data-settings-saving" style="display:none;margin-left:10px;color:#8BA3A9;font-size:13px;">Saving…</span>
                <span id="fs-data-settings-saved"  style="display:none;margin-left:10px;color:#34d399;font-size:13px;">✓ Saved</span>
            </div>
        </div>

    </div>
</div>
