# 🥕 萝卜 WebP 大师 (Radish WebP Optimizer)

<p align="center">
  <img src="https://img.shields.io/badge/WordPress-5.0+-21759B?style=flat-square&logo=wordpress&logoColor=white" alt="WordPress Version">
  <img src="https://img.shields.io/badge/PHP-7.2+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP Version">
  <img src="https://img.shields.io/badge/License-GPL--2.0-green?style=flat-square" alt="License">
  <img src="https://img.shields.io/badge/Author-求知的萝卜-orange?style=flat-square" alt="Author">
</p>

> **出品人**：微信公众号 **【求知的萝卜】**  
> **定位**：轻量、零外部收费、集「原图深度瘦身 + WebP 终极优化」于一体的 WordPress 高性能图片优化大师。

---

## 🌟 核心特性

- ⚡ **双重优化架构 (Dual-stage Optimization)**
  - **阶段一（原图智能瘦身）**：采用 TinyPNG 同款原理的 **256 色智能色彩量化（Color Quantization）** 与 **4:2:0 色度抽样**，自动剔除不可见的 EXIF/GPS 元数据，原图体积直接骤降 60%~75%，极大拯救服务器硬盘空间！
  - **阶段二（WebP 终极优化）**：基于优化后的原图生成极速轻量的 `.webp` 副本，前台图片体积暴减 80%~95%，大幅提升 Google PageSpeed / Lighthouse 测速评分。
- 🛡️ **原始母本安全备份与一键恢复**
  - 可选在瘦身前保留原始备份文件（`.radish_bak`）。若对画质不满意，可在媒体库随时**一键【恢复最初原图】**。
- 🖥️ **全屏宽屏大模态弹窗工作台 (Modal UI)**
  - 告别狭窄拥挤边栏，一屏全景浏览全站媒体库；
  - 彻底杜绝横向滚动条，内容自适应一览无余；
  - 高清缩略图支持 **点击弹出大图灯箱（Lightbox）** 细致查验画质；
  - 提供【全选】、【取消全选】、【仅选未优化图片】等快捷批量筛选与异步队列处理。
- 🎨 **媒体库列表全景数据链**
  - 列表视图直观展示：`原始大小` $\rightarrow$ `① 原图瘦身` $\rightarrow$ `② WebP 二次优化` 及总瘦身比例；
  - 单图支持独立的【⚡ 单独优化】与【↩️ 恢复原图】操作。
- 🚀 **HTML5 `<picture>` 智能优雅降级**
  - 现代浏览器加载极速 WebP，不支持的老旧设备或特定爬虫自动回退加载原图，100% 杜绝破图风险。
- 💰 **零第三方 API 费用**
  - 优先调用服务器底层 `ImageMagick` 驱动，向下兼容 `GD` 扩展，无限张数免费转换。

---

## 📦 安装与使用指南

### 方法一：上传 ZIP 压缩包安装（推荐）
1. 在 GitHub Releases 或本项目根目录下载 `radish-webp-optimizer.zip`；
2. 登录 WordPress 管理后台，进入 **【插件】 $\rightarrow$ 【安装插件】 $\rightarrow$ 【上传插件】**；
3. 选择 zip 文件上传并点击 **【现在安装】**，安装完成后点击 **【启用插件】**。

### 方法二：Git Clone 部署
将本项目克隆至 WordPress 的 `wp-content/plugins/` 目录：
```bash
cd wp-content/plugins/
git clone https://github.com/你的GitHub用户名/radish-webp-optimizer.git
```
进入 WordPress 后台在【已安装的插件】中点击启用。

---

## ⚙️ 配置推荐

进入 WordPress 后台 **【设置】 $\rightarrow$ 【🥕 萝卜 WebP 大师】**：
- **WebP 压缩质量**：推荐设置为 `80%`（视觉无损黄金平衡点）；
- **原图压缩质量**：推荐设置为 `85%`；
- **原图最大分辨率限制**：默认 `2560px`（自动等比约束相机 4K/6K 巨幅原片）；
- **输出模式**：保持默认的 `HTML5 <picture> 模式`。

---

## 🔧 系统运行环境要求

- **WordPress 版本**：5.0 或更高版本
- **PHP 版本**：7.2 或更高版本（推荐 PHP 8.0+）
- **PHP 图像扩展**：
  - `ImageMagick`（`imagick` 扩展且支持 WebP，强烈推荐）
  - 或 `GD` 扩展（内置 `imagewebp` 支持）

---

## 📄 开源许可证

本项目基于 [GPL v2.0](LICENSE) 协议开源。

---

## 关注作者

欢迎关注微信公众号：**【求知的萝卜】**  
第一时间获取更多极客工具、技术折腾与效率干货！
