<?php
/**
 * 后台管理设置页面、系统健康检查、双重优化与大模态弹窗管理 (萝卜 WebP 大师)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Radish_WebP_Admin {

    public function __construct() {
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_filter('plugin_action_links_' . RADISH_WEBP_BASENAME, array($this, 'add_action_links'));

        // 媒体库列表列与操作按钮增强
        add_filter('manage_media_columns', array($this, 'add_media_columns'));
        add_action('manage_media_custom_column', array($this, 'render_media_column'), 10, 2);

        // 媒体详情面板增强
        add_filter('attachment_fields_to_edit', array($this, 'add_attachment_webp_fields'), 10, 2);

        // 单图手动优化与恢复 AJAX 接口
        add_action('wp_ajax_radish_webp_optimize_single_action', array($this, 'ajax_optimize_single_action'));
        add_action('wp_ajax_radish_webp_restore_single_action', array($this, 'ajax_restore_single_action'));
    }

    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=radish-webp-settings') . '">' . __('Settings', 'webp-radish-webp-optimizer') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function register_admin_menu() {
        add_options_page(
            __('🥕 Radish WebP Optimizer', 'webp-radish-webp-optimizer'),
            __('🥕 Radish WebP Optimizer', 'webp-radish-webp-optimizer'),
            'manage_options',
            'radish-webp-settings',
            array($this, 'render_admin_page')
        );
    }

    public function register_settings() {
        register_setting('radish_webp_settings_group', 'radish_webp_settings', array($this, 'sanitize_settings'));
    }

    public function sanitize_settings($input) {
        $output = array();
        $output['enabled']           = !empty($input['enabled']) ? 1 : 0;
        $output['convert_on_upload'] = !empty($input['convert_on_upload']) ? 1 : 0;
        $output['optimize_original'] = !empty($input['optimize_original']) ? 1 : 0;
        $output['backup_original']   = !empty($input['backup_original']) ? 1 : 0;
        
        $output['plugin_lang'] = isset($input['plugin_lang']) && in_array($input['plugin_lang'], array('auto', 'zh_CN', 'en_US'), true) ? $input['plugin_lang'] : 'auto';

        $quality = isset($input['quality']) ? intval($input['quality']) : 80;
        $output['quality'] = max(1, min(100, $quality));

        $orig_quality = isset($input['orig_quality']) ? intval($input['orig_quality']) : 82;
        $output['orig_quality'] = max(1, min(100, $orig_quality));

        $max_dim = isset($input['max_dimension']) ? intval($input['max_dimension']) : 2560;
        $output['max_dimension'] = max(0, $max_dim);

        $delivery_mode = isset($input['delivery_mode']) && in_array($input['delivery_mode'], array('picture', 'replace')) ? $input['delivery_mode'] : 'picture';
        $output['delivery_mode'] = $delivery_mode;

        return $output;
    }

    public function add_media_columns($columns) {
        $columns['radish_webp_status'] = __('Dual Optimization & Actions', 'webp-radish-webp-optimizer');
        return $columns;
    }

    public function render_media_column($column_name, $post_id) {
        if ($column_name !== 'radish_webp_status') {
            return;
        }

        $mime = get_post_mime_type($post_id);
        if (!in_array($mime, array('image/jpeg', 'image/png', 'image/jpg'), true)) {
            echo '<span style="color:#a7aaad;">—</span>';
            return;
        }

        $meta = wp_get_attachment_metadata($post_id);
        if (empty($meta) || empty($meta['file'])) {
            echo '<span style="color:#d63638;">' . esc_html__('No metadata', 'webp-radish-webp-optimizer') . '</span>';
            return;
        }

        $upload_dir = wp_upload_dir();
        $orig_path = path_join($upload_dir['basedir'], $meta['file']);
        $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $orig_path);
        $bak_path  = $orig_path . '.radish_bak';

        if (!file_exists($orig_path)) {
            echo '<span style="color:#a7aaad;">' . esc_html__('File missing', 'webp-radish-webp-optimizer') . '</span>';
            return;
        }

        $initial_size = get_post_meta($post_id, '_radish_initial_size', true);
        $current_orig_size = filesize($orig_path);

        if (!$initial_size || $initial_size < $current_orig_size) {
            $initial_size = $current_orig_size;
        }

        $has_webp = file_exists($webp_path);
        $webp_size = $has_webp ? filesize($webp_path) : 0;
        $has_bak  = file_exists($bak_path);

        echo '<div id="radish-row-' . esc_attr($post_id) . '" class="radish-media-status-cell" style="font-size:12px; line-height:1.4;">';

        // 1. 原图阶段数据
        $orig_saved = $initial_size - $current_orig_size;
        $orig_saved_pct = $initial_size > 0 ? round(($orig_saved / $initial_size) * 100) : 0;
        
        echo '<div style="margin-bottom:4px; padding:2px 5px; background:#f6f7f7; border-radius:3px;">';
        echo esc_html__('① Original:', 'webp-radish-webp-optimizer') . ' <strong>' . size_format($current_orig_size, 1) . '</strong>';
        if ($orig_saved_pct > 0) {
            echo ' <span style="color:#2271b1; font-weight:600;">(-' . $orig_saved_pct . '%)</span>';
        }
        echo '</div>';

        // 2. WebP 阶段数据
        if ($has_webp) {
            $total_saved = $initial_size - $webp_size;
            $total_saved_pct = $initial_size > 0 ? round(($total_saved / $initial_size) * 100) : 0;

            echo '<div style="margin-bottom:6px; padding:2px 5px; background:#e7f7ed; border-radius:3px; border-left:2px solid #008a20;">';
            echo esc_html__('② WebP:', 'webp-radish-webp-optimizer') . ' <strong style="color:#008a20;">' . size_format($webp_size, 1) . '</strong> <span style="color:#008a20; font-weight:700;">(-' . $total_saved_pct . '%)</span>';
            echo '</div>';
        } else {
            echo '<div style="margin-bottom:6px; color:#d63638;">' . esc_html__('② WebP: Not generated', 'webp-radish-webp-optimizer') . '</div>';
        }

        // 3. 单图快捷操作按钮区
        echo '<div class="radish-action-links" style="display:flex; gap:6px; flex-wrap:wrap;">';
        echo '<button type="button" class="button button-small btn-radish-single-opt" data-id="' . esc_attr($post_id) . '" title="' . esc_attr__('Optimize original image and generate WebP', 'webp-radish-webp-optimizer') . '">⚡ ' . esc_html__('Optimize', 'webp-radish-webp-optimizer') . '</button>';
        if ($has_bak) {
            echo '<button type="button" class="button button-small btn-radish-single-restore" data-id="' . esc_attr($post_id) . '" title="' . esc_attr__('Restore initial uncompressed original image', 'webp-radish-webp-optimizer') . '" style="color:#8c5b00;">↩️ ' . esc_html__('Restore', 'webp-radish-webp-optimizer') . '</button>';
        }
        echo '</div>';

        echo '</div>';
    }

    public function add_attachment_webp_fields($form_fields, $post) {
        $mime = get_post_mime_type($post->ID);
        if (!in_array($mime, array('image/jpeg', 'image/png', 'image/jpg'), true)) {
            return $form_fields;
        }

        $meta = wp_get_attachment_metadata($post->ID);
        if (empty($meta) || empty($meta['file'])) {
            return $form_fields;
        }

        $upload_dir = wp_upload_dir();
        $orig_path = path_join($upload_dir['basedir'], $meta['file']);
        $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $orig_path);
        $bak_path  = $orig_path . '.radish_bak';

        $initial_size = get_post_meta($post->ID, '_radish_initial_size', true);
        $current_orig_size = file_exists($orig_path) ? filesize($orig_path) : 0;
        if (!$initial_size || $initial_size < $current_orig_size) {
            $initial_size = $current_orig_size;
        }

        $has_webp = file_exists($webp_path);
        $webp_size = $has_webp ? filesize($webp_path) : 0;
        $has_bak  = file_exists($bak_path);

        $html = '<div id="radish-detail-' . esc_attr($post->ID) . '" style="padding:14px; background:#f6f7f7; border-radius:8px; border:1px solid #dcdcde;">';
        $html .= '<p style="margin:0 0 6px 0;"><strong>' . esc_html__('Initial Upload Size:', 'webp-radish-webp-optimizer') . '</strong> ' . size_format($initial_size, 2) . '</p>';
        
        $orig_saved = $initial_size - $current_orig_size;
        $orig_saved_pct = $initial_size > 0 ? round(($orig_saved / $initial_size) * 100) : 0;
        $html .= '<p style="margin:0 0 6px 0;"><strong>' . esc_html__('① Optimized Original Size:', 'webp-radish-webp-optimizer') . '</strong> ' . size_format($current_orig_size, 2) . ($orig_saved_pct > 0 ? ' <span style="color:#2271b1; font-weight:600;">(-' . $orig_saved_pct . '%)</span>' : '') . '</p>';

        if ($has_webp) {
            $total_saved = $initial_size - $webp_size;
            $total_saved_pct = $initial_size > 0 ? round(($total_saved / $initial_size) * 100) : 0;
            $html .= '<p style="margin:0 0 8px 0;"><strong>' . esc_html__('② WebP Image Size:', 'webp-radish-webp-optimizer') . '</strong> <span style="color:#008a20; font-weight:700; font-size:14px;">' . size_format($webp_size, 2) . '</span></p>';
            $html .= '<p style="margin:0 0 12px 0; padding:4px 8px; background:#e7f7ed; border-radius:4px; color:#008a20; font-weight:700;">🎉 ' . esc_html__('Total Savings:', 'webp-radish-webp-optimizer') . ' ' . size_format($total_saved, 2) . ' (' . $total_saved_pct . '%)</p>';
        } else {
            $html .= '<p style="margin:0 0 12px 0; color:#d63638;"><strong>' . esc_html__('② WebP Status:', 'webp-radish-webp-optimizer') . '</strong> ' . esc_html__('WebP copy has not been generated yet', 'webp-radish-webp-optimizer') . '</p>';
        }

        $html .= '<div style="display:flex; gap:8px;">';
        $html .= '<button type="button" class="button button-primary btn-radish-single-opt" data-id="' . esc_attr($post->ID) . '">⚡ ' . esc_html__('Re-optimize Original + WebP', 'webp-radish-webp-optimizer') . '</button>';
        if ($has_bak) {
            $html .= '<button type="button" class="button btn-radish-single-restore" data-id="' . esc_attr($post->ID) . '">↩️ ' . esc_html__('Restore Initial Image', 'webp-radish-webp-optimizer') . '</button>';
        }
        $html .= '</div>';

        $html .= '</div>';

        $form_fields['radish_webp_info'] = array(
            'label' => __('Optimization Info & Actions', 'webp-radish-webp-optimizer'),
            'input' => 'html',
            'html'  => $html,
        );

        return $form_fields;
    }

    public function ajax_optimize_single_action() {
        check_ajax_referer('radish_webp_admin_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'webp-radish-webp-optimizer')));
        }

        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        if (!$attachment_id) {
            wp_send_json_error(array('message' => __('Invalid attachment ID', 'webp-radish-webp-optimizer')));
        }

        $engine = new Radish_WebP_Engine();
        $res = $engine->optimize_single_attachment($attachment_id);

        if ($res) {
            wp_send_json_success(array('message' => __('Optimized successfully!', 'webp-radish-webp-optimizer')));
        } else {
            wp_send_json_error(array('message' => __('Optimization failed or original image missing', 'webp-radish-webp-optimizer')));
        }
    }

    public function ajax_restore_single_action() {
        check_ajax_referer('radish_webp_admin_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'webp-radish-webp-optimizer')));
        }

        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        if (!$attachment_id) {
            wp_send_json_error(array('message' => __('Invalid attachment ID', 'webp-radish-webp-optimizer')));
        }

        $engine = new Radish_WebP_Engine();
        $res = $engine->restore_original_file($attachment_id);

        if ($res) {
            wp_send_json_success(array('message' => __('Restored initial original file successfully!', 'webp-radish-webp-optimizer')));
        } else {
            wp_send_json_error(array('message' => __('No backup file found for this image', 'webp-radish-webp-optimizer')));
        }
    }

    public function enqueue_admin_assets($hook) {
        wp_enqueue_style('radish-webp-admin-css', RADISH_WEBP_URL . 'assets/admin.css', array(), RADISH_WEBP_VERSION);
        wp_enqueue_script('radish-webp-admin-js', RADISH_WEBP_URL . 'assets/admin.js', array('jquery'), RADISH_WEBP_VERSION, true);

        wp_localize_script('radish-webp-admin-js', 'radishWebpData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('radish_webp_admin_nonce'),
            'strings' => array(
                'start'           => __('Start Bulk Optimization', 'webp-radish-webp-optimizer'),
                'processing'      => __('Optimizing...', 'webp-radish-webp-optimizer'),
                'completed'       => __('🎉 All selected images have been optimized successfully!', 'webp-radish-webp-optimizer'),
                'error'           => __('An error occurred during operation', 'webp-radish-webp-optimizer'),
                'optimizing'      => __('Optimizing...', 'webp-radish-webp-optimizer'),
                'restoring'       => __('Restoring...', 'webp-radish-webp-optimizer'),
                'restoreSuccess'  => __('✔ Restored successfully', 'webp-radish-webp-optimizer'),
                'optimizeSuccess' => __('✔ Optimized successfully', 'webp-radish-webp-optimizer'),
                'confirmRestore'  => __('Are you sure you want to restore the initial uncompressed original file?', 'webp-radish-webp-optimizer'),
                'noSelected'      => __('Please select at least one image!', 'webp-radish-webp-optimizer'),
                'noOptionSelected'=> __('Please select at least one action (Optimize Original or Generate WebP)!', 'webp-radish-webp-optimizer'),
                'scanning'        => __('Scanning media library images, please wait...', 'webp-radish-webp-optimizer'),
                'noImages'        => __('No JPG/PNG images found in media library', 'webp-radish-webp-optimizer'),
                'generated'       => __('🟢 Generated', 'webp-radish-webp-optimizer'),
                'notGenerated'    => __('⚪ Not Generated', 'webp-radish-webp-optimizer'),
                'scanFailed'      => __('Scan failed, please check network connection', 'webp-radish-webp-optimizer'),
                'previewTitle'    => __('Image Preview', 'webp-radish-webp-optimizer'),
                'previewHover'    => __('🔍 Click to view large image preview', 'webp-radish-webp-optimizer'),
                'uploadDate'      => __('Upload date:', 'webp-radish-webp-optimizer'),
                'processingBatch' => __('Processing item %1$d of %2$d (ID: %3$d)...', 'webp-radish-webp-optimizer'),
                'reOptimizeBtn'   => __('Re-optimize selected images', 'webp-radish-webp-optimizer'),
            )
        ));
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = get_option('radish_webp_settings', array(
            'enabled'           => 1,
            'quality'           => 80,
            'delivery_mode'     => 'picture',
            'convert_on_upload' => 1,
            'optimize_original' => 1,
            'orig_quality'      => 82,
            'max_dimension'     => 2560,
            'backup_original'   => 0,
        ));

        $driver = Radish_WebP_Engine::get_available_driver();
        ?>
        <div class="wrap visil-webp-wrap">
            <!-- 顶部紧凑型一体化 Hero + 状态横条 -->
            <div class="radish-compact-header">
                <div class="radish-header-brand">
                    <span class="radish-brand-logo">🥕</span>
                    <div class="radish-brand-info">
                        <h1>
                            <?php echo esc_html__('Radish WebP Optimizer', 'webp-radish-webp-optimizer'); ?>
                            <span class="radish-version-tag">v<?php echo RADISH_WEBP_VERSION; ?></span>
                        </h1>
                        <p><?php echo esc_html__('Dual-stage optimization architecture for massive server storage savings and lightning-fast WebP loading.', 'webp-radish-webp-optimizer'); ?></p>
                    </div>
                </div>

                <div class="radish-header-meta">
                    <div class="radish-meta-pill">
                        <span>PHP:</span> <strong><?php echo PHP_VERSION; ?></strong>
                    </div>
                    <div class="radish-meta-pill">
                        <span>Driver:</span> 
                        <?php if ($driver === 'imagick'): ?>
                            <strong style="color:#34d399;">ImageMagick</strong>
                        <?php elseif ($driver === 'gd'): ?>
                            <strong style="color:#60a5fa;">PHP GD</strong>
                        <?php else: ?>
                            <strong style="color:#f87171;">None</strong>
                        <?php endif; ?>
                    </div>
                    <div class="radish-meta-pill" style="border-color: rgba(16, 185, 129, 0.4); background: rgba(16, 185, 129, 0.15); color: #34d399;">
                        ● <strong><?php echo esc_html__('Engine Active', 'webp-radish-webp-optimizer'); ?></strong>
                    </div>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('radish_webp_settings_group'); ?>

                <div class="radish-dashboard-grid">
                    <!-- 左栏 (50%)：核心设置 + 原图智能瘦身 -->
                    <div class="radish-grid-col">
                        <!-- 卡片 1：基础自动化与语言 -->
                        <div class="visil-card">
                            <h2>⚙️ <?php echo esc_html__('Core Optimization Settings', 'webp-radish-webp-optimizer'); ?></h2>
                            
                            <div class="radish-form-row">
                                <div class="radish-form-label">
                                    <strong><?php echo esc_html__('Interface Language', 'webp-radish-webp-optimizer'); ?></strong>
                                    <p class="description"><?php echo esc_html__('Select the display language for the plugin settings and batch workbench.', 'webp-radish-webp-optimizer'); ?></p>
                                </div>
                                <div class="radish-form-control">
                                    <select name="radish_webp_settings[plugin_lang]" style="min-width: 170px; border-radius: 8px; font-size:13px;">
                                        <option value="auto" <?php selected('auto', isset($settings['plugin_lang']) ? $settings['plugin_lang'] : 'auto'); ?>><?php echo esc_html__('Auto (Follow Site Language)', 'webp-radish-webp-optimizer'); ?></option>
                                        <option value="zh_CN" <?php selected('zh_CN', isset($settings['plugin_lang']) ? $settings['plugin_lang'] : 'auto'); ?>><?php echo esc_html__('Simplified Chinese', 'webp-radish-webp-optimizer'); ?> (zh_CN)</option>
                                        <option value="en_US" <?php selected('en_US', isset($settings['plugin_lang']) ? $settings['plugin_lang'] : 'auto'); ?>><?php echo esc_html__('English', 'webp-radish-webp-optimizer'); ?> (en_US)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="radish-form-row">
                                <div class="radish-form-label">
                                    <strong><?php echo esc_html__('Enable Plugin Features', 'webp-radish-webp-optimizer'); ?></strong>
                                    <p class="description"><?php echo esc_html__('Master switch. Disabling stops frontend replacement and automatic conversion.', 'webp-radish-webp-optimizer'); ?></p>
                                </div>
                                <div class="radish-form-control">
                                    <label class="switch">
                                        <input type="checkbox" name="radish_webp_settings[enabled]" value="1" <?php checked(1, !empty($settings['enabled'])); ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>

                            <div class="radish-form-row">
                                <div class="radish-form-label">
                                    <strong><?php echo esc_html__('Auto Convert on Upload', 'webp-radish-webp-optimizer'); ?></strong>
                                    <p class="description"><?php echo esc_html__('Automatically optimize original images and generate WebP files upon uploading new JPG/PNG.', 'webp-radish-webp-optimizer'); ?></p>
                                </div>
                                <div class="radish-form-control">
                                    <label class="switch">
                                        <input type="checkbox" name="radish_webp_settings[convert_on_upload]" value="1" <?php checked(1, !empty($settings['convert_on_upload'])); ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- 卡片 2：原图智能瘦身与安全备份 -->
                        <div class="visil-card">
                            <h2>① <?php echo esc_html__('Original Slimming', 'webp-radish-webp-optimizer'); ?></h2>
                            
                            <div class="radish-form-row">
                                <div class="radish-form-label">
                                    <strong><?php echo esc_html__('Enable Original Image Slimming Optimization', 'webp-radish-webp-optimizer'); ?></strong>
                                    <p class="description"><?php echo esc_html__('Strip EXIF metadata and perform smart color quantization on JPG/PNG originals to drastically save server disk space!', 'webp-radish-webp-optimizer'); ?></p>
                                </div>
                                <div class="radish-form-control">
                                    <label class="switch">
                                        <input type="checkbox" name="radish_webp_settings[optimize_original]" value="1" <?php checked(1, !empty($settings['optimize_original'])); ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>

                            <div class="radish-form-row">
                                <div class="radish-form-label">
                                    <strong><?php echo esc_html__('Keep Initial Master Backup (.radish_bak)', 'webp-radish-webp-optimizer'); ?></strong>
                                    <p class="description"><?php echo esc_html__('Preserve a .radish_bak backup before slimming. Enables one-click restoration in Media Library anytime.', 'webp-radish-webp-optimizer'); ?></p>
                                </div>
                                <div class="radish-form-control">
                                    <label class="switch">
                                        <input type="checkbox" name="radish_webp_settings[backup_original]" value="1" <?php checked(1, !empty($settings['backup_original'])); ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>

                            <div class="radish-sub-box" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                                <div>
                                    <label style="font-weight:600; font-size:12.5px; color:#334155;"><?php echo esc_html__('Original Image Quality:', 'webp-radish-webp-optimizer'); ?></label>
                                    <input type="number" name="radish_webp_settings[orig_quality]" min="50" max="100" value="<?php echo esc_attr(isset($settings['orig_quality']) ? $settings['orig_quality'] : 82); ?>" style="width:65px; border-radius:6px; margin-left:4px;"> %
                                </div>

                                <div>
                                    <label style="font-weight:600; font-size:12.5px; color:#334155;"><?php echo esc_html__('Max Original Resolution Dimension:', 'webp-radish-webp-optimizer'); ?></label>
                                    <input type="number" name="radish_webp_settings[max_dimension]" min="0" step="100" value="<?php echo esc_attr(isset($settings['max_dimension']) ? $settings['max_dimension'] : 2560); ?>" style="width:85px; border-radius:6px; margin-left:4px;"> px
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 右栏 (50%)：批量优化工作台入口 + WebP 终极优化与保存 -->
                    <div class="radish-grid-col">
                        <!-- 卡片 3：批量优化大工作台 -->
                        <div class="visil-card" style="border-top: 4px solid var(--radish-primary);">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                                <h2 style="margin:0; padding:0; border:none; color:var(--radish-primary);">🚀 <?php echo esc_html__('Bulk Optimization Workbench', 'webp-radish-webp-optimizer'); ?></h2>
                                <button id="btn-open-modal" type="button" class="button button-primary" style="font-weight:700; height:38px; line-height:36px; padding:0 18px !important; font-size:13.5px; border-radius:8px;">
                                    🔍 <?php echo esc_html__('Open Bulk Optimization Workbench', 'webp-radish-webp-optimizer'); ?>
                                </button>
                            </div>
                            <p class="description" style="margin-bottom:12px;"><?php echo esc_html__('Scan and filter all media library images in an independent large modal dialog with large preview, selection, and dual-optimization.', 'webp-radish-webp-optimizer'); ?></p>

                            <div style="background:var(--radish-primary-light); border:1px solid rgba(255, 94, 54, 0.2); border-radius:8px; padding:10px 14px;">
                                <div style="display:flex; justify-content:space-around; color:#9a2c0c; font-size:12px; font-weight:600;">
                                    <span>✨ <?php echo esc_html__('No Horizontal Scroll', 'webp-radish-webp-optimizer'); ?></span>
                                    <span>🔍 <?php echo esc_html__('Lightbox Large Preview', 'webp-radish-webp-optimizer'); ?></span>
                                    <span>🎯 <?php echo esc_html__('Batch Selection', 'webp-radish-webp-optimizer'); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- 卡片 4：WebP 终极优化与交付 -->
                        <div class="visil-card">
                            <h2>② <?php echo esc_html__('WebP Conversion Quality', 'webp-radish-webp-optimizer'); ?></h2>
                            
                            <div class="radish-form-row" style="padding-bottom:14px;">
                                <div class="radish-form-label">
                                    <strong><?php echo esc_html__('WebP Conversion Quality', 'webp-radish-webp-optimizer'); ?></strong>
                                    <p class="description"><?php echo esc_html__('WebP quality delivered to website visitors. Recommended: 75% ~ 82% (sweet spot for size & quality).', 'webp-radish-webp-optimizer'); ?></p>
                                </div>
                                <div class="radish-form-control" style="min-width:180px;">
                                    <div class="range-container">
                                        <input type="range" id="webp_quality_range" name="radish_webp_settings[quality]" min="1" max="100" value="<?php echo esc_attr($settings['quality']); ?>" oninput="document.getElementById('quality_val').innerText = this.value">
                                        <span id="quality_val" class="range-val"><?php echo esc_html($settings['quality']); ?></span>%
                                    </div>
                                </div>
                            </div>

                            <div style="margin-top:12px;">
                                <strong style="display:block; font-size:13.5px; color:#1e293b; margin-bottom:8px;"><?php echo esc_html__('Frontend Delivery Mode', 'webp-radish-webp-optimizer'); ?></strong>
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                                    <label style="display:flex; gap:8px; padding:10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; cursor:pointer;">
                                        <input type="radio" name="radish_webp_settings[delivery_mode]" value="picture" <?php checked('picture', $settings['delivery_mode']); ?> style="margin-top:2px;">
                                        <span style="font-size:12.5px; font-weight:600; color:#0f172a;">HTML5 &lt;picture&gt; (<?php echo esc_html__('Recommended', 'webp-radish-webp-optimizer'); ?>)</span>
                                    </label>

                                    <label style="display:flex; gap:8px; padding:10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; cursor:pointer;">
                                        <input type="radio" name="radish_webp_settings[delivery_mode]" value="replace" <?php checked('replace', $settings['delivery_mode']); ?> style="margin-top:2px;">
                                        <span style="font-size:12.5px; font-weight:600; color:#0f172a;"><?php echo esc_html__('Direct URL Replacement Mode', 'webp-radish-webp-optimizer'); ?></span>
                                    </label>
                                </div>
                            </div>

                            <div style="margin-top:16px; padding-top:14px; border-top:1px solid #f1f5f9; display:flex; justify-content:flex-end;">
                                <?php submit_button(__('Save Changes', 'webp-radish-webp-optimizer'), 'primary', 'submit', false, array('style' => 'font-size:14px; padding:8px 36px; height:auto; border-radius:8px;')); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- ================= 全新宽屏大模态弹窗 (Modal) ================= -->
        <div id="radish-bulk-modal" class="visil-modal-overlay">
            <div class="visil-modal-container">
                <!-- 弹窗 Header -->
                <div class="visil-modal-header">
                    <h2>🥕 <?php echo esc_html__('Radish WebP Optimizer — Bulk Workbench', 'webp-radish-webp-optimizer'); ?></h2>
                    <button type="button" class="visil-modal-close" id="btn-close-modal" title="<?php echo esc_attr__('Close (ESC)', 'webp-radish-webp-optimizer'); ?>">&times;</button>
                </div>

                <!-- 弹窗 Toolbar 工具栏 -->
                <div class="visil-modal-toolbar">
                    <div class="visil-toolbar-left">
                        <span style="font-weight:600; color:#1d2327;">🛠️ <?php echo esc_html__('Actions:', 'webp-radish-webp-optimizer'); ?></span>
                        <label style="cursor:pointer;">
                            <input type="checkbox" id="bulk_opt_orig" value="1" checked> 
                            <strong>① <?php echo esc_html__('Original Slimming', 'webp-radish-webp-optimizer'); ?></strong>
                        </label>
                        <label style="cursor:pointer;">
                            <input type="checkbox" id="bulk_gen_webp" value="1" checked> 
                            <strong>② <?php echo esc_html__('Generate WebP', 'webp-radish-webp-optimizer'); ?></strong>
                        </label>
                    </div>

                    <div class="visil-toolbar-right">
                        <button type="button" id="btn-modal-rescan" class="button button-secondary">🔄 <?php echo esc_html__('Rescan', 'webp-radish-webp-optimizer'); ?></button>
                        <button type="button" id="btn-select-all" class="button button-secondary"><?php echo esc_html__('Select All', 'webp-radish-webp-optimizer'); ?></button>
                        <button type="button" id="btn-select-none" class="button button-secondary"><?php echo esc_html__('Deselect All', 'webp-radish-webp-optimizer'); ?></button>
                        <button type="button" id="btn-select-unconverted" class="button button-secondary"><?php echo esc_html__('Unconverted Only', 'webp-radish-webp-optimizer'); ?></button>
                        <span style="color:#50575e; font-size:14px; margin-left:8px;">
                            <?php echo esc_html__('Selected:', 'webp-radish-webp-optimizer'); ?> <strong id="selected-count" style="color:#2271b1; font-size:16px;">0</strong> / <span id="total-scanned-count">0</span>
                        </span>
                    </div>
                </div>

                <!-- 弹窗 Body 表格清单区 -->
                <div class="visil-modal-body">
                    <div id="modal-loading-state" style="display:none; text-align:center; padding:60px 20px;">
                        <span class="spinner is-active" style="float:none; width:32px; height:32px; margin-bottom:12px;"></span>
                        <p style="font-size:16px; color:#50575e; font-weight:600;"><?php echo esc_html__('Scanning media library images, please wait...', 'webp-radish-webp-optimizer'); ?></p>
                    </div>

                    <table id="radish-modal-table" class="visil-modal-table" style="table-layout:fixed; width:100%;">
                        <thead>
                            <tr>
                                <th style="width:44px; text-align:center;"><input type="checkbox" id="th-select-all" checked></th>
                                <th style="width:64px; text-align:center;"><?php echo esc_html__('Thumbnail', 'webp-radish-webp-optimizer'); ?></th>
                                <th style="width:36%;"><?php echo esc_html__('File Name / Upload Date', 'webp-radish-webp-optimizer'); ?></th>
                                <th style="width:18%;"><?php echo esc_html__('Original Size', 'webp-radish-webp-optimizer'); ?></th>
                                <th style="width:18%;"><?php echo esc_html__('WebP Size', 'webp-radish-webp-optimizer'); ?></th>
                                <th style="width:16%; text-align:center;"><?php echo esc_html__('Status', 'webp-radish-webp-optimizer'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="scanned-media-tbody">
                            <!-- 动态渲染 -->
                        </tbody>
                    </table>
                </div>

                <!-- 弹窗 Footer 进度与执行按钮 -->
                <div class="visil-modal-footer">
                    <div class="visil-footer-progress">
                        <div id="bulk-progress-container" style="display:none;">
                            <div class="progress-bar-bg">
                                <div id="bulk-progress-bar" class="progress-bar-fill" style="width: 0%;"></div>
                            </div>
                            <div class="progress-status">
                                <span id="bulk-progress-text"><?php echo esc_html__('Ready to start...', 'webp-radish-webp-optimizer'); ?></span>
                                <span id="bulk-progress-percent">0%</span>
                            </div>
                        </div>
                    </div>

                    <div class="visil-footer-actions">
                        <button id="btn-start-bulk" type="button" class="button button-primary button-hero" style="min-width:240px; text-align:center; font-size:15px; font-weight:700;">
                            ⚡ <?php echo esc_html__('Start Bulk Optimization', 'webp-radish-webp-optimizer'); ?> (<span id="btn-selected-count">0</span>)
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= 全新高清大图放大预览灯箱 (Lightbox) ================= -->
        <div id="radish-lightbox-modal" class="radish-lightbox-overlay">
            <div class="radish-lightbox-container">
                <button type="button" class="radish-lightbox-close" id="btn-close-lightbox" title="<?php echo esc_attr__('Close (ESC)', 'webp-radish-webp-optimizer'); ?>">&times;</button>
                <div class="radish-lightbox-img-wrap">
                    <img id="radish-lightbox-img" src="" alt="<?php echo esc_attr__('Preview', 'webp-radish-webp-optimizer'); ?>">
                </div>
                <div id="radish-lightbox-caption" class="radish-lightbox-caption"></div>
            </div>
        </div>
        <?php
    }
}
