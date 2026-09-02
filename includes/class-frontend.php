<?php
/**
 * 前端图片展示与智能替换引擎 (萝卜 WebP 大师)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Radish_WebP_Frontend {

    private $settings;
    private $upload_dir;

    public function __construct() {
        $this->settings = get_option('radish_webp_settings', array(
            'enabled'       => 1,
            'delivery_mode' => 'picture',
        ));

        if (empty($this->settings['enabled'])) {
            return;
        }

        $this->upload_dir = wp_upload_dir();

        if (!is_admin() && !is_feed() && !wp_is_json_request()) {
            add_filter('the_content', array($this, 'filter_content_images'), 99);
            add_filter('post_thumbnail_html', array($this, 'filter_content_images'), 99);
            add_filter('widget_text', array($this, 'filter_content_images'), 99);
        }
    }

    public function filter_content_images($content) {
        if (empty($content) || !is_string($content)) {
            return $content;
        }

        $pattern = '/<img\s+(?![^>]*\bdata-no-webp\b)([^>]+)>/i';
        return preg_replace_callback($pattern, array($this, 'replace_image_callback'), $content);
    }

    private function replace_image_callback($matches) {
        $full_img_tag = $matches[0];
        $attributes_str = $matches[1];

        if (!preg_match('/src=[\'"]([^\'"]+\.(jpe?g|png))[\'"]/i', $attributes_str, $src_match)) {
            return $full_img_tag;
        }

        $img_url = $src_match[1];
        
        if (strpos($img_url, $this->upload_dir['baseurl']) === false) {
            return $full_img_tag;
        }

        $webp_url = preg_replace('/\.(jpe?g|png)(\?.*)?$/i', '.webp$2', $img_url);
        $local_path = str_replace($this->upload_dir['baseurl'], $this->upload_dir['basedir'], $img_url);
        $local_path = preg_replace('/\?.*$/', '', $local_path);
        $webp_local_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $local_path);

        if (!file_exists($webp_local_path)) {
            return $full_img_tag;
        }

        $mode = isset($this->settings['delivery_mode']) ? $this->settings['delivery_mode'] : 'picture';

        // 1. 直接 URL 替换模式
        if ($mode === 'replace') {
            $new_img_tag = str_replace($img_url, $webp_url, $full_img_tag);
            if (preg_match('/srcset=[\'"]([^\'"]+)[\'"]/i', $new_img_tag, $srcset_match)) {
                $new_srcset = preg_replace('/\.(jpe?g|png)/i', '.webp', $srcset_match[1]);
                $new_img_tag = str_replace($srcset_match[0], 'srcset="' . esc_attr($new_srcset) . '"', $new_img_tag);
            }
            return $new_img_tag;
        }

        // 2. HTML5 <picture> 标签降级模式
        $source_srcset = $webp_url;
        if (preg_match('/srcset=[\'"]([^\'"]+)[\'"]/i', $attributes_str, $srcset_match)) {
            $source_srcset = preg_replace('/\.(jpe?g|png)/i', '.webp', $srcset_match[1]);
        }

        $sizes_attr = '';
        if (preg_match('/sizes=[\'"]([^\'"]+)[\'"]/i', $attributes_str, $sizes_match)) {
            $sizes_attr = ' sizes="' . esc_attr($sizes_match[1]) . '"';
        }

        $picture_html  = '<picture class="radish-webp-picture">';
        $picture_html .= '<source type="image/webp" srcset="' . esc_attr($source_srcset) . '"' . $sizes_attr . '>';
        $picture_html .= $full_img_tag;
        $picture_html .= '</picture>';

        return $picture_html;
    }
}
