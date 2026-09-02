/**
 * 萝卜 WebP 大师 (Radish WebP Optimizer)
 * 媒体库扫描、全屏大模态弹窗、无左右滑动一屏全显、大图点击放大预览灯箱与异步批量优化
 */
jQuery(document).ready(function ($) {
    var scannedItems = [];
    var queue = [];
    var totalItems = 0;
    var processedCount = 0;

    var $modal = $('#radish-bulk-modal');
    var $btnOpenModal = $('#btn-open-modal');
    var $btnCloseModal = $('#btn-close-modal');
    var $btnRescan = $('#btn-modal-rescan');

    var $table = $('#radish-modal-table');
    var $tbody = $('#scanned-media-tbody');
    var $loadingState = $('#modal-loading-state');
    var $selectedCount = $('#selected-count');
    var $btnSelectedCount = $('#btn-selected-count');
    var $totalCount = $('#total-scanned-count');
    var $thSelectAll = $('#th-select-all');

    var $btnStart = $('#btn-start-bulk');
    var $progressContainer = $('#bulk-progress-container');
    var $progressBar = $('#bulk-progress-bar');
    var $progressText = $('#bulk-progress-text');
    var $progressPercent = $('#bulk-progress-percent');

    // 灯箱 DOM
    var $lightbox = $('#radish-lightbox-modal');
    var $lightboxImg = $('#radish-lightbox-img');
    var $lightboxCaption = $('#radish-lightbox-caption');
    var $btnCloseLightbox = $('#btn-close-lightbox');

    // -------------------------------------------------------------
    // 1. 批量大弹窗打开与关闭
    // -------------------------------------------------------------
    $btnOpenModal.on('click', function () {
        $modal.addClass('active');
        $('body').css('overflow', 'hidden');
        if (scannedItems.length === 0) {
            triggerScan();
        }
    });

    $btnCloseModal.on('click', function () {
        closeModal();
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            if ($lightbox.hasClass('active')) {
                closeLightbox();
            } else if ($modal.hasClass('active')) {
                closeModal();
            }
        }
    });

    $modal.on('click', function (e) {
        if ($(e.target).hasClass('visil-modal-overlay')) {
            closeModal();
        }
    });

    function closeModal() {
        $modal.removeClass('active');
        $('body').css('overflow', '');
    }

    $btnRescan.on('click', function () {
        triggerScan();
    });

    // -------------------------------------------------------------
    // 2. 点击缩略图弹出放大灯箱 (Lightbox)
    // -------------------------------------------------------------
    $(document).on('click', '.visil-thumb-img', function (e) {
        e.stopPropagation();
        var fullUrl = $(this).data('full-url') || $(this).attr('src');
        var title = $(this).data('title') || '图片预览';

        if (fullUrl) {
            $lightboxImg.attr('src', fullUrl);
            $lightboxCaption.text(title);
            $lightbox.addClass('active');
        }
    });

    $btnCloseLightbox.on('click', function (e) {
        e.stopPropagation();
        closeLightbox();
    });

    $lightbox.on('click', function (e) {
        if ($(e.target).is('#radish-lightbox-modal') || $(e.target).is('.radish-lightbox-container') || $(e.target).is('.radish-lightbox-img-wrap')) {
            closeLightbox();
        }
    });

    function closeLightbox() {
        $lightbox.removeClass('active');
        $lightboxImg.attr('src', '');
    }

    // -------------------------------------------------------------
    // 3. 扫描媒体库
    // -------------------------------------------------------------
    function triggerScan() {
        $loadingState.show();
        $table.hide();
        $tbody.empty();
        $btnStart.prop('disabled', true);

        $.post(radishWebpData.ajaxUrl, {
            action: 'radish_webp_scan_detailed_media',
            nonce: radishWebpData.nonce
        }, function (res) {
            $loadingState.hide();
            $table.show();

            if (res.success && res.data.items) {
                scannedItems = res.data.items;
                $totalCount.text(res.data.total);
                renderTable(scannedItems);
            } else {
                alert(res.data.message || '扫描失败');
            }
        }).fail(function () {
            $loadingState.hide();
            $table.show();
            alert('扫描请求失败，请检查网络');
        });
    }

    function renderTable(items) {
        $tbody.empty();
        if (items.length === 0) {
            $tbody.html('<tr><td colspan="6" style="text-align:center; padding:40px; color:#a7aaad; font-size:15px;">未扫描到 JPG/PNG 格式图片</td></tr>');
            updateSelectedCount();
            return;
        }

        items.forEach(function (item) {
            var badgeHtml = item.has_webp 
                ? '<span class="badge badge-success" style="font-size:11px;">🟢 已生成</span>' 
                : '<span class="badge badge-danger" style="font-size:11px;">⚪ 未生成</span>';

            var origHtml = '<strong>' + item.orig_size + '</strong>';
            var webpHtml = item.has_webp && item.webp_size 
                ? '<strong style="color:#008a20;">' + item.webp_size + '</strong>' 
                : '<span style="color:#a7aaad;">—</span>';

            var previewUrl = item.full_img || item.thumb;
            var thumbImg = item.thumb 
                ? '<img src="' + item.thumb + '" data-full-url="' + previewUrl + '" data-title="' + item.title + '" class="visil-thumb-img" title="🔍 点击弹出放大预览">' 
                : '<span style="font-size:24px;">🖼️</span>';

            var tr = '<tr id="scan-row-' + item.id + '" data-has-webp="' + (item.has_webp ? '1' : '0') + '">' +
                '<td style="text-align:center;"><input type="checkbox" class="media-check-item" value="' + item.id + '" checked></td>' +
                '<td style="text-align:center;">' + thumbImg + '</td>' +
                '<td><strong style="color:#1d2327; font-size:13px; line-height:1.4;">' + item.title + '</strong><br><small style="color:#8c8f94;">上传日期: ' + item.date + ' (ID: ' + item.id + ')</small></td>' +
                '<td id="orig-size-' + item.id + '">' + origHtml + '</td>' +
                '<td id="webp-size-' + item.id + '">' + webpHtml + '</td>' +
                '<td id="status-cell-' + item.id + '" style="text-align:center;">' + badgeHtml + '</td>' +
            '</tr>';

            $tbody.append(tr);
        });

        updateSelectedCount();
    }

    function updateSelectedCount() {
        var count = $('.media-check-item:checked').length;
        $selectedCount.text(count);
        $btnSelectedCount.text(count);
        $thSelectAll.prop('checked', count > 0 && count === $('.media-check-item').length);
        $btnStart.prop('disabled', count === 0);
    }

    // -------------------------------------------------------------
    // 4. 勾选与筛选联动
    // -------------------------------------------------------------
    $thSelectAll.on('change', function () {
        var checked = $(this).is(':checked');
        $('.media-check-item').prop('checked', checked);
        updateSelectedCount();
    });

    $(document).on('change', '.media-check-item', function () {
        updateSelectedCount();
    });

    $('#btn-select-all').on('click', function () {
        $('.media-check-item').prop('checked', true);
        updateSelectedCount();
    });

    $('#btn-select-none').on('click', function () {
        $('.media-check-item').prop('checked', false);
        updateSelectedCount();
    });

    $('#btn-select-unconverted').on('click', function () {
        $('.media-check-item').each(function () {
            var $row = $(this).closest('tr');
            var hasWebp = $row.data('has-webp') === 1 || $row.data('has-webp') === '1';
            $(this).prop('checked', !hasWebp);
        });
        updateSelectedCount();
    });

    // -------------------------------------------------------------
    // 5. 执行大窗口批量处理
    // -------------------------------------------------------------
    $btnStart.on('click', function () {
        queue = [];
        $('.media-check-item:checked').each(function () {
            queue.push(parseInt($(this).val()));
        });

        if (queue.length === 0) {
            alert('请至少勾选一张图片！');
            return;
        }

        var doOptOrig = $('#bulk_opt_orig').is(':checked') ? 1 : 0;
        var doGenWebp = $('#bulk_gen_webp').is(':checked') ? 1 : 0;

        if (!doOptOrig && !doGenWebp) {
            alert('请至少勾选一个处理项目（原图瘦身 或 WebP生成）！');
            return;
        }

        totalItems = queue.length;
        processedCount = 0;

        $btnStart.prop('disabled', true).text('正在批量优化中...');
        $progressContainer.show();
        $progressBar.css('width', '0%');
        $progressPercent.text('0%');
        $progressText.text('正在处理选中的 ' + totalItems + ' 张图片...');

        processNext();
    });

    function processNext() {
        if (queue.length === 0) {
            $progressBar.css('width', '100%');
            $progressPercent.text('100%');
            $progressText.text('🎉 选中的 ' + totalItems + ' 张图片全部优化完成！');
            $btnStart.prop('disabled', false).text('重新优化选中的图片');
            return;
        }

        var currentId = queue.shift();
        processedCount++;

        var percent = Math.round((processedCount / totalItems) * 100);
        $progressBar.css('width', percent + '%');
        $progressPercent.text(percent + '%');
        $progressText.text('正在处理第 ' + processedCount + ' / ' + totalItems + ' 张 (ID: ' + currentId + ')');

        var $statusCell = $('#status-cell-' + currentId);
        var $origCell = $('#orig-size-' + currentId);
        var $webpCell = $('#webp-size-' + currentId);
        $statusCell.html('<span style="color:#2271b1; font-weight:600; font-size:12px;">⏳ 优化中...</span>');

        var doOptOrig = $('#bulk_opt_orig').is(':checked') ? 1 : 0;
        var doGenWebp = $('#bulk_gen_webp').is(':checked') ? 1 : 0;

        $.post(radishWebpData.ajaxUrl, {
            action: 'radish_webp_convert_single_attachment',
            nonce: radishWebpData.nonce,
            attachment_id: currentId,
            do_opt_orig: doOptOrig,
            do_gen_webp: doGenWebp,
            skip_exists: 0
        }, function (res) {
            if (res.success) {
                $statusCell.html('<span class="badge badge-success" style="font-size:11px;">✔ 已完成</span>');
                if (res.data.new_orig_size) {
                    $origCell.html('<strong>' + res.data.new_orig_size + '</strong>');
                }
                if (res.data.new_webp_size) {
                    $webpCell.html('<strong style="color:#008a20;">' + res.data.new_webp_size + '</strong>');
                }
                $('#scan-row-' + currentId).attr('data-has-webp', '1');
            } else {
                $statusCell.html('<span class="badge badge-danger" style="font-size:11px;">✖ 失败</span>');
            }
            processNext();
        }).fail(function () {
            $statusCell.html('<span class="badge badge-danger" style="font-size:11px;">✖ 超时</span>');
            processNext();
        });
    }

    // -------------------------------------------------------------
    // 6. 媒体库列表页单图单独优化与恢复
    // -------------------------------------------------------------
    $(document).on('click', '.btn-radish-single-opt', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var attachmentId = $btn.data('id');
        if (!attachmentId || $btn.prop('disabled')) return;

        var oldText = $btn.text();
        $btn.prop('disabled', true).text(radishWebpData.strings.optimizing || '正在优化...');

        $.post(radishWebpData.ajaxUrl, {
            action: 'radish_webp_optimize_single_action',
            nonce: radishWebpData.nonce,
            attachment_id: attachmentId
        }, function (res) {
            if (res.success) {
                $btn.text('✔ 优化成功');
                setTimeout(function () {
                    location.reload();
                }, 800);
            } else {
                alert(res.data.message || '优化失败');
                $btn.prop('disabled', false).text(oldText);
            }
        }).fail(function () {
            alert('请求失败，请检查网络');
            $btn.prop('disabled', false).text(oldText);
        });
    });

    $(document).on('click', '.btn-radish-single-restore', function (e) {
        e.preventDefault();
        if (!confirm('确定要恢复该图片的最初原始文件吗？')) return;

        var $btn = $(this);
        var attachmentId = $btn.data('id');
        if (!attachmentId || $btn.prop('disabled')) return;

        var oldText = $btn.text();
        $btn.prop('disabled', true).text(radishWebpData.strings.restoring || '正在恢复...');

        $.post(radishWebpData.ajaxUrl, {
            action: 'radish_webp_restore_single_action',
            nonce: radishWebpData.nonce,
            attachment_id: attachmentId
        }, function (res) {
            if (res.success) {
                $btn.text('✔ 恢复成功');
                setTimeout(function () {
                    location.reload();
                }, 800);
            } else {
                alert(res.data.message || '恢复失败');
                $btn.prop('disabled', false).text(oldText);
            }
        }).fail(function () {
            alert('请求失败，请检查网络');
            $btn.prop('disabled', false).text(oldText);
        });
    });
});
