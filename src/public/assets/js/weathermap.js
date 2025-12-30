/**
 * Zabbix Weathermap - Main JavaScript
 */

(function() {
    'use strict';
    
    // Utility functions
    const WM = {
        /**
         * Make an API request
         */
        api: async function(endpoint, options = {}) {
            const defaults = {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
            };
            
            const config = { ...defaults, ...options };
            
            if (config.body && typeof config.body === 'object') {
                config.body = JSON.stringify(config.body);
            }
            
            try {
                const response = await fetch('/api' + endpoint, config);
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.error || 'API request failed');
                }
                
                return data;
            } catch (error) {
                throw error;
            }
        },
        
        /**
         * Format bytes to human readable
         */
        formatBytes: function(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB'];
            
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        },
        
        /**
         * Format bits per second
         */
        formatBps: function(bps, decimals = 2) {
            if (bps === 0) return '0 bps';
            
            const k = 1000;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];
            
            const i = Math.floor(Math.log(bps) / Math.log(k));
            
            return parseFloat((bps / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        },
        
        /**
         * Debounce function
         */
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },
        
        /**
         * Show notification
         */
        notify: function(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.add('show');
            }, 10);
            
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        },
        
        /**
         * Confirm dialog
         */
        confirm: function(message) {
            return window.confirm(message);
        }
    };

    function wmCreateHoverPanel() {
        let el = document.getElementById('wm-ds-hover');
        if (el) return el;

        el = document.createElement('div');
        el.id = 'wm-ds-hover';
        el.style.position = 'fixed';
        el.style.zIndex = '9999';
        el.style.pointerEvents = 'none';
        el.style.minWidth = '320px';
        el.style.maxWidth = '520px';
        el.style.background = 'rgba(15, 18, 24, 0.92)';
        el.style.border = '1px solid rgba(255,255,255,0.12)';
        el.style.borderRadius = '10px';
        el.style.padding = '10px 12px';
        el.style.color = '#e9eefb';
        el.style.fontFamily = 'Arial, sans-serif';
        el.style.fontSize = '12px';
        el.style.boxShadow = '0 10px 30px rgba(0,0,0,0.35)';
        el.style.display = 'none';

        document.body.appendChild(el);
        return el;
    }

    function wmFormatNum(n) {
        if (!isFinite(n)) return '-';
        const abs = Math.abs(n);
        if (abs >= 1e9) return (n / 1e9).toFixed(2) + 'G';
        if (abs >= 1e6) return (n / 1e6).toFixed(2) + 'M';
        if (abs >= 1e3) return (n / 1e3).toFixed(2) + 'K';
        return n.toFixed(2);
    }

    function wmSparkline(series, width, height, color) {
        const pts = Array.isArray(series) ? series : [];
        if (pts.length < 2) {
            return `<svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}"></svg>`;
        }

        const values = pts.map(p => Number(p.v) || 0);
        let min = Math.min(...values);
        let max = Math.max(...values);
        if (min === max) {
            max = min + 1;
        }

        const dx = width / (pts.length - 1);
        let d = '';
        for (let i = 0; i < pts.length; i++) {
            const x = i * dx;
            const v = Number(pts[i].v) || 0;
            const y = height - ((v - min) / (max - min)) * height;
            d += (i === 0 ? 'M' : 'L') + x.toFixed(2) + ' ' + y.toFixed(2) + ' ';
        }

        return `<svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">
            <path d="${d}" fill="none" stroke="${color}" stroke-width="2" />
        </svg>`;
    }

    async function wmLoadDatasourceHover(selection) {
        const sourceId = Number(selection.sourceId || 0);
        if (!sourceId) {
            throw new Error('Invalid sourceId');
        }

        const params = new URLSearchParams();
        params.set('selection', JSON.stringify({
            hostId: selection.hostId,
            inKey: selection.inKey,
            outKey: selection.outKey,
        }));
        params.set('minutes', '1440');

        const res = await fetch('/api/data-sources/' + encodeURIComponent(String(sourceId)) + '/link-bandwidth?' + params.toString());
        const data = await res.json();
        if (!res.ok || !data || !data.success) {
            throw new Error((data && data.error) ? data.error : 'Failed to load');
        }
        return data.data;
    }

    function wmAttachDatasourceHover() {
        const hover = wmCreateHoverPanel();
        let lastKey = null;
        let lastAbort = null;

        document.addEventListener('mousemove', function(e) {
            if (hover.style.display === 'none') return;
            const x = Math.min(window.innerWidth - 20, e.clientX + 18);
            const y = Math.min(window.innerHeight - 20, e.clientY + 18);
            hover.style.left = x + 'px';
            hover.style.top = y + 'px';
        });

        document.querySelectorAll('area.link').forEach(function(area) {
            area.addEventListener('mouseenter', async function(e) {
                const selectionRaw = area.getAttribute('data-datasource') || '';
                if (!selectionRaw) return;

                let selection;
                try {
                    selection = JSON.parse(selectionRaw);
                } catch (err) {
                    return;
                }

                const key = selectionRaw;
                lastKey = key;
                if (lastAbort) {
                    lastAbort.abort();
                }
                lastAbort = new AbortController();

                hover.style.display = 'block';
                hover.innerHTML = '<div style="opacity:0.85">Loading…</div>';

                try {
                    const payload = await wmLoadDatasourceHover(selection);
                    if (lastKey !== key) return;

                    const inLast = Number(payload?.in?.lastvalue || 0);
                    const outLast = Number(payload?.out?.lastvalue || 0);
                    const inUnits = payload?.in?.units || '';
                    const outUnits = payload?.out?.units || '';

                    const w = 460;
                    const h = 70;
                    const inSvg = wmSparkline(payload?.in?.series || [], w, h, '#59d185');
                    const outSvg = wmSparkline(payload?.out?.series || [], w, h, '#6aa7ff');

                    hover.innerHTML = `
                        <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;margin-bottom:6px;">
                            <div style="font-weight:600;">Link bandwidth (24h)</div>
                            <div style="opacity:0.75;">${new Date().toLocaleString()}</div>
                        </div>
                        <div style="display:flex;gap:10px;align-items:center;margin:6px 0;">
                            <div style="width:70px;opacity:0.9;">IN</div>
                            <div style="font-weight:600;color:#59d185;">${wmFormatNum(inLast)} ${inUnits}</div>
                        </div>
                        <div>${inSvg}</div>
                        <div style="display:flex;gap:10px;align-items:center;margin:10px 0 6px;">
                            <div style="width:70px;opacity:0.9;">OUT</div>
                            <div style="font-weight:600;color:#6aa7ff;">${wmFormatNum(outLast)} ${outUnits}</div>
                        </div>
                        <div>${outSvg}</div>
                    `;
                } catch (err) {
                    if (lastKey !== key) return;
                    hover.innerHTML = '<div style="color:#ff8b8b">' + String(err.message || err) + '</div>';
                }
            });

            area.addEventListener('mouseleave', function() {
                hover.style.display = 'none';
                hover.innerHTML = '';
            });
        });
    }
    
    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        // Add any global initialization here
        wmAttachDatasourceHover();
    });
    
    // Expose to global scope
    window.WM = WM;
})();
