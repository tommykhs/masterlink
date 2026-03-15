<?php
/**
 * Unified Asset Picker Component
 *
 * Modes:
 * - 'icon': Icon Library + External URL + Media (images only)
 * - 'image': External URL + Media (images only)
 * - 'file': Media only (all files)
 *
 * Uses <dialog> element for proper top-layer stacking
 */

function renderAssetPickerStyles() {
?>
<style>
/* Define fallback for --bg-secondary if not set by admin.css */
.asset-picker-dialog {
    --bg-secondary: var(--bg-card-hover, #f1f5f9);
}
@media (prefers-color-scheme: dark) {
    .asset-picker-dialog {
        --bg-secondary: var(--bg-card-hover, #334155);
    }
}

/* Asset Picker Dialog - Centered, Fixed Size */
.asset-picker-dialog {
    border: none;
    padding: 0;
    width: 90%;
    max-width: 700px;
    height: 600px; /* Fixed height - not responsive to content */
    border-radius: 12px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    background: var(--bg-card);
    overflow: hidden;
    /* Explicit centering */
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    margin: 0;
}
.asset-picker-dialog::backdrop {
    background: rgba(0, 0, 0, 0.5);
}
.asset-picker-dialog[open] {
    display: flex;
    flex-direction: column;
}

/* Header */
.asset-picker-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}
.asset-picker-header h3 {
    font-size: 1rem;
    font-weight: 600;
    margin: 0;
}
.asset-picker-close {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: none;
    background: transparent;
    cursor: pointer;
    color: var(--text-muted);
    border-radius: 6px;
    transition: all 0.15s;
}
.asset-picker-close:hover {
    background: var(--bg-secondary);
    color: var(--text-primary);
}
.asset-picker-close i { width: 20px; height: 20px; }

/* Tabs */
.asset-picker-tabs {
    display: flex;
    gap: 0.25rem;
    padding: 0.75rem 1.25rem;
    border-bottom: 1px solid var(--border);
    background: var(--bg-secondary);
    flex-shrink: 0;
}
.asset-picker-tab {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border: none;
    background: transparent;
    color: var(--text-muted);
    font-size: 0.8125rem;
    cursor: pointer;
    border-radius: 6px;
    transition: all 0.15s;
}
.asset-picker-tab i { width: 16px; height: 16px; }
.asset-picker-tab:hover { color: var(--text-primary); background: var(--bg-card); }
.asset-picker-tab.active {
    background: var(--bg-card);
    color: var(--primary);
    font-weight: 500;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

/* Content Area - fills remaining space between header/tabs and footer */
.asset-picker-content {
    flex: 1;
    min-height: 0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.asset-picker-tab-content {
    display: none;
    flex: 1;
    min-height: 0;
    flex-direction: column;
    overflow: hidden;
}
.asset-picker-tab-content.active {
    display: flex;
}

/* Icon Library Tab */
.icon-search {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.875rem;
    background: var(--bg-card) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E") no-repeat 0.75rem center;
    margin: 1rem 1.25rem 0.75rem;
    box-sizing: border-box;
    color: var(--text-primary);
}
.icon-search:focus {
    outline: none;
    border-color: var(--primary);
}
.icon-grid-container {
    flex: 1;
    overflow-y: auto;
    padding: 0 1.25rem 1.25rem;
}
.icon-category-title {
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 1rem 0 0.5rem;
}
.icon-category-title:first-child { margin-top: 0; }
.icon-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(42px, 1fr));
    gap: 0.25rem;
}
.icon-item {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s;
    border: 1px solid var(--border);
    background: var(--bg-card);
}
.icon-item:hover { background: var(--bg-card-hover); border-color: var(--text-muted); }
.icon-item.selected { border-color: var(--primary); background: rgba(102, 126, 234, 0.15); }
.icon-item i, .icon-item svg { width: 20px; height: 20px; color: var(--text-primary); stroke: currentColor; }
.show-all-btn {
    display: block;
    width: 100%;
    padding: 0.625rem;
    margin-top: 0.75rem;
    background: var(--bg-secondary);
    border: 1px dashed var(--border);
    border-radius: 8px;
    color: var(--text-muted);
    font-size: 0.8125rem;
    cursor: pointer;
    transition: all 0.15s;
}
.show-all-btn:hover { border-color: var(--primary); color: var(--primary); }

/* URL Tab */
.url-tab-content {
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    flex: 1;
    overflow: hidden;
}
.url-input-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.url-input-group label {
    font-size: 0.8125rem;
    font-weight: 500;
    color: var(--text-secondary);
}
.url-input-group input {
    padding: 0.75rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.875rem;
    background: var(--bg-card);
    color: var(--text-primary);
}
.url-input-group input:focus {
    outline: none;
    border-color: var(--primary);
}
.url-preview-box {
    flex: 1;
    min-height: 150px;
    border: 1px solid var(--border);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: var(--bg-secondary);
}
.url-preview-box img {
    max-width: 100%;
    max-height: 200px;
    object-fit: contain;
}
.url-preview-box .placeholder {
    text-align: center;
    color: var(--text-muted);
}
.url-preview-box .placeholder i {
    width: 48px;
    height: 48px;
    margin-bottom: 0.5rem;
    opacity: 0.5;
}

/* Media Tab - File Browser */
.media-tab-layout {
    display: flex;
    flex: 1;
    min-height: 0;
    overflow: hidden;
}
.media-sidebar {
    width: 150px;
    border-right: 1px solid var(--border);
    background: var(--bg-card);
    overflow-y: auto;
    flex-shrink: 0;
    padding: 0.5rem 0;
}
.media-sidebar-header {
    display: flex;
    align-items: center;
    padding: 0.5rem 0.75rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid var(--border);
    margin-bottom: 0.25rem;
}
.media-sidebar-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    cursor: pointer;
    font-size: 0.8125rem;
    color: var(--text-primary);
    transition: all 0.15s;
    border-left: 3px solid transparent;
}
.media-sidebar-item:hover { background: var(--bg-card-hover); }
.media-sidebar-item.active {
    background: var(--bg-card-hover);
    color: var(--primary);
    border-left-color: var(--primary);
    font-weight: 500;
}
.media-sidebar-item i { width: 16px; height: 16px; flex-shrink: 0; color: var(--warning); }
.media-sidebar-item.root-item i { color: var(--text-muted); }
.media-sidebar-item span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.media-sidebar-item .item-count {
    font-size: 0.6875rem;
    color: var(--text-muted);
    background: var(--gray-100);
    padding: 0.125rem 0.375rem;
    border-radius: 10px;
    margin-left: auto;
}

.media-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    min-height: 0;
    background: var(--bg-card);
    overflow: hidden;
}
/* Media toolbar with upload and view toggle - Consistent with Files Manager */
.media-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 0.75rem;
    border-bottom: 1px solid var(--border);
    background: var(--bg-card);
    gap: 0.5rem;
}
.media-upload-btn {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    border: 1px dashed var(--border);
    border-radius: 6px;
    background: var(--bg-secondary);
    cursor: pointer;
    transition: all 0.15s;
    font-size: 0.8125rem;
    color: var(--text-secondary);
}
.media-upload-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--bg-card); }
.media-upload-btn.dragover { border-color: var(--primary); background: rgba(102, 126, 234, 0.1); }
.media-upload-btn i { width: 16px; height: 16px; }
.media-upload-btn input { display: none; }

.media-view-toggle {
    display: flex;
    gap: 0.25rem;
    background: var(--bg-secondary);
    padding: 0.25rem;
    border-radius: 6px;
    border: 1px solid var(--border);
}
.media-view-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border: none;
    background: transparent;
    color: var(--text-muted);
    cursor: pointer;
    border-radius: 4px;
    transition: all 0.15s;
}
.media-view-btn:hover { color: var(--text-primary); }
.media-view-btn.active { background: var(--bg-card); color: var(--primary); box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
.media-view-btn i { width: 16px; height: 16px; }

.media-breadcrumb {
    display: none;
    align-items: center;
    gap: 0.25rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.6875rem;
    border-bottom: 1px solid var(--border);
    background: var(--bg-card);
    flex-wrap: wrap;
}
.media-breadcrumb.visible { display: flex; }
.media-breadcrumb a { color: var(--text-muted); text-decoration: none; }
.media-breadcrumb a:hover { color: var(--primary); }
.media-breadcrumb .sep { color: var(--text-muted); }
.media-breadcrumb .current { color: var(--text-primary); font-weight: 500; }

/* Media Grid View - Consistent with Files Manager */
.media-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    gap: 0.75rem;
    padding: 1rem;
    overflow-y: auto;
    flex: 1;
    align-content: start;
    background: var(--bg-card);
}
.media-grid-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 0.5rem;
    border-radius: 8px;
    cursor: pointer;
    border: 2px solid transparent;
    transition: all 0.15s;
    position: relative;
    text-align: center;
}
.media-grid-item:hover { background: var(--bg-secondary); }
.media-grid-item.selected { border-color: var(--primary); background: rgba(102, 126, 234, 0.08); }
.media-grid-item-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    overflow: hidden;
    flex-shrink: 0;
}
.media-grid-item-icon.folder { background: transparent; }
.media-grid-item-icon i { width: 40px; height: 40px; color: var(--text-muted); }
.media-grid-item-icon.folder i { color: var(--warning); }
.media-grid-item-icon img { width: 48px; height: 48px; object-fit: cover; border-radius: 6px; }
.media-grid-item-name {
    font-size: 0.75rem;
    color: var(--text-primary);
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    word-break: break-all;
}
.media-grid-item-size {
    font-size: 0.6875rem;
    color: var(--text-muted);
}
.media-grid-item .check-mark {
    position: absolute;
    top: 4px;
    right: 4px;
    width: 18px;
    height: 18px;
    background: var(--primary);
    border-radius: 50%;
    display: none;
    align-items: center;
    justify-content: center;
    color: white;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.media-grid-item.selected .check-mark { display: flex; }
.media-grid-item .check-mark i { width: 10px; height: 10px; }

/* Media List View - Consistent with Files Manager */
.media-list {
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    flex: 1;
    background: var(--bg-card);
}
.media-list-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.625rem 1rem;
    cursor: pointer;
    border-bottom: 1px solid var(--border);
    transition: all 0.15s;
}
.media-list-item:last-child { border-bottom: none; }
.media-list-item:hover { background: var(--bg-secondary); }
.media-list-item.selected { background: rgba(102, 126, 234, 0.08); }
.media-list-item-icon {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    overflow: hidden;
    flex-shrink: 0;
}
.media-list-item-icon.folder { background: transparent; }
.media-list-item-icon i { width: 20px; height: 20px; color: var(--text-muted); }
.media-list-item-icon.folder i { color: var(--warning); }
.media-list-item-icon img { width: 100%; height: 100%; object-fit: cover; }
.media-list-item-info { flex: 1; min-width: 0; }
.media-list-item-name {
    font-size: 0.8125rem;
    color: var(--text-primary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.media-list-item-size {
    font-size: 0.6875rem;
    color: var(--text-muted);
    margin-top: 0.125rem;
}
.media-list-item .check-mark {
    width: 20px;
    height: 20px;
    background: var(--primary);
    border-radius: 50%;
    display: none;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.media-list-item.selected .check-mark { display: flex; }
.media-list-item .check-mark i { width: 12px; height: 12px; }

.media-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 2rem;
    color: var(--text-muted);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.media-list .media-empty { padding: 3rem 1rem; }
.media-empty i {
    width: 32px;
    height: 32px;
    margin-bottom: 0.5rem;
    opacity: 0.5;
}
.media-empty p { font-size: 0.8125rem; }

/* Footer */
.asset-picker-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    padding: 1rem 1.25rem;
    border-top: 1px solid var(--border);
    background: var(--bg-card);
    flex-shrink: 0;
}

/* Trigger Buttons */
.asset-trigger {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 0.75rem;
    background: var(--bg-card-hover);
    border: 1px solid var(--border);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s;
    font-size: 0.875rem;
    color: var(--text-primary);
    width: 100%;
}
.asset-trigger:hover {
    border-color: var(--primary);
    background: var(--bg-card);
}
.asset-trigger-preview {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-secondary);
    flex-shrink: 0;
    overflow: hidden;
}
.asset-trigger-preview i { width: 20px; height: 20px; color: var(--text-muted); }
.asset-trigger-preview img { width: 100%; height: 100%; object-fit: cover; }
.asset-trigger-text {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--text-secondary);
}
.asset-trigger-text.has-value { color: var(--text-primary); }
.asset-trigger-action {
    font-size: 0.75rem;
    color: var(--primary);
    flex-shrink: 0;
}

/* Mobile Responsive */
@media (max-width: 600px) {
    .asset-picker-dialog {
        width: 100vw;
        max-width: 100vw;
        height: 100vh;
        height: 100dvh; /* Dynamic viewport height for mobile */
        max-height: 100vh;
        max-height: 100dvh;
        border-radius: 0;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        transform: none;
        margin: 0;
    }
    .asset-picker-header { padding: 0.75rem 1rem; }
    .asset-picker-header h3 { font-size: 0.9375rem; }
    .asset-picker-tabs { padding: 0.5rem 1rem; overflow-x: auto; }
    .asset-picker-tab { padding: 0.5rem 0.75rem; font-size: 0.75rem; white-space: nowrap; }
    .asset-picker-tab span { display: none; }
    .icon-search { margin: 0.75rem 1rem 0.5rem; padding: 0.625rem 0.625rem 0.625rem 2.25rem; }
    .icon-grid-container { padding: 0 1rem 0.75rem; }
    .icon-grid { grid-template-columns: repeat(auto-fill, minmax(38px, 1fr)); }
    .media-sidebar { display: none; }
    .media-breadcrumb.visible { display: flex; }
    .media-grid { grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); gap: 0.5rem; padding: 0.75rem; }
    .media-grid-item { padding: 0.5rem 0.375rem; }
    .media-grid-item-icon { width: 40px; height: 40px; }
    .media-grid-item-icon i { width: 32px; height: 32px; }
    .media-grid-item-icon img { width: 40px; height: 40px; }
    .media-toolbar { padding: 0.5rem 0.75rem; }
    .asset-picker-footer { padding: 0.75rem 1rem; }
}

/* Tablet - slightly smaller fixed height */
@media (min-width: 601px) and (max-height: 700px) {
    .asset-picker-dialog {
        height: 90vh;
        max-height: 550px;
    }
}
</style>
<?php
}

function renderAssetPickerModal($popularIcons = []) {
    // Default popular icons if not provided (60 icons to fill the grid)
    if (empty($popularIcons)) {
        $popularIcons = [
            'link', 'globe', 'star', 'heart', 'bookmark', 'folder', 'file', 'image', 'video', 'music',
            'code', 'terminal', 'database', 'server', 'cloud', 'mail', 'message-circle', 'phone', 'calendar', 'clock',
            'map-pin', 'home', 'user', 'users', 'settings', 'tool', 'zap', 'trending-up', 'bar-chart', 'pie-chart',
            'search', 'bell', 'lock', 'unlock', 'key', 'shield', 'eye', 'download', 'upload', 'share',
            'copy', 'trash', 'edit', 'plus', 'minus', 'check', 'x', 'alert-circle', 'info', 'help-circle',
            'arrow-right', 'arrow-left', 'chevron-right', 'chevron-down', 'menu', 'grid', 'list', 'layout', 'layers', 'box'
        ];
    }
?>
<dialog class="asset-picker-dialog" id="assetPickerDialog">
    <div class="asset-picker-header">
        <h3 id="assetPickerTitle">Select Asset</h3>
        <button type="button" class="asset-picker-close" onclick="closeAssetPicker()">
            <i data-lucide="x"></i>
        </button>
    </div>

    <div class="asset-picker-tabs" id="assetPickerTabs">
        <button type="button" class="asset-picker-tab" data-tab="library" onclick="showAssetTab('library')">
            <i data-lucide="grid-3x3"></i><span>Icons</span>
        </button>
        <button type="button" class="asset-picker-tab" data-tab="url" onclick="showAssetTab('url')">
            <i data-lucide="link"></i><span>URL</span>
        </button>
        <button type="button" class="asset-picker-tab" data-tab="media" onclick="showAssetTab('media')">
            <i data-lucide="folder"></i><span>Media</span>
        </button>
    </div>

    <div class="asset-picker-content">
        <!-- Icon Library Tab -->
        <div class="asset-picker-tab-content" data-tab="library" id="assetTabLibrary">
            <input type="text" class="icon-search" id="assetIconSearch" placeholder="Search 1500+ Lucide icons..." oninput="filterAssetIcons(this.value)">
            <div class="icon-grid-container" id="assetIconGrid">
                <div class="icon-category-title">Popular</div>
                <div class="icon-grid" id="assetPopularIcons">
                    <?php foreach ($popularIcons as $icon): ?>
                    <div class="icon-item" data-icon="<?= $icon ?>" onclick="selectAssetIcon('<?= $icon ?>', this)">
                        <i data-lucide="<?= $icon ?>"></i>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="show-all-btn" id="assetShowAllBtn" onclick="loadAllAssetIcons()">
                    Show all 1500+ icons
                </button>
                <div id="assetAllIcons" style="display:none;"></div>
            </div>
        </div>

        <!-- URL Tab -->
        <div class="asset-picker-tab-content" data-tab="url" id="assetTabUrl">
            <div class="url-tab-content">
                <div class="url-input-group">
                    <label>Image URL</label>
                    <input type="text" id="assetUrlInput" placeholder="https://example.com/image.png" oninput="previewAssetUrl(this.value)">
                </div>
                <div class="url-preview-box" id="assetUrlPreview">
                    <div class="placeholder">
                        <i data-lucide="image"></i>
                        <p>Enter an image URL above to preview</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Media Tab -->
        <div class="asset-picker-tab-content" data-tab="media" id="assetTabMedia">
            <div class="media-tab-layout">
                <div class="media-sidebar" id="assetMediaSidebar"></div>
                <div class="media-main">
                    <div class="media-toolbar">
                        <div class="media-upload-btn" id="assetUploadZone" onclick="document.getElementById('assetUploadInput').click()">
                            <i data-lucide="upload"></i>
                            <span>Upload</span>
                            <input type="file" id="assetUploadInput" multiple>
                        </div>
                        <div class="media-view-toggle">
                            <button type="button" class="media-view-btn active" id="assetGridViewBtn" onclick="setAssetMediaView('grid')" title="Grid view">
                                <i data-lucide="grid-3x3"></i>
                            </button>
                            <button type="button" class="media-view-btn" id="assetListViewBtn" onclick="setAssetMediaView('list')" title="List view">
                                <i data-lucide="list"></i>
                            </button>
                        </div>
                    </div>
                    <div class="media-breadcrumb" id="assetBreadcrumb"></div>
                    <div class="media-grid" id="assetMediaGrid">
                        <div class="media-empty">
                            <i data-lucide="loader"></i>
                            <p>Loading...</p>
                        </div>
                    </div>
                    <div class="media-list" id="assetMediaList" style="display:none;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="asset-picker-footer">
        <button type="button" class="btn" onclick="closeAssetPicker()">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="confirmAssetPicker()">Select</button>
    </div>
</dialog>

<script>
// Asset Picker State
let assetPickerMode = 'icon'; // 'icon', 'image', 'file'
let assetPickerCallback = null;
let assetPickerTrigger = null;
let assetCurrentPath = '';
let assetFolders = [];
let assetSelectedValue = null;
let assetSelectedType = null; // 'library', 'external', 'file'
let assetAllIconsLoaded = false;
let assetMediaView = localStorage.getItem('assetMediaView') || 'grid';

const assetImageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico'];
const assetFileIcons = {
    'md': 'file-text', 'html': 'file-code', 'htm': 'file-code',
    'pdf': 'file-text', 'doc': 'file-text', 'docx': 'file-text', 'txt': 'file-text',
    'css': 'file-code', 'js': 'file-code', 'json': 'file-code', 'xml': 'file-code',
    'csv': 'file-spreadsheet', 'zip': 'file-archive'
};

/**
 * Open the asset picker
 * @param {string} mode - 'icon', 'image', or 'file'
 * @param {function} callback - Called with {type, value} on confirm
 * @param {object} current - Current value {type, value}
 * @param {HTMLElement} trigger - Trigger element to update
 */
function openAssetPicker(mode, callback, current = null, trigger = null) {
    assetPickerMode = mode;
    assetPickerCallback = callback;
    assetPickerTrigger = trigger;
    assetSelectedValue = current?.value || null;
    assetSelectedType = current?.type || null;
    assetCurrentPath = '';

    // Configure tabs based on mode
    const tabs = document.getElementById('assetPickerTabs');
    const libraryTab = tabs.querySelector('[data-tab="library"]');
    const urlTab = tabs.querySelector('[data-tab="url"]');
    const mediaTab = tabs.querySelector('[data-tab="media"]');

    // Set title based on mode
    const titles = { icon: 'Select Icon', image: 'Select Image', file: 'Select File' };
    document.getElementById('assetPickerTitle').textContent = titles[mode] || 'Select Asset';

    // Show/hide tabs based on mode
    libraryTab.style.display = mode === 'icon' ? 'flex' : 'none';
    urlTab.style.display = (mode === 'icon' || mode === 'image') ? 'flex' : 'none';
    mediaTab.style.display = 'flex';

    // Configure upload input
    const uploadInput = document.getElementById('assetUploadInput');
    uploadInput.accept = (mode === 'file') ? '*/*' : 'image/*';

    // Set default active tab
    let defaultTab = mode === 'icon' ? 'library' : (mode === 'image' ? 'url' : 'media');

    // If we have a current value, switch to appropriate tab
    if (current) {
        if (current.type === 'library') defaultTab = 'library';
        else if (current.type === 'external') defaultTab = 'url';
        else if (current.type === 'file') defaultTab = 'media';
    }

    showAssetTab(defaultTab);

    // Pre-fill current value
    if (current?.type === 'library' && current?.value) {
        const iconName = current.value.replace('lucide:', '');
        document.querySelectorAll('#assetPopularIcons .icon-item').forEach(el => {
            el.classList.toggle('selected', el.dataset.icon === iconName);
        });
    } else if (current?.type === 'external' && current?.value) {
        document.getElementById('assetUrlInput').value = current.value;
        previewAssetUrl(current.value);
    }

    // Load media
    loadAssetMedia();

    // Show dialog
    document.getElementById('assetPickerDialog').showModal();
    lucide.createIcons();
}

function closeAssetPicker() {
    document.getElementById('assetPickerDialog').close();
    assetPickerCallback = null;
    assetPickerTrigger = null;
}

function showAssetTab(tab) {
    document.querySelectorAll('.asset-picker-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.asset-picker-tab-content').forEach(c => c.classList.remove('active'));

    const tabBtn = document.querySelector(`.asset-picker-tab[data-tab="${tab}"]`);
    const tabContent = document.querySelector(`.asset-picker-tab-content[data-tab="${tab}"]`);

    if (tabBtn) tabBtn.classList.add('active');
    if (tabContent) tabContent.classList.add('active');

    if (tab === 'media') {
        updateAssetMediaViewUI();
        loadAssetMediaPath(assetCurrentPath);
    }

    lucide.createIcons();
}

// Media View Toggle
function setAssetMediaView(view) {
    assetMediaView = view;
    localStorage.setItem('assetMediaView', view);
    updateAssetMediaViewUI();
    loadAssetMediaPath(assetCurrentPath);
}

function updateAssetMediaViewUI() {
    const gridBtn = document.getElementById('assetGridViewBtn');
    const listBtn = document.getElementById('assetListViewBtn');
    const gridView = document.getElementById('assetMediaGrid');
    const listView = document.getElementById('assetMediaList');

    if (assetMediaView === 'list') {
        gridBtn?.classList.remove('active');
        listBtn?.classList.add('active');
        if (gridView) gridView.style.display = 'none';
        if (listView) listView.style.display = 'flex';
    } else {
        gridBtn?.classList.add('active');
        listBtn?.classList.remove('active');
        if (gridView) gridView.style.display = 'grid';
        if (listView) listView.style.display = 'none';
    }
    lucide.createIcons();
}

// Icon Library
function selectAssetIcon(iconName, element) {
    document.querySelectorAll('#assetIconGrid .icon-item').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    assetSelectedType = 'library';
    assetSelectedValue = 'lucide:' + iconName;
}

// All Lucide icons list
const ALL_LUCIDE_ICONS = ["activity","airplay","alarm-check","alarm-clock","alarm-minus","alarm-plus","album","alert-circle","alert-octagon","alert-triangle","align-center","align-center-horizontal","align-center-vertical","align-end-horizontal","align-end-vertical","align-horizontal-distribute-center","align-horizontal-distribute-end","align-horizontal-distribute-start","align-horizontal-justify-center","align-horizontal-justify-end","align-horizontal-justify-start","align-horizontal-space-around","align-horizontal-space-between","align-justify","align-left","align-right","align-start-horizontal","align-start-vertical","align-vertical-distribute-center","align-vertical-distribute-end","align-vertical-distribute-start","align-vertical-justify-center","align-vertical-justify-end","align-vertical-justify-start","align-vertical-space-around","align-vertical-space-between","anchor","angry","annoyed","aperture","app-window","apple","archive","archive-restore","armchair","arrow-big-down","arrow-big-left","arrow-big-right","arrow-big-up","arrow-down","arrow-down-circle","arrow-down-left","arrow-down-right","arrow-left","arrow-left-circle","arrow-left-right","arrow-right","arrow-right-circle","arrow-up","arrow-up-circle","arrow-up-down","arrow-up-left","arrow-up-right","asterisk","at-sign","atom","award","axe","axis-3d","baby","backpack","badge","badge-alert","badge-check","badge-dollar-sign","badge-help","badge-info","badge-minus","badge-percent","badge-plus","badge-x","banana","banknote","bar-chart","bar-chart-2","bar-chart-3","bar-chart-4","bar-chart-horizontal","baseline","bath","battery","battery-charging","battery-full","battery-low","battery-medium","battery-warning","beaker","bean","bean-off","bed","bed-double","bed-single","beef","beer","bell","bell-minus","bell-off","bell-plus","bell-ring","bike","binary","biohazard","bird","bitcoin","bluetooth","bluetooth-connected","bluetooth-off","bluetooth-searching","bold","bomb","bone","book","book-copy","book-down","book-key","book-lock","book-marked","book-minus","book-open","book-open-check","book-plus","book-template","book-up","book-user","book-x","bookmark","bookmark-minus","bookmark-plus","bot","box","box-select","boxes","braces","brackets","brain","brain-circuit","brain-cog","briefcase","bring-to-front","brush","bug","building","building-2","bus","cable","cable-car","cake","cake-slice","calculator","calendar","calendar-check","calendar-check-2","calendar-clock","calendar-days","calendar-heart","calendar-minus","calendar-off","calendar-plus","calendar-range","calendar-search","calendar-x","calendar-x-2","camera","camera-off","candlestick-chart","candy","candy-cane","candy-off","car","carrot","case-lower","case-sensitive","case-upper","cast","castle","cat","check","check-circle","check-circle-2","check-square","chef-hat","cherry","chevron-down","chevron-first","chevron-last","chevron-left","chevron-right","chevron-up","chevrons-down","chevrons-down-up","chevrons-left","chevrons-left-right","chevrons-right","chevrons-right-left","chevrons-up","chevrons-up-down","chrome","church","cigarette","cigarette-off","circle","circle-dashed","circle-dot","circle-ellipsis","circle-equal","circle-off","circle-slash","circle-slash-2","citrus","clapperboard","clipboard","clipboard-check","clipboard-copy","clipboard-edit","clipboard-list","clipboard-paste","clipboard-signature","clipboard-type","clipboard-x","clock","clock-1","clock-10","clock-11","clock-12","clock-2","clock-3","clock-4","clock-5","clock-6","clock-7","clock-8","clock-9","cloud","cloud-cog","cloud-drizzle","cloud-fog","cloud-hail","cloud-lightning","cloud-moon","cloud-moon-rain","cloud-off","cloud-rain","cloud-rain-wind","cloud-snow","cloud-sun","cloud-sun-rain","cloudy","clover","club","code","code-2","codepen","codesandbox","coffee","cog","coins","columns","combine","command","compass","component","computer","concierge-bell","cone","construction","contact","contact-2","container","contrast","cookie","copy","copy-check","copy-minus","copy-plus","copy-slash","copy-x","copyleft","copyright","corner-down-left","corner-down-right","corner-left-down","corner-left-up","corner-right-down","corner-right-up","corner-up-left","corner-up-right","cpu","creative-commons","credit-card","croissant","crop","cross","crosshair","crown","cuboid","cup-soda","currency","database","database-backup","delete","dessert","diamond","dice-1","dice-2","dice-3","dice-4","dice-5","dice-6","dices","diff","disc","disc-2","disc-3","divide","divide-circle","divide-square","dna","dna-off","dog","dollar-sign","donut","door-closed","door-open","dot","download","download-cloud","drafting-compass","drama","dribbble","droplet","droplets","drumstick","dumbbell","ear","ear-off","edit","edit-2","edit-3","egg","egg-fried","egg-off","equal","equal-not","eraser","euro","expand","external-link","eye","eye-off","facebook","factory","fan","fast-forward","feather","figma","file","file-archive","file-audio","file-audio-2","file-axis-3d","file-badge","file-badge-2","file-bar-chart","file-bar-chart-2","file-box","file-check","file-check-2","file-clock","file-code","file-code-2","file-cog","file-cog-2","file-diff","file-digit","file-down","file-edit","file-heart","file-image","file-input","file-json","file-json-2","file-key","file-key-2","file-line-chart","file-lock","file-lock-2","file-minus","file-minus-2","file-output","file-pie-chart","file-plus","file-plus-2","file-question","file-scan","file-search","file-search-2","file-signature","file-spreadsheet","file-symlink","file-terminal","file-text","file-type","file-type-2","file-up","file-video","file-video-2","file-volume","file-volume-2","file-warning","file-x","file-x-2","files","film","filter","filter-x","fingerprint","flag","flag-off","flag-triangle-left","flag-triangle-right","flame","flashlight","flashlight-off","flask-conical","flask-conical-off","flask-round","flip-horizontal","flip-horizontal-2","flip-vertical","flip-vertical-2","flower","flower-2","focus","folder","folder-archive","folder-check","folder-clock","folder-closed","folder-cog","folder-cog-2","folder-dot","folder-down","folder-edit","folder-git","folder-git-2","folder-heart","folder-input","folder-kanban","folder-key","folder-lock","folder-minus","folder-open","folder-open-dot","folder-output","folder-plus","folder-root","folder-search","folder-search-2","folder-symlink","folder-sync","folder-tree","folder-up","folder-x","folders","footprints","forklift","form-input","forward","frame","framer","frown","fuel","function-square","gallery-horizontal","gallery-horizontal-end","gallery-thumbnails","gallery-vertical","gallery-vertical-end","gamepad","gamepad-2","gauge","gauge-circle","gavel","gem","ghost","gift","git-branch","git-branch-plus","git-commit","git-compare","git-fork","git-merge","git-pull-request","git-pull-request-closed","git-pull-request-draft","github","gitlab","glass-water","glasses","globe","globe-2","goal","grab","graduation-cap","grape","grid","grid-2x2","grid-3x3","grip","grip-horizontal","grip-vertical","group","hammer","hand","hand-metal","hard-drive","hard-drive-download","hard-drive-upload","hard-hat","hash","haze","hdmi-port","heading","heading-1","heading-2","heading-3","heading-4","heading-5","heading-6","headphones","heart","heart-crack","heart-handshake","heart-off","heart-pulse","help-circle","hexagon","highlighter","history","home","hop","hop-off","hotel","hourglass","ice-cream","ice-cream-2","image","image-minus","image-off","image-plus","import","inbox","indent","indian-rupee","infinity","info","inspect","instagram","italic","iteration-ccw","iteration-cw","japanese-yen","joystick","kanban","kanban-square","kanban-square-dashed","key","key-round","key-square","keyboard","lamp","lamp-ceiling","lamp-desk","lamp-floor","lamp-wall-down","lamp-wall-up","landmark","languages","laptop","laptop-2","lasso","lasso-select","laugh","layers","layout","layout-dashboard","layout-grid","layout-list","layout-panel-left","layout-panel-top","layout-template","leaf","library","life-buoy","ligature","lightbulb","lightbulb-off","line-chart","link","link-2","link-2-off","linkedin","list","list-checks","list-end","list-filter","list-minus","list-music","list-ordered","list-plus","list-restart","list-start","list-todo","list-tree","list-video","list-x","loader","loader-2","locate","locate-fixed","locate-off","lock","log-in","log-out","lollipop","luggage","magnet","mail","mail-check","mail-minus","mail-open","mail-plus","mail-question","mail-search","mail-warning","mail-x","mailbox","mails","map","map-pin","map-pin-off","map-pinned","martini","maximize","maximize-2","medal","megaphone","meh","memory-stick","menu","menu-square","merge","message-circle","message-square","message-square-dashed","message-square-plus","mic","mic-2","mic-off","microscope","microwave","milestone","milk","milk-off","minimize","minimize-2","minus","minus-circle","minus-square","monitor","monitor-check","monitor-dot","monitor-down","monitor-off","monitor-pause","monitor-play","monitor-smartphone","monitor-speaker","monitor-stop","monitor-up","monitor-x","moon","moon-star","more-horizontal","more-vertical","mountain","mountain-snow","mouse","mouse-pointer","mouse-pointer-2","mouse-pointer-click","mouse-pointer-square","mouse-pointer-square-dashed","move","move-3d","move-diagonal","move-diagonal-2","move-down","move-down-left","move-down-right","move-horizontal","move-left","move-right","move-up","move-up-left","move-up-right","move-vertical","music","music-2","music-3","music-4","navigation","navigation-2","navigation-2-off","navigation-off","network","newspaper","nfc","octagon","option","orbit","outdent","package","package-2","package-check","package-minus","package-open","package-plus","package-search","package-x","paint-bucket","paintbrush","paintbrush-2","palette","palmtree","pan-bottom-left","pan-bottom-right","pan-left","pan-right","pan-top-left","pan-top-right","panel-bottom","panel-bottom-close","panel-bottom-inactive","panel-bottom-open","panel-left","panel-left-close","panel-left-inactive","panel-left-open","panel-right","panel-right-close","panel-right-inactive","panel-right-open","panel-top","panel-top-close","panel-top-inactive","panel-top-open","paperclip","parentheses","parking-circle","parking-circle-off","parking-meter","parking-square","parking-square-off","party-popper","pause","pause-circle","pause-octagon","paw-print","pc-case","pen","pen-line","pen-tool","pencil","pencil-line","pencil-ruler","percent","percent-circle","percent-diamond","percent-square","person-standing","phone","phone-call","phone-forwarded","phone-incoming","phone-missed","phone-off","phone-outgoing","pi","pi-square","picture-in-picture","picture-in-picture-2","pie-chart","piggy-bank","pilcrow","pilcrow-square","pill","pin","pin-off","pipette","pizza","plane","plane-landing","plane-takeoff","play","play-circle","play-square","plug","plug-2","plug-zap","plug-zap-2","plus","plus-circle","plus-square","pocket","pocket-knife","podcast","pointer","popcorn","popsicle","pound-sterling","power","power-circle","power-off","power-square","presentation","printer","projector","puzzle","pyramid","qr-code","quote","rabbit","radar","radiation","radio","radio-receiver","radio-tower","rainbow","rat","ratio","receipt","rectangle-horizontal","rectangle-vertical","recycle","redo","redo-2","redo-dot","refresh-ccw","refresh-ccw-dot","refresh-cw","refresh-cw-off","refrigerator","regex","remove-formatting","repeat","repeat-1","repeat-2","replace","replace-all","reply","reply-all","rewind","ribbon","rocket","rocking-chair","roller-coaster","rotate-3d","rotate-ccw","rotate-cw","route","route-off","router","rows","rss","ruler","russian-ruble","sailboat","salad","sandwich","satellite","satellite-dish","save","save-all","scale","scale-3d","scaling","scan","scan-barcode","scan-eye","scan-face","scan-line","scan-search","scan-text","scatter-chart","school","school-2","scissors","scissors-line-dashed","scissors-square","scissors-square-dashed-bottom","screen-share","screen-share-off","scroll","scroll-text","search","search-check","search-code","search-slash","search-x","send","send-horizontal","send-to-back","separator-horizontal","separator-vertical","server","server-cog","server-crash","server-off","settings","settings-2","share","share-2","sheet","shell","shield","shield-alert","shield-ban","shield-check","shield-ellipsis","shield-half","shield-minus","shield-off","shield-plus","shield-question","shield-x","ship","ship-wheel","shirt","shopping-bag","shopping-basket","shopping-cart","shovel","shower-head","shrink","shrub","shuffle","sigma","sigma-square","signal","signal-high","signal-low","signal-medium","signal-zero","siren","skip-back","skip-forward","skull","slack","slash","slice","sliders","sliders-horizontal","smartphone","smartphone-charging","smartphone-nfc","smile","smile-plus","snail","snowflake","sofa","sort-asc","sort-desc","soup","space","spade","sparkle","sparkles","speaker","speech","spell-check","spell-check-2","spline","split","split-square-horizontal","split-square-vertical","spray-can","sprout","square","square-asterisk","square-code","square-dashed-bottom","square-dashed-bottom-code","square-dot","square-equal","square-gantt-chart","square-kanban","square-library","square-m","square-menu","square-minus","square-parking","square-parking-off","square-pen","square-percent","square-pi","square-pilcrow","square-play","square-plus","square-power","square-radical","square-scissors","square-sigma","square-slash","square-split-horizontal","square-split-vertical","square-stack","square-terminal","square-user","square-user-round","square-x","squircle","squirrel","stamp","star","star-half","star-off","step-back","step-forward","stethoscope","sticker","sticky-note","stop-circle","store","stretch-horizontal","stretch-vertical","strikethrough","subscript","subtitles","sun","sun-dim","sun-medium","sun-moon","sun-snow","sunrise","sunset","superscript","swap-horizontal","swiss-franc","switch-camera","sword","swords","syringe","table","table-2","table-properties","tablet","tablet-smartphone","tablets","tag","tags","tally-1","tally-2","tally-3","tally-4","tally-5","tangent","target","tent","tent-tree","terminal","terminal-square","test-tube","test-tube-2","test-tubes","text","text-cursor","text-cursor-input","text-quote","text-select","theater","thermometer","thermometer-snowflake","thermometer-sun","thumbs-down","thumbs-up","ticket","timer","timer-off","timer-reset","toggle-left","toggle-right","tornado","torus","touchpad","touchpad-off","tower-control","toy-brick","tractor","traffic-cone","train","train-front","train-front-tunnel","train-track","tram-front","trash","trash-2","tree-deciduous","tree-pine","trees","trello","trending-down","trending-up","triangle","triangle-right","trophy","truck","turtle","tv","tv-2","twitch","twitter","type","umbrella","umbrella-off","underline","undo","undo-2","undo-dot","unfold-horizontal","unfold-vertical","ungroup","unlink","unlink-2","unlock","unlock-keyhole","unplug","upload","upload-cloud","usb","user","user-check","user-cog","user-minus","user-plus","user-round","user-round-check","user-round-cog","user-round-minus","user-round-plus","user-round-search","user-round-x","user-search","user-x","users","users-round","utensils","utensils-crossed","utility-pole","variable","vault","vegan","venetian-mask","vibrate","vibrate-off","video","video-off","videotape","view","voicemail","volume","volume-1","volume-2","volume-x","vote","wallet","wallet-2","wallet-cards","wallpaper","wand","wand-2","warehouse","washing-machine","watch","waves","waypoints","webcam","webhook","weight","wheat","wheat-off","whole-word","wifi","wifi-off","wind","wine","wine-off","workflow","wrap-text","wrench","x","x-circle","x-octagon","x-square","youtube","zap","zap-off","zoom-in","zoom-out"];

// Search icons - renders only matching icons (fast, no pre-loading needed)
function filterAssetIcons(query) {
    query = (query || '').toLowerCase().trim();
    const container = document.getElementById('assetAllIcons');
    const popularTitle = document.querySelector('#assetIconGrid > .icon-category-title');
    const popularGrid = document.getElementById('assetPopularIcons');
    const showAllBtn = document.getElementById('assetShowAllBtn');

    // No query - show popular icons, hide search results
    if (!query) {
        if (popularTitle) popularTitle.style.display = 'block';
        if (popularGrid) popularGrid.style.display = 'grid';
        if (showAllBtn) showAllBtn.style.display = assetAllIconsLoaded ? 'none' : 'block';
        container.style.display = assetAllIconsLoaded ? 'block' : 'none';
        return;
    }

    // Hide popular section, show search results
    if (popularTitle) popularTitle.style.display = 'none';
    if (popularGrid) popularGrid.style.display = 'none';
    if (showAllBtn) showAllBtn.style.display = 'none';
    container.style.display = 'block';

    // Filter icons from array (fast)
    const matches = ALL_LUCIDE_ICONS.filter(icon => icon.includes(query));

    if (matches.length === 0) {
        container.innerHTML = '<div class="icon-category-title">No icons found</div>';
        return;
    }

    // Render only matching icons (much faster than filtering 1400+ DOM elements)
    let html = `<div class="icon-category-title">Results (${matches.length})</div><div class="icon-grid">`;
    matches.forEach(icon => {
        const isSelected = assetSelectedValue === 'lucide:' + icon;
        html += `<div class="icon-item ${isSelected ? 'selected' : ''}" data-icon="${icon}" onclick="selectAssetIcon('${icon}', this)"><i data-lucide="${icon}"></i></div>`;
    });
    html += '</div>';

    container.innerHTML = html;
    lucide.createIcons({ nodes: [container] });
}

// Show all icons with batch rendering
function loadAllAssetIcons(callback) {
    if (assetAllIconsLoaded) {
        if (callback) callback();
        return;
    }

    const btn = document.getElementById('assetShowAllBtn');
    btn.textContent = 'Loading...';
    btn.disabled = true;

    const container = document.getElementById('assetAllIcons');

    // Create HTML with placeholder icons (no lucide icon yet - will be lazy loaded)
    let html = '<div class="icon-category-title">All Icons</div><div class="icon-grid">';
    ALL_LUCIDE_ICONS.forEach(icon => {
        html += `<div class="icon-item" data-icon="${icon}" onclick="selectAssetIcon('${icon}', this)"><i data-lucide="${icon}"></i></div>`;
    });
    html += '</div>';

    container.innerHTML = html;
    btn.style.display = 'none';
    container.style.display = 'block';
    assetAllIconsLoaded = true;

    // Render icons in batches to avoid UI freeze
    const items = container.querySelectorAll('.icon-item');
    const batchSize = 50;
    let index = 0;

    function renderBatch() {
        const batch = Array.from(items).slice(index, index + batchSize);
        if (batch.length === 0) {
            if (callback) callback();
            return;
        }

        batch.forEach(item => {
            item.dataset.loaded = 'true';
        });

        // Use requestAnimationFrame for smooth rendering
        requestAnimationFrame(() => {
            lucide.createIcons({ nodes: batch });
            index += batchSize;
            // Small delay between batches to keep UI responsive
            setTimeout(renderBatch, 10);
        });
    }

    renderBatch();
}

// URL Tab
function previewAssetUrl(url) {
    const box = document.getElementById('assetUrlPreview');
    if (url && url.match(/^https?:\/\/.+/)) {
        box.innerHTML = `<img src="${url}" alt="Preview" onerror="this.parentElement.innerHTML='<div class=\\'placeholder\\'><i data-lucide=\\'alert-circle\\'></i><p>Failed to load image</p></div>';lucide.createIcons();">`;
        assetSelectedType = 'external';
        assetSelectedValue = url;
    } else {
        box.innerHTML = `<div class="placeholder"><i data-lucide="image"></i><p>Enter an image URL above to preview</p></div>`;
        lucide.createIcons();
    }
}

// Media Tab
function loadAssetMedia() {
    fetch(SITE_URL + '/api/files.php?folders=1')
        .then(r => r.json())
        .then(data => {
            assetFolders = data.folders || [];
            renderAssetSidebar();
        })
        .catch(() => {});

    loadAssetMediaPath('');
}

function renderAssetSidebar() {
    const sidebar = document.getElementById('assetMediaSidebar');
    let html = `
        <div class="media-sidebar-header">Folders</div>
        <div class="media-sidebar-item root-item ${assetCurrentPath === '' ? 'active' : ''}" onclick="loadAssetMediaPath('')">
            <i data-lucide="home"></i>
            <span>Home</span>
        </div>
    `;
    assetFolders.forEach(folder => {
        html += `
            <div class="media-sidebar-item ${assetCurrentPath === folder ? 'active' : ''}" onclick="loadAssetMediaPath('${folder}')">
                <i data-lucide="folder"></i>
                <span>${folder}</span>
            </div>
        `;
    });
    sidebar.innerHTML = html;
    lucide.createIcons();
}

function loadAssetMediaPath(path) {
    assetCurrentPath = path;
    renderAssetSidebar();

    const grid = document.getElementById('assetMediaGrid');
    const list = document.getElementById('assetMediaList');
    const breadcrumb = document.getElementById('assetBreadcrumb');

    const loadingHtml = '<div class="media-empty"><i data-lucide="loader"></i><p>Loading...</p></div>';
    grid.innerHTML = loadingHtml;
    list.innerHTML = loadingHtml;
    lucide.createIcons();

    // Breadcrumb
    if (path) {
        const parts = path.split('/');
        let buildPath = '';
        let html = '<a href="#" onclick="loadAssetMediaPath(\'\'); return false;"><i data-lucide="home" style="width:12px;height:12px;"></i></a>';
        parts.forEach((part, i) => {
            buildPath += (buildPath ? '/' : '') + part;
            html += '<span class="sep">/</span>';
            if (i === parts.length - 1) {
                html += `<span class="current">${part}</span>`;
            } else {
                html += `<a href="#" onclick="loadAssetMediaPath('${buildPath}'); return false;">${part}</a>`;
            }
        });
        breadcrumb.innerHTML = html;
        breadcrumb.classList.add('visible');
    } else {
        breadcrumb.innerHTML = '';
        breadcrumb.classList.remove('visible');
    }

    fetch(SITE_URL + '/api/files.php?path=' + encodeURIComponent(path))
        .then(r => r.json())
        .then(data => {
            if (data.items && data.items.length > 0) {
                // Filter based on mode
                const filteredItems = data.items.filter(item => {
                    if (item.type === 'folder') return true;
                    if (assetPickerMode === 'file') return true; // All files
                    return assetImageExts.includes(item.ext || ''); // Images only
                });

                if (filteredItems.length > 0) {
                    renderAssetMediaItems(filteredItems);
                } else {
                    const msg = assetPickerMode === 'file' ? 'No files' : 'No images';
                    const emptyHtml = `<div class="media-empty"><i data-lucide="folder-open"></i><p>${msg} in this folder</p></div>`;
                    grid.innerHTML = emptyHtml;
                    list.innerHTML = emptyHtml;
                }
            } else {
                const emptyHtml = '<div class="media-empty"><i data-lucide="folder-open"></i><p>Empty folder</p></div>';
                grid.innerHTML = emptyHtml;
                list.innerHTML = emptyHtml;
            }
            lucide.createIcons();
        })
        .catch(() => {
            const errorHtml = '<div class="media-empty"><i data-lucide="alert-circle"></i><p>Failed to load</p></div>';
            grid.innerHTML = errorHtml;
            list.innerHTML = errorHtml;
            lucide.createIcons();
        });
}

function renderAssetMediaItems(items) {
    const grid = document.getElementById('assetMediaGrid');
    const list = document.getElementById('assetMediaList');

    let gridHtml = '';
    let listHtml = '';

    items.forEach(item => {
        const isImage = assetImageExts.includes(item.ext || '');
        const fullUrl = '/uploads/' + item.path;
        const isSelected = assetSelectedValue === (assetPickerMode === 'file' ? item.path : fullUrl);
        const icon = item.type === 'folder' ? 'folder' : (assetFileIcons[item.ext] || 'file');
        const sizeText = item.size ? (item.size / 1024).toFixed(1) + ' KB' : '';

        if (item.type === 'folder') {
            // Grid folder
            gridHtml += `
                <div class="media-grid-item" onclick="loadAssetMediaPath('${item.path}')">
                    <div class="media-grid-item-icon folder"><i data-lucide="folder"></i></div>
                    <div class="media-grid-item-name">${item.name}</div>
                </div>
            `;
            // List folder
            listHtml += `
                <div class="media-list-item" onclick="loadAssetMediaPath('${item.path}')">
                    <div class="media-list-item-icon folder"><i data-lucide="folder"></i></div>
                    <div class="media-list-item-info">
                        <div class="media-list-item-name">${item.name}</div>
                    </div>
                </div>
            `;
        } else if (isImage) {
            // Grid image
            gridHtml += `
                <div class="media-grid-item ${isSelected ? 'selected' : ''}" onclick="selectAssetMedia('${item.path}', this, 'grid')" title="${item.name}">
                    <div class="media-grid-item-icon"><img src="${fullUrl}" alt="${item.name}"></div>
                    <div class="media-grid-item-name">${item.name}</div>
                    <div class="media-grid-item-size">${sizeText}</div>
                    <div class="check-mark"><i data-lucide="check"></i></div>
                </div>
            `;
            // List image
            listHtml += `
                <div class="media-list-item ${isSelected ? 'selected' : ''}" onclick="selectAssetMedia('${item.path}', this, 'list')">
                    <div class="media-list-item-icon"><img src="${fullUrl}" alt="${item.name}"></div>
                    <div class="media-list-item-info">
                        <div class="media-list-item-name">${item.name}</div>
                        <div class="media-list-item-size">${sizeText}</div>
                    </div>
                    <div class="check-mark"><i data-lucide="check"></i></div>
                </div>
            `;
        } else {
            // Grid file
            gridHtml += `
                <div class="media-grid-item ${isSelected ? 'selected' : ''}" onclick="selectAssetMedia('${item.path}', this, 'grid')" title="${item.name}">
                    <div class="media-grid-item-icon"><i data-lucide="${icon}"></i></div>
                    <div class="media-grid-item-name">${item.name}</div>
                    <div class="media-grid-item-size">${sizeText}</div>
                    <div class="check-mark"><i data-lucide="check"></i></div>
                </div>
            `;
            // List file
            listHtml += `
                <div class="media-list-item ${isSelected ? 'selected' : ''}" onclick="selectAssetMedia('${item.path}', this, 'list')">
                    <div class="media-list-item-icon"><i data-lucide="${icon}"></i></div>
                    <div class="media-list-item-info">
                        <div class="media-list-item-name">${item.name}</div>
                        <div class="media-list-item-size">${sizeText}</div>
                    </div>
                    <div class="check-mark"><i data-lucide="check"></i></div>
                </div>
            `;
        }
    });

    grid.innerHTML = gridHtml;
    list.innerHTML = listHtml;
}

function selectAssetMedia(path, element, viewType) {
    // Deselect in both views
    document.querySelectorAll('#assetMediaGrid .media-grid-item, #assetMediaList .media-list-item').forEach(el => el.classList.remove('selected'));

    // Select in both views (find matching element by path)
    document.querySelectorAll(`[onclick*="selectAssetMedia('${path}'"]`).forEach(el => el.classList.add('selected'));

    if (assetPickerMode === 'file') {
        assetSelectedType = 'file';
        assetSelectedValue = path;
    } else {
        assetSelectedType = 'external';
        assetSelectedValue = '/uploads/' + path;
    }
}

// Upload & Dialog events
document.addEventListener('DOMContentLoaded', function() {
    const uploadBtn = document.getElementById('assetUploadZone');
    const input = document.getElementById('assetUploadInput');
    const mediaMain = document.querySelector('.media-main');

    // Drag/drop on upload button
    if (uploadBtn) {
        uploadBtn.addEventListener('dragover', e => { e.preventDefault(); uploadBtn.classList.add('dragover'); });
        uploadBtn.addEventListener('dragleave', () => uploadBtn.classList.remove('dragover'));
        uploadBtn.addEventListener('drop', e => {
            e.preventDefault();
            uploadBtn.classList.remove('dragover');
            if (e.dataTransfer.files.length) uploadAssetFiles(e.dataTransfer.files);
        });
    }

    // Also allow drop on the whole media area
    if (mediaMain) {
        mediaMain.addEventListener('dragover', e => { e.preventDefault(); uploadBtn?.classList.add('dragover'); });
        mediaMain.addEventListener('dragleave', e => {
            if (!mediaMain.contains(e.relatedTarget)) uploadBtn?.classList.remove('dragover');
        });
        mediaMain.addEventListener('drop', e => {
            e.preventDefault();
            uploadBtn?.classList.remove('dragover');
            if (e.dataTransfer.files.length) uploadAssetFiles(e.dataTransfer.files);
        });
    }

    if (input) {
        input.addEventListener('change', () => {
            if (input.files.length) {
                uploadAssetFiles(input.files);
                input.value = '';
            }
        });
    }

    // Close on backdrop click
    const dialog = document.getElementById('assetPickerDialog');
    if (dialog) {
        dialog.addEventListener('click', e => {
            if (e.target === dialog) closeAssetPicker();
        });
    }
});

function uploadAssetFiles(files) {
    const grid = document.getElementById('assetMediaGrid');
    const list = document.getElementById('assetMediaList');
    const loadingHtml = '<div class="media-empty"><i data-lucide="loader"></i><p>Uploading...</p></div>';
    grid.innerHTML = loadingHtml;
    list.innerHTML = loadingHtml;
    lucide.createIcons();

    const formData = new FormData();
    formData.append('action', 'upload');
    formData.append('folder', assetCurrentPath || 'images');
    for (let i = 0; i < files.length; i++) {
        formData.append('files[]', files[i]);
    }

    fetch(SITE_URL + '/api/files.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.files?.length > 0) {
                const uploaded = data.files[0];
                if (assetPickerMode === 'file') {
                    assetSelectedType = 'file';
                    assetSelectedValue = uploaded;
                } else {
                    assetSelectedType = 'external';
                    assetSelectedValue = '/uploads/' + uploaded;
                }
                loadAssetMediaPath(assetCurrentPath || 'images');
            } else {
                alert(data.error || 'Upload failed');
                loadAssetMediaPath(assetCurrentPath);
            }
        })
        .catch(() => {
            alert('Upload failed');
            loadAssetMediaPath(assetCurrentPath);
        });
}

// Confirm selection
function confirmAssetPicker() {
    if (!assetSelectedValue) {
        alert('Please select an asset');
        return;
    }

    if (assetPickerCallback) {
        assetPickerCallback({
            type: assetSelectedType,
            value: assetSelectedValue
        });
    }

    closeAssetPicker();
}

// Helper: Open for icon field
function openIconPicker(typeInputId, valueInputId, triggerElement) {
    const typeInput = document.getElementById(typeInputId);
    const valueInput = document.getElementById(valueInputId);

    const current = {
        type: typeInput?.value || 'library',
        value: valueInput?.value || ''
    };

    openAssetPicker('icon', result => {
        if (typeInput) typeInput.value = result.type;
        if (valueInput) valueInput.value = result.value;
        updateAssetTrigger(triggerElement, result);
    }, current, triggerElement);
}

// Helper: Open for image field
function openImagePicker(inputId, triggerElement) {
    const input = document.getElementById(inputId);
    const current = input?.value ? { type: 'external', value: input.value } : null;

    openAssetPicker('image', result => {
        if (input) input.value = result.value;
        updateAssetTrigger(triggerElement, result);
    }, current, triggerElement);
}

// Helper: Open for file field
function openFilePicker(inputId, triggerElement) {
    const input = document.getElementById(inputId);
    const current = input?.value ? { type: 'file', value: input.value } : null;

    openAssetPicker('file', result => {
        if (input) {
            input.value = result.value;
            input.dispatchEvent(new Event('change'));
        }
        updateAssetTrigger(triggerElement, result);
    }, current, triggerElement);
}

function updateAssetTrigger(trigger, result) {
    if (!trigger) return;

    const preview = trigger.querySelector('.asset-trigger-preview');
    const text = trigger.querySelector('.asset-trigger-text');

    if (result.type === 'library') {
        const iconName = result.value.replace('lucide:', '');
        preview.innerHTML = `<i data-lucide="${iconName}"></i>`;
        text.textContent = iconName;
    } else if (result.type === 'external') {
        preview.innerHTML = `<img src="${result.value}" onerror="this.outerHTML='<i data-lucide=\\'image\\'></i>'">`;
        text.textContent = result.value.split('/').pop();
    } else if (result.type === 'file') {
        preview.innerHTML = '<i data-lucide="file"></i>';
        text.textContent = result.value;
    }

    text.classList.add('has-value');
    lucide.createIcons();
}
</script>
<?php
}

/**
 * Render trigger for icon picker (icon library + url + media images)
 */
function renderAssetIconTrigger($id, $typeValue = 'library', $iconValue = 'lucide:link') {
    $displayIcon = str_replace('lucide:', '', $iconValue);
    $isLibrary = $typeValue === 'library';
    $displayText = $isLibrary ? $displayIcon : basename($iconValue);
?>
<div class="asset-trigger" onclick="openIconPicker('<?= $id ?>_type', '<?= $id ?>_value', this)">
    <div class="asset-trigger-preview">
        <?php if ($isLibrary): ?>
            <i data-lucide="<?= htmlspecialchars($displayIcon) ?>"></i>
        <?php else: ?>
            <img src="<?= htmlspecialchars($iconValue) ?>" onerror="this.outerHTML='<i data-lucide=\'image\'></i>'">
        <?php endif; ?>
    </div>
    <span class="asset-trigger-text has-value"><?= htmlspecialchars($displayText) ?></span>
    <span class="asset-trigger-action">Change</span>
</div>
<input type="hidden" id="<?= $id ?>_type" name="icon_type" value="<?= htmlspecialchars($typeValue) ?>">
<input type="hidden" id="<?= $id ?>_value" name="icon_value" value="<?= htmlspecialchars($iconValue) ?>">
<?php
}

/**
 * Render trigger for image picker (url + media images only)
 */
function renderAssetImageTrigger($id, $value = '', $label = 'Change') {
    $hasValue = !empty($value);
    $displayText = $hasValue ? basename($value) : 'Select image...';
?>
<div class="asset-trigger" onclick="openImagePicker('<?= $id ?>', this)">
    <div class="asset-trigger-preview">
        <?php if ($hasValue): ?>
            <img src="<?= htmlspecialchars($value) ?>" onerror="this.outerHTML='<i data-lucide=\'image\'></i>'">
        <?php else: ?>
            <i data-lucide="image"></i>
        <?php endif; ?>
    </div>
    <span class="asset-trigger-text <?= $hasValue ? 'has-value' : '' ?>"><?= htmlspecialchars($displayText) ?></span>
    <span class="asset-trigger-action"><?= htmlspecialchars($label) ?></span>
</div>
<input type="hidden" id="<?= $id ?>" name="<?= $id ?>" value="<?= htmlspecialchars($value) ?>">
<?php
}

/**
 * Render trigger for file picker (media all files only)
 */
function renderAssetFileTrigger($id, $value = '') {
    $hasValue = !empty($value);
    $displayText = $hasValue ? $value : 'Select file...';
?>
<div class="asset-trigger" onclick="openFilePicker('<?= $id ?>', this)">
    <div class="asset-trigger-preview">
        <i data-lucide="file"></i>
    </div>
    <span class="asset-trigger-text <?= $hasValue ? 'has-value' : '' ?>"><?= htmlspecialchars($displayText) ?></span>
    <span class="asset-trigger-action">Browse</span>
</div>
<input type="hidden" id="<?= $id ?>" name="file_path" value="<?= htmlspecialchars($value) ?>">
<?php
}
