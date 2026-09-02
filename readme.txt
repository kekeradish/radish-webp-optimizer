=== 萝卜 WebP 大师 (Radish WebP Optimizer) ===
Contributors: kekeradish
Tags: webp, image optimizer, tinypng, compress images, speed up, performance, core web vitals, seo
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.2
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

专为站长与性能极客打造！集「原图智能量化瘦身 + WebP 终极二次优化 + SEO 极速加速 + 服务器资源暴省」于一体的 WordPress 高性能开源插件。

== Description ==

在现代网站运营中，图片通常占据了整站 70% 以上的带宽与磁盘空间。
「🥕 萝卜 WebP 大师 (Radish WebP Optimizer)」采用全新的本地双重优化架构：

1. **原图智能脱水瘦身**：上传时采用 256 色智能色彩量化（Color Quantization）与 4:2:0 色度抽样，剥离不可见的 EXIF 元数据，原图体积直接骤降 60%~75%，极大节省服务器磁盘！
2. **WebP 终极二次优化**：基于瘦身母本生成超轻 WebP，前台加载体积暴减 80%~95%，大幅改善 Google Core Web Vitals (LCP) 与 SEO 排名。
3. **宽屏大模态工作台**：全景扫描媒体库，无左右横向滑动，大缩略图支持点击弹出高清大图灯箱（Lightbox）预览。
4. **安全母本备份**：可选保留原始备份，在媒体库支持一键【恢复最初原图】。
5. **完整国际化多语言支持**：原生内置中英双语（i18n / l10n），完美适配全球 WordPress 站点。
6. **零外部 API 费用**：100% 本地驱动转换，无限张数免费处理，数据 100% 隐私安全。

== Installation ==

1. 登录 WordPress 管理后台，进入【插件】->【安装插件】->【上传插件】；
2. 选择 `radish-webp-optimizer.zip` 上传并安装启用；
3. 进入【设置】->【🥕 萝卜 WebP 大师】开启使用。

== Frequently Asked Questions ==

= 插件会破坏我的原图画质吗？ =
不会。插件采用感知色彩量化与 4:2:0 抽样，视觉上肉眼几乎无法分辨差异。同时开启安全备份后，可随时一键恢复最初原图。

= 需要购买第三方 API 吗？ =
完全不需要！插件直接调用服务器本地的 ImageMagick 或 GD 扩展，零成本无限次使用。

== Screenshots ==

1. 批量优化全屏大模态工作台与图片灯箱预览
2. 核心设置面板与系统健康检查
3. 媒体库列表双重优化数据展示与单图操作

== Changelog ==

= 1.1.1 =
* 新增官方规范的完整中英双语国际化支持 (i18n / l10n)，内置 POT / PO / MO 语言包。
* 修复 Text Domain 与 WordPress.org 官方 Slug 对齐校验。
* 升级 WordPress 核心兼容性测试支持 (Tested up to 7.1)。

= 1.1.0 =
* 引入 TinyPNG 同款 256 色智能色彩量化算法。
* 上线全屏宽屏大模态弹窗（消除横向滚动条，支持点击弹出大图灯箱）。
* 挂载 wp_handle_upload 实现上传落盘即时自动瘦身。
* 优化 SEO、Core Web Vitals 与服务器资源开销。

= 1.0.0 =
* 初始版本发布。
