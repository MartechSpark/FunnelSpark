/* global FS, FunnelSparkCanvas */
/**
 * FunnelSpark GA4 Overlay
 * Fetches live GA4 metrics per page path and injects data badges
 * onto matching funnel step nodes on the canvas.
 */
(function() {
    'use strict';

    function init() {
        const btn = document.getElementById('fs-load-ga4');
        if ( !btn ) return;

        btn.addEventListener('click', () => loadGA4Data( false ));

        // Show date label on load and on change
        updateDateLabel();
        document.getElementById('fs-date-range')?.addEventListener('change', () => {
            updateDateLabel();
            FunnelSparkCanvas?.resetSourceCache?.();
            loadGA4Data( true );
        });

        // Auto-load on canvas open
        if ( window.FS?.ga4_configured ) {
            loadGA4Data( true );
        }
    }

    function updateDateLabel() {
        const sel   = document.getElementById('fs-date-range');
        const label = document.getElementById('fs-date-label');
        if ( !sel || !label ) return;

        const days  = parseInt( sel.value, 10 );
        const end   = new Date();
        const start = new Date();
        start.setDate( end.getDate() - days );

        const fmt = { month: 'short', day: 'numeric', year: 'numeric' };
        const startStr = start.toLocaleDateString( 'en-US', fmt );
        const endStr   = end.toLocaleDateString( 'en-US', fmt );
        label.textContent = startStr + ' – ' + endStr;
    }

    function loadGA4Data( silent ) {
        if ( ! window.FS?.ga4_configured ) {
            if ( !silent ) alert('Please configure GA4 in FunnelSpark → Settings first.');
            return;
        }

        const allNodes  = Object.values( FunnelSparkCanvas?.getNodes?.() || {} );
        const dateRange = document.getElementById('fs-date-range')?.value || '30daysAgo';

        const adNodes   = allNodes.filter( n => n.type === 'ad' && n.source );
        const pageNodes = allNodes.filter( n => n.type !== 'ad' );
        const paths     = [ ...new Set( pageNodes.map( n => extractPath(n.url) ).filter(Boolean) ) ];

        if ( paths.length === 0 && adNodes.length === 0 ) {
            if ( !silent ) alert('Add page URLs or select traffic sources on your funnel steps, then click Load GA4 Data.');
            return;
        }

        const btn = document.getElementById('fs-load-ga4');
        btn.textContent = '⏳ Loading…';
        btn.disabled    = true;

        const requests = [];

        // ── Page metrics ──────────────────────────────────────────────
        if ( paths.length > 0 ) {
            requests.push(
                fetch( FS.ajax_url, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body:    new URLSearchParams({
                        action:     'fs_get_ga4_overlay',
                        nonce:      FS.nonce,
                        date_range: dateRange,
                        ...buildPathsParam(paths),
                    }),
                })
                .then( r => r.json() )
                .then( resp => {
                    if ( !resp.success ) {
                        alert('GA4 Error: ' + (resp.data || 'Unknown error. Check your credentials.'));
                        return;
                    }
                    overlayPageData( pageNodes, resp.data );
                    if ( FunnelSparkCanvas?.showGa4Summary ) {
                        FunnelSparkCanvas.showGa4Summary( resp.data );
                    }
                })
            );
        }

        // ── Traffic source metrics ────────────────────────────────────
        if ( adNodes.length > 0 ) {
            requests.push(
                fetch( FS.ajax_url, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body:    new URLSearchParams({
                        action:     'fs_get_ga4_sources',
                        nonce:      FS.nonce,
                        date_range: dateRange,
                    }),
                })
                .then( r => r.json() )
                .then( resp => {
                    if ( resp.success && resp.data.sources && resp.data.sources.length ) {
                        overlayAdData( adNodes, resp.data.sources, resp.data.total_sessions || 0 );
                    }
                })
            );
        }

        Promise.all( requests )
            .catch( () => alert('Network error fetching GA4 data.') )
            .finally( () => {
                btn.textContent = '📊 Refresh GA4';
                btn.disabled    = false;
            });
    }

    // ── Page node overlay ─────────────────────────────────────────────
    function overlayPageData( pageNodes, metrics ) {
        const keys        = Object.keys( metrics );
        const connections = FunnelSparkCanvas?.getConnections?.() || [];

        // Pass 1: match every page node to its GA4 metrics row
        const nodeMet = {};
        pageNodes.forEach( node => {
            const path = extractPath( node.url );
            if ( !path ) return;

            let matchedKey = keys.find( k => k === path || k.replace(/\/$/, '') === path );
            if ( !matchedKey ) {
                matchedKey = keys.find( k => {
                    if ( k === '/' || k.length <= 1 ) return false;
                    return path.startsWith(k) || k.startsWith(path);
                });
            }
            if ( matchedKey ) nodeMet[ node.id ] = { ...metrics[ matchedKey ] };
        });

        // Pass 2: recalculate CVR using the predecessor step's sessions.
        // If multiple predecessors exist (merge point), sum their sessions.
        // For nodes marked as conversion steps, sessions reaching that page
        // are the conversions — GA4 event-based conversion counts are not used.
        const nodes = FunnelSparkCanvas?.getNodes?.() || {};
        const predSessionsMap = {};
        connections.forEach( conn => {
            const pred = nodeMet[ conn.from ];
            const curr = nodeMet[ conn.to ];
            if ( !pred || !curr ) return;
            predSessionsMap[ conn.to ] = ( predSessionsMap[ conn.to ] || 0 ) + pred.sessions;
        });
        Object.entries( predSessionsMap ).forEach( ([ nodeId, predSessions ]) => {
            if ( !nodeMet[ nodeId ] || predSessions <= 0 ) return;
            const isConv = !! nodes[ nodeId ]?.conversion;
            if ( isConv ) nodeMet[ nodeId ].conversions = nodeMet[ nodeId ].sessions;
            nodeMet[ nodeId ].conversion_rate = parseFloat(
                ( ( nodeMet[ nodeId ].conversions / predSessions ) * 100 ).toFixed(1)
            );
        });

        // Pass 3: render badges
        Object.entries( nodeMet ).forEach( ([ nodeId, data ]) => {
            if ( FunnelSparkCanvas?.showBadge ) {
                FunnelSparkCanvas.showBadge( nodeId, data );
            }
        });
    }

    // ── Ad/Traffic node overlay ───────────────────────────────────────
    function overlayAdData( adNodes, sources, totalSessions ) {
        adNodes.forEach( node => {
            const match = sources.find( s => s.source === node.source );
            if ( match && FunnelSparkCanvas?.showAdBadge ) {
                FunnelSparkCanvas.showAdBadge( node.id, match, totalSessions );
            }
        });
    }

    function extractPath( url ) {
        if ( !url ) return '';
        try {
            if ( url.startsWith('/') ) return url.replace(/\/$/, '') || '/';
            const u = new URL(url);
            return u.pathname.replace(/\/$/, '') || '/';
        } catch {
            return url.startsWith('/') ? url : '/' + url;
        }
    }

    function buildPathsParam( paths ) {
        const out = {};
        paths.forEach( (p, i) => { out['paths[' + i + ']'] = p; } );
        return out;
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
