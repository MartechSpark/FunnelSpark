/* global FS */
/**
 * FunnelSpark Canvas Engine
 * DOM-based drag-drop funnel builder with SVG arrow connections.
 * No external dependencies — vanilla JS only.
 */
(function() {
    'use strict';

    // ── Module-level cache for GA4 paid sources ────────────────────────
    let ga4Sources = null;

    // ── State ──────────────────────────────────────────────────────────
    const state = {
        nodes:       {},   // { id: { id, type, label, url, notes, x, y, el } }
        connections: [],   // [ { from, to, el } ]  (el = SVG <g> element)
        selected:    null, // currently selected node id
        connecting:  null, // node id waiting for connection target
        dragging:    null, // { id, startX, startY, origX, origY }
        pan:         { x: 0, y: 0 },
        zoom:        1,
        nodeCounter: 0,
    };

    // ── DOM Refs ───────────────────────────────────────────────────────
    let canvas, svg, wrap, emptyHint;

    // ── Node Type Config ───────────────────────────────────────────────
    const NODE_TYPES = {
        ad:        { icon: '📣', label: 'Ad / Traffic',     color: '#6366f1' },
        landing:   { icon: '🎯', label: 'Landing Page',     color: '#FF6E4E' },
        optin:     { icon: '📧', label: 'Opt-In Page',      color: '#FF6E4E' },
        sales:     { icon: '💰', label: 'Sales Page',       color: '#E8B84B' },
        order:     { icon: '🛒', label: 'Order Form',       color: '#E8B84B' },
        upsell:    { icon: '⬆',  label: 'Upsell',           color: '#34d399' },
        downsell:  { icon: '⬇',  label: 'Downsell',         color: '#f87171' },
        thankyou:  { icon: '✅', label: 'Thank You',        color: '#34d399' },
        webinar:   { icon: '🎙', label: 'Webinar',          color: '#a78bfa' },
        email:     { icon: '📬', label: 'Email Sequence',   color: '#60a5fa' },
        page:      { icon: '📄', label: 'Page',             color: '#8BA3A9' },
        decision:  { icon: '🔀', label: 'Decision',         color: '#f59e0b' },
    };

    // ── Init ───────────────────────────────────────────────────────────
    function init() {
        canvas   = document.getElementById('fs-canvas');
        svg      = document.getElementById('fs-connections-svg');
        wrap     = document.getElementById('fs-canvas-wrap');
        emptyHint = document.getElementById('fs-canvas-empty');

        if ( ! canvas ) return;

        setupPaletteDrops();
        setupCanvasPan();
        setupZoomButtons();
        setupConnectionMode();
        bindInspector();
        bindSaveButton();
        bindClearButton();
        buildPageDropdown();

        // Load existing canvas data
        loadCanvasData( window.FS?.canvas_data );
    }

    // ── Load Saved Canvas ──────────────────────────────────────────────
    function loadCanvasData( raw ) {
        if ( ! raw || raw === '{}' ) return;
        try {
            const data = typeof raw === 'string' ? JSON.parse( raw ) : raw;
            if ( data.nodes ) {
                data.nodes.forEach( n => createNode( n.type, n.x, n.y, n ) );
            }
            if ( data.connections ) {
                data.connections.forEach( c => drawConnection( c.from, c.to ) );
            }
            updateEmptyHint();
        } catch(e) {
            console.warn( 'FunnelSpark: Could not parse canvas data.', e );
        }
    }

    // ── Palette Drag & Drop ────────────────────────────────────────────
    function setupPaletteDrops() {
        // Palette items: dragstart
        document.querySelectorAll('.fs-palette-node').forEach( el => {
            el.addEventListener('dragstart', e => {
                e.dataTransfer.setData('fs-node-type', el.dataset.type );
                e.dataTransfer.effectAllowed = 'copy';
            });
        });

        // Canvas wrap: drop target
        wrap.addEventListener('dragover', e => { e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; });
        wrap.addEventListener('drop', e => {
            e.preventDefault();
            const type = e.dataTransfer.getData('fs-node-type');
            if ( ! type ) return;

            const rect = wrap.getBoundingClientRect();
            const x    = ( e.clientX - rect.left - state.pan.x ) / state.zoom;
            const y    = ( e.clientY - rect.top  - state.pan.y ) / state.zoom;
            createNode( type, x - 80, y - 30 );
        });
    }

    // ── Create Node ────────────────────────────────────────────────────
    function createNode( type, x, y, data = {} ) {
        const id     = data.id || 'node_' + ( ++state.nodeCounter );
        const config = NODE_TYPES[ type ] || NODE_TYPES.page;
        const label  = data.label || config.label;

        const el = document.createElement('div');
        el.className   = 'fs-node fs-node--' + type;
        el.id          = 'fs-node-' + id;
        el.dataset.id  = id;
        el.style.left  = Math.round(x) + 'px';
        el.style.top   = Math.round(y) + 'px';
        el.style.setProperty('--node-color', config.color );

        el.innerHTML = `
            <div class="fs-node__header">
                <span class="fs-node__icon">${config.icon}</span>
                <span class="fs-node__label">${escHtml(label)}</span>
            </div>
            <div class="fs-node__url" id="fs-node-url-${id}"></div>
            <div class="fs-node__badge" id="fs-badge-${id}" style="display:none;"></div>
            <div class="fs-node__connect-btn" title="Connect to another step">⊕</div>
        `;

        canvas.appendChild( el );

        state.nodes[ id ] = {
            id, type, label,
            url:        data.url        || '',
            source:     data.source     || '',
            notes:      data.notes      || '',
            conversion: data.conversion || false,
            x: Math.round(x),
            y: Math.round(y),
            el,
        };

        if ( type === 'ad' && data.source ) {
            el.querySelector('.fs-node__url').textContent = data.source;
        } else if ( data.url ) {
            el.querySelector('.fs-node__url').textContent = data.url;
        }

        // Drag to reposition
        setupNodeDrag( el, id );

        // Select on click
        el.addEventListener('click', e => {
            if ( e.target.classList.contains('fs-node__connect-btn') ) return;
            selectNode( id );
        });

        // Connect button
        el.querySelector('.fs-node__connect-btn').addEventListener('click', e => {
            e.stopPropagation();
            startConnection( id );
        });

        updateEmptyHint();
        return id;
    }

    // ── Node Drag ──────────────────────────────────────────────────────
    function setupNodeDrag( el, id ) {
        let startX, startY, origX, origY, dragging = false;

        el.addEventListener('mousedown', e => {
            if ( e.target.classList.contains('fs-node__connect-btn') ) return;
            e.stopPropagation();
            dragging = true;
            startX   = e.clientX;
            startY   = e.clientY;
            origX    = state.nodes[id].x;
            origY    = state.nodes[id].y;
            el.classList.add('fs-node--dragging');
        });

        document.addEventListener('mousemove', e => {
            if ( ! dragging ) return;
            const dx = ( e.clientX - startX ) / state.zoom;
            const dy = ( e.clientY - startY ) / state.zoom;
            const newX = Math.round( origX + dx );
            const newY = Math.round( origY + dy );
            state.nodes[id].x = newX;
            state.nodes[id].y = newY;
            el.style.left = newX + 'px';
            el.style.top  = newY + 'px';
            redrawConnections();
        });

        document.addEventListener('mouseup', () => {
            if ( dragging ) {
                dragging = false;
                el.classList.remove('fs-node--dragging');
            }
        });
    }

    // ── Select Node ───────────────────────────────────────────────────
    function selectNode( id ) {
        if ( state.connecting ) {
            if ( state.connecting !== id && ! connectionExists( state.connecting, id ) ) {
                drawConnection( state.connecting, id );
            }
            cancelConnection();
            return;
        }

        // Deselect previous
        if ( state.selected ) {
            const prev = document.getElementById('fs-node-' + state.selected);
            if (prev) prev.classList.remove('fs-node--selected');
        }

        state.selected = id;
        document.getElementById('fs-node-' + id)?.classList.add('fs-node--selected');
        populateInspector( id );
    }

    // ── Connections ────────────────────────────────────────────────────
    function startConnection( id ) {
        state.connecting = id;
        document.getElementById('fs-node-' + id)?.classList.add('fs-node--connecting');
        document.querySelectorAll('.fs-node').forEach( el => {
            if ( el.dataset.id !== id ) el.classList.add('fs-node--connectable');
        });
        canvas.classList.add('fs-canvas--connecting');
    }

    function cancelConnection() {
        if ( state.connecting ) {
            document.getElementById('fs-node-' + state.connecting)?.classList.remove('fs-node--connecting');
            document.querySelectorAll('.fs-node').forEach( el => el.classList.remove('fs-node--connectable') );
            canvas.classList.remove('fs-canvas--connecting');
        }
        state.connecting = null;
    }

    function connectionExists( from, to ) {
        return state.connections.some( c => c.from === from && c.to === to );
    }

    function drawConnection( fromId, toId ) {
        if ( ! state.nodes[fromId] || ! state.nodes[toId] ) return;

        const id = 'conn_' + fromId + '_' + toId;
        const g  = document.createElementNS('http://www.w3.org/2000/svg','g');
        g.id     = id;

        const path   = document.createElementNS('http://www.w3.org/2000/svg','path');
        path.setAttribute('stroke', '#FF6E4E');
        path.setAttribute('stroke-width', '2');
        path.setAttribute('fill', 'none');
        path.setAttribute('stroke-dasharray', '0');
        path.setAttribute('marker-end', 'url(#fs-arrowhead)');

        const label = document.createElementNS('http://www.w3.org/2000/svg','text');
        label.setAttribute('fill', '#8BA3A9');
        label.setAttribute('font-size', '11');
        label.setAttribute('text-anchor', 'middle');
        label.setAttribute('dominant-baseline', 'middle');

        g.appendChild(path);
        g.appendChild(label);
        svg.appendChild(g);

        // Delete on double-click
        g.addEventListener('dblclick', () => {
            state.connections = state.connections.filter( c => ! ( c.from === fromId && c.to === toId ) );
            g.remove();
        });

        state.connections.push({ from: fromId, to: toId, el: g });
        updateConnectionPath({ from: fromId, to: toId, el: g });
        ensureArrowhead();
    }

    function ensureArrowhead() {
        if ( svg.querySelector('#fs-arrowhead') ) return;
        const defs   = document.createElementNS('http://www.w3.org/2000/svg','defs');
        const marker = document.createElementNS('http://www.w3.org/2000/svg','marker');
        marker.setAttribute('id','fs-arrowhead');
        marker.setAttribute('markerWidth','10');
        marker.setAttribute('markerHeight','7');
        marker.setAttribute('refX','9');
        marker.setAttribute('refY','3.5');
        marker.setAttribute('orient','auto');
        const poly = document.createElementNS('http://www.w3.org/2000/svg','polygon');
        poly.setAttribute('points','0 0, 10 3.5, 0 7');
        poly.setAttribute('fill','#FF6E4E');
        marker.appendChild(poly);
        defs.appendChild(marker);
        svg.insertBefore(defs, svg.firstChild);
    }

    function updateConnectionPath( conn ) {
        const fn = state.nodes[conn.from];
        const tn = state.nodes[conn.to];
        if ( !fn || !tn ) return;

        const fw = 200, fh = 80;
        const x1 = fn.x + fw;
        const y1 = fn.y + fh / 2;
        const x2 = tn.x;
        const y2 = tn.y + fh / 2;
        const cx1 = x1 + Math.abs(x2 - x1) * 0.5;
        const cx2 = x2 - Math.abs(x2 - x1) * 0.5;

        const path  = conn.el.querySelector('path');
        const label = conn.el.querySelector('text');
        path.setAttribute('d', `M${x1},${y1} C${cx1},${y1} ${cx2},${y2} ${x2},${y2}`);

        const mx = (x1 + x2) / 2;
        const my = (y1 + y2) / 2 - 10;
        label.setAttribute('x', mx);
        label.setAttribute('y', my);
    }

    function redrawConnections() {
        state.connections.forEach( conn => updateConnectionPath(conn) );
    }

    // ── Canvas Pan ─────────────────────────────────────────────────────
    function setupCanvasPan() {
        let panning = false, startX, startY;

        wrap.addEventListener('mousedown', e => {
            if ( e.target !== wrap && e.target !== canvas && e.target !== svg ) return;
            panning = true;
            startX  = e.clientX - state.pan.x;
            startY  = e.clientY - state.pan.y;
            wrap.style.cursor = 'grabbing';
        });

        document.addEventListener('mousemove', e => {
            if ( !panning ) return;
            state.pan.x = e.clientX - startX;
            state.pan.y = e.clientY - startY;
            applyTransform();
        });

        document.addEventListener('mouseup', () => {
            panning = false;
            wrap.style.cursor = '';
        });

        wrap.addEventListener('wheel', e => {
            e.preventDefault();
            state.pan.x -= e.deltaX;
            state.pan.y -= e.deltaY;
            applyTransform();
        }, { passive: false });

        // Cancel connection on canvas click
        wrap.addEventListener('click', e => {
            if ( state.connecting && ( e.target === wrap || e.target === canvas || e.target === svg ) ) {
                cancelConnection();
            }
            if ( e.target === wrap || e.target === canvas ) {
                deselectNode();
            }
        });
    }

    function deselectNode() {
        if ( state.selected ) {
            document.getElementById('fs-node-' + state.selected)?.classList.remove('fs-node--selected');
        }
        state.selected = null;
        document.getElementById('fs-inspector').style.display      = 'none';
        document.getElementById('fs-inspector-empty').style.display = '';
    }

    function applyTransform() {
        const t = `translate(${state.pan.x}px, ${state.pan.y}px) scale(${state.zoom})`;
        canvas.style.transform = t;
        svg.style.transform    = t;
    }

    // ── Zoom ───────────────────────────────────────────────────────────
    function setupZoomButtons() {
        document.getElementById('fs-zoom-in')?.addEventListener('click',  () => setZoom( state.zoom + 0.15 ));
        document.getElementById('fs-zoom-out')?.addEventListener('click', () => setZoom( state.zoom - 0.15 ));
        document.getElementById('fs-zoom-fit')?.addEventListener('click', fitToScreen);
    }

    function setZoom( z ) {
        state.zoom = Math.min( 2, Math.max( 0.3, parseFloat(z.toFixed(2)) ) );
        applyTransform();
    }

    function fitToScreen() {
        if ( Object.keys( state.nodes ).length === 0 ) return;
        const xs   = Object.values(state.nodes).map(n=>n.x);
        const ys   = Object.values(state.nodes).map(n=>n.y);
        const minX = Math.min(...xs), maxX = Math.max(...xs) + 200;
        const minY = Math.min(...ys), maxY = Math.max(...ys) + 80;
        const ww   = wrap.clientWidth, wh = wrap.clientHeight;
        const z    = Math.min( ww / (maxX - minX + 80), wh / (maxY - minY + 80), 1 );
        state.zoom  = parseFloat(z.toFixed(2));
        state.pan.x = (ww - (maxX - minX) * z) / 2 - minX * z;
        state.pan.y = (wh - (maxY - minY) * z) / 2 - minY * z;
        applyTransform();
    }

    // ── Connection Mode Setup ──────────────────────────────────────
    function setupConnectionMode() {
        // Escape cancels an in-progress connection
        document.addEventListener('keydown', e => {
            if ( e.key === 'Escape' && state.connecting ) {
                cancelConnection();
            }
        });
    }

    // ── Page Dropdown ─────────────────────────────────────────────────
    function buildPageDropdown() {
        const sel = document.getElementById('fs-page-picker');
        if ( !sel ) return;

        const pages = window.FS?.pages;
        if ( !pages || !pages.length ) {
            sel.closest('.fs-field').style.display = 'none';
            return;
        }

        pages.forEach( function(p) {
            const opt = document.createElement('option');
            opt.value       = p.url;
            opt.textContent = p.title;
            sel.appendChild(opt);
        });

        sel.addEventListener('change', function() {
            if ( this.value ) {
                document.getElementById('fs-node-url').value = this.value;
            }
        });
    }

    // ── Source Dropdown (Ad / Traffic nodes) ──────────────────────────
    function loadSourceDropdown( selectedSource ) {
        const sel  = document.getElementById('fs-node-source');
        const hint = document.getElementById('fs-source-hint');
        if ( !sel ) return;

        if ( ga4Sources !== null ) {
            renderSourceOptions( sel, ga4Sources, selectedSource );
            return;
        }

        sel.innerHTML = '<option value="">Loading paid sources…</option>';
        if ( hint ) hint.textContent = '';

        fetch( FS.ajax_url, {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    new URLSearchParams({
                action:     'fs_get_ga4_sources',
                nonce:      FS.nonce,
                date_range: document.getElementById('fs-date-range')?.value || '30daysAgo',
            }),
        })
        .then( r => r.json() )
        .then( resp => {
            if ( resp.success && resp.data.sources && resp.data.sources.length ) {
                ga4Sources = resp.data.sources;
                renderSourceOptions( sel, ga4Sources, selectedSource );
            } else {
                ga4Sources = [];
                sel.innerHTML = '<option value="">— no paid sources found —</option>';
                if ( hint ) hint.textContent = 'No paid traffic found in GA4 for the selected date range.';
            }
        })
        .catch( () => {
            sel.innerHTML = '<option value="">— error loading sources —</option>';
        });
    }

    function renderSourceOptions( sel, sources, selectedSource ) {
        const hint = document.getElementById('fs-source-hint');
        sel.innerHTML = '<option value="">— select source —</option>';
        sources.forEach( s => {
            const opt = document.createElement('option');
            opt.value       = s.source;
            opt.textContent = s.label + ' (' + s.sessions.toLocaleString() + ' sessions)';
            if ( s.source === selectedSource ) opt.selected = true;
            sel.appendChild( opt );
        });
        if ( hint ) hint.textContent = 'Paid sources (cpc, paid, paidsocial) from GA4.';
    }

    // ── Inspector ─────────────────────────────────────────────────────
    function bindInspector() {
        document.getElementById('fs-update-node')?.addEventListener('click', () => {
            if ( !state.selected ) return;
            const n  = state.nodes[ state.selected ];
            const el = document.getElementById('fs-node-' + state.selected);

            n.label      = document.getElementById('fs-node-label').value.trim() || n.label;
            n.notes      = document.getElementById('fs-node-notes').value.trim();
            n.conversion = document.getElementById('fs-node-conversion').checked;

            if ( n.type === 'ad' ) {
                const sourceSel = document.getElementById('fs-node-source');
                n.source = sourceSel ? sourceSel.value : '';
                n.url    = '';
                if ( el ) {
                    el.querySelector('.fs-node__label').textContent = n.label;
                    el.querySelector('.fs-node__url').textContent   = n.source;
                }
            } else {
                n.url    = document.getElementById('fs-node-url').value.trim();
                n.source = '';
                if ( el ) {
                    el.querySelector('.fs-node__label').textContent = n.label;
                    el.querySelector('.fs-node__url').textContent   = n.url;
                }
            }
        });

        document.getElementById('fs-delete-node')?.addEventListener('click', () => {
            if ( !state.selected ) return;
            deleteNode( state.selected );
        });
    }

    function populateInspector( id ) {
        const n    = state.nodes[ id ];
        const isAd = n.type === 'ad';

        document.getElementById('fs-node-label').value      = n.label;
        document.getElementById('fs-node-notes').value      = n.notes;
        document.getElementById('fs-node-conversion').checked = !! n.conversion;

        document.getElementById('fs-page-fields').style.display  = isAd ? 'none' : '';
        document.getElementById('fs-source-field').style.display = isAd ? '' : 'none';

        if ( isAd ) {
            loadSourceDropdown( n.source || '' );
        } else {
            document.getElementById('fs-node-url').value = n.url;
            const sel = document.getElementById('fs-page-picker');
            if ( sel ) {
                const match = Array.from( sel.options ).find( o => o.value && o.value === n.url );
                sel.value = match ? n.url : '';
            }
        }

        document.getElementById('fs-inspector').style.display       = '';
        document.getElementById('fs-inspector-empty').style.display = 'none';
    }

    function deleteNode( id ) {
        state.nodes[ id ]?.el?.remove();
        delete state.nodes[ id ];

        state.connections = state.connections.filter( c => {
            if ( c.from === id || c.to === id ) { c.el?.remove(); return false; }
            return true;
        });

        deselectNode();
        updateEmptyHint();
    }

    // ── Clear Canvas ───────────────────────────────────────────────────
    function bindClearButton() {
        document.getElementById('fs-clear-canvas')?.addEventListener('click', () => {
            if ( ! confirm('Clear all steps and connections?') ) return;
            Object.keys(state.nodes).forEach( id => {
                state.nodes[id]?.el?.remove();
            });
            state.nodes       = {};
            state.connections.forEach( c => c.el?.remove() );
            state.connections = [];
            state.selected    = null;
            state.nodeCounter = 0;
            document.getElementById('fs-inspector').style.display       = 'none';
            document.getElementById('fs-inspector-empty').style.display = '';
            updateEmptyHint();
        });
    }

    // ── Save ───────────────────────────────────────────────────────────
    function bindSaveButton() {
        document.getElementById('fs-save-btn')?.addEventListener('click', saveCanvas);

        // Keyboard shortcut Ctrl/Cmd+S
        document.addEventListener('keydown', e => {
            if ( (e.ctrlKey || e.metaKey) && e.key === 's' ) {
                e.preventDefault();
                saveCanvas();
            }
        });
    }

    function saveCanvas() {
        const title = document.getElementById('fs-funnel-title')?.value.trim() || 'Untitled Funnel';
        const data  = serializeCanvas();

        setStatus('Saving…', '');
        document.getElementById('fs-save-btn').disabled = true;

        fetch( FS.ajax_url, {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    new URLSearchParams({
                action:      'fs_save_funnel',
                nonce:       FS.nonce,
                funnel_id:   FS.funnel_id || 0,
                title,
                canvas_data: JSON.stringify(data),
            }),
        })
        .then( r => r.json() )
        .then( resp => {
            if ( resp.success ) {
                FS.funnel_id = resp.data.funnel_id;
                // Update URL without reload
                const url = new URL(window.location.href);
                url.searchParams.set('page', 'funnelspark-editor');
                url.searchParams.set('funnel_id', resp.data.funnel_id);
                history.replaceState({}, '', url.toString());
                setStatus('✓ Saved', 'success');
            } else {
                setStatus('✗ ' + (resp.data || 'Save failed'), 'error');
            }
        })
        .catch( () => setStatus('✗ Network error', 'error') )
        .finally( () => { document.getElementById('fs-save-btn').disabled = false; });
    }

    function serializeCanvas() {
        return {
            nodes: Object.values(state.nodes).map( n => ({
                id: n.id, type: n.type, label: n.label,
                url: n.url, source: n.source, notes: n.notes,
                conversion: n.conversion, x: n.x, y: n.y,
            })),
            connections: state.connections.map( c => ({ from: c.from, to: c.to }) ),
        };
    }

    // ── GA4 Overlay (called from ga4-overlay.js) ───────────────────────
    window.FunnelSparkCanvas = {
        getNodes:         ()  => state.nodes,
        getConnections:   ()  => state.connections.map( c => ({ from: c.from, to: c.to }) ),
        resetSourceCache: ()  => { ga4Sources = null; },
        showBadge:   (id, data) => {
            const badge  = document.getElementById('fs-badge-' + id);
            if ( !badge ) return;
            const isConv = !! state.nodes[id]?.conversion;
            const cvr    = data.conversion_rate ?? 0;
            const cls    = isConv ? ( cvr >= 5 ? 'good' : cvr >= 2 ? 'avg' : 'low' ) : '';
            badge.className = 'fs-node__badge' + ( cls ? ' fs-node__badge--' + cls : '' );
            badge.innerHTML = `
                <div class="fs-badge-row"><span class="fs-badge-val">${data.sessions.toLocaleString()}</span><span class="fs-badge-lbl">Sessions</span></div>
                ${ isConv ? `
                <div class="fs-badge-row"><span class="fs-badge-val">${data.conversions.toLocaleString()}</span><span class="fs-badge-lbl">Conversions</span></div>
                <div class="fs-badge-row fs-badge-row--cvr"><span class="fs-badge-val">${cvr}%</span><span class="fs-badge-lbl">CVR</span></div>
                ` : '' }
            `;
            badge.style.display = '';
        },
        showAdBadge: (id, sourceData, totalSessions) => {
            const badge = document.getElementById('fs-badge-' + id);
            if ( !badge ) return;
            const pct = totalSessions > 0 ? Math.round( (sourceData.sessions / totalSessions) * 100 ) : 0;
            badge.className = 'fs-node__badge fs-node__badge--ad';
            badge.innerHTML = `
                <div class="fs-badge-row"><span class="fs-badge-val">${sourceData.sessions.toLocaleString()}</span><span class="fs-badge-lbl">Sessions</span></div>
                <div class="fs-badge-row fs-badge-row--pct"><span class="fs-badge-val">${pct}%</span><span class="fs-badge-lbl">of Traffic</span></div>
            `;
            badge.style.display = '';
        },
        showGa4Summary: showGa4Summary,
    };

    function showGa4Summary( metrics ) {
        const el = document.getElementById('fs-ga4-summary');
        const content = document.getElementById('fs-ga4-summary-content');
        if ( !el || !content ) return;

        const totalSessions = Object.values(metrics).reduce((s,m) => s + m.sessions, 0);
        const totalConvs    = Object.values(metrics).reduce((s,m) => s + m.conversions, 0);
        const overallCvr    = totalSessions > 0 ? ((totalConvs/totalSessions)*100).toFixed(1) : 0;

        content.innerHTML = `
            <div class="fs-ga4-stat"><span>${totalSessions.toLocaleString()}</span><label>Total Sessions</label></div>
            <div class="fs-ga4-stat"><span>${totalConvs.toLocaleString()}</span><label>Conversions</label></div>
            <div class="fs-ga4-stat fs-ga4-stat--highlight"><span>${overallCvr}%</span><label>Overall CVR</label></div>
        `;
        el.style.display = '';
    }

    // ── Helpers ────────────────────────────────────────────────────────
    function updateEmptyHint() {
        if ( emptyHint ) emptyHint.style.display = Object.keys(state.nodes).length ? 'none' : '';
    }

    function setStatus( msg, type ) {
        const el = document.getElementById('fs-save-status');
        if (!el) return;
        el.textContent  = msg;
        el.className    = 'fs-save-status' + (type ? ' fs-save-status--' + type : '');
        if (type === 'success' || type === 'error') {
            setTimeout(() => { el.textContent = ''; el.className = 'fs-save-status'; }, 3000);
        }
    }

    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Kick Off ───────────────────────────────────────────────────────
    if ( document.readyState === 'loading' ) {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
