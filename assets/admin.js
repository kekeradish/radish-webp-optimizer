/**
 * Radish WebP Optimizer (萝卜 WebP 大师) v1.1.1
 * Bilingual (i18n), Modal Workbench, Lightbox & Asynchronous Bulk Optimization
 */
jQuery(document).ready(function ($) {
    var scannedItems = [];
    var queue = [];
    var totalItems = 0;
    var processedCount = 0;

    var s = (typeof radishWebpData !== 'undefined' && radishWebpData.strings) ? radishWebpData.strings : {};

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

    // Lightbox DOM
    var $lightbox = $('#radish-lightbox-modal');
    var $lightboxImg = $('#radish-lightbox-img');
    var $lightboxCaption = $('#radish-lightbox-caption');
    var $btnCloseLightbox = $('#btn-close-lightbox');

    // -------------------------------------------------------------
    // 1. Modal Open / Close
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
    // 2. Click Thumbnail to open Lightbox
    // -------------------------------------------------------------
    $(document).on('click', '.visil-thumb-img', function (e) {
        e.stopPropagation();
        var fullUrl = $(this).data('full-url') || $(this).attr('src');
        var title = $(this).data('title') || s.previewTitle || 'Image Preview';

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
    // 3. Scan Media Library
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
                alert(res.data.message || s.scanFailed || 'Scan failed');
            }
        }).fail(function () {
            $loadingState.hide();
            $table.show();
            alert(s.scanFailed || 'Scan failed, please check network');
        });
    }

    function renderTable(items) {
        $tbody.empty();
        if (items.length === 0) {
            var noImagesText = s.noImages || 'No JPG/PNG images found';
            $tbody.html('<tr><td colspan="6" style="text-align:center; padding:40px; color:#a7aaad; font-size:15px;">' + noImagesText + '</td></tr>');
            updateSelectedCount();
            return;
        }

        var genBadge = s.generated || '🟢 Generated';
        var notGenBadge = s.notGenerated || '⚪ Not Generated';
        var hoverText = s.previewHover || '🔍 Click to view large image preview';
        var datePrefix = s.uploadDate || 'Upload date:';

        items.forEach(function (item) {
            var badgeHtml = item.has_webp 
                ? '<span class="badge badge-success" style="font-size:11px;">' + genBadge + '</span>' 
                : '<span class="badge badge-danger" style="font-size:11px;">' + notGenBadge + '</span>';

            var origHtml = '<strong>' + item.orig_size + '</strong>';
            var webpHtml = item.has_webp && item.webp_size 
                ? '<strong style="color:#008a20;">' + item.webp_size + '</strong>' 
                : '<span style="color:#a7aaad;">—</span>';

            var previewUrl = item.full_img || item.thumb;
            var thumbImg = item.thumb 
                ? '<img src="' + item.thumb + '" data-full-url="' + previewUrl + '" data-title="' + item.title + '" class="visil-thumb-img" title="' + hoverText + '">' 
                : '<span style="font-size:24px;">🖼️</span>';

            var tr = '<tr id="scan-row-' + item.id + '" data-has-webp="' + (item.has_webp ? '1' : '0') + '">' +
                '<td style="text-align:center;"><input type="checkbox" class="media-check-item" value="' + item.id + '" checked></td>' +
                '<td style="text-align:center;">' + thumbImg + '</td>' +
                '<td><strong style="color:#1d2327; font-size:13px; line-height:1.4;">' + item.title + '</strong><br><small style="color:#8c8f94;">' + datePrefix + ' ' + item.date + ' (ID: ' + item.id + ')</small></td>' +
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
    // 4. Checkbox Controls
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
    // 5. Bulk Optimization Execution
    // -------------------------------------------------------------
    $btnStart.on('click', function () {
        queue = [];
        $('.media-check-item:checked').each(function () {
            queue.push(parseInt($(this).val()));
        });

        if (queue.length === 0) {
            alert(s.noSelected || 'Please select at least one image!');
            return;
        }

        var doOptOrig = $('#bulk_opt_orig').is(':checked') ? 1 : 0;
        var doGenWebp = $('#bulk_gen_webp').is(':checked') ? 1 : 0;

        if (!doOptOrig && !doGenWebp) {
            alert(s.noOptionSelected || 'Please select at least one action!');
            return;
        }

        totalItems = queue.length;
        processedCount = 0;

        $btnStart.prop('disabled', true).text(s.processing || 'Optimizing...');
        $progressContainer.show();
        $progressBar.css('width', '0%');
        $progressPercent.text('0%');
        $progressText.text((s.processing || 'Optimizing...') + ' (' + totalItems + ')');

        processNext();
    });

    function processNext() {
        if (queue.length === 0) {
            $progressBar.css('width', '100%');
            $progressPercent.text('100%');
            $progressText.text(s.completed || '🎉 All selected images have been optimized successfully!');
            $btnStart.prop('disabled', false).text(s.reOptimizeBtn || 'Re-optimize selected images');
            return;
        }

        var currentId = queue.shift();
        processedCount++;

        var percent = Math.round((processedCount / totalItems) * 100);
        $progressBar.css('width', percent + '%');
        $progressPercent.text(percent + '%');
        $progressText.text(s.processing + ' (' + processedCount + ' / ' + totalItems + ') ID: ' + currentId);

        var $statusCell = $('#status-cell-' + currentId);
        var $origCell = $('#orig-size-' + currentId);
        var $webpCell = $('#webp-size-' + currentId);
        $statusCell.html('<span style="color:#2271b1; font-weight:600; font-size:12px;">⏳ ' + (s.optimizing || 'Optimizing...') + '</span>');

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
                $statusCell.html('<span class="badge badge-success" style="font-size:11px;">✔ ' + (s.generated || 'Done') + '</span>');
                if (res.data.new_orig_size) {
                    $origCell.html('<strong>' + res.data.new_orig_size + '</strong>');
                }
                if (res.data.new_webp_size) {
                    $webpCell.html('<strong style="color:#008a20;">' + res.data.new_webp_size + '</strong>');
                }
                $('#scan-row-' + currentId).attr('data-has-webp', '1');
            } else {
                $statusCell.html('<span class="badge badge-danger" style="font-size:11px;">✖ Error</span>');
            }
            processNext();
        }).fail(function () {
            $statusCell.html('<span class="badge badge-danger" style="font-size:11px;">✖ Timeout</span>');
            processNext();
        });
    }

    // -------------------------------------------------------------
    // 6. Media Library Single Item Actions
    // -------------------------------------------------------------
    $(document).on('click', '.btn-radish-single-opt', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var attachmentId = $btn.data('id');
        if (!attachmentId || $btn.prop('disabled')) return;

        var oldText = $btn.text();
        $btn.prop('disabled', true).text(s.optimizing || 'Optimizing...');

        $.post(radishWebpData.ajaxUrl, {
            action: 'radish_webp_optimize_single_action',
            nonce: radishWebpData.nonce,
            attachment_id: attachmentId
        }, function (res) {
            if (res.success) {
                $btn.text(s.optimizeSuccess || '✔ Optimized successfully');
                setTimeout(function () {
                    location.reload();
                }, 800);
            } else {
                alert(res.data.message || s.error || 'Optimization failed');
                $btn.prop('disabled', false).text(oldText);
            }
        }).fail(function () {
            alert(s.error || 'Request failed, please check network');
            $btn.prop('disabled', false).text(oldText);
        });
    });

    $(document).on('click', '.btn-radish-single-restore', function (e) {
        e.preventDefault();
        if (!confirm(s.confirmRestore || 'Are you sure you want to restore the initial uncompressed original file?')) return;

        var $btn = $(this);
        var attachmentId = $btn.data('id');
        if (!attachmentId || $btn.prop('disabled')) return;

        var oldText = $btn.text();
        $btn.prop('disabled', true).text(s.restoring || 'Restoring...');

        $.post(radishWebpData.ajaxUrl, {
            action: 'radish_webp_restore_single_action',
            nonce: radishWebpData.nonce,
            attachment_id: attachmentId
        }, function (res) {
            if (res.success) {
                $btn.text(s.restoreSuccess || '✔ Restored successfully');
                setTimeout(function () {
                    location.reload();
                }, 800);
            } else {
                alert(res.data.message || s.error || 'Restore failed');
                $btn.prop('disabled', false).text(oldText);
            }
        }).fail(function () {
            alert(s.error || 'Request failed, please check network');
            $btn.prop('disabled', false).text(oldText);
        });
    });
});
