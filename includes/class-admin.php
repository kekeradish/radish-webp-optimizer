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
        $settings_link = '<a href="' . admin_url('admin.php?page=radish-webp-settings') . '">' . radish_t('Settings') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function register_admin_menu() {
        // 1. 在 WordPress 左侧主菜单栏注册独立的一级顶级菜单（位于【设置】下方、底部独立插件专区）
        add_menu_page(
            radish_t('🥕 Radish WebP'),
            radish_t('🥕 Radish WebP'),
            'manage_options',
            'radish-webp-settings',
            array($this, 'render_admin_page'),
            'dashicons-images-alt2',
            99.5
        );

        // 2. 注册左侧二级菜单（设置面板）
        add_submenu_page(
            'radish-webp-settings',
            radish_t('Settings & Dashboard'),
            radish_t('Settings & Dashboard'),
            'manage_options',
            'radish-webp-settings',
            array($this, 'render_admin_page')
        );

        // 3. 同时在常规【设置】中保留快捷入口
        add_options_page(
            radish_t('🥕 Radish WebP Optimizer'),
            radish_t('🥕 Radish WebP Optimizer'),
            'manage_options',
            'radish-webp-settings-alt',
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
        $columns['radish_webp_status'] = radish_t('Dual Optimization & Actions');
        return $columns;
    }

    public function render_media_column($column_name, $post_id) {
        if ($column_name !== 'radish_webp_status') {
            return;
        }

        $meta = wp_get_attachment_metadata($post_id);
        if (empty($meta) || empty($meta['file'])) {
            echo '<span style="color:#999;">' . radish_esc_html_t('No metadata') . '</span>';
            return;
        }

        $upload_dir = wp_upload_dir();
        $orig_path = path_join($upload_dir['basedir'], $meta['file']);
        if (!file_exists($orig_path)) {
            echo '<span style="color:#d63638;">' . radish_esc_html_t('File missing') . '</span>';
            return;
        }

        $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $orig_path);
        $bak_path  = $orig_path . '.radish_bak';

        $initial_size = get_post_meta($post_id, '_radish_initial_size', true);
        $current_orig_size = filesize($orig_path);
        if (!$initial_size || $initial_size < $current_orig_size) {
            $initial_size = $current_orig_size;
        }

        $has_webp = file_exists($webp_path);
        $webp_size = $has_webp ? filesize($webp_path) : 0;
        $has_bak  = file_exists($bak_path);

        echo '<div class="radish-media-col" id="radish-col-' . esc_attr($post_id) . '" style="font-size:12px; line-height:1.4;">';

        // 1. 原图阶段数据
        $orig_saved = $initial_size - $current_orig_size;
        $orig_saved_pct = $initial_size > 0 ? round(($orig_saved / $initial_size) * 100) : 0;

        echo '<div style="margin-bottom:4px; padding:2px 5px; background:#f0f6fc; border-radius:3px; border-left:2px solid #2271b1;">';
        echo radish_esc_html_t('① Original:') . ' <strong>' . size_format($current_orig_size, 1) . '</strong>';
        if ($orig_saved_pct > 0) {
            echo ' <span style="color:#2271b1; font-weight:700;">(-' . $orig_saved_pct . '%)</span>';
        }
        echo '</div>';

        // 2. WebP 阶段数据
        if ($has_webp) {
            $total_saved = $initial_size - $webp_size;
            $total_saved_pct = $initial_size > 0 ? round(($total_saved / $initial_size) * 100) : 0;

            echo '<div style="margin-bottom:6px; padding:2px 5px; background:#e7f7ed; border-radius:3px; border-left:2px solid #008a20;">';
            echo radish_esc_html_t('② WebP:') . ' <strong style="color:#008a20;">' . size_format($webp_size, 1) . '</strong> <span style="color:#008a20; font-weight:700;">(-' . $total_saved_pct . '%)</span>';
            echo '</div>';
        } else {
            echo '<div style="margin-bottom:6px; color:#d63638;">' . radish_esc_html_t('② WebP: Not generated') . '</div>';
        }

        // 3. 单图快捷操作按钮区
        echo '<div class="radish-action-links" style="display:flex; gap:6px; flex-wrap:wrap;">';
        echo '<button type="button" class="button button-small btn-radish-single-opt" data-id="' . esc_attr($post_id) . '" title="' . esc_attr(radish_t('Optimize original image and generate WebP')) . '">⚡ ' . radish_esc_html_t('Optimize') . '</button>';
        if ($has_bak) {
            echo '<button type="button" class="button button-small btn-radish-single-restore" data-id="' . esc_attr($post_id) . '" title="' . esc_attr(radish_t('Restore initial uncompressed original image')) . '" style="color:#8c5b00;">↩️ ' . radish_esc_html_t('Restore') . '</button>';
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
        $html .= '<p style="margin:0 0 6px 0;"><strong>' . radish_esc_html_t('Initial Upload Size:') . '</strong> ' . size_format($initial_size, 2) . '</p>';
        
        $orig_saved = $initial_size - $current_orig_size;
        $orig_saved_pct = $initial_size > 0 ? round(($orig_saved / $initial_size) * 100) : 0;
        $html .= '<p style="margin:0 0 6px 0;"><strong>' . radish_esc_html_t('① Optimized Original Size:') . '</strong> ' . size_format($current_orig_size, 2) . ($orig_saved_pct > 0 ? ' <span style="color:#2271b1; font-weight:600;">(-' . $orig_saved_pct . '%)</span>' : '') . '</p>';

        if ($has_webp) {
            $total_saved = $initial_size - $webp_size;
            $total_saved_pct = $initial_size > 0 ? round(($total_saved / $initial_size) * 100) : 0;
            $html .= '<p style="margin:0 0 8px 0;"><strong>' . radish_esc_html_t('② WebP Image Size:') . '</strong> <span style="color:#008a20; font-weight:700; font-size:14px;">' . size_format($webp_size, 2) . '</span></p>';
            $html .= '<p style="margin:0 0 12px 0; padding:4px 8px; background:#e7f7ed; border-radius:4px; color:#008a20; font-weight:700;">🎉 ' . radish_esc_html_t('Total Savings:') . ' ' . size_format($total_saved, 2) . ' (' . $total_saved_pct . '%)</p>';
        } else {
            $html .= '<p style="margin:0 0 12px 0; color:#d63638;"><strong>' . radish_esc_html_t('② WebP Status:') . '</strong> ' . radish_esc_html_t('WebP copy has not been generated yet') . '</p>';
        }

        $html .= '<div style="display:flex; gap:8px;">';
        $html .= '<button type="button" class="button button-primary btn-radish-single-opt" data-id="' . esc_attr($post->ID) . '">⚡ ' . radish_esc_html_t('Re-optimize Original + WebP') . '</button>';
        if ($has_bak) {
            $html .= '<button type="button" class="button btn-radish-single-restore" data-id="' . esc_attr($post->ID) . '">↩️ ' . radish_esc_html_t('Restore Initial Image') . '</button>';
        }
        $html .= '</div>';

        $html .= '</div>';

        $form_fields['radish_webp_info'] = array(
            'label' => radish_t('Optimization Info & Actions'),
            'input' => 'html',
            'html'  => $html,
        );

        return $form_fields;
    }

    public function ajax_optimize_single_action() {
        check_ajax_referer('radish_webp_admin_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => radish_t('Permission denied')));
        }

        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        if (!$attachment_id) {
            wp_send_json_error(array('message' => radish_t('Invalid attachment ID')));
        }

        $engine = new Radish_WebP_Engine();
        $res = $engine->optimize_single_attachment($attachment_id);

        if ($res) {
            wp_send_json_success(array('message' => radish_t('Optimized successfully!')));
        } else {
            wp_send_json_error(array('message' => radish_t('Optimization failed or original image missing')));
        }
    }

    public function ajax_restore_single_action() {
        check_ajax_referer('radish_webp_admin_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => radish_t('Permission denied')));
        }

        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        if (!$attachment_id) {
            wp_send_json_error(array('message' => radish_t('Invalid attachment ID')));
        }

        $engine = new Radish_WebP_Engine();
        $res = $engine->restore_original_file($attachment_id);

        if ($res) {
            wp_send_json_success(array('message' => radish_t('Restored initial original file successfully!')));
        } else {
            wp_send_json_error(array('message' => radish_t('No backup file found for this image')));
        }
    }

    public function enqueue_admin_assets($hook) {
        $is_radish_page = isset($_GET['page']) && (strpos($_GET['page'], 'radish-webp') !== false);
        $is_media_page  = in_array($hook, array('upload.php', 'post.php', 'post-new.php'), true);

        // 只要是插件后台、媒体库或包含 radish-webp 的页面，100% 确保加载样式和脚本
        if (!$is_radish_page && !$is_media_page && strpos($hook, 'radish') === false) {
            return;
        }

        $ver = RADISH_WEBP_VERSION . '.' . time();
        wp_enqueue_style('radish-webp-admin-css', RADISH_WEBP_URL . 'assets/admin.css', array(), $ver);
        wp_enqueue_script('radish-webp-admin-js', RADISH_WEBP_URL . 'assets/admin.js', array('jquery'), $ver, true);

        wp_localize_script('radish-webp-admin-js', 'radishWebpData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('radish_webp_admin_nonce'),
            'strings' => array(
                'start'           => radish_t('Start Bulk Optimization'),
                'processing'      => radish_t('Optimizing...'),
                'completed'       => radish_t('Bulk Process Finished'),
                'error'           => radish_t('Optimization failed or original image missing'),
                'optimizing'      => radish_t('Optimizing...'),
                'restoring'       => radish_t('Restoring...'),
                'restoreSuccess'  => radish_t('Restored initial original file successfully!'),
                'optimizeSuccess' => radish_t('Optimized successfully!'),
                'confirmRestore'  => radish_t('Restore initial uncompressed original image'),
                'noSelected'      => radish_t('Please select at least one image to optimize.'),
                'noOptionSelected'=> radish_t('Please select at least one task (Slim Original or Generate WebP).'),
                'scanning'        => radish_t('Scan and filter all media library images in an independent large modal dialog with large preview, selection, and dual-optimization.'),
                'noImages'        => radish_t('No JPG/PNG images found in media library'),
                'generated'       => radish_t('Completed'),
                'notGenerated'    => radish_t('② WebP: Not generated'),
                'scanFailed'      => radish_t('Scan failed, please check network connection'),
                'previewTitle'    => radish_t('Image Preview'),
                'previewHover'    => radish_t('Lightbox Large Preview'),
                'uploadDate'      => radish_t('File Name / Upload Date'),
                'reOptimizeBtn'   => radish_t('Re-optimize Original + WebP'),
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
                            <?php echo radish_esc_html_t('Radish WebP Optimizer'); ?>
                            <span class="radish-version-tag">v<?php echo RADISH_WEBP_VERSION; ?></span>
                        </h1>
                        <p><?php echo radish_esc_html_t('Dual-stage optimization architecture for massive server storage savings and lightning-fast WebP loading.'); ?></p>
                    </div>
                </div>

                <div class="radish-header-meta">
                    <div class="radish-meta-pill">
                        <span><?php echo radish_esc_html_t('PHP Version:'); ?></span> <strong><?php echo PHP_VERSION; ?></strong>
                    </div>
                    <div class="radish-meta-pill">
                        <span><?php echo radish_esc_html_t('Driver:'); ?></span> 
                        <?php if ($driver === 'imagick'): ?>
                            <strong style="color:#34d399;">ImageMagick</strong>
                        <?php elseif ($driver === 'gd'): ?>
                            <strong style="color:#60a5fa;">PHP GD</strong>
                        <?php else: ?>
                            <strong style="color:#f87171;"><?php echo radish_esc_html_t('None'); ?></strong>
                        <?php endif; ?>
                    </div>
                    <div class="radish-meta-pill" style="border-color: rgba(16, 185, 129, 0.4); background: rgba(16, 185, 129, 0.15); color: #34d399;">
                        ● <strong><?php echo radish_esc_html_t('Engine Active'); ?></strong>
                    </div>
                </div>
            </div>

            <!-- 1. 核心大工作台醒目 Banner -->
            <div class="radish-workbench-hero">
                <div class="radish-workbench-left">
                    <h2>🚀 <?php echo radish_esc_html_t('Bulk Optimization Workbench'); ?></h2>
                    <p><?php echo radish_esc_html_t('Scan and filter all media library images in an independent large modal dialog with large preview, selection, and dual-optimization.'); ?></p>
                    <div class="radish-workbench-badges">
                        <span>✨ <?php echo radish_esc_html_t('No Horizontal Scroll'); ?></span>
                        <span>🔍 <?php echo radish_esc_html_t('Lightbox Large Preview'); ?></span>
                        <span>🎯 <?php echo radish_esc_html_t('Batch Selection'); ?></span>
                    </div>
                </div>
                <div class="radish-workbench-right">
                    <button id="btn-open-modal" type="button" class="button button-primary button-hero btn-open-modal" onclick="if(window.radishOpenModalDirect){window.radishOpenModalDirect();}else{var m=document.getElementById('radish-bulk-modal');if(m){m.style.display='flex';m.classList.add('active');document.body.style.overflow='hidden';}}" style="height:44px; line-height:42px; font-size:14px; padding:0 24px !important; border-radius:10px;">
                        🔍 <?php echo radish_esc_html_t('Open Bulk Optimization Workbench'); ?>
                    </button>
                </div>
            </div>

            <!-- 2. 主设置表单流 -->
            <form method="post" action="options.php">
                <?php settings_fields('radish_webp_settings_group'); ?>

                <!-- 卡片 1：基础自动化与语言 -->
                <div class="visil-card">
                    <h2>⚙️ <?php echo radish_esc_html_t('Core Optimization Settings'); ?></h2>
                    
                    <div class="radish-form-row">
                        <div class="radish-form-label">
                            <strong><?php echo radish_esc_html_t('Interface Language'); ?></strong>
                            <p class="description"><?php echo radish_esc_html_t('Select the display language for the plugin settings and batch workbench.'); ?></p>
                        </div>
                        <div class="radish-form-control">
                            <select name="radish_webp_settings[plugin_lang]" style="min-width: 180px; border-radius: 8px; font-size:13px;">
                                <option value="auto" <?php selected('auto', isset($settings['plugin_lang']) ? $settings['plugin_lang'] : 'auto'); ?>><?php echo radish_esc_html_t('Auto (Follow Site Language)'); ?></option>
                                <option value="zh_CN" <?php selected('zh_CN', isset($settings['plugin_lang']) ? $settings['plugin_lang'] : 'auto'); ?>>🇨🇳 <?php echo radish_esc_html_t('Simplified Chinese'); ?> (zh_CN)</option>
                                <option value="en_US" <?php selected('en_US', isset($settings['plugin_lang']) ? $settings['plugin_lang'] : 'auto'); ?>>🇺🇸 <?php echo radish_esc_html_t('English'); ?> (en_US)</option>
                            </select>
                        </div>
                    </div>

                    <div class="radish-form-row">
                        <div class="radish-form-label">
                            <strong><?php echo radish_esc_html_t('Enable Plugin Features'); ?></strong>
                            <p class="description"><?php echo radish_esc_html_t('Master switch. Disabling stops frontend replacement and automatic conversion.'); ?></p>
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
                            <strong><?php echo radish_esc_html_t('Auto Convert on Upload'); ?></strong>
                            <p class="description"><?php echo radish_esc_html_t('Automatically optimize original images and generate WebP files upon uploading new JPG/PNG.'); ?></p>
                        </div>
                        <div class="radish-form-control">
                            <label class="switch">
                                <input type="checkbox" name="radish_webp_settings[convert_on_upload]" value="1" <?php checked(1, !empty($settings['convert_on_upload'])); ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- 卡片 2：原图智能脱水瘦身 -->
                <div class="visil-card">
                    <h2>① <?php echo radish_esc_html_t('Original Slimming & Safe Backup'); ?></h2>
                    
                    <div class="radish-form-row">
                        <div class="radish-form-label">
                            <strong><?php echo radish_esc_html_t('Enable Original Image Slimming Optimization'); ?></strong>
                            <p class="description"><?php echo radish_esc_html_t('Strip EXIF metadata and perform smart color quantization on JPG/PNG originals to drastically save server disk space!'); ?></p>
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
                            <strong><?php echo radish_esc_html_t('Keep Initial Master Backup (.radish_bak)'); ?></strong>
                            <p class="description"><?php echo radish_esc_html_t('Preserve a .radish_bak backup before slimming. Enables one-click restoration in Media Library anytime.'); ?></p>
                        </div>
                        <div class="radish-form-control">
                            <label class="switch">
                                <input type="checkbox" name="radish_webp_settings[backup_original]" value="1" <?php checked(1, !empty($settings['backup_original'])); ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="radish-sub-box" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
                        <div>
                            <label style="font-weight:600; font-size:13px; color:#334155;"><?php echo radish_esc_html_t('Original Image Quality:'); ?></label>
                            <input type="number" name="radish_webp_settings[orig_quality]" min="50" max="100" value="<?php echo esc_attr(isset($settings['orig_quality']) ? $settings['orig_quality'] : 82); ?>" style="width:70px; border-radius:6px; margin-left:6px;"> %
                            <span class="description" style="margin-left:6px;"><?php echo radish_esc_html_t('(Recommended: 82% ~ 85% for high visual fidelity)'); ?></span>
                        </div>

                        <div>
                            <label style="font-weight:600; font-size:13px; color:#334155;"><?php echo radish_esc_html_t('Max Original Resolution Dimension:'); ?></label>
                            <input type="number" name="radish_webp_settings[max_dimension]" min="0" step="100" value="<?php echo esc_attr(isset($settings['max_dimension']) ? $settings['max_dimension'] : 2560); ?>" style="width:90px; border-radius:6px; margin-left:6px;"> px
                        </div>
                    </div>
                </div>

                <!-- 卡片 3：WebP 终极优化与交付 -->
                <div class="visil-card">
                    <h2>② <?php echo radish_esc_html_t('WebP Conversion Quality'); ?></h2>
                    
                    <div class="radish-form-row" style="padding-bottom:16px;">
                        <div class="radish-form-label">
                            <strong><?php echo radish_esc_html_t('WebP Conversion Quality'); ?></strong>
                            <p class="description"><?php echo radish_esc_html_t('WebP quality delivered to website visitors. Recommended: 75% ~ 82% (sweet spot for size & quality).'); ?></p>
                        </div>
                        <div class="radish-form-control" style="min-width:220px;">
                            <div class="range-container">
                                <input type="range" id="webp_quality_range" name="radish_webp_settings[quality]" min="1" max="100" value="<?php echo esc_attr($settings['quality']); ?>" oninput="document.getElementById('quality_val').innerText = this.value">
                                <span id="quality_val" class="range-val"><?php echo esc_html($settings['quality']); ?></span>%
                            </div>
                        </div>
                    </div>

                    <div style="margin-top:14px; padding-top:12px; border-top:1px dashed #f1f5f9;">
                        <strong style="display:block; font-size:13.5px; color:#1e293b; margin-bottom:10px;"><?php echo radish_esc_html_t('Frontend Delivery Mode'); ?></strong>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                            <label style="display:flex; gap:10px; padding:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; cursor:pointer;">
                                <input type="radio" name="radish_webp_settings[delivery_mode]" value="picture" <?php checked('picture', $settings['delivery_mode']); ?> style="margin-top:2px;">
                                <div>
                                    <strong style="font-size:13px; color:#0f172a;">HTML5 &lt;picture&gt; (<?php echo radish_esc_html_t('Recommended'); ?>)</strong>
                                    <p class="description" style="margin:2px 0 0 0;"><?php echo radish_esc_html_t('Modern browsers load WebP, legacy clients automatically fallback to original images. 100% zero broken images.'); ?></p>
                                </div>
                            </label>

                            <label style="display:flex; gap:10px; padding:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; cursor:pointer;">
                                <input type="radio" name="radish_webp_settings[delivery_mode]" value="replace" <?php checked('replace', $settings['delivery_mode']); ?> style="margin-top:2px;">
                                <div>
                                    <strong style="font-size:13px; color:#0f172a;"><?php echo radish_esc_html_t('Direct URL Replacement Mode'); ?></strong>
                                    <p class="description" style="margin:2px 0 0 0;"><?php echo radish_esc_html_t('Directly replaces <img src="..."> image URLs with .webp URLs.'); ?></p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div style="margin-top:20px; padding-top:16px; border-top:1px solid #f1f5f9; display:flex; justify-content:flex-end;">
                        <?php submit_button(radish_t('Save Changes'), 'primary', 'submit', false, array('style' => 'font-size:14px; padding:8px 36px; height:auto; border-radius:8px;')); ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- 3. 全屏独立大模态弹窗工作台 -->
        <div id="radish-bulk-modal" class="visil-modal-overlay" style="display:none;">
            <div class="visil-modal-container">
                <div class="visil-modal-header">
                    <h2>🚀 <?php echo radish_esc_html_t('Bulk Optimization Workbench'); ?></h2>
                    <button id="btn-close-modal" type="button" class="visil-modal-close" onclick="if(window.radishCloseModalDirect){window.radishCloseModalDirect(event);}else{var m=document.getElementById('radish-bulk-modal');if(m){m.style.display='none';m.classList.remove('active');document.body.style.overflow='';}}" title="<?php echo esc_attr(radish_t('Close (ESC)')); ?>">&times;</button>
                </div>

                <div class="visil-modal-toolbar">
                    <div class="visil-toolbar-left">
                        <div class="visil-task-options" style="display:flex; align-items:center; gap:14px;">
                            <span style="font-weight:700; font-size:13px; color:#1e293b;"><?php echo radish_esc_html_t('Actions:'); ?></span>
                            <label style="display:inline-flex; align-items:center; gap:6px; cursor:pointer; font-weight:600; font-size:13px; color:#2563eb;">
                                <input type="checkbox" id="modal-task-opt-orig" value="1" checked>
                                <?php echo radish_esc_html_t('Slim Original'); ?>
                            </label>
                            <label style="display:inline-flex; align-items:center; gap:6px; cursor:pointer; font-weight:600; font-size:13px; color:#10b981;">
                                <input type="checkbox" id="modal-task-gen-webp" value="1" checked>
                                <?php echo radish_esc_html_t('Generate WebP'); ?>
                            </label>
                        </div>
                    </div>

                    <div class="visil-toolbar-right">
                        <button id="btn-modal-rescan" type="button" class="button" style="border-radius:6px; font-weight:600;"><?php echo radish_esc_html_t('Rescan'); ?></button>
                        <button id="btn-modal-select-all" type="button" class="button button-secondary" style="border-radius:6px;"><?php echo radish_esc_html_t('Select All'); ?></button>
                        <button id="btn-modal-deselect-all" type="button" class="button" style="border-radius:6px;"><?php echo radish_esc_html_t('Deselect All'); ?></button>
                        <button id="btn-modal-select-unconverted" type="button" class="button button-secondary" style="border-radius:6px; font-weight:600; color:#2563eb;"><?php echo radish_esc_html_t('Unconverted Only'); ?></button>
                    </div>
                </div>

                <div class="visil-modal-body">
                    <div id="modal-loading-state" style="padding:60px; text-align:center; color:#64748b;">
                        <span class="spinner is-active" style="float:none; margin:0 8px 0 0;"></span>
                        <?php echo radish_esc_html_t('Scan and filter all media library images in an independent large modal dialog with large preview, selection, and dual-optimization.'); ?>
                    </div>

                    <table class="visil-modal-table" id="radish-modal-table" style="display:none;">
                        <thead>
                            <tr>
                                <th style="width: 44px; text-align:center;"><input type="checkbox" id="th-select-all"></th>
                                <th style="width: 70px; text-align:center;"><?php echo radish_esc_html_t('Thumbnail'); ?></th>
                                <th style="width: 40%;"><?php echo radish_esc_html_t('File Name / Upload Date'); ?></th>
                                <th style="width: 15%;"><?php echo radish_esc_html_t('Original Size'); ?></th>
                                <th style="width: 15%;"><?php echo radish_esc_html_t('WebP Size'); ?></th>
                                <th style="width: 18%;"><?php echo radish_esc_html_t('Status'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="scanned-media-tbody">
                            <!-- JS 动态注入 -->
                        </tbody>
                    </table>
                </div>

                <div class="visil-modal-footer">
                    <div class="visil-footer-left">
                        <div style="font-size:13.5px; color:#334155; font-weight:600; white-space:nowrap;">
                            <?php echo radish_esc_html_t('Selected:'); ?> <strong id="selected-count" style="color:var(--radish-primary); font-size:16px;">0</strong> / <span id="total-scanned-count">0</span>
                        </div>
                        <div id="bulk-progress-container" class="radish-progress-card" style="display:none;">
                            <div class="progress-status-row">
                                <span id="bulk-progress-text"><?php echo radish_esc_html_t('Optimizing...'); ?></span>
                                <span id="bulk-progress-percent" class="progress-percent-badge">0%</span>
                            </div>
                            <div class="progress-bar-bg">
                                <div id="bulk-progress-bar" class="progress-bar-fill" style="width:0%;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="visil-footer-right" style="display:flex; gap:10px; flex-shrink:0;">
                        <button id="btn-start-bulk" type="button" class="button button-primary" style="font-weight:700; height:38px; line-height:36px; padding:0 24px !important; border-radius:8px; white-space:nowrap;">
                            ⚡ <?php echo radish_esc_html_t('Start Bulk Optimization'); ?> (<span id="btn-selected-count">0</span>)
                        </button>
                        <button id="btn-close-modal-footer" type="button" class="button" onclick="if(window.radishCloseModalDirect){window.radishCloseModalDirect(event);}else{var m=document.getElementById('radish-bulk-modal');if(m){m.style.display='none';m.classList.remove('active');document.body.style.overflow='';}}" style="height:38px; line-height:36px; border-radius:8px;">
                            <?php echo radish_esc_html_t('Close (ESC)'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. 高清大图灯箱预览 -->
        <div id="radish-lightbox-modal" class="radish-lightbox-overlay" style="display:none;" onclick="if(window.radishCloseLightbox){window.radishCloseLightbox(event);}">
            <div class="radish-lightbox-container" onclick="event.stopPropagation();">
                <button type="button" id="btn-close-lightbox" class="radish-lightbox-close" onclick="if(window.radishCloseLightbox){window.radishCloseLightbox(event);}">&times;</button>
                <div class="radish-lightbox-img-wrap">
                    <img id="radish-lightbox-img" src="" alt="">
                </div>
                <div id="radish-lightbox-caption" class="radish-lightbox-caption"></div>
            </div>
        </div>
        <?php
    }
}
