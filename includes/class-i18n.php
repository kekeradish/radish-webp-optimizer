<?php
/**
 * 萝卜 WebP 大师 (Radish WebP Optimizer) 国际化独立翻译引擎
 * 彻底解决 WordPress 核心多语言缓存/Locale 污染导致中英切换失效的问题
 */

if (!defined('ABSPATH')) {
    exit;
}

class Radish_WebP_I18n {

    private static $translations = array(
        'Radish WebP Optimizer' => '萝卜 WebP 大师',
        '🥕 Radish WebP Optimizer' => '🥕 萝卜 WebP 大师',
        '🥕 Radish WebP' => '🥕 萝卜 WebP',
        'Dual-stage optimization architecture for massive server storage savings and lightning-fast WebP loading.' => '双重图片优化架构：原图智能瘦身 + WebP 终极优化，兼顾服务器磁盘与前台秒开速度。',
        'PHP Version:' => 'PHP 版本:',
        'Driver:' => '图像驱动:',
        'Engine Active' => '引擎运行中',
        'None' => '未检测到支持库',
        'Bulk Optimization Workbench' => '批量图片优化工作台',
        'Scan and filter all media library images in an independent large modal dialog with large preview, selection, and dual-optimization.' => '以独立大窗口形式直观扫描、筛选全站图片，支持高清大图预览、自定义勾选与实时双重优化。',
        'Open Bulk Optimization Workbench' => '打开批量优化管理大窗口',
        'No Horizontal Scroll' => '全屏宽阔视野',
        'Lightbox Large Preview' => '大图灯箱预览',
        'Batch Selection' => '精准批量选择',
        'Core Optimization Settings' => '双重优化核心配置',
        'Interface Language' => '界面显示语言',
        'Select the display language for the plugin settings and batch workbench.' => '选择插件管理后台、弹窗工作台与操作提示的界面语言。',
        'Auto (Follow Site Language)' => '跟随站点默认语言 (Site Default)',
        'Simplified Chinese' => '简体中文',
        'English' => '英文',
        'Enable Plugin Features' => '启用插件功能',
        'Master switch. Disabling stops frontend replacement and automatic conversion.' => '总开关。关闭后将停止前端替换与自动优化。',
        'Auto Convert on Upload' => '上传新图时自动优化',
        'Automatically optimize original images and generate WebP files upon uploading new JPG/PNG.' => '上传 JPG/PNG 时，自动进行【原图自身瘦身】+【WebP 副本生成】。',
        'Original Slimming & Safe Backup' => '原图瘦身与安全备份',
        'Enable Original Image Slimming Optimization' => '开启原图自身瘦身优化',
        'Strip EXIF metadata and perform smart color quantization on JPG/PNG originals to drastically save server disk space!' => '对原 JPG/PNG 剥离 EXIF 元数据并进行 256 色智能色彩量化与高保真压缩，大幅节省服务器磁盘！',
        'Keep Initial Master Backup (.radish_bak)' => '保留原始母本备份 (安全备份)',
        'Preserve a .radish_bak backup before slimming. Enables one-click restoration in Media Library anytime.' => '瘦身前保留一份 .radish_bak 原始备份文件。开启后可在媒体库随时一键【恢复最初原图】。',
        'Original Image Quality:' => '原图压缩质量:',
        '(Recommended: 82% ~ 85% for high visual fidelity)' => '(建议 82% ~ 85%，确保原图母本高保真)',
        'Max Original Resolution Dimension:' => '原图最大分辨率限制:',
        'WebP Conversion Quality' => 'WebP 二次终极优化质量',
        'WebP quality delivered to website visitors. Recommended: 75% ~ 82% (sweet spot for size & quality).' => '前台访客加载的 WebP 质量。推荐 75% ~ 82%（肉眼几乎无损且体积最小）。',
        'Frontend Delivery Mode' => '前端输出与降级模式',
        'HTML5 <picture> Mode' => 'HTML5 <picture> 模式',
        'Recommended' => '强烈推荐',
        'Modern browsers load WebP, legacy clients automatically fallback to original images. 100% zero broken images.' => '现代浏览器加载超轻 WebP，老旧客户端自动加载瘦身后的原图，100% 零破图。',
        'Direct URL Replacement Mode' => '直接替换模式',
        'Directly replaces <img src="..."> image URLs with .webp URLs.' => '直接将 <img src="..."> 替换为 WebP 格式 URL。',
        'Save Changes' => '保存配置',
        'Settings' => '设置',
        'Settings & Dashboard' => '核心设置与面板',
        'Dual Optimization & Actions' => '双重优化与单图操作',
        'No metadata' => '未生成元数据',
        'File missing' => '原图丢失',
        '① Original:' => '① 原图:',
        '② WebP:' => '② WebP:',
        '② WebP: Not generated' => '② WebP: 尚未生成',
        'Optimize original image and generate WebP' => '对原图进行自身瘦身并生成 WebP',
        'Optimize' => '单独优化',
        'Restore initial uncompressed original image' => '恢复最初未压缩原图母本',
        'Restore' => '恢复原图',
        'Initial Upload Size:' => '原始上传大小：',
        '① Optimized Original Size:' => '① 原图瘦身后大小：',
        '② WebP Image Size:' => '② WebP 二次优化大小：',
        'Total Savings:' => '终极优化收益：',
        '② WebP Status:' => '② WebP 状态：',
        'WebP copy has not been generated yet' => '尚未生成 WebP 副本',
        'Re-optimize Original + WebP' => '单独重新优化原图+WebP',
        'Restore Initial Image' => '恢复最初原始图',
        'Optimization Info & Actions' => '优化操作与详情',
        'Permission denied' => '权限不足',
        'Invalid attachment ID' => '无效的图片 ID',
        'Optimized successfully!' => '优化成功！',
        'Optimization failed or original image missing' => '优化失败或原图不存在',
        'Restored initial original file successfully!' => '已成功恢复最初原图！',
        'No backup file found for this image' => '未找到该图片的原始备份文件',
        'Start Bulk Optimization' => '开始批量优化选中的图片',
        'Optimizing...' => '正在优化中...',
        'Bulk Process Finished' => '批量优化已完成！',
        'All media library images are fully optimized.' => '所有媒体库图片已全部完成优化。',
        'Total items processed:' => '共计处理附件数：',
        'Total storage saved:' => '累计节省服务器磁盘：',
        'Original Size:' => '原图总大小：',
        'Optimized WebP Size:' => '优化后 WebP 大小：',
        'Close' => '关闭窗口',
        'Close (ESC)' => '关闭窗口 (ESC)',
        'Rescan' => '重新扫描',
        'Select All' => '全选',
        'Deselect All' => '取消全选',
        'Unconverted Only' => '仅选未优化图片',
        'Selected:' => '已勾选:',
        'Thumbnail' => '缩略图',
        'File Name / Upload Date' => '文件名 / 上传日期',
        'Original Size' => '原图体积',
        'WebP Size' => 'WebP 体积',
        'Status' => '当前状态',
        'Image Preview' => '大图预览',
        'Processing:' => '正在处理：',
        'Completed' => '处理完毕',
        'Failed' => '处理失败',
        'Skipped' => '已跳过',
        'Please select at least one image to optimize.' => '请至少勾选一张需要优化的图片！',
        'Please select at least one task (Slim Original or Generate WebP).' => '请至少勾选一项处理项目（原图瘦身 或 生成 WebP）！',
        'Actions:' => '处理项目:',
        'Slim Original' => '原图自身瘦身',
        'Generate WebP' => '生成 WebP 副本',
        'No metadata found' => '未找到图片元数据',
        'WebP exists, skipped' => '已存在 WebP，已跳过',
        'Processed successfully' => '处理完成',
        'Invalid file path' => '无效的文件路径',
    );

    /**
     * 获取当前实际生效的语言代码 ('zh_CN' 或 'en_US')
     */
    public static function get_current_lang() {
        $settings = get_option('radish_webp_settings', array());
        $plugin_lang = !empty($settings['plugin_lang']) ? $settings['plugin_lang'] : 'auto';

        if ($plugin_lang === 'zh_CN') {
            return 'zh_CN';
        }

        if ($plugin_lang === 'en_US') {
            return 'en_US';
        }

        // auto 跟随站点
        $locale = get_locale();
        if (strpos($locale, 'zh') !== false) {
            return 'zh_CN';
        }

        return 'en_US';
    }

    /**
     * 核心翻译方法
     */
    public static function t($text) {
        $lang = self::get_current_lang();
        
        // 如果是英文模式，直接返回英文源文本
        if ($lang === 'en_US') {
            return $text;
        }

        // 如果是中文模式，查字典，找不到则回退到 WordPress __()
        if (isset(self::$translations[$text])) {
            return self::$translations[$text];
        }

        return __($text, 'webp-radish-webp-optimizer');
    }
}

/**
 * 全局极速翻译辅助函数
 */
function radish_t($text) {
    return Radish_WebP_I18n::t($text);
}

function radish_esc_html_t($text) {
    return esc_html(Radish_WebP_I18n::t($text));
}
