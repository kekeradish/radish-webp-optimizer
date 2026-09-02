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
        $settings_link = '<a href="' . admin_url('options-general.php?page=radish-webp-settings') . '">' . __('设置', 'radish-webp-optimizer') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function register_admin_menu() {
        add_options_page(
            __('🥕 萝卜 WebP 大师', 'radish-webp-optimizer'),
            __('🥕 萝卜 WebP 大师', 'radish-webp-optimizer'),
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
        
        $quality = isset($input['quality']) ? intval($input['quality']) : 80;
        $output['quality'] = max(1, min(100, $quality));

        $orig_quality = isset($input['orig_quality']) ? intval($input['orig_quality']) : 85;
        $output['orig_quality'] = max(1, min(100, $orig_quality));

        $max_dim = isset($input['max_dimension']) ? intval($input['max_dimension']) : 2560;
        $output['max_dimension'] = max(0, $max_dim);

        $delivery_mode = isset($input['delivery_mode']) && in_array($input['delivery_mode'], array('picture', 'replace')) ? $input['delivery_mode'] : 'picture';
        $output['delivery_mode'] = $delivery_mode;

        return $output;
    }

    public function add_media_columns($columns) {
        $columns['radish_webp_status'] = __('双重优化与单图操作', 'radish-webp-optimizer');
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
            echo '<span style="color:#d63638;">未生成元数据</span>';
            return;
        }

        $upload_dir = wp_upload_dir();
        $orig_path = path_join($upload_dir['basedir'], $meta['file']);
        $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $orig_path);
        $bak_path  = $orig_path . '.radish_bak';

        if (!file_exists($orig_path)) {
            echo '<span style="color:#a7aaad;">原图丢失</span>';
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
        echo '① 原图: <strong>' . size_format($current_orig_size, 1) . '</strong>';
        if ($orig_saved_pct > 0) {
            echo ' <span style="color:#2271b1; font-weight:600;">(-' . $orig_saved_pct . '%)</span>';
        }
        echo '</div>';

        // 2. WebP 阶段数据
        if ($has_webp) {
            $total_saved = $initial_size - $webp_size;
            $total_saved_pct = $initial_size > 0 ? round(($total_saved / $initial_size) * 100) : 0;

            echo '<div style="margin-bottom:6px; padding:2px 5px; background:#e7f7ed; border-radius:3px; border-left:2px solid #008a20;">';
            echo '② WebP: <strong style="color:#008a20;">' . size_format($webp_size, 1) . '</strong> <span style="color:#008a20; font-weight:700;">(-' . $total_saved_pct . '%)</span>';
            echo '</div>';
        } else {
            echo '<div style="margin-bottom:6px; color:#d63638;">② WebP: 尚未生成</div>';
        }

        // 3. 单图快捷操作按钮区
        echo '<div class="radish-action-links" style="display:flex; gap:6px; flex-wrap:wrap;">';
        echo '<button type="button" class="button button-small btn-radish-single-opt" data-id="' . esc_attr($post_id) . '" title="对此图片单独执行原图瘦身与WebP生成">⚡ 单独优化</button>';
        if ($has_bak) {
            echo '<button type="button" class="button button-small btn-radish-single-restore" data-id="' . esc_attr($post_id) . '" title="恢复最初未经压缩的原图母本" style="color:#8c5b00;">↩️ 恢复原图</button>';
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
        $html .= '<p style="margin:0 0 6px 0;"><strong>原始上传大小：</strong>' . size_format($initial_size, 2) . '</p>';
        
        $orig_saved = $initial_size - $current_orig_size;
        $orig_saved_pct = $initial_size > 0 ? round(($orig_saved / $initial_size) * 100) : 0;
        $html .= '<p style="margin:0 0 6px 0;"><strong>① 原图瘦身后大小：</strong>' . size_format($current_orig_size, 2) . ($orig_saved_pct > 0 ? ' <span style="color:#2271b1; font-weight:600;">(减少 ' . $orig_saved_pct . '%)</span>' : '') . '</p>';

        if ($has_webp) {
            $total_saved = $initial_size - $webp_size;
            $total_saved_pct = $initial_size > 0 ? round(($total_saved / $initial_size) * 100) : 0;
            $html .= '<p style="margin:0 0 8px 0;"><strong>② WebP 二次优化大小：</strong><span style="color:#008a20; font-weight:700; font-size:14px;">' . size_format($webp_size, 2) . '</span></p>';
            $html .= '<p style="margin:0 0 12px 0; padding:4px 8px; background:#e7f7ed; border-radius:4px; color:#008a20; font-weight:700;">🎉 终极优化收益：共节省 ' . size_format($total_saved, 2) . ' (' . $total_saved_pct . '%)</p>';
        } else {
            $html .= '<p style="margin:0 0 12px 0; color:#d63638;"><strong>② WebP 状态：</strong>尚未生成 WebP 副本</p>';
        }

        $html .= '<div style="display:flex; gap:8px;">';
        $html .= '<button type="button" class="button button-primary btn-radish-single-opt" data-id="' . esc_attr($post->ID) . '">⚡ 单独重新优化原图+WebP</button>';
        if ($has_bak) {
            $html .= '<button type="button" class="button btn-radish-single-restore" data-id="' . esc_attr($post->ID) . '">↩️ 恢复最初原始图</button>';
        }
        $html .= '</div>';

        $html .= '</div>';

        $form_fields['radish_webp_info'] = array(
            'label' => __('优化操作与详情', 'radish-webp-optimizer'),
            'input' => 'html',
            'html'  => $html,
        );

        return $form_fields;
    }

    public function ajax_optimize_single_action() {
        check_ajax_referer('radish_webp_admin_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => '权限不足'));
        }

        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        if (!$attachment_id) {
            wp_send_json_error(array('message' => '无效的图片 ID'));
        }

        $engine = new Radish_WebP_Engine();
        $res = $engine->optimize_single_attachment($attachment_id);

        if ($res) {
            wp_send_json_success(array('message' => '优化成功！'));
        } else {
            wp_send_json_error(array('message' => '优化失败或原图不存在'));
        }
    }

    public function ajax_restore_single_action() {
        check_ajax_referer('radish_webp_admin_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => '权限不足'));
        }

        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        if (!$attachment_id) {
            wp_send_json_error(array('message' => '无效的图片 ID'));
        }

        $engine = new Radish_WebP_Engine();
        $res = $engine->restore_original_file($attachment_id);

        if ($res) {
            wp_send_json_success(array('message' => '已成功恢复最初原图！'));
        } else {
            wp_send_json_error(array('message' => '未找到该图片的原始备份文件'));
        }
    }

    public function enqueue_admin_assets($hook) {
        wp_enqueue_style('radish-webp-admin-css', RADISH_WEBP_URL . 'assets/admin.css', array(), RADISH_WEBP_VERSION);
        wp_enqueue_script('radish-webp-admin-js', RADISH_WEBP_URL . 'assets/admin.js', array('jquery'), RADISH_WEBP_VERSION, true);

        wp_localize_script('radish-webp-admin-js', 'radishWebpData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('radish_webp_admin_nonce'),
            'strings' => array(
                'start'      => __('开始批量转换', 'radish-webp-optimizer'),
                'processing' => __('正在转换中...', 'radish-webp-optimizer'),
                'completed'  => __('所有历史图片已成功完成双重优化与 WebP 转换！', 'radish-webp-optimizer'),
                'error'      => __('操作过程中发生错误', 'radish-webp-optimizer'),
                'optimizing' => __('正在优化...', 'radish-webp-optimizer'),
                'restoring'  => __('正在恢复...', 'radish-webp-optimizer'),
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
            'orig_quality'      => 85,
            'max_dimension'     => 2560,
            'backup_original'   => 0,
        ));

        $driver = Radish_WebP_Engine::get_available_driver();
        ?>
        <div class="wrap visil-webp-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p class="visil-subtitle">由 <strong>求知的萝卜</strong> 倾力打造：原图智能瘦身 + WebP 终极二次优化双重架构，兼顾服务器磁盘与前台秒开速度。</p>

            <!-- 服务器环境状态卡片 -->
            <div class="visil-card visil-health-card">
                <h2>🔍 系统环境检测</h2>
                <div class="visil-health-grid">
                    <div class="health-item">
                        <span class="label">当前 PHP 引擎版本：</span>
                        <span class="value"><?php echo PHP_VERSION; ?></span>
                    </div>
                    <div class="health-item">
                        <span class="label">图像处理核心驱动：</span>
                        <?php if ($driver === 'imagick'): ?>
                            <span class="badge badge-success">✅ ImageMagick (推荐，支持色彩与元数据优化)</span>
                        <?php elseif ($driver === 'gd'): ?>
                            <span class="badge badge-success">✅ PHP GD 扩展 (支持 WebP)</span>
                        <?php else: ?>
                            <span class="badge badge-danger">❌ 未检测到 WebP 驱动 (请在 php.ini 中启用 gd 或 imagick 扩展)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="visil-columns">
                <!-- 左栏：常规设置 -->
                <div class="visil-main-col">
                    <div class="visil-card">
                        <h2>⚙️ 双重优化核心配置</h2>
                        <form method="post" action="options.php">
                            <?php
                            settings_fields('radish_webp_settings_group');
                            ?>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">启用插件功能</th>
                                    <td>
                                        <label class="switch">
                                            <input type="checkbox" name="radish_webp_settings[enabled]" value="1" <?php checked(1, !empty($settings['enabled'])); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">总开关。关闭后将停止前端替换与自动优化。</p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">上传新图时自动优化</th>
                                    <td>
                                        <label class="switch">
                                            <input type="checkbox" name="radish_webp_settings[convert_on_upload]" value="1" <?php checked(1, !empty($settings['convert_on_upload'])); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">上传 JPG/PNG 时，自动进行【原图瘦身】+【WebP 副本生成】。</p>
                                    </td>
                                </tr>

                                <tr style="background:#fafafa; border-top:1px dashed #dcdcde; border-bottom:1px dashed #dcdcde;">
                                    <th scope="row" style="padding-left:10px;">
                                        <strong>① 原图瘦身与安全备份</strong>
                                    </th>
                                    <td style="padding:15px 10px;">
                                        <div style="margin-bottom:12px;">
                                            <label class="switch">
                                                <input type="checkbox" name="radish_webp_settings[optimize_original]" value="1" <?php checked(1, !empty($settings['optimize_original'])); ?>>
                                                <span class="slider"></span>
                                            </label>
                                            <strong style="margin-left:8px;">开启原图自身瘦身优化</strong>
                                            <p class="description">对原 JPG/PNG 剥离 EXIF 元数据并高保真重压缩，大幅节省服务器磁盘！</p>
                                        </div>

                                        <div style="margin-bottom:12px; padding:10px; background:#fff; border:1px solid #e2e4e7; border-radius:6px;">
                                            <label class="switch">
                                                <input type="checkbox" name="radish_webp_settings[backup_original]" value="1" <?php checked(1, !empty($settings['backup_original'])); ?>>
                                                <span class="slider"></span>
                                            </label>
                                            <strong style="margin-left:8px;">保留原始母本备份（安全备份）</strong>
                                            <p class="description">瘦身前保留一份 <code>.radish_bak</code> 原始备份文件。开启后可在媒体库随时<strong>一键【恢复最初原图】</strong>；若关闭则不占额外备份磁盘空间。</p>
                                        </div>
                                        
                                        <div style="margin-top:12px;">
                                            <label>原图压缩质量：</label>
                                            <input type="number" name="radish_webp_settings[orig_quality]" min="50" max="100" value="<?php echo esc_attr(isset($settings['orig_quality']) ? $settings['orig_quality'] : 85); ?>" style="width:70px;"> %
                                            <span class="description" style="margin-left:8px;">(建议 85% ~ 90%，确保原图母本高保真)</span>
                                        </div>

                                        <div style="margin-top:8px;">
                                            <label>原图最大分辨率限制：</label>
                                            <input type="number" name="radish_webp_settings[max_dimension]" min="0" step="100" value="<?php echo esc_attr(isset($settings['max_dimension']) ? $settings['max_dimension'] : 2560); ?>" style="width:90px;"> px
                                            <span class="description" style="margin-left:8px;">(防止相机 4K/6K 巨幅原片撑爆服务器，0为不限制)</span>
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">② WebP 二次终极优化质量</th>
                                    <td>
                                        <div class="range-container">
                                            <input type="range" id="webp_quality_range" name="radish_webp_settings[quality]" min="1" max="100" value="<?php echo esc_attr($settings['quality']); ?>" oninput="document.getElementById('quality_val').innerText = this.value">
                                            <span id="quality_val" class="range-val"><?php echo esc_html($settings['quality']); ?></span>%
                                        </div>
                                        <p class="description">前台访客加载的 WebP 质量。推荐 <strong>75% ~ 82%</strong>（肉眼几乎无损且体积最小）。</p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">前端输出与降级模式</th>
                                    <td>
                                        <fieldset>
                                            <label>
                                                <input type="radio" name="radish_webp_settings[delivery_mode]" value="picture" <?php checked('picture', $settings['delivery_mode']); ?>>
                                                <strong>HTML5 &lt;picture&gt; 模式（强烈推荐）</strong>
                                            </label>
                                            <p class="description" style="margin-left:24px; margin-bottom:12px;">现代浏览器加载超轻 WebP，老旧客户端自动加载瘦身后的原图，100% 零破图。</p>

                                            <label>
                                                <input type="radio" name="radish_webp_settings[delivery_mode]" value="replace" <?php checked('replace', $settings['delivery_mode']); ?>>
                                                <strong>直接替换模式</strong>
                                            </label>
                                            <p class="description" style="margin-left:24px;">直接将 <code>&lt;img src="..."&gt;</code> 替换为 WebP 格式 URL。</p>
                                        </fieldset>
                                    </td>
                                </tr>
                            </table>

                            <?php submit_button('保存配置'); ?>
                        </form>
                    </div>
                </div>

                <!-- 右栏：批量优化入口卡片 -->
                <div class="visil-side-col">
                    <div class="visil-card" style="border-top: 4px solid #2271b1;">
                        <h2>🚀 批量图片优化工作台</h2>
                        <p class="description" style="margin-bottom:18px;">以独立大窗口形式直观扫描、筛选全站图片，支持高清大图预览、自定义勾选与实时双重优化。</p>

                        <div style="background:#f0f6fc; border:1px solid #c8d8f8; border-radius:8px; padding:16px; margin-bottom:18px;">
                            <ul style="margin:0; padding-left:18px; color:#1d2327; font-size:13px; line-height:1.8;">
                                <li>✨ <strong>全屏宽阔视野：</strong>告别窄小边栏，宽屏高清表格浏览；</li>
                                <li>🔍 <strong>大缩略图悬停放大：</strong>鼠标悬停即刻清晰查看原图；</li>
                                <li>🎯 <strong>精准多选：</strong>一键全选、一键仅选未优化图片。</li>
                            </ul>
                        </div>

                        <button id="btn-open-modal" type="button" class="button button-primary button-hero" style="width:100%; text-align:center; font-weight:700; height:46px; line-height:44px;">
                            🔍 打开批量优化管理大窗口
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= 全新宽屏大模态弹窗 (Modal) ================= -->
        <div id="radish-bulk-modal" class="visil-modal-overlay">
            <div class="visil-modal-container">
                <!-- 弹窗 Header -->
                <div class="visil-modal-header">
                    <h2>🥕 萝卜 WebP 大师 — 批量优化工作台</h2>
                    <button type="button" class="visil-modal-close" id="btn-close-modal" title="关闭窗口 (ESC)">&times;</button>
                </div>

                <!-- 弹窗 Toolbar 工具栏 -->
                <div class="visil-modal-toolbar">
                    <div class="visil-toolbar-left">
                        <span style="font-weight:600; color:#1d2327;">🛠️ 处理项目：</span>
                        <label style="cursor:pointer;">
                            <input type="checkbox" id="bulk_opt_orig" value="1" checked> 
                            <strong>① 原图瘦身优化</strong>
                        </label>
                        <label style="cursor:pointer;">
                            <input type="checkbox" id="bulk_gen_webp" value="1" checked> 
                            <strong>② 生成/更新 WebP</strong>
                        </label>
                    </div>

                    <div class="visil-toolbar-right">
                        <button type="button" id="btn-modal-rescan" class="button button-secondary">🔄 重新扫描</button>
                        <button type="button" id="btn-select-all" class="button button-secondary">全选</button>
                        <button type="button" id="btn-select-none" class="button button-secondary">取消全选</button>
                        <button type="button" id="btn-select-unconverted" class="button button-secondary">仅选未优化图片</button>
                        <span style="color:#50575e; font-size:14px; margin-left:8px;">
                            已勾选 <strong id="selected-count" style="color:#2271b1; font-size:16px;">0</strong> / <span id="total-scanned-count">0</span> 张
                        </span>
                    </div>
                </div>

                <!-- 弹窗 Body 表格清单区 -->
                <div class="visil-modal-body">
                    <div id="modal-loading-state" style="display:none; text-align:center; padding:60px 20px;">
                        <span class="spinner is-active" style="float:none; width:32px; height:32px; margin-bottom:12px;"></span>
                        <p style="font-size:16px; color:#50575e; font-weight:600;">正在扫描媒体库所有图片，请稍候...</p>
                    </div>

                    <table id="radish-modal-table" class="visil-modal-table" style="table-layout:fixed; width:100%;">
                        <thead>
                            <tr>
                                <th style="width:44px; text-align:center;"><input type="checkbox" id="th-select-all" checked></th>
                                <th style="width:64px; text-align:center;">缩略图</th>
                                <th style="width:36%;">文件名 / 上传日期</th>
                                <th style="width:18%;">原图体积</th>
                                <th style="width:18%;">WebP 体积</th>
                                <th style="width:16%; text-align:center;">当前状态</th>
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
                                <span id="bulk-progress-text">准备开始...</span>
                                <span id="bulk-progress-percent">0%</span>
                            </div>
                        </div>
                    </div>

                    <div class="visil-footer-actions">
                        <button id="btn-start-bulk" type="button" class="button button-primary button-hero" style="min-width:240px; text-align:center; font-size:15px; font-weight:700;">
                            ⚡ 开始批量优化选中的图片 (<span id="btn-selected-count">0</span>)
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= 全新高清大图放大预览灯箱 (Lightbox) ================= -->
        <div id="radish-lightbox-modal" class="radish-lightbox-overlay">
            <div class="radish-lightbox-container">
                <button type="button" class="radish-lightbox-close" id="btn-close-lightbox" title="关闭大图预览 (ESC)">&times;</button>
                <div class="radish-lightbox-img-wrap">
                    <img id="radish-lightbox-img" src="" alt="大图预览">
                </div>
                <div id="radish-lightbox-caption" class="radish-lightbox-caption"></div>
            </div>
        </div>
        <?php
    }
}
