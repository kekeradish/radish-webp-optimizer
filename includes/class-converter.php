<?php
/**
 * 核心 WebP 转换、原图二次优化与原始备份恢复引擎 (萝卜 WebP 大师)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Radish_WebP_Engine {

    private $settings;

    public function __construct() {
        $this->settings = get_option('radish_webp_settings', array(
            'enabled'           => 1,
            'quality'           => 80,
            'convert_on_upload' => 1,
            'optimize_original' => 1,
            'orig_quality'      => 85,
            'max_dimension'     => 2560,
            'backup_original'   => 0,
        ));

        if (!empty($this->settings['enabled']) && !empty($this->settings['convert_on_upload'])) {
            add_filter('wp_generate_attachment_metadata', array($this, 'handle_attachment_upload'), 10, 2);
        }

        // 监听附件删除事件，清理 WebP 和备份文件
        add_action('delete_attachment', array($this, 'handle_delete_attachment'));
    }

    /**
     * 检测服务器支持的转换驱动
     */
    public static function get_available_driver() {
        if (extension_loaded('imagick') && class_exists('Imagick')) {
            try {
                $imagick = new Imagick();
                $formats = $imagick->queryFormats('WEBP');
                if (!empty($formats)) {
                    return 'imagick';
                }
            } catch (Exception $e) {
                // 降级
            }
        }

        if (extension_loaded('gd') && function_exists('imagewebp')) {
            return 'gd';
        }

        return 'none';
    }

    /**
     * 上传图片时进行双重优化
     */
    public function handle_attachment_upload($metadata, $attachment_id) {
        $mime_type = get_post_mime_type($attachment_id);
        $allowed_mimes = array('image/jpeg', 'image/png', 'image/jpg');

        if (!in_array($mime_type, $allowed_mimes, true)) {
            return $metadata;
        }

        $upload_dir = wp_upload_dir();
        $quality = isset($this->settings['quality']) ? intval($this->settings['quality']) : 80;
        $orig_quality = isset($this->settings['orig_quality']) ? intval($this->settings['orig_quality']) : 85;
        $max_dimension = isset($this->settings['max_dimension']) ? intval($this->settings['max_dimension']) : 2560;
        $backup = !empty($this->settings['backup_original']);

        if (isset($metadata['file'])) {
            $original_path = path_join($upload_dir['basedir'], $metadata['file']);
            if (file_exists($original_path)) {
                $initial_size = get_post_meta($attachment_id, '_radish_initial_size', true);
                if (!$initial_size) {
                    $initial_size = filesize($original_path);
                    update_post_meta($attachment_id, '_radish_initial_size', $initial_size);
                }

                // 阶段一：原图瘦身
                if (!empty($this->settings['optimize_original'])) {
                    $this->optimize_original_file($original_path, $orig_quality, $max_dimension, $backup);
                    update_post_meta($attachment_id, '_radish_optimized_orig_size', filesize($original_path));
                }

                // 阶段二：生成 WebP
                $this->convert_file_to_webp($original_path, $quality);
            }
        }

        // 缩略图处理
        if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            $base_dir = dirname($original_path);
            foreach ($metadata['sizes'] as $size_info) {
                if (!empty($size_info['file'])) {
                    $thumb_path = path_join($base_dir, $size_info['file']);
                    if (file_exists($thumb_path)) {
                        if (!empty($this->settings['optimize_original'])) {
                            $this->optimize_original_file($thumb_path, $orig_quality, 0, false);
                        }
                        $this->convert_file_to_webp($thumb_path, $quality);
                    }
                }
            }
        }

        return $metadata;
    }

    /**
     * 原图瘦身优化
     */
    public function optimize_original_file($file_path, $quality = 82, $max_dimension = 2560, $backup = false) {
        if (!file_exists($file_path) || !is_writable($file_path)) {
            return false;
        }

        $backup_path = $file_path . '.radish_bak';
        if ($backup && !file_exists($backup_path)) {
            @copy($file_path, $backup_path);
        }

        $driver = self::get_available_driver();

        if ($driver === 'imagick') {
            try {
                $image = new Imagick($file_path);

                // 纠正 EXIF 旋转
                $orientation = $image->getImageOrientation();
                switch ($orientation) {
                    case Imagick::ORIENTATION_BOTTOMRIGHT:
                        $image->rotateImage(new ImagickPixel('none'), 180);
                        break;
                    case Imagick::ORIENTATION_RIGHTTOP:
                        $image->rotateImage(new ImagickPixel('none'), 90);
                        break;
                    case Imagick::ORIENTATION_LEFTBOTTOM:
                        $image->rotateImage(new ImagickPixel('none'), -90);
                        break;
                }
                $image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);

                // 尺寸限制
                if ($max_dimension > 0) {
                    $w = $image->getImageWidth();
                    $h = $image->getImageHeight();
                    if ($w > $max_dimension || $h > $max_dimension) {
                        $image->resizeImage($max_dimension, $max_dimension, Imagick::FILTER_LANCZOS, 1, true);
                    }
                }

                $format = strtoupper($image->getImageFormat());
                if ($format === 'JPEG' || $format === 'JPG') {
                    // JPEG 深度优化：4:2:0 色度抽样 + 渐进式
                    $image->setImageCompression(Imagick::COMPRESSION_JPEG);
                    $image->setImageCompressionQuality($quality);
                    $image->setSamplingFactors(array('2x2', '1x1', '1x1'));
                    $image->setInterlaceScheme(Imagick::INTERLACE_PLANE);
                } elseif ($format === 'PNG') {
                    // PNG 深度优化（TinyPNG 同款原理）：256 智能色彩量化 + 抖动 + 保留透明通道
                    if ($image->getImageAlphaChannel()) {
                        $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
                    }
                    // 智能色彩量化为 256 索引色，带误差扩散抖动
                    $image->quantizeImage(256, Imagick::COLORSPACE_SRGB, 0, true, false);
                    $image->setImageFormat('PNG8');
                    $image->setImageCompressionQuality(95);
                }

                // 彻底剥离元数据
                $image->stripImage();
                $image->writeImage($file_path);
                $image->clear();
                $image->destroy();
                return true;
            } catch (Exception $e) {
                // 出错降级使用 GD
            }
        }

        if ($driver === 'gd') {
            $info = @getimagesize($file_path);
            if (!$info) return false;

            $mime = $info['mime'];
            $w = $info[0];
            $h = $info[1];

            if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
                $src = @imagecreatefromjpeg($file_path);
                if ($src) {
                    if ($max_dimension > 0 && ($w > $max_dimension || $h > $max_dimension)) {
                        $ratio = min($max_dimension / $w, $max_dimension / $h);
                        $nw = round($w * $ratio);
                        $nh = round($h * $ratio);
                        $dst = imagecreatetruecolor($nw, $nh);
                        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                        imagedestroy($src);
                        $src = $dst;
                    }
                    imagejpeg($src, $file_path, $quality);
                    imagedestroy($src);
                    return true;
                }
            } elseif ($mime === 'image/png') {
                $src = @imagecreatefrompng($file_path);
                if ($src) {
                    if ($max_dimension > 0 && ($w > $max_dimension || $h > $max_dimension)) {
                        $ratio = min($max_dimension / $w, $max_dimension / $h);
                        $nw = round($w * $ratio);
                        $nh = round($h * $ratio);
                        $dst = imagecreatetruecolor($nw, $nh);
                        imagealphablending($dst, false);
                        imagesavealpha($dst, true);
                        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                        imagedestroy($src);
                        $src = $dst;
                    }
                    // GD 智能色彩量化为 256 色
                    imagetruecolortopalette($src, true, 256);
                    imagepng($src, $file_path, 9);
                    imagedestroy($src);
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 恢复原始图
     */
    public function restore_original_file($attachment_id) {
        $meta = wp_get_attachment_metadata($attachment_id);
        if (empty($meta) || empty($meta['file'])) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        $original_path = path_join($upload_dir['basedir'], $meta['file']);
        $backup_path = $original_path . '.radish_bak';

        if (file_exists($backup_path)) {
            @copy($backup_path, $original_path);
            $new_size = filesize($original_path);
            update_post_meta($attachment_id, '_radish_initial_size', $new_size);
            update_post_meta($attachment_id, '_radish_optimized_orig_size', $new_size);
            return true;
        }

        return false;
    }

    /**
     * 单图优化
     */
    public function optimize_single_attachment($attachment_id) {
        $meta = wp_get_attachment_metadata($attachment_id);
        if (empty($meta) || empty($meta['file'])) {
            return false;
        }

        $settings = get_option('radish_webp_settings', array());
        $quality = isset($settings['quality']) ? intval($settings['quality']) : 80;
        $orig_quality = isset($settings['orig_quality']) ? intval($settings['orig_quality']) : 85;
        $max_dimension = isset($settings['max_dimension']) ? intval($settings['max_dimension']) : 2560;
        $backup = !empty($settings['backup_original']);

        $upload_dir = wp_upload_dir();
        $original_path = path_join($upload_dir['basedir'], $meta['file']);

        if (!file_exists($original_path)) {
            return false;
        }

        $initial_size = get_post_meta($attachment_id, '_radish_initial_size', true);
        if (!$initial_size) {
            $initial_size = filesize($original_path);
            update_post_meta($attachment_id, '_radish_initial_size', $initial_size);
        }

        $this->optimize_original_file($original_path, $orig_quality, $max_dimension, $backup);
        update_post_meta($attachment_id, '_radish_optimized_orig_size', filesize($original_path));

        $this->convert_file_to_webp($original_path, $quality);

        if (!empty($meta['sizes']) && is_array($meta['sizes'])) {
            $base_dir = dirname($original_path);
            foreach ($meta['sizes'] as $size_info) {
                if (!empty($size_info['file'])) {
                    $thumb_path = path_join($base_dir, $size_info['file']);
                    if (file_exists($thumb_path)) {
                        $this->optimize_original_file($thumb_path, $orig_quality, 0, false);
                        $this->convert_file_to_webp($thumb_path, $quality);
                    }
                }
            }
        }

        return true;
    }

    /**
     * 生成 WebP
     */
    public function convert_file_to_webp($source_path, $quality = 80) {
        if (!file_exists($source_path) || !is_readable($source_path)) {
            return false;
        }

        $target_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $source_path);
        
        $driver = self::get_available_driver();

        if ($driver === 'imagick') {
            return $this->convert_with_imagick($source_path, $target_path, $quality);
        } elseif ($driver === 'gd') {
            return $this->convert_with_gd($source_path, $target_path, $quality);
        }

        return false;
    }

    private function convert_with_imagick($source_path, $target_path, $quality) {
        try {
            $image = new Imagick($source_path);

            $orientation = $image->getImageOrientation();
            switch ($orientation) {
                case Imagick::ORIENTATION_BOTTOMRIGHT:
                    $image->rotateImage(new ImagickPixel('none'), 180);
                    break;
                case Imagick::ORIENTATION_RIGHTTOP:
                    $image->rotateImage(new ImagickPixel('none'), 90);
                    break;
                case Imagick::ORIENTATION_LEFTBOTTOM:
                    $image->rotateImage(new ImagickPixel('none'), -90);
                    break;
            }
            $image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);

            $image->setImageFormat('WEBP');
            $image->setImageCompressionQuality($quality);

            if ($image->getImageAlphaChannel()) {
                $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
                $image->setBackgroundColor(new ImagickPixel('transparent'));
            }

            $image->stripImage();
            $success = $image->writeImage($target_path);
            $image->clear();
            $image->destroy();

            return $success ? $target_path : false;
        } catch (Exception $e) {
            error_log('Radish WebP Master Imagick Error: ' . $e->getMessage());
            return $this->convert_with_gd($source_path, $target_path, $quality);
        }
    }

    private function convert_with_gd($source_path, $target_path, $quality) {
        $info = @getimagesize($source_path);
        if (!$info) return false;

        $mime = $info['mime'];
        $image = null;

        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                $image = @imagecreatefromjpeg($source_path);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($source_path);
                if ($image) {
                    imagepalettetotruecolor($image);
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                }
                break;
            default:
                return false;
        }

        if (!$image) return false;

        $success = @imagewebp($image, $target_path, $quality);
        imagedestroy($image);

        return $success ? $target_path : false;
    }

    public function handle_delete_attachment($post_id) {
        $metadata = wp_get_attachment_metadata($post_id);
        if (empty($metadata)) return;

        $upload_dir = wp_upload_dir();

        if (!empty($metadata['file'])) {
            $original_path = path_join($upload_dir['basedir'], $metadata['file']);
            $webp_original = preg_replace('/\.(jpe?g|png)$/i', '.webp', $original_path);
            $bak_original  = $original_path . '.radish_bak';

            if (file_exists($webp_original)) @unlink($webp_original);
            if (file_exists($bak_original)) @unlink($bak_original);

            if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
                $base_dir = dirname($original_path);
                foreach ($metadata['sizes'] as $size_info) {
                    if (!empty($size_info['file'])) {
                        $thumb_path = path_join($base_dir, $size_info['file']);
                        $webp_thumb = preg_replace('/\.(jpe?g|png)$/i', '.webp', $thumb_path);
                        if (file_exists($webp_thumb)) @unlink($webp_thumb);
                    }
                }
            }
        }
    }
}
