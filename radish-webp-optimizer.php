<?php
/**
 * Plugin Name:       Radish WebP Optimizer (萝卜 WebP 大师)
 * Plugin URI:        https://github.com/kekeradish/radish-webp-optimizer
 * Description:       Automatically optimize original JPG/PNG images and convert them into lightweight WebP format. Features visual lossless compression, large batch modal workbench, image lightbox preview, and safe backup.
 * Version:           1.1.1
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            求知的萝卜
 * Author URI:        https://mp.weixin.qq.com
 * License:           GPL-2.0+
 * Text Domain:       webp-radish-webp-optimizer
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit; // 防止直接访问
}

define('RADISH_WEBP_VERSION', '1.1.1');
define('RADISH_WEBP_PATH', plugin_dir_path(__FILE__));
define('RADISH_WEBP_URL', plugin_dir_url(__FILE__));
define('RADISH_WEBP_BASENAME', plugin_basename(__FILE__));

// 加载核心模块类
require_once RADISH_WEBP_PATH . 'includes/class-i18n.php';
require_once RADISH_WEBP_PATH . 'includes/class-converter.php';
require_once RADISH_WEBP_PATH . 'includes/class-frontend.php';
require_once RADISH_WEBP_PATH . 'includes/class-admin.php';
require_once RADISH_WEBP_PATH . 'includes/class-bulk-processor.php';

/**
 * 插件主控单例类
 */
final class Radish_WebP_Master {

    private static $instance = null;

    public $converter;
    public $frontend;
    public $admin;
    public $bulk_processor;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        add_filter('plugin_locale', array($this, 'filter_plugin_locale'), 10, 2);
        
        // 尽早加载多语言
        $this->load_textdomain();
        add_action('init', array($this, 'load_textdomain'));

        $this->converter      = new Radish_WebP_Engine();
        $this->frontend       = new Radish_WebP_Frontend();
        $this->admin          = new Radish_WebP_Admin();
        $this->bulk_processor = new Radish_WebP_Bulk_Processor();
    }

    public function filter_plugin_locale($locale, $domain) {
        if ($domain === 'webp-radish-webp-optimizer') {
            $settings = get_option('radish_webp_settings');
            if (!empty($settings['plugin_lang']) && $settings['plugin_lang'] !== 'auto') {
                return $settings['plugin_lang'];
            }
        }
        return $locale;
    }

    public function load_textdomain() {
        $settings = get_option('radish_webp_settings');
        $lang = !empty($settings['plugin_lang']) ? $settings['plugin_lang'] : 'auto';

        if ($lang === 'en_US') {
            // 强制英文：卸载 mo 文件，源码直接输出纯英文
            unload_textdomain('webp-radish-webp-optimizer');
            return;
        }

        if ($lang === 'zh_CN') {
            // 强制加载简体中文语言包
            $mo_file = RADISH_WEBP_PATH . 'languages/webp-radish-webp-optimizer-zh_CN.mo';
            if (file_exists($mo_file)) {
                load_textdomain('webp-radish-webp-optimizer', $mo_file);
                return;
            }
        }

        // 跟随站点语言
        load_plugin_textdomain('webp-radish-webp-optimizer', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function activate() {
        $default_options = array(
            'enabled'           => 1,
            'plugin_lang'       => 'auto',
            'quality'           => 80,
            'delivery_mode'     => 'picture',
            'convert_on_upload' => 1,
            'optimize_original' => 1,
            'orig_quality'      => 82,
            'max_dimension'     => 2560,
            'backup_original'   => 0,
        );

        if (!get_option('radish_webp_settings')) {
            update_option('radish_webp_settings', $default_options);
        }
    }
}

// 启动插件
function radish_webp_init() {
    return Radish_WebP_Master::instance();
}
add_action('plugins_loaded', 'radish_webp_init');
