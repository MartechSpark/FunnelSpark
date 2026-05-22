/* global FS */
(function($) {
    'use strict';

    $(document).ready(function() {

        // ── Funnel Dashboard Actions ───────────────────────────────────
        // Delete
        $(document).on('click', '.fs-delete-btn', function() {
            const id   = $(this).data('id');
            const card = $(this).closest('.fs-funnel-card');
            if (!confirm('Delete this funnel? This cannot be undone.')) return;

            $.post(FS.ajax_url, { action: 'fs_delete_funnel', nonce: FS.nonce, funnel_id: id })
            .done(resp => {
                if (resp.success) card.fadeOut(300, function() { $(this).remove(); checkEmpty(); });
            });
        });

        // Duplicate
        $(document).on('click', '.fs-duplicate-btn', function() {
            const id  = $(this).data('id');
            const btn = $(this);
            btn.text('Copying…').prop('disabled', true);

            $.post(FS.ajax_url, { action: 'fs_duplicate_funnel', nonce: FS.nonce, funnel_id: id })
            .done(resp => {
                if (resp.success) {
                    window.location.href = FS.editor_url + '&funnel_id=' + resp.data.funnel_id;
                }
            })
            .fail(() => { btn.text('Copy').prop('disabled', false); });
        });

        function checkEmpty() {
            if ($('.fs-funnel-card').length === 0) location.reload();
        }

        // ── Settings Page Save ─────────────────────────────────────────
        $('#fs-save-settings').on('click', function() {
            const $btn    = $(this);
            const $spin   = $('#fs-settings-saving');
            const $notice = $('#fs-settings-notice');

            $btn.prop('disabled', true);
            $spin.show();
            $notice.html('');

            $.post(FS.ajax_url, {
                action:            'fs_save_settings',
                nonce:             FS.nonce,
                ga4_property_id:   $('#ga4_property_id').val(),
                ga4_client_id:     $('#ga4_client_id').val(),
                ga4_client_secret: $('#ga4_client_secret').val(),
            })
            .done(resp => {
                if (resp.success) {
                    $notice.html('<div class="fs-notice fs-notice--success">✅ Credentials saved. Click "Connect Google Analytics" to authorize.</div>');
                    // Show connect button if it isn't already visible (creds just became complete)
                    if ($('#ga4_client_id').val() && $('#ga4_client_secret').val()) {
                        location.reload();
                    }
                } else {
                    $notice.html('<div class="fs-notice fs-notice--error">❌ ' + (resp.data || 'Save failed.') + '</div>');
                }
            })
            .fail(() => {
                $notice.html('<div class="fs-notice fs-notice--error">❌ Network error. Please try again.</div>');
            })
            .always(() => { $btn.prop('disabled', false); $spin.hide(); });
        });

        // ── Data & Privacy Setting ────────────────────────────────────
        $('#fs-save-data-settings').on('click', function() {
            const $btn    = $(this);
            const $spin   = $('#fs-data-settings-saving');
            const $saved  = $('#fs-data-settings-saved');

            $btn.prop('disabled', true);
            $spin.show();
            $saved.hide();

            $.post(FS.ajax_url, {
                action:             'fs_save_data_settings',
                nonce:              FS.nonce,
                delete_on_uninstall: $('#delete_on_uninstall').is(':checked') ? '1' : '0',
            })
            .done(resp => {
                if (resp.success) {
                    $saved.show();
                    setTimeout(() => $saved.hide(), 3000);
                }
            })
            .always(() => { $btn.prop('disabled', false); $spin.hide(); });
        });

        // ── GA4 Property Info (Settings page) ────────────────────────
        if ( $('#fs-property-loading').length ) {
            $.post(FS.ajax_url, { action: 'fs_get_ga4_property_info', nonce: FS.nonce })
            .done(function(resp) {
                $('#fs-property-loading').hide();
                if ( resp.success ) {
                    $('#fs-prop-name').text( resp.data.property_name || '—' );
                    $('#fs-prop-id').text( 'properties/' + resp.data.property_id );

                    var streams = resp.data.streams || [];
                    if ( streams.length ) {
                        var html = '<div style="margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,.07);">'
                            + '<div style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--fs-muted);margin-bottom:8px;">Data Streams</div>';
                        streams.forEach(function(s) {
                            html += '<div style="display:flex;align-items:baseline;gap:8px;padding:5px 0;border-bottom:1px solid rgba(255,255,255,.05);">';
                            if ( s.measurement_id ) {
                                html += '<code style="background:rgba(255,110,78,.1);color:#FF6E4E;padding:2px 7px;border-radius:4px;font-size:12px;flex-shrink:0;">' + esc(s.measurement_id) + '</code>';
                            }
                            if ( s.name ) {
                                html += '<span style="color:var(--fs-white);font-size:13px;">' + esc(s.name) + '</span>';
                            }
                            if ( s.uri ) {
                                html += '<span style="color:var(--fs-muted);font-size:11px;margin-left:auto;">' + esc(s.uri) + '</span>';
                            }
                            html += '</div>';
                        });
                        html += '</div>';
                        $('#fs-prop-streams').html(html);
                    }

                    $('#fs-property-details').show();
                } else {
                    $('#fs-property-error').text('Could not load property info: ' + ( resp.data || 'unknown error' ) ).show();
                }
            })
            .fail(function() {
                $('#fs-property-loading').hide();
                $('#fs-property-error').text('Network error loading property info.').show();
            });
        }

        // ── Disconnect GA4 ─────────────────────────────────────────────
        $('#fs-disconnect-ga4').on('click', function() {
            if (!confirm('Disconnect Google Analytics? You will need to reconnect to restore live data overlays.')) return;

            const $btn    = $(this);
            const $notice = $('#fs-disconnect-notice');

            $btn.prop('disabled', true).text('Disconnecting…');

            $.post(FS.ajax_url, { action: 'fs_disconnect_ga4', nonce: FS.nonce })
            .done(resp => {
                if (resp.success) {
                    location.reload();
                } else {
                    $notice.html('<div class="fs-notice fs-notice--error">❌ ' + (resp.data || 'Disconnect failed.') + '</div>');
                    $btn.prop('disabled', false).text('Disconnect');
                }
            })
            .fail(() => {
                $notice.html('<div class="fs-notice fs-notice--error">❌ Network error.</div>');
                $btn.prop('disabled', false).text('Disconnect');
            });
        });

        // ── Promo Refresh ──────────────────────────────────────────────
        $('#fs-refresh-promo').on('click', function() {
            const $btn    = $(this);
            const $notice = $('#fs-promo-notice');

            $btn.text('Fetching…').prop('disabled', true);
            $notice.html('');

            $.post(FS.ajax_url, {
                action: 'fs_refresh_promo',
                nonce:  FS.nonce,
            })
            .done(function(resp) {
                if ( resp.success ) {
                    $notice.html('<div class="fs-notice fs-notice--success">✅ Promo refreshed from server. ' + esc(resp.data.status.label) + '</div>');
                    renderPromoPreview( resp.data.promo );
                } else {
                    $notice.html('<div class="fs-notice fs-notice--error">❌ Could not fetch promo JSON. Fallback defaults are active. Check that <code>martechspark.com/funnelspark-promo.json</code> is reachable.</div>');
                }
            })
            .fail(function() {
                $notice.html('<div class="fs-notice fs-notice--error">❌ Network error.</div>');
            })
            .always(function() {
                $btn.text('↻ Refresh Now').prop('disabled', false);
            });
        });

        function renderPromoPreview( promo ) {
            $('#fs-promo-preview-badge').text( promo.badge  || '' );
            $('#fs-promo-preview-icon').text(  promo.icon   || '' );
            $('#fs-promo-preview-head').text(  promo.headline || '' );
            $('#fs-promo-preview-body').text(  promo.body   || '' );
            $('#fs-promo-preview-cta').text(   promo.cta_text || '' );
            $('#fs-promo-preview-pow').text(   promo.powered_by_text || '' );

            const $ul = $('#fs-promo-preview-bullets').empty();
            if ( promo.bullets && promo.bullets.length ) {
                promo.bullets.forEach(function(b) {
                    $ul.append( $('<li>').text(b) );
                });
            }
            $('#fs-promo-preview').show();
        }

        function esc(str) {
            return $('<div>').text(str).html();
        }


    });

})(jQuery);
