<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Map Editor') ?></title>
    <link rel="stylesheet" href="/assets/css/weathermap.css">
    <link rel="stylesheet" href="/assets/css/editor.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #1a1a2e; color: #fff; overflow: hidden; }
        
        #toolbar {
            display: flex;
            background: #16213e;
            border-bottom: 2px solid #0f3460;
            padding: 0;
            height: 60px;
        }
        #toolbar ul {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            width: 100%;
        }
        #toolbar li {
            padding: 10px 15px;
            cursor: pointer;
            text-align: center;
            font-size: 11px;
            border-right: 1px solid #0f3460;
            transition: background 0.2s;
        }
        #toolbar li:hover { background: #0f3460; }
        #toolbar li.tb_active:hover { background: #e94560; }
        #toolbar .tb_coords {
            margin-left: auto;
            background: #0f3460;
            min-width: 100px;
        }
        #toolbar .tb_help {
            flex: 1;
            text-align: left;
            padding: 15px;
            font-size: 12px;
            color: #888;
            background: #0d0d1a;
        }
        #tb_manualconfig {
            background: #0f203b;
            font-weight: 600;
        }
        #tb_manualconfig:hover {
            background: #1f3a68;
        }
        
        .mainArea {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 20px;
            height: calc(100vh - 60px);
            overflow: auto;
            background: #0d0d1a;
        }
        
        #existingdata, #xycapture {
            border: 2px solid #0f3460;
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
            cursor: crosshair;
        }
        
        /* Dialogs */
        .dlgProperties {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #16213e;
            border: 2px solid #0f3460;
            border-radius: 8px;
            padding: 20px;
            z-index: 1000;
            min-width: 500px;
            max-width: 90vw;
            max-height: 90vh;
            overflow-y: auto;
        }
        .dlgProperties.active { display: block; }
        .dlgProperties h3 {
            margin: 0 0 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #0f3460;
            color: #e94560;
        }
        .dlgProperties table { width: 100%; }
        .dlgProperties td { padding: 8px 5px; vertical-align: middle; }
        .dlgProperties td:first-child { width: 150px; color: #888; }
        .dlgProperties input[type="text"],
        .dlgProperties textarea,
        .dlgProperties select {
            width: 100%;
            padding: 8px;
            background: #1a1a2e;
            border: 1px solid #0f3460;
            border-radius: 4px;
            color: #fff;
        }
        .dlgProperties input:focus,
        .dlgProperties textarea:focus,
        .dlgProperties select:focus {
            outline: none;
            border-color: #e94560;
        }
        .dlgButtons {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #0f3460;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .dlgButtons a, .dlgButtons button {
            padding: 8px 16px;
            background: #0f3460;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
        }
        .dlgButtons a:hover, .dlgButtons button:hover { background: #e94560; }
        .dlgButtons .wm_submit { background: #28a745; }
        .dlgButtons .wm_cancel { background: #6c757d; }
        
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 999;
            pointer-events: none;
        }
        .overlay.active { display: block; }
        
        /* Back button */
        .back-btn {
            background: #6c757d !important;
            margin-right: auto !important;
        }

        .manual-config-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(5, 8, 20, 0.82);
            z-index: 1500;
        }
        .manual-config-overlay.active { display: block; }

        .manual-config-panel {
            position: fixed;
            top: 5vh;
            left: 50%;
            transform: translateX(-50%);
            width: min(1100px, 90vw);
            height: 90vh;
            background: #10182c;
            border: 2px solid #1f3a68;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.65);
            padding: 20px;
            z-index: 1550;
            display: none;
            flex-direction: column;
        }
        .manual-config-panel.active {
            display: flex;
        }
        .manual-config-panel header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .manual-config-panel header h3 {
            margin: 0;
            font-size: 20px;
            color: #f4f6fb;
        }
        .manual-config-panel header .actions {
            display: flex;
            gap: 10px;
        }
        .manual-config-panel textarea {
            flex: 1;
            width: 100%;
            margin-top: 10px;
            border-radius: 8px;
            border: 1px solid #233a63;
            background: #0b1222;
            color: #e9eefc;
            font-family: 'JetBrains Mono', 'Fira Code', monospace;
            font-size: 13px;
            line-height: 1.5;
            padding: 16px;
            resize: none;
        }
        .manual-config-panel footer {
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #8da1cc;
            font-size: 13px;
        }
        .manual-config-status {
            min-height: 18px;
        }
        .btn {
            cursor: pointer;
        }
        .btn-icon {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        /* Icon Grid */
        .icon-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 8px;
            max-height: 200px;
            overflow-y: auto;
            padding: 8px;
            border: 1px solid #444;
            background: #2a2a3e;
            border-radius: 4px;
        }
        
        .icon-item {
            cursor: pointer;
            border: 2px solid transparent;
            border-radius: 4px;
            padding: 4px;
            text-align: center;
            transition: all 0.2s;
        }
        
        .icon-item:hover {
            background: #3a3a4e;
            border-color: #666;
        }
        
        .icon-item.selected {
            background: #4a4a5e;
            border-color: #e94560;
        }
        
        .icon-preview {
            width: 48px;
            height: 48px;
            margin: 0 auto 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #1a1a2e;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .icon-preview.no-icon {
            font-size: 10px;
            color: #888;
            padding: 4px;
            text-align: center;
        }
        
        .icon-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .icon-name {
            font-size: 10px;
            color: #ccc;
            word-break: break-all;
            line-height: 1.2;
        }

        .wm-area-editor {
            position: absolute;
            border: 2px solid #e94560;
            z-index: 900;
            pointer-events: none;
        }

        .wm-area-editor .wm-handle {
            position: absolute;
            width: 10px;
            height: 10px;
            background: #e94560;
            border: 2px solid #ffffff;
            z-index: 901;
            pointer-events: auto;
        }

        .wm-area-editor .wm-handle.nw { left: -7px; top: -7px; cursor: nwse-resize; }
        .wm-area-editor .wm-handle.ne { right: -7px; top: -7px; cursor: nesw-resize; }
        .wm-area-editor .wm-handle.sw { left: -7px; bottom: -7px; cursor: nesw-resize; }
        .wm-area-editor .wm-handle.se { right: -7px; bottom: -7px; cursor: nwse-resize; }

        .wm-map-resize-handle {
            position: absolute;
            z-index: 950;
            background: transparent;
            pointer-events: auto;
        }

        .wm-map-resize-handle.e {
            top: 0;
            right: -6px;
            width: 12px;
            height: 100%;
            cursor: ew-resize;
        }

        .wm-map-resize-handle.s {
            left: 0;
            bottom: -6px;
            height: 12px;
            width: 100%;
            cursor: ns-resize;
        }

        .wm-map-resize-handle.se {
            right: -8px;
            bottom: -8px;
            width: 16px;
            height: 16px;
            cursor: nwse-resize;
        }

        .wm-map-resize-outline {
            position: absolute;
            border: 2px dashed rgba(40, 167, 69, 0.9);
            background: rgba(40, 167, 69, 0.06);
            z-index: 940;
            pointer-events: none;
        }

        .wm-map-resize-outline .wm-map-resize-label {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.55);
            border: 1px solid rgba(40, 167, 69, 0.9);
            color: #e7ffe7;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-family: Arial, sans-serif;
            letter-spacing: 0.4px;
            white-space: nowrap;
        }
    </style>
    <script>
        var editor_url = '/editor/action/<?= $mapId ?>';
        var mapId = <?= $mapId ?>;
        var mapFile = '<?= htmlspecialchars($mapFile) ?>';
        var manualConfigPanel = null;
        var manualConfigOverlay = null;
        var manualConfigTextarea = null;
        var manualConfigStatus = null;
        
        var sessionMessageOk = 'Ok';
        var sessionMessageTitle = 'Operation successful';
        var sessionMessageSave = 'The operation was successful.';
        var sessionMessagePause = 'Pause';
        var moveNodeHelp = 'Click on the map where you would like to move the node to.';
        var viaLinkHelp = 'Click on the map via which point you want to redirect link.';
        var addLinkHelp = 'Click on the first node for the start of the link.';
        var timeStHelp = 'Click on the map where you would like to put the timestamp.';
        var posLegendHelp = 'Click on the map where you would like to put the legend.';
        var addNodeHelp = 'Click on the map where you would like to add a new node.';
        var delNodeWarning = 'WARNING: This will delete the node and all connected links.';
        var delNodeTitle = 'Delete Node';
        var delLinkWarning = 'WARNING: This will delete the link.';
        var delLinkTitle = 'Delete Link';
        var txtCancel = 'Cancel';
        var txtDelLink = 'Delete Link';
        var txtDelNode = 'Delete Node';
        var txtPosition = 'Position';
        var txtNodeActions = 'Node Actions';
        var txtLinkActions = 'Link Actions';
        var txtMove = 'Move';
        var txtClone = 'Clone';
        var txtEdit = 'Edit';
        var txtDelete = 'Delete';
        var txtVia = 'Via';
        var txtTidy = 'Tidy';
        var txtProperties = 'Properties';
        
        var manualConfigPanel = null;
        var manualConfigOverlay = null;
        var manualConfigTextarea = null;
        var manualConfigStatus = null;
        
        var helptexts = {
            'link_default': 'Click on a link to edit its properties',
            'map_default': 'Use the toolbar to add nodes and links',
            'node_default': 'Click on a node to edit its properties',
            'tb_default': 'Click a Node or Link to edit, or use toolbar buttons'
        };
        
        // Node and Link data from WeatherMap
        <?php echo $wmap->asJS(); ?>

        function setManualConfigStatus(message, type) {
            if (!manualConfigStatus) return;
            manualConfigStatus.textContent = message;
            manualConfigStatus.style.color = type === 'error' ? '#ff6b81' : '#8da1cc';
        }

        function toggleManualConfig(show) {
            if (!manualConfigPanel || !manualConfigOverlay) return;
            const shouldShow = typeof show === 'boolean' ? show : !manualConfigPanel.classList.contains('active');
            manualConfigPanel.classList.toggle('active', shouldShow);
            manualConfigOverlay.classList.toggle('active', shouldShow);
            manualConfigPanel.setAttribute('aria-hidden', (!shouldShow).toString());
            if (shouldShow) {
                loadManualConfig();
            }
        }

        function loadManualConfig() {
            if (!manualConfigTextarea) return;
            setManualConfigStatus('Loading latest config...', 'info');
            fetch('/editor/config/' + mapId)
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.error || 'Failed to fetch config');
                    }
                    manualConfigTextarea.value = data.config;
                    setManualConfigStatus('Loaded from disk just now.', 'info');
                })
                .catch(err => {
                    setManualConfigStatus('Load failed: ' + err.message, 'error');
                });
        }

        function saveManualConfig() {
            if (!manualConfigTextarea) return;
            const payload = manualConfigTextarea.value;
            setManualConfigStatus('Saving...', 'info');
            fetch('/editor/save/' + mapId, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'config=' + encodeURIComponent(payload)
            })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.error || 'Save failed');
                    }
                    setManualConfigStatus('Saved successfully.', 'info');
                    reloadMapImage();
                })
                .catch(err => {
                    setManualConfigStatus('Save failed: ' + err.message, 'error');
                });
        }
    </script>
</head>
<body id="mainView" class="mainView">
    <div id="toolbar">
        <ul>
            <li class="back-btn" onclick="window.location.href='/editor'">← Back to<br>Map List</li>
            <li class="tb_active" id="tb_addnode">Add<br>Node</li>
            <li class="tb_active" id="tb_addlink">Add<br>Link</li>
            <li class="tb_active" id="tb_poslegend">Position<br>Legend</li>
            <li class="tb_active" id="tb_postime">Position<br>Timestamp</li>
            <li class="tb_active" id="tb_mapprops">Map<br>Properties</li>
            <li class="tb_active" id="tb_manualconfig">Manual<br>Config</li>
            <li class="tb_coords" id="tb_coords">Position<br>---, ---</li>
            <li class="tb_help">
                <span id="tb_help">Click on a Node or Link to edit it, or use the toolbar buttons to add new elements</span>
            </li>
        </ul>
    </div>
    
    <form id="frmMain" method="post" onsubmit="return false;">
        <div class="mainArea">
            <img id="xycapture" name="xycapture" 
                   data-width="<?= $wmap->width ?>" 
                   data-height="<?= $wmap->height ?>" 
                   style="display:none" 
                   src="/editor/draw/<?= $mapId ?>?t=<?= time() ?>" />
            <img id="existingdata" name="existingdata" 
                 data-width="<?= $wmap->width ?>" 
                 data-height="<?= $wmap->height ?>" 
                 src="/editor/draw/<?= $mapId ?>?t=<?= time() ?>" 
                 usemap="#weathermap_imap" />
            <input id="x" name="x" type="hidden" />
            <input id="y" name="y" type="hidden" />
            <input id="action" name="action" type="hidden" value="" />
            <input id="param" name="param" type="hidden" value="" />
            <input id="param2" name="param2" type="hidden" value="" />
        </div>
        
        <div class="mapData" id="mapData">
            <?php
            // Generate imagemap
            $wmap->DrawMap('null');
            $wmap->htmlstyle = 'editor';
            $wmap->PreloadMapHTML();
            echo $wmap->SortedImagemap('weathermap_imap');
            ?>
        </div>
        
    </form>
    
    <div class="overlay" id="overlay"></div>
    <div class="manual-config-overlay" id="manualConfigOverlay"></div>
    <section class="manual-config-panel" id="manualConfigPanel" aria-hidden="true">
        <header>
            <div>
                <h3>Manual Configuration Editor</h3>
                <p style="margin:4px 0 0;font-size:13px;color:#8da1cc;">Edit the raw WeatherMap configuration directly. Remember to save your changes.</p>
            </div>
            <div class="actions">
                <button class="btn btn-info btn-sm btn-icon" type="button" id="manualConfigRefresh">
                    ⟳ Refresh
                </button>
                <button class="btn btn-success btn-sm btn-icon" type="button" id="manualConfigSave">
                    💾 Save
                </button>
                <button class="btn btn-sm" type="button" id="manualConfigClose">✕ Close</button>
            </div>
        </header>
        <textarea id="manualConfigTextarea"><?= htmlspecialchars($mapConfig ?? '') ?></textarea>
        <footer>
            <span class="manual-config-status" id="manualConfigStatus">Loaded local copy.</span>
            <span>Map file: <code><?= htmlspecialchars($mapFile) ?></code></span>
        </footer>
    </section>
    
    <!-- Node Properties Dialog -->
    <div id="dlgNodeProperties" class="dlgProperties">
        <div class="dlgTitleRow">
            <div class="dlgTitle">Node Properties</div>
            <div id="node_id_display" class="node-id-display"></div>
        </div>
        <table>
            <tr>
                <td><input id="node_name" name="node_name" type="hidden" /></td>
                <td><input id="node_new_name" name="node_new_name" type="hidden" /></td>
            </tr>
            <tr>
                <td>Position</td>
                <td>
                    <input id="node_x" name="node_x" type="text" style="width:60px;display:inline" />, 
                    <input id="node_y" name="node_y" type="text" style="width:60px;display:inline" />
                </td>
            </tr>
            <tr>
                <td>Label</td>
                <td><input id="node_label" name="node_label" type="text" /></td>
            </tr>
            <tr>
                <td>Icon</td>
                <td>
                    <input type="hidden" id="node_iconfilename" name="node_iconfilename" value="--NONE--">
                    <div id="icon-grid" class="icon-grid">
                        <div class="icon-item" data-icon="--NONE--">
                            <div class="icon-preview no-icon">No Icon</div>
                            <div class="icon-name">Label Only</div>
                        </div>
                        <?php foreach ($imageList as $img): ?>
                        <div class="icon-item" data-icon="<?= htmlspecialchars($img) ?>">
                            <div class="icon-preview">
                                <img src="/objects/<?= htmlspecialchars(basename($img)) ?>" alt="<?= htmlspecialchars(basename($img)) ?>">
                            </div>
                            <div class="icon-name"><?= htmlspecialchars(basename($img)) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td>Info URL</td>
                <td><input id="node_infourl" name="node_infourl" type="text" /></td>
            </tr>
            <tr>
                <td>Hover URL</td>
                <td><textarea id="node_hover" name="node_hover" rows="2"></textarea></td>
            </tr>
        </table>
        <div class="dlgButtons">
            <a id="node_move">Move</a>
            <a id="node_delete">Delete</a>
            <a id="node_clone">Clone</a>
            <a class="wm_cancel" id="tb_node_cancel">Cancel</a>
            <a class="wm_submit" id="tb_node_submit">Save</a>
        </div>
    </div>
    
    <!-- Link Properties Dialog -->
    <div id="dlgLinkProperties" class="dlgProperties">
        <div class="dlgTitleRow">
            <div class="dlgTitle">Link Properties</div>
            <div id="link_id_display" class="node-id-display"></div>
        </div>
        <table>
            <tr>
                <td><input id="link_name" name="link_name" type="hidden" /></td>
            </tr>
            <tr>
                <td colspan="2">
                    <input id="link_datasource" name="link_datasource" type="hidden" />
                </td>
            </tr>
            <tr id="bw_row_combined">
                <td>Bandwidth In/Out</td>
                <td>
                    <input id="link_bandwidth_in" name="link_bandwidth_in" type="text" style="width:120px" /> bits/sec
                    <label style="margin-left:15px;cursor:pointer;">
                        <input id="link_bandwidth_out_cb" name="link_bandwidth_out_cb" type="checkbox" value="symmetric" checked /> Out same as In
                    </label>
                </td>
            </tr>
            <tr id="bw_row_in" style="display:none;">
                <td>Bandwidth In</td>
                <td>
                    <input id="link_bandwidth_in_sep" name="link_bandwidth_in_sep" type="text" style="width:120px" /> bits/sec
                    <label style="margin-left:15px;cursor:pointer;">
                        <input id="link_bandwidth_out_cb_sep" type="checkbox" /> Out same as In
                    </label>
                </td>
            </tr>
            <tr id="bw_row_out" style="display:none;">
                <td>Bandwidth Out</td>
                <td><input id="link_bandwidth_out" name="link_bandwidth_out" type="text" style="width:120px" /> bits/sec</td>
            </tr>
            <tr id="ds_row_manual">
                <td>Data Source</td>
                <td style="position:relative;">
                    <textarea id="link_target" name="link_target" rows="2" style="width:calc(100% - 40px);"></textarea>
                    <button type="button" id="ds_toggle_picker" style="position:absolute;right:5px;top:5px;width:30px;height:30px;border:1px solid #555;background:#2a2a3e;color:#6aa7ff;cursor:pointer;border-radius:4px;font-size:16px;" title="Open Datasource Picker">⚙</button>
                </td>
            </tr>
            <tr id="ds_row_picker" style="display:none;">
                <td>Datasource Picker</td>
                <td>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <select id="ds_source" style="min-width:160px"></select>
                        <select id="ds_host" style="min-width:220px"></select>
                        <select id="ds_iface" style="min-width:220px"></select>
                        <button type="button" id="ds_apply" style="display:none;padding:6px 16px;background:#28a745;color:#fff;border:none;border-radius:4px;cursor:pointer;font-weight:600;">Apply</button>
                        <button type="button" id="ds_cancel" style="padding:6px 16px;background:#6c757d;color:#fff;border:none;border-radius:4px;cursor:pointer;">Cancel</button>
                    </div>
                </td>
            </tr>
            <tr>
                <td>Link Width</td>
                <td><input id="link_width" name="link_width" type="text" style="width:60px" /> pixels</td>
            </tr>
            <tr>
                <td>Info URL</td>
                <td><input id="link_infourl" name="link_infourl" type="text" /></td>
            </tr>
            <tr>
                <td>Hover URL</td>
                <td><textarea id="link_hover" name="link_hover" rows="2"></textarea></td>
            </tr>
            <tr>
                <td>IN Comment</td>
                <td><input id="link_commentin" name="link_commentin" type="text" /></td>
            </tr>
            <tr>
                <td>OUT Comment</td>
                <td><input id="link_commentout" name="link_commentout" type="text" /></td>
            </tr>
        </table>
        <div class="dlgButtons">
            <a id="link_delete">Delete</a>
            <a id="link_tidy">Tidy</a>
            <a id="link_via">Via</a>
            <a class="wm_cancel" id="tb_link_cancel">Cancel</a>
            <a class="wm_submit" id="tb_link_submit">Save</a>
        </div>
    </div>
    
    <!-- Map Properties Dialog -->
    <div id="dlgMapProperties" class="dlgProperties">
        <h3>Map Properties</h3>
        <table>
            <tr>
                <td>Map Title</td>
                <td><input id="map_title" name="map_title" type="text" value="<?= htmlspecialchars($wmap->title) ?>" /></td>
            </tr>
            <tr>
                <td>Legend Text</td>
                <td><input id="map_legend" name="map_legend" type="text" value="<?= htmlspecialchars($wmap->keytext['DEFAULT'] ?? '') ?>" /></td>
            </tr>
            <tr>
                <td>Background</td>
                <td>
                    <select id="map_bgfile" name="map_bgfile">
                        <option value="--NONE--">No Background</option>
                        <?php foreach ($bgList as $bg): ?>
                        <option value="<?= htmlspecialchars($bg) ?>" <?= ($wmap->background == $bg) ? 'selected' : '' ?>><?= htmlspecialchars(basename($bg)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td>Timestamp Text</td>
                <td><input id="map_stamp" name="map_stamp" type="text" value="<?= htmlspecialchars($wmap->stamptext) ?>" /></td>
            </tr>
            <tr>
                <td>Map Size</td>
                <td>
                    <input id="map_width" name="map_width" type="text" style="width:60px;display:inline" value="<?= $wmap->width ?>" /> x 
                    <input id="map_height" name="map_height" type="text" style="width:60px;display:inline" value="<?= $wmap->height ?>" /> pixels
                </td>
            </tr>
        </table>
        <div class="dlgButtons">
            <a class="wm_cancel" id="tb_map_cancel">Cancel</a>
            <a class="wm_submit" id="tb_map_submit">Save</a>
        </div>
    </div>

    <script>
    // Simple editor implementation
    var currentAction = '';
    var selectedNode = null;
    var selectedLink = null;
    var lastClickedNodeArea = null;
    var lastClickedNodeKey = null;
    var areaEditor = { el: null, areaEl: null, dragging: null, start: null, rect: null };
    var mapResize = { active: false, mode: null, start: null, outlineEl: null };
    
    function showDialog(id) {
        document.getElementById('overlay').classList.add('active');
        document.getElementById(id).classList.add('active');
    }
    
    function hideAllDialogs() {
        document.getElementById('overlay').classList.remove('active');
        document.querySelectorAll('.dlgProperties').forEach(d => d.classList.remove('active'));
        // Clear any node highlights
        document.querySelectorAll('.node-highlight').forEach(el => el.remove());
        stopAreaEditor();
        document.getElementById('action').value = '';
        currentAction = '';
    }

    function parseCoordsToRect(coordsStr) {
        var raw = String(coordsStr || '').split(',').map(v => parseInt(v, 10)).filter(v => !isNaN(v));
        var xs = [];
        var ys = [];
        for (var i = 0; i + 1 < raw.length; i += 2) {
            xs.push(raw[i]);
            ys.push(raw[i + 1]);
        }
        if (!xs.length || !ys.length) return null;
        return {
            minX: Math.min.apply(null, xs),
            maxX: Math.max.apply(null, xs),
            minY: Math.min.apply(null, ys),
            maxY: Math.max.apply(null, ys)
        };
    }

    function pickBestNodeAreaFromClicked(areaEl) {
        if (!areaEl) return null;

        // Weathermap editor ids typically look like: NODE:N123:0 (or :1)
        var id = areaEl.id || '';
        var m = id.match(/^NODE:(N\d+):/);
        var nodeKey = m ? m[1] : null;
        if (!nodeKey) return areaEl;

        lastClickedNodeKey = nodeKey;

        var root = document.getElementById('mapData') || document;
        var candidates = Array.from(root.querySelectorAll("area.node"))
            .filter(a => (a.id || '').startsWith('NODE:' + nodeKey + ':'));

        if (!candidates.length) return areaEl;

        // Prefer :1 if present (usually icon body), otherwise :0, otherwise largest bbox
        var a1 = candidates.find(a => /:1$/.test(a.id || ''));
        if (a1) return a1;
        var a0 = candidates.find(a => /:0$/.test(a.id || ''));
        if (a0) return a0;

        var best = candidates[0];
        var bestArea = -1;
        candidates.forEach(a => {
            var r = parseCoordsToRect(a.coords);
            if (!r) return;
            var s = Math.abs((r.maxX - r.minX) * (r.maxY - r.minY));
            if (s > bestArea) {
                bestArea = s;
                best = a;
            }
        });
        return best;
    }

    function rectToCoords(rect) {
        return [rect.minX, rect.minY, rect.maxX, rect.maxY].join(',');
    }

    function stopAreaEditor() {
        if (areaEditor.el && areaEditor.el.parentNode) {
            areaEditor.el.parentNode.removeChild(areaEditor.el);
        }
        areaEditor.el = null;
        areaEditor.areaEl = null;
        areaEditor.dragging = null;
        areaEditor.start = null;
        areaEditor.rect = null;
        document.removeEventListener('mousemove', onAreaHandleMouseMove);
        document.removeEventListener('mouseup', onAreaHandleMouseUp);
    }

    function startAreaEditorFromArea(areaEl) {
        stopAreaEditor();
        if (!areaEl || !areaEl.coords) return;

        var rect = parseCoordsToRect(areaEl.coords);
        if (!rect) return;

        var container = document.querySelector('.mainArea');
        var imgEl = document.getElementById('existingdata');
        if (!container || !imgEl) return;
        container.style.position = 'relative';

        var editor = document.createElement('div');
        editor.className = 'wm-area-editor';
        editor.style.left = (imgEl.offsetLeft + rect.minX) + 'px';
        editor.style.top = (imgEl.offsetTop + rect.minY) + 'px';
        editor.style.width = (rect.maxX - rect.minX) + 'px';
        editor.style.height = (rect.maxY - rect.minY) + 'px';

        ['nw', 'ne', 'sw', 'se'].forEach(pos => {
            var h = document.createElement('div');
            h.className = 'wm-handle ' + pos;
            h.dataset.handle = pos;
            h.addEventListener('mousedown', onAreaHandleMouseDown);
            editor.appendChild(h);
        });

        container.appendChild(editor);

        areaEditor.el = editor;
        areaEditor.areaEl = areaEl;
        areaEditor.rect = rect;
    }

    function onAreaHandleMouseDown(e) {
        e.preventDefault();
        e.stopPropagation();
        if (!areaEditor.el || !areaEditor.rect) return;
        areaEditor.dragging = e.target.dataset.handle;
        areaEditor.start = {
            x: e.clientX,
            y: e.clientY,
            rect: {
                minX: areaEditor.rect.minX,
                minY: areaEditor.rect.minY,
                maxX: areaEditor.rect.maxX,
                maxY: areaEditor.rect.maxY
            }
        };
        document.addEventListener('mousemove', onAreaHandleMouseMove);
        document.addEventListener('mouseup', onAreaHandleMouseUp);
    }

    function onAreaHandleMouseMove(e) {
        if (!areaEditor.dragging || !areaEditor.start || !areaEditor.el || !areaEditor.areaEl) return;
        var dx = e.clientX - areaEditor.start.x;
        var dy = e.clientY - areaEditor.start.y;

        var r = {
            minX: areaEditor.start.rect.minX,
            minY: areaEditor.start.rect.minY,
            maxX: areaEditor.start.rect.maxX,
            maxY: areaEditor.start.rect.maxY
        };

        if (areaEditor.dragging === 'nw') { r.minX += dx; r.minY += dy; }
        if (areaEditor.dragging === 'ne') { r.maxX += dx; r.minY += dy; }
        if (areaEditor.dragging === 'sw') { r.minX += dx; r.maxY += dy; }
        if (areaEditor.dragging === 'se') { r.maxX += dx; r.maxY += dy; }

        if (r.minX > r.maxX) { var t = r.minX; r.minX = r.maxX; r.maxX = t; }
        if (r.minY > r.maxY) { var t2 = r.minY; r.minY = r.maxY; r.maxY = t2; }

        areaEditor.rect = r;

        var imgEl = document.getElementById('existingdata');
        areaEditor.el.style.left = (imgEl.offsetLeft + r.minX) + 'px';
        areaEditor.el.style.top = (imgEl.offsetTop + r.minY) + 'px';
        areaEditor.el.style.width = (r.maxX - r.minX) + 'px';
        areaEditor.el.style.height = (r.maxY - r.minY) + 'px';

        areaEditor.areaEl.coords = rectToCoords(r);
    }

    function onAreaHandleMouseUp() {
        areaEditor.dragging = null;
        areaEditor.start = null;
        document.removeEventListener('mousemove', onAreaHandleMouseMove);
        document.removeEventListener('mouseup', onAreaHandleMouseUp);
    }

    function getCurrentMapSize() {
        var imgEl = document.getElementById('existingdata');
        if (!imgEl) return { w: 0, h: 0 };
        return {
            w: imgEl.naturalWidth || imgEl.width || parseInt(imgEl.getAttribute('data-width') || '0', 10) || 0,
            h: imgEl.naturalHeight || imgEl.height || parseInt(imgEl.getAttribute('data-height') || '0', 10) || 0
        };
    }

    function ensureMapResizeOutline() {
        if (mapResize.outlineEl) return mapResize.outlineEl;
        var container = document.querySelector('.mainArea');
        if (!container) return null;
        container.style.position = 'relative';
        var el = document.createElement('div');
        el.className = 'wm-map-resize-outline';
        el.style.display = 'none';

        var label = document.createElement('div');
        label.className = 'wm-map-resize-label';
        label.textContent = '';
        el.appendChild(label);

        container.appendChild(el);
        mapResize.outlineEl = el;
        return el;
    }

    function positionMapResizeUI() {
        var container = document.querySelector('.mainArea');
        var imgEl = document.getElementById('existingdata');
        if (!container || !imgEl) return;
        container.style.position = 'relative';

        var w = imgEl.naturalWidth || imgEl.width;
        var h = imgEl.naturalHeight || imgEl.height;
        if (!w || !h) {
            var dsW = parseInt(imgEl.getAttribute('data-width') || '0', 10);
            var dsH = parseInt(imgEl.getAttribute('data-height') || '0', 10);
            if (dsW && dsH) { w = dsW; h = dsH; }
        }

        var rightHandle = document.getElementById('wm_map_resize_e');
        var bottomHandle = document.getElementById('wm_map_resize_s');
        var cornerHandle = document.getElementById('wm_map_resize_se');
        if (!rightHandle || !bottomHandle || !cornerHandle) return;

        // Handles are positioned relative to the map image top-left (offsetLeft/Top)
        var left = imgEl.offsetLeft;
        var top = imgEl.offsetTop;

        rightHandle.style.left = (left + w) + 'px';
        rightHandle.style.top = top + 'px';
        rightHandle.style.height = h + 'px';

        bottomHandle.style.left = left + 'px';
        bottomHandle.style.top = (top + h) + 'px';
        bottomHandle.style.width = w + 'px';

        cornerHandle.style.left = (left + w) + 'px';
        cornerHandle.style.top = (top + h) + 'px';
    }

    function initMapResizeUI() {
        var container = document.querySelector('.mainArea');
        var imgEl = document.getElementById('existingdata');
        if (!container || !imgEl) return;
        container.style.position = 'relative';

        if (!document.getElementById('wm_map_resize_e')) {
            var e = document.createElement('div');
            e.id = 'wm_map_resize_e';
            e.className = 'wm-map-resize-handle e';
            e.addEventListener('mousedown', function(ev) { onMapResizeMouseDown(ev, 'e'); });
            container.appendChild(e);
        }
        if (!document.getElementById('wm_map_resize_s')) {
            var s = document.createElement('div');
            s.id = 'wm_map_resize_s';
            s.className = 'wm-map-resize-handle s';
            s.addEventListener('mousedown', function(ev) { onMapResizeMouseDown(ev, 's'); });
            container.appendChild(s);
        }
        if (!document.getElementById('wm_map_resize_se')) {
            var se = document.createElement('div');
            se.id = 'wm_map_resize_se';
            se.className = 'wm-map-resize-handle se';
            se.addEventListener('mousedown', function(ev) { onMapResizeMouseDown(ev, 'se'); });
            container.appendChild(se);
        }

        // Reposition handles after image load
        imgEl.addEventListener('load', function() {
            positionMapResizeUI();
        });

        positionMapResizeUI();
    }

    function onMapResizeMouseDown(e, mode) {
        e.preventDefault();
        e.stopPropagation();
        var imgEl = document.getElementById('existingdata');
        if (!imgEl) return;

        var size = getCurrentMapSize();
        var outline = ensureMapResizeOutline();
        if (!outline) return;

        mapResize.active = true;
        mapResize.mode = mode;
        mapResize.start = {
            x: e.clientX,
            y: e.clientY,
            w: size.w,
            h: size.h,
            left: imgEl.offsetLeft,
            top: imgEl.offsetTop
        };

        outline.style.display = 'block';
        outline.style.left = mapResize.start.left + 'px';
        outline.style.top = mapResize.start.top + 'px';
        outline.style.width = mapResize.start.w + 'px';
        outline.style.height = mapResize.start.h + 'px';

        var label = outline.querySelector('.wm-map-resize-label');
        if (label) {
            label.textContent = 'resizing ' + mapResize.start.w + ' x ' + mapResize.start.h;
        }

        document.addEventListener('mousemove', onMapResizeMouseMove);
        document.addEventListener('mouseup', onMapResizeMouseUp);
    }

    function onMapResizeMouseMove(e) {
        if (!mapResize.active || !mapResize.start || !mapResize.outlineEl) return;
        var dx = e.clientX - mapResize.start.x;
        var dy = e.clientY - mapResize.start.y;

        var newW = mapResize.start.w;
        var newH = mapResize.start.h;
        if (mapResize.mode === 'e' || mapResize.mode === 'se') newW = mapResize.start.w + dx;
        if (mapResize.mode === 's' || mapResize.mode === 'se') newH = mapResize.start.h + dy;

        // Clamp
        newW = Math.max(100, Math.round(newW));
        newH = Math.max(100, Math.round(newH));

        mapResize.outlineEl.style.width = newW + 'px';
        mapResize.outlineEl.style.height = newH + 'px';

        var label = mapResize.outlineEl.querySelector('.wm-map-resize-label');
        if (label) {
            label.textContent = 'resizing ' + newW + ' x ' + newH;
        }
    }

    function onMapResizeMouseUp() {
        if (!mapResize.active) return;
        mapResize.active = false;
        document.removeEventListener('mousemove', onMapResizeMouseMove);
        document.removeEventListener('mouseup', onMapResizeMouseUp);

        var outline = mapResize.outlineEl;
        var newW = outline ? parseInt(outline.style.width || '0', 10) : 0;
        var newH = outline ? parseInt(outline.style.height || '0', 10) : 0;

        if (outline) outline.style.display = 'none';

        if (!newW || !newH) return;

        // Update Map Properties inputs so user sees the new values next time
        var wInp = document.getElementById('map_width');
        var hInp = document.getElementById('map_height');
        if (wInp) wInp.value = String(newW);
        if (hInp) hInp.value = String(newH);

        // Persist via existing endpoint
        submitAction('set_map_properties', {
            map_title: document.getElementById('map_title').value,
            map_legend: document.getElementById('map_legend').value,
            map_bgfile: document.getElementById('map_bgfile').value,
            map_stamp: document.getElementById('map_stamp').value,
            map_width: newW,
            map_height: newH
        });
    }
    
    function mapmode(mode) {
        if (mode == 'xy') {
            document.getElementById('xycapture').style.display = 'block';
            document.getElementById('existingdata').style.display = 'none';
        } else {
            document.getElementById('xycapture').style.display = 'none';
            document.getElementById('existingdata').style.display = 'block';
        }
    }
    
    function reloadFullMap() {
        var timestamp = new Date().getTime();
        var img = document.getElementById('existingdata');
        var xyimg = document.getElementById('xycapture');
        img.src = '/editor/draw/<?= $mapId ?>?t=' + timestamp;
        xyimg.src = '/editor/draw/<?= $mapId ?>?t=' + timestamp;
        
        // Load both area and JS data from combined endpoint to ensure consistency
        return fetch('/editor/mapdata/<?= $mapId ?>?t=' + timestamp)
            .then(r => r.json())
            .then(data => {
                // Update area HTML
                document.getElementById('mapData').innerHTML = data.area;
                
                // Execute JS in global context by wrapping in script tag
                var script = document.createElement('script');
                script.textContent = data.js;
                document.head.appendChild(script);
                document.head.removeChild(script);
                
                // Attach events after both are loaded
                attachAreaEvents();
            });
    }
    
    // Smooth image reload without flickering
    function reloadMapImage(skipAreaReload) {
        var timestamp = new Date().getTime();
        var img = document.getElementById('existingdata');
        var xyimg = document.getElementById('xycapture');
        
        // Create new images
        var newMapImg = new Image();
        var newCaptureImg = new Image();
        
        // Load new images
        newMapImg.src = '/editor/draw/<?= $mapId ?>?t=' + timestamp;
        newCaptureImg.src = '/editor/draw/<?= $mapId ?>?t=' + timestamp;
        
        // When loaded, replace with crossfade
        newMapImg.onload = function() {
            img.style.opacity = '0';
            setTimeout(function() {
                img.src = newMapImg.src;
                img.style.opacity = '1';
            }, 100);
        };
        
        newCaptureImg.onload = function() {
            xyimg.src = newCaptureImg.src;
        };
        
        // Only reload area data if not skipping (needed for new nodes to be clickable)
        if (!skipAreaReload) {
            fetch('/editor/area/<?= $mapId ?>')
                .then(r => r.text())
                .then(html => {
                    document.getElementById('mapData').innerHTML = html;
                    attachAreaEvents();
                });
        }
    }
    
    function submitAction(action, params) {
        params = params || {};
        params.action = action;
        
        var formData = new FormData();
        for (var key in params) {
            formData.append(key, params[key]);
        }
        
        fetch(editor_url, {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // For set_node_properties and set_link_properties, wait for data reload before closing dialog
                if (action === 'set_node_properties' || action === 'set_link_properties') {
                    return reloadFullMap().then(() => {
                        hideAllDialogs();
                    });
                }
                
                hideAllDialogs();
                
                // For all actions, use incremental updates instead of full reload
                if (action === 'add_node' || action === 'clone_node') {
                    // Add the new node to existing arrays
                    var nodeId = data.node.id;
                    var nodeName = data.node.name;
                    
                    if (typeof NodeIDs !== 'undefined') {
                        NodeIDs[nodeId] = nodeName;
                    }
                    
                    if (typeof Nodes !== 'undefined') {
                        Nodes[nodeName] = {
                            name: nodeName,
                            x: data.node.x,
                            y: data.node.y,
                            label: nodeName,
                            id: nodeId.replace('N', ''),
                            iconfile: '--NONE--',
                            infourl: '',
                            overliburl: ''
                        };
                    }
                    
                    // Use the area HTML from backend
                    if (data.areaHtml) {
                        document.getElementById('mapData').innerHTML = data.areaHtml;
                        attachAreaEvents();
                    }
                    
                    // Reload the map image to show the new node
                    var timestamp = new Date().getTime();
                    var random = Math.random();
                    document.getElementById('existingdata').src = '/editor/draw/<?= $mapId ?>?t=' + timestamp + '&r=' + random;
                    document.getElementById('xycapture').src = '/editor/draw/<?= $mapId ?>?t=' + timestamp + '&r=' + random;
                    
                } else if ((action === 'add_link' || action === 'add_link2') && data.link) {
                    // Add the new link to existing arrays
                    var linkId = data.link.id;
                    var linkName = data.link.name;
                    
                    if (typeof LinkIDs !== 'undefined') {
                        LinkIDs[linkId] = linkName;
                    }
                    
                    if (typeof Links !== 'undefined') {
                        Links[linkName] = {
                            name: linkName,
                            a: data.link.a,
                            b: data.link.b,
                            id: linkId.replace('L', ''),
                            width: '5',
                            bw_in: '100M',
                            bw_out: '100M',
                            target: '',
                            infourl: '',
                            overliburl: '',
                            commentin: '',
                            commentout: ''
                        };
                    }
                    
                    // Use the area HTML from backend
                    if (data.areaHtml) {
                        document.getElementById('mapData').innerHTML = data.areaHtml;
                        attachAreaEvents();
                    }
                    
                    // Reload the map image to show the new link
                    var timestamp = new Date().getTime();
                    var random = Math.random();
                    document.getElementById('existingdata').src = '/editor/draw/<?= $mapId ?>?t=' + timestamp + '&r=' + random;
                    document.getElementById('xycapture').src = '/editor/draw/<?= $mapId ?>?t=' + timestamp + '&r=' + random;
                    
                } else if (action === 'set_node_properties' || action === 'set_link_properties') {
                    // Data reload is handled earlier before closing dialog
                    // No additional action needed here
                    
                } else if (action === 'move_node' && data.node) {
                    // Update node position
                    var nodeName = data.node.name;
                    if (typeof Nodes !== 'undefined' && Nodes[nodeName]) {
                        Nodes[nodeName].x = data.node.x;
                        Nodes[nodeName].y = data.node.y;
                    }
                    
                    // Reload both map images so switching modes doesn't show stale map state
                    var timestamp = new Date().getTime();
                    document.getElementById('existingdata').src = '/editor/draw/<?= $mapId ?>?t=' + timestamp;
                    document.getElementById('xycapture').src = '/editor/draw/<?= $mapId ?>?t=' + timestamp;
                    
                } else if (action === 'delete_node' && data.node) {
                    // Remove node from arrays
                    var nodeName = data.node.name;
                    
                    // Find and remove from NodeIDs
                    for (var nodeId in NodeIDs) {
                        if (NodeIDs[nodeId] === nodeName) {
                            delete NodeIDs[nodeId];
                            break;
                        }
                    }
                    
                    // Remove from Nodes
                    delete Nodes[nodeName];
                    
                    // Reload area and image
                    fetch('/editor/area/<?= $mapId ?>')
                        .then(r => r.text())
                        .then(html => {
                            document.getElementById('mapData').innerHTML = html;
                            attachAreaEvents();
                        });
                    
                    var timestamp = new Date().getTime();
                    document.getElementById('existingdata').src = '/editor/draw/<?= $mapId ?>?t=' + timestamp;
                    
                } else if (action === 'delete_link' && data.link) {
                    // Remove link from arrays
                    var linkName = data.link.name;
                    
                    // Find and remove from LinkIDs
                    for (var linkId in LinkIDs) {
                        if (LinkIDs[linkId] === linkName) {
                            delete LinkIDs[linkId];
                            break;
                        }
                    }
                    
                    // Remove from Links
                    delete Links[linkName];
                    
                    // Reload area and image
                    fetch('/editor/area/<?= $mapId ?>')
                        .then(r => r.text())
                        .then(html => {
                            document.getElementById('mapData').innerHTML = html;
                            attachAreaEvents();
                        });
                    
                    var timestamp = new Date().getTime();
                    document.getElementById('existingdata').src = '/editor/draw/<?= $mapId ?>?t=' + timestamp;
                    
                } else {
                    // For map properties changes, we need to reload the full map
                    reloadFullMap();
                }
                
                mapmode('existing');
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            alert('Error: ' + err.message);
        });
    }
    
    function attachAreaEvents() {
        var root = document.getElementById('mapData') || document;
        var nodeAreas = root.querySelectorAll("area.node, area[id^='NODE:']");

        nodeAreas.forEach(area => {
            area.href = '#';
            area.onclick = function(e) {
                e.preventDefault();
                handleClick(e, this);
            };
        });

        root.querySelectorAll("area.link, area[id^='LINK:']").forEach(area => {
            area.href = '#';
            area.onclick = function(e) {
                e.preventDefault();
                handleClick(e, this);
            };
        });
        
        root.querySelectorAll("area[id^='LEGEN']").forEach(area => {
            area.href = '#';
            area.onclick = function(e) {
                e.preventDefault();
                document.getElementById('tb_help').textContent = posLegendHelp;
                document.getElementById('action').value = 'place_legend';
                document.getElementById('param').value = 'DEFAULT';
                mapmode('xy');
            };
        });
        
        root.querySelectorAll("area[id^='TIMES']").forEach(area => {
            area.href = '#';
            area.onclick = function(e) {
                e.preventDefault();
                document.getElementById('tb_help').textContent = timeStHelp;
                document.getElementById('action').value = 'place_stamp';
                mapmode('xy');
            };
        });
    }
    
    function handleClick(event, areaEl) {
        var id = areaEl && areaEl.id ? areaEl.id : '';
        var type = id.substring(0, 4);
        var rest = id.length > 5 ? id.substring(5) : '';
        var nodeId = rest ? rest.split(':')[0] : '';

        var action = document.getElementById('action').value;
        
        if (type === 'NODE') {
            lastClickedNodeArea = pickBestNodeAreaFromClicked(areaEl);
        } else if (type === 'LINK') {
        }
        
        if (action === 'add_link') {
            if (type === 'NODE') {
                var nodeName = NodeIDs[nodeId];
                if (!nodeName) {
                    alert('Node not found in NodeIDs: ' + nodeId);
                    return;
                }
                document.getElementById('param').value = nodeName;
                document.getElementById('action').value = 'add_link2';
                document.getElementById('tb_help').textContent = 'Now click on the second node for the end of the link.';
            }
            return;
        }
        
        if (action === 'add_link2') {
            if (type === 'NODE') {
                var nodeName = NodeIDs[nodeId];
                if (!nodeName) {
                    alert('Node not found in NodeIDs: ' + nodeId);
                    return;
                }
                document.getElementById('param2').value = nodeName;
                submitAction('add_link2', {
                    param: document.getElementById('param').value,
                    param2: document.getElementById('param2').value
                });
            }
            return;
        }
        
        if (type === 'NODE') {
            var nodeName = NodeIDs[nodeId];
            if (!nodeName) {
                alert('Node not found in NodeIDs: ' + nodeId);
                return;
            }
            showNodeProperties(nodeName);
        } else if (type === 'LINK') {
            var linkName = LinkIDs[nodeId]; // nodeId here is actually linkId like L100
            if (!linkName) {
                alert('Link not found in LinkIDs: ' + nodeId);
                return;
            }
            showLinkProperties(linkName);
        }
    }
    
    function showNodeProperties(name) {
        var node = Nodes[name];
        if (!node) {
            alert('Node not found: ' + name);
            return;
        }

        // Do not highlight nodes during edit
        document.querySelectorAll('.node-highlight').forEach(el => el.remove());
        
        document.getElementById('node_name').value = name;
        document.getElementById('node_new_name').value = name;
        document.getElementById('node_x').value = node.x;
        document.getElementById('node_y').value = node.y;
        document.getElementById('node_label').value = node.label || '';
        document.getElementById('node_infourl').value = node.infourl || '';
        document.getElementById('node_hover').value = node.overliburl || '';
        document.getElementById('node_iconfilename').value = node.iconfile || '--NONE--';
        document.getElementById('param').value = name;
        document.getElementById('action').value = 'set_node_properties';
        
        document.getElementById('node_id_display').textContent = 'NODE: ' + name;
        
        showDialog('dlgNodeProperties');

        startAreaEditorFromArea(lastClickedNodeArea);
    }
    
    function showLinkProperties(name) {
        var link = Links[name];
        if (!link) {
            alert('Link not found: ' + name);
            return;
        }
        
        document.getElementById('link_name').value = name;
        document.getElementById('link_target').value = link.target || '';
        document.getElementById('link_datasource').value = link.datasource || '';
        document.getElementById('link_width').value = link.width || '';
        document.getElementById('link_infourl').value = link.infourl || '';
        document.getElementById('link_hover').value = link.overliburl || '';
        document.getElementById('link_commentin').value = link.commentin || '';
        document.getElementById('link_commentout').value = link.commentout || '';
        document.getElementById('param').value = name;
        document.getElementById('action').value = 'set_link_properties';
        
        var isSymmetric = (link.bw_in == link.bw_out);
        document.getElementById('link_bandwidth_out_cb').checked = isSymmetric;
        
        if (isSymmetric) {
            document.getElementById('link_bandwidth_in').value = link.bw_in || '';
            document.getElementById('bw_row_combined').style.display = '';
            document.getElementById('bw_row_in').style.display = 'none';
            document.getElementById('bw_row_out').style.display = 'none';
        } else {
            document.getElementById('link_bandwidth_in_sep').value = link.bw_in || '';
            document.getElementById('link_bandwidth_out').value = link.bw_out || '';
            document.getElementById('bw_row_combined').style.display = 'none';
            document.getElementById('bw_row_in').style.display = '';
            document.getElementById('bw_row_out').style.display = '';
        }
        
        document.getElementById('link_id_display').textContent = 'LINK: ' + (link.a || '?') + ' to ' + (link.b || '?');
        
        showDialog('dlgLinkProperties');
    }

    async function dsFetchJson(url) {
        const r = await fetch(url);

        let data = null;
        try {
            data = await r.json();
        } catch (e) {
            data = null;
        }

        if (!r.ok) {
            const msg = (data && data.error) ? data.error : ('HTTP ' + r.status);
            throw new Error(msg);
        }

        if (!data || !data.success) {
            let msg = (data && data.error) ? data.error : 'API error';
            if (data && data.debug) {
                try {
                    msg += ' | debug: ' + JSON.stringify(data.debug);
                } catch (e) {
                    // ignore
                }
            }
            throw new Error(msg);
        }

        return data.data;
    }

    function dsFillSelect(selectEl, items, valueKey, labelKey, placeholder) {
        selectEl.innerHTML = '';
        const ph = document.createElement('option');
        ph.value = '';
        ph.textContent = placeholder;
        selectEl.appendChild(ph);

        items.forEach(item => {
            const opt = document.createElement('option');
            opt.value = String(item[valueKey] ?? '');
            opt.textContent = String(item[labelKey] ?? opt.value);
            selectEl.appendChild(opt);
        });
    }

    async function initDatasourcePicker() {
        const elSource = document.getElementById('ds_source');
        const elHost = document.getElementById('ds_host');
        const elIface = document.getElementById('ds_iface');
        if (!elSource || !elHost || !elIface) return;

        function applyIfaceSelection() {
            const sourceId = elSource.value;
            const hostId = elHost.value;
            const ifaceId = elIface.value;
            if (!sourceId || !hostId || !ifaceId) return;

            let items = [];
            try {
                items = JSON.parse(elIface.dataset.items || '[]');
            } catch (e) {
                items = [];
            }
            const selected = items.find(i => String(i.interfaceId) === String(ifaceId));
            if (!selected) return;

            const selection = {
                sourceId: parseInt(sourceId, 10),
                hostId: String(hostId),
                interfaceId: String(ifaceId),
                inKey: String(selected.rxKey || ''),
                outKey: String(selected.txKey || ''),
            };

            document.getElementById('link_datasource').value = JSON.stringify(selection);
            const target = 'zabbix:hostid:' + selection.hostId + ':key:' + selection.inKey + ':' + selection.outKey;
            document.getElementById('link_target').value = target;
        }

        try {
            const sources = await dsFetchJson('/api/data-sources');
            dsFillSelect(elSource, sources, 'id', 'name', 'Select source');
        } catch (e) {
            dsFillSelect(elSource, [], 'id', 'name', 'No sources');
        }

        elSource.addEventListener('change', async function() {
            elHost.disabled = true;
            elIface.disabled = true;
            dsFillSelect(elHost, [], 'hostid', 'name', 'Select host');
            dsFillSelect(elIface, [], 'interfaceId', 'label', 'Select interface');

            const sourceId = elSource.value;
            if (!sourceId) return;

            try {
                const hosts = await dsFetchJson('/api/data-sources/' + encodeURIComponent(sourceId) + '/hosts');
                dsFillSelect(elHost, hosts, 'hostid', 'name', 'Select host');
                elHost.disabled = false;
            } catch (e) {
                dsFillSelect(elHost, [], 'hostid', 'name', String(e && e.message ? e.message : 'Failed to load'));
            }
        });

        elHost.addEventListener('change', async function() {
            elIface.disabled = true;
            dsFillSelect(elIface, [], 'interfaceId', 'label', 'Select interface');

            const sourceId = elSource.value;
            const hostId = elHost.value;
            if (!sourceId || !hostId) return;

            try {
                const ifaces = await dsFetchJson('/api/data-sources/' + encodeURIComponent(sourceId) + '/hosts/' + encodeURIComponent(hostId) + '/bandwidths');
                if (!ifaces || !ifaces.length) {
                    dsFillSelect(elIface, [], 'interfaceId', 'label', 'No net.if.in/out items on host');
                    elIface.disabled = true;
                    elIface.dataset.items = '[]';
                    return;
                }

                dsFillSelect(elIface, ifaces, 'interfaceId', 'label', 'Select interface');
                elIface.disabled = false;
                elIface.dataset.items = JSON.stringify(ifaces);
            } catch (e) {
                dsFillSelect(elIface, [], 'interfaceId', 'label', String(e && e.message ? e.message : 'Failed to load'));
            }
        });

        elIface.addEventListener('change', function() {
            const applyBtn = document.getElementById('ds_apply');
            if (elIface.value && elSource.value && elHost.value) {
                applyBtn.style.display = 'inline-block';
            } else {
                applyBtn.style.display = 'none';
            }
        });

        // Toggle picker button
        document.getElementById('ds_toggle_picker').addEventListener('click', function() {
            document.getElementById('ds_row_manual').style.display = 'none';
            document.getElementById('ds_row_picker').style.display = '';
        });

        // Apply button - fill Data Source and switch back to manual mode
        document.getElementById('ds_apply').addEventListener('click', function() {
            applyIfaceSelection();
            document.getElementById('ds_row_manual').style.display = '';
            document.getElementById('ds_row_picker').style.display = 'none';
            this.style.display = 'none';
        });

        // Cancel button - switch back to manual mode without applying
        document.getElementById('ds_cancel').addEventListener('click', function() {
            document.getElementById('ds_row_manual').style.display = '';
            document.getElementById('ds_row_picker').style.display = 'none';
            document.getElementById('ds_apply').style.display = 'none';
        });

    }
    
    // Toolbar events
    document.getElementById('tb_addnode').onclick = function(e) {
        e.preventDefault();
        document.getElementById('tb_help').textContent = addNodeHelp;
        document.getElementById('action').value = 'add_node';
        mapmode('xy');
    };
    
    document.getElementById('tb_addlink').onclick = function(e) {
        e.preventDefault();
        document.getElementById('tb_help').textContent = addLinkHelp;
        document.getElementById('action').value = 'add_link';
        mapmode('existing');
    };
    
    document.getElementById('tb_poslegend').onclick = function(e) {
        e.preventDefault();
        document.getElementById('tb_help').textContent = posLegendHelp;
        document.getElementById('action').value = 'place_legend';
        document.getElementById('param').value = 'DEFAULT';
        mapmode('xy');
    };
    
    document.getElementById('tb_postime').onclick = function() {
        document.getElementById('tb_help').textContent = timeStHelp;
        document.getElementById('action').value = 'place_stamp';
        mapmode('xy');
    };
    
    document.getElementById('tb_mapprops').onclick = function() {
        document.getElementById('action').value = 'set_map_properties';
        showDialog('dlgMapProperties');
    };

    document.getElementById('tb_manualconfig').onclick = function(e) {
        e.preventDefault();
        toggleManualConfig(true);
    };
    
    // XY capture click
    document.getElementById('xycapture').onclick = function(e) {
        e.preventDefault();
        
        var rect = this.getBoundingClientRect();
        var x = Math.round(e.clientX - rect.left);
        var y = Math.round(e.clientY - rect.top);
        
        var action = document.getElementById('action').value;
        
        if (action === 'add_node') {
            submitAction('add_node', { x: x, y: y });
        } else if (action === 'move_node') {
            submitAction('move_node', {
                x: x,
                y: y,
                node_name: document.getElementById('param').value
            });
        } else if (action === 'place_legend') {
            submitAction('place_legend', {
                x: x,
                y: y,
                param: document.getElementById('param').value
            });
        } else if (action === 'place_stamp') {
            submitAction('place_stamp', { x: x, y: y });
        } else if (action === 'via_link') {
            submitAction('via_link', {
                x: x,
                y: y,
                link_name: document.getElementById('param').value
            });
        }
        
        return false;
    };
    
    // Mouse move for coordinates
    document.getElementById('xycapture').onmousemove = function(e) {
        var rect = this.getBoundingClientRect();
        var x = Math.round(e.clientX - rect.left);
        var y = Math.round(e.clientY - rect.top);
        document.getElementById('tb_coords').innerHTML = 'Position<br>' + x + ', ' + y;
    };
    
    document.getElementById('existingdata').onmousemove = function(e) {
        var rect = this.getBoundingClientRect();
        var x = Math.round(e.clientX - rect.left);
        var y = Math.round(e.clientY - rect.top);
        document.getElementById('tb_coords').innerHTML = 'Position<br>' + x + ', ' + y;
    };
    
    // Icon grid selection
    function initIconGrid() {
        const iconGrid = document.getElementById('icon-grid');
        const hiddenInput = document.getElementById('node_iconfilename');
        
        if (!iconGrid || !hiddenInput) return;
        
        // Bind click handler only once (initIconGrid is called every time the dialog opens)
        if (!iconGrid.dataset.bound) {
            iconGrid.addEventListener('click', function(e) {
                const iconItem = e.target.closest('.icon-item');
                if (!iconItem) return;
                
                // Remove previous selection (within this grid)
                iconGrid.querySelectorAll('.icon-item').forEach(item => {
                    item.classList.remove('selected');
                });
                
                // Add selection to clicked icon
                iconItem.classList.add('selected');
                
                // Update hidden input
                const iconValue = iconItem.dataset.icon;
                hiddenInput.value = iconValue;
            });
            iconGrid.dataset.bound = '1';
        }
        
        // Always reset selection and apply current node's iconfile
        iconGrid.querySelectorAll('.icon-item').forEach(item => {
            item.classList.remove('selected');
        });
        
        const currentValue = hiddenInput.value || '--NONE--';
        const currentItem = iconGrid.querySelector(`[data-icon="${currentValue}"]`);
        if (currentItem) {
            currentItem.classList.add('selected');
        }
    }
    
    // Initialize icon grid when dialog opens
    const originalShowNodeProperties = showNodeProperties;
    showNodeProperties = function(name) {
        originalShowNodeProperties(name);
        initIconGrid();
    };
    
    // Dialog buttons
    document.getElementById('overlay').onclick = hideAllDialogs;
    document.getElementById('tb_node_cancel').onclick = hideAllDialogs;
    document.getElementById('tb_link_cancel').onclick = hideAllDialogs;
    document.getElementById('tb_map_cancel').onclick = hideAllDialogs;
    
    document.getElementById('tb_node_submit').onclick = function() {
        submitAction('set_node_properties', {
            node_name: document.getElementById('node_name').value,
            node_new_name: document.getElementById('node_new_name').value,
            node_x: document.getElementById('node_x').value,
            node_y: document.getElementById('node_y').value,
            node_label: document.getElementById('node_label').value,
            node_infourl: document.getElementById('node_infourl').value,
            node_hover: document.getElementById('node_hover').value,
            node_iconfilename: document.getElementById('node_iconfilename').value
        });
    };
    
    document.getElementById('tb_link_submit').onclick = function() {
        var isSymmetric = document.getElementById('link_bandwidth_out_cb').checked;
        var bwIn, bwOut;
        
        if (isSymmetric) {
            bwIn = document.getElementById('link_bandwidth_in').value;
            bwOut = bwIn;
        } else {
            bwIn = document.getElementById('link_bandwidth_in_sep').value;
            bwOut = document.getElementById('link_bandwidth_out').value;
        }
        
        submitAction('set_link_properties', {
            link_name: document.getElementById('link_name').value,
            link_bandwidth_in: bwIn,
            link_bandwidth_out: bwOut,
            link_bandwidth_out_cb: isSymmetric ? 'symmetric' : '',
            link_target: document.getElementById('link_target').value,
            link_datasource: document.getElementById('link_datasource').value,
            link_width: document.getElementById('link_width').value,
            link_infourl: document.getElementById('link_infourl').value,
            link_hover: document.getElementById('link_hover').value,
            link_commentin: document.getElementById('link_commentin').value,
            link_commentout: document.getElementById('link_commentout').value
        });
    };
    
    document.getElementById('tb_map_submit').onclick = function() {
        submitAction('set_map_properties', {
            map_title: document.getElementById('map_title').value,
            map_legend: document.getElementById('map_legend').value,
            map_bgfile: document.getElementById('map_bgfile').value,
            map_stamp: document.getElementById('map_stamp').value,
            map_width: document.getElementById('map_width').value,
            map_height: document.getElementById('map_height').value
        });
    };
    
    document.getElementById('node_move').onclick = function() {
        hideAllDialogs();
        document.getElementById('tb_help').textContent = moveNodeHelp;
        document.getElementById('action').value = 'move_node';
        mapmode('xy');
    };
    
    document.getElementById('node_delete').onclick = function() {
        if (confirm(delNodeWarning)) {
            submitAction('delete_node', {
                param: document.getElementById('node_name').value
            });
        }
    };
    
    document.getElementById('node_clone').onclick = function() {
        submitAction('clone_node', {
            param: document.getElementById('node_name').value
        });
    };
    
    document.getElementById('link_delete').onclick = function() {
        if (confirm(delLinkWarning)) {
            submitAction('delete_link', {
                param: document.getElementById('link_name').value
            });
        }
    };
    
    document.getElementById('link_tidy').onclick = function() {
        submitAction('link_tidy', {
            param: document.getElementById('link_name').value
        });
    };
    
    document.getElementById('link_via').onclick = function() {
        hideAllDialogs();
        document.getElementById('tb_help').textContent = viaLinkHelp;
        document.getElementById('action').value = 'via_link';
        mapmode('xy');
    };
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        manualConfigPanel = document.getElementById('manualConfigPanel');
        manualConfigOverlay = document.getElementById('manualConfigOverlay');
        manualConfigTextarea = document.getElementById('manualConfigTextarea');
        manualConfigStatus = document.getElementById('manualConfigStatus');

        if (manualConfigOverlay) {
            manualConfigOverlay.addEventListener('click', () => toggleManualConfig(false));
        }
        document.getElementById('manualConfigClose').addEventListener('click', () => toggleManualConfig(false));
        document.getElementById('manualConfigRefresh').addEventListener('click', loadManualConfig);
        document.getElementById('manualConfigSave').addEventListener('click', saveManualConfig);

        attachAreaEvents();
        mapmode('existing');
        initMapResizeUI();
        initDatasourcePicker();
        
        var bwCheckboxMain = document.getElementById('link_bandwidth_out_cb');
        var bwCheckboxSep = document.getElementById('link_bandwidth_out_cb_sep');
        
        function toggleBandwidthMode(isSymmetric) {
            if (isSymmetric) {
                var bwValue = document.getElementById('link_bandwidth_in_sep').value || document.getElementById('link_bandwidth_in').value;
                document.getElementById('link_bandwidth_in').value = bwValue;
                document.getElementById('bw_row_combined').style.display = '';
                document.getElementById('bw_row_in').style.display = 'none';
                document.getElementById('bw_row_out').style.display = 'none';
            } else {
                var bwValue = document.getElementById('link_bandwidth_in').value;
                document.getElementById('link_bandwidth_in_sep').value = bwValue;
                document.getElementById('link_bandwidth_out').value = bwValue;
                document.getElementById('bw_row_combined').style.display = 'none';
                document.getElementById('bw_row_in').style.display = '';
                document.getElementById('bw_row_out').style.display = '';
            }
            bwCheckboxMain.checked = isSymmetric;
            bwCheckboxSep.checked = isSymmetric;
        }
        
        bwCheckboxMain.addEventListener('change', function() {
            toggleBandwidthMode(this.checked);
        });
        
        bwCheckboxSep.addEventListener('change', function() {
            toggleBandwidthMode(this.checked);
        });
    });
    </script>
</body>
</html>
