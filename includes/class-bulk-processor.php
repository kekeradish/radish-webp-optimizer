<?php
/**
 * 历史图片扫描、列表化勾选与批量转换 AJAX 处理器 (萝卜 WebP 大师)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Radish_WebP_Bulk_Processor {

    public function __construct() {
        add_action('wp_ajax_radish_webp_scan_detailed_media', array($this, 'ajax_scan_detailed_media'));
        add_action('wp_ajax_radish_webp_convert_single_attachment', array($this, 'ajax_convert_single_attachment'));
    }

    /**
     * 深度扫描媒体库，返回用于前端大表格勾选的完整图片清单
     */
    public function ajax_scan_detailed_media() {
        if (!check_ajax_referer('radish_webp_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed (Nonce expired). Please refresh page.'));
        }

        if (!current_user_can('manage_options') && !current_user_can('upload_files')) {
            wp_send_json_error(array('message' => radish_t('Permission denied')));
        }

        @ini_set('memory_limit', '512M');
        @set_time_limit(120);

        $query_args = array(
            'post_type'      => 'attachment',
            'post_mime_type' => array('image/jpeg', 'image/png'),
            'post_status'    => array('inherit', 'publish', 'private'),
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        $attachments = get_posts($query_args);
        $upload_dir = wp_upload_dir();
        $items = array();

        $base_url_root = $upload_dir['baseurl'];
        $base_dir_root = $upload_dir['basedir'];

        foreach ($attachments as $post) {
            $meta = wp_get_attachment_metadata($post->ID);
            if (empty($meta) || empty($meta['file'])) {
                continue;
            }

            $orig_path = path_join($base_dir_root, $meta['file']);
            if (!file_exists($orig_path)) {
                continue;
            }

            $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $orig_path);
            $has_webp = file_exists($webp_path);

            $current_orig_size = filesize($orig_path);
            $initial_size = get_post_meta($post->ID, '_radish_initial_size', true);
            if (!$initial_size || $initial_size < $current_orig_size) {
                $initial_size = $current_orig_size;
            }

            $webp_size = $has_webp ? filesize($webp_path) : 0;
            
            $file_dir = dirname($meta['file']);
            $file_dir_url = ($file_dir === '.' || empty($file_dir)) ? $base_url_root : $base_url_root . '/' . $file_dir;
            $full_img_url = $base_url_root . '/' . $meta['file'];
            $thumb_url = $full_img_url;

            if (!empty($meta['sizes']['thumbnail']['file'])) {
                $thumb_url = $file_dir_url . '/' . $meta['sizes']['thumbnail']['file'];
            } elseif (!empty($meta['sizes']['medium']['file'])) {
                $thumb_url = $file_dir_url . '/' . $meta['sizes']['medium']['file'];
            }

            $orig_saved_pct = 0;
            if ($initial_size > $current_orig_size) {
                $orig_saved_pct = round((($initial_size - $current_orig_size) / $initial_size) * 100);
            }

            $items[] = array(
                'id'                 => $post->ID,
                'title'              => basename($meta['file']),
                'thumb'              => $thumb_url,
                'full_img'           => $full_img_url,
                'initial_size_str'   => size_format($initial_size, 1),
                'current_orig_str'   => size_format($current_orig_size, 1),
                'orig_saved_pct'     => $orig_saved_pct,
                'webp_size'          => $has_webp ? size_format($webp_size, 1) : '',
                'has_webp'           => $has_webp,
                'date'               => get_the_date('Y/m/d', $post->ID),
            );
        }

        wp_send_json_success(array(
            'total' => count($items),
            'items' => $items,
        ));
    }

    /**
     * 处理单个附件转换
     */
    public function ajax_convert_single_attachment() {
        check_ajax_referer('radish_webp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => radish_t('Permission denied')));
        }

        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        if (!$attachment_id) {
            wp_send_json_error(array('message' => radish_t('Invalid attachment ID')));
        }

        $do_opt_orig  = isset($_POST['do_opt_orig']) ? intval($_POST['do_opt_orig']) : 1;
        $do_gen_webp  = isset($_POST['do_gen_webp']) ? intval($_POST['do_gen_webp']) : 1;
        $skip_exists  = isset($_POST['skip_exists']) ? intval($_POST['skip_exists']) : 0;

        $metadata = wp_get_attachment_metadata($attachment_id);
        if (empty($metadata)) {
            wp_send_json_error(array('message' => radish_t('No metadata found')));
        }

        $engine = new Radish_WebP_Engine();
        $settings = get_option('radish_webp_settings', array());
        $quality = isset($settings['quality']) ? intval($settings['quality']) : 80;
        $orig_quality = isset($settings['orig_quality']) ? intval($settings['orig_quality']) : 82;
        $max_dimension = isset($settings['max_dimension']) ? intval($settings['max_dimension']) : 2560;
        $backup = !empty($settings['backup_original']);

        $upload_dir = wp_upload_dir();
        $converted_count = 0;
        $opt_orig_done = false;
        $initial_size = 0;

        if (!empty($metadata['file'])) {
            $original_path = path_join($upload_dir['basedir'], $metadata['file']);
            $webp_original = preg_replace('/\.(jpe?g|png)$/i', '.webp', $original_path);

            if (file_exists($original_path)) {
                $initial_size = get_post_meta($attachment_id, '_radish_initial_size', true);
                if (!$initial_size) {
                    $initial_size = filesize($original_path);
                    update_post_meta($attachment_id, '_radish_initial_size', $initial_size);
                }

                if ($skip_exists && file_exists($webp_original) && !$do_opt_orig) {
                    wp_send_json_success(array(
                        'attachment_id'   => $attachment_id,
                        'skipped'         => true,
                        'message'         => radish_t('WebP exists, skipped')
                    ));
                }

                // 1. 原图瘦身
                if ($do_opt_orig) {
                    $engine->optimize_original_file($original_path, $orig_quality, $max_dimension, $backup);
                    update_post_meta($attachment_id, '_radish_optimized_orig_size', filesize($original_path));
                    $opt_orig_done = true;
                }

                // 2. 主图 WebP 转换
                if ($do_gen_webp) {
                    $res = $engine->convert_file_to_webp($original_path, $quality);
                    if ($res) {
                        $converted_count++;
                    }
                }
            }

            // 3. 处理所有尺寸缩略图
            if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
                $base_dir = dirname($original_path);
                foreach ($metadata['sizes'] as $size_info) {
                    if (!empty($size_info['file'])) {
                        $thumb_path = path_join($base_dir, $size_info['file']);
                        if (file_exists($thumb_path)) {
                            if ($do_opt_orig) {
                                $engine->optimize_original_file($thumb_path, $orig_quality, 0, false);
                            }
                            if ($do_gen_webp) {
                                $res_t = $engine->convert_file_to_webp($thumb_path, $quality);
                                if ($res_t) {
                                    $converted_count++;
                                }
                            }
                        }
                    }
                }
            }

            $current_filesize = file_exists($original_path) ? filesize($original_path) : 0;
            $new_orig_size_str = size_format($current_filesize, 1);
            $initial_size_str = size_format($initial_size > 0 ? $initial_size : $current_filesize, 1);
            $new_webp_size_str = file_exists($webp_original) ? size_format(filesize($webp_original), 1) : '';

            $orig_saved_pct = 0;
            if ($initial_size > $current_filesize) {
                $orig_saved_pct = round((($initial_size - $current_filesize) / $initial_size) * 100);
            }

            wp_send_json_success(array(
                'attachment_id'    => $attachment_id,
                'opt_orig_done'    => $opt_orig_done,
                'converted_count'  => $converted_count,
                'initial_orig_str' => $initial_size_str,
                'new_orig_size'    => $new_orig_size_str,
                'orig_saved_pct'   => $orig_saved_pct,
                'new_webp_size'    => $new_webp_size_str,
                'message'          => radish_t('Processed successfully')
            ));
        }

        wp_send_json_error(array('message' => radish_t('Invalid file path')));
    }
}
