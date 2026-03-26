/**
 * Rating System Pro – Frontend JS
 */
(function ($) {
    'use strict';

    var cfg = typeof rsp_cfg !== 'undefined' ? rsp_cfg : {};
    var TAB_KEY = cfg.tab_key || 'rsp-ratings';

    var STAR_LABELS = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];

    /* ── animate bars ─────────────────────────────────────────────────── */
    function animateBars() {
        $('#rsp-widget .rsp-bar-fill').each(function () {
            var $b  = $(this);
            var pct = parseFloat($b.data('width')) || 0;
            $b.css('width', '0%');
            setTimeout(function () { $b.css('width', pct + '%'); }, 80);
        });
    }

    /* ── scroll to element ────────────────────────────────────────────── */
    function scrollTo($el) {
        if (!$el || !$el.length) { return; }
        var adminH = $('#wpadminbar').outerHeight(true) || 0;
        var top    = $el.offset().top - adminH - 20;
        $('html,body').stop(true).animate({ scrollTop: top }, 500, 'swing');
    }

    /* ── open our Ratings tab + scroll ───────────────────────────────── */
    function openTabAndScroll() {
        // WooCommerce sets id="tab-title-{key}" on the <li>
        var $a = $('#tab-title-' + TAB_KEY + ' a');
        if (!$a.length) {
            $a = $('a[href="#tab-' + TAB_KEY + '"]').first();
        }
        if (!$a.length) { return; }

        // Native click – WC JS handles show/hide of panels
        $a[0].click();

        // Wait for WC to paint, then scroll to widget
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                var $target = $('#rsp-widget').length ? $('#rsp-widget') : $('#tab-' + TAB_KEY);
                scrollTo($target);
                animateBars();
            });
        });
    }

    /* ── mini summary click ───────────────────────────────────────────── */
    function initMiniSummaryClick() {
        $(document).on('click', '.rsp-mini-summary', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var $this = $(this);
            if ($this.hasClass('rsp-mini-summary--shop')) {
                // On shop page, go to product page with tab hash
                window.location.href = $this.data('product-url') + '#tab-' + TAB_KEY;
            } else {
                // On single product, open tab and scroll
                openTabAndScroll();
            }
        });
    }

    /* ── animate bars when tab is clicked normally ────────────────────── */
    function initTabBarAnimation() {
        $(document).on('click', '#tab-title-' + TAB_KEY + ' a', function () {
            setTimeout(animateBars, 180);
        });
    }

    /* ── star picker interaction ──────────────────────────────────────── */
    function initStarPicker() {
        var $form   = $('#rsp-rate-form');
        var $btn    = $('#rsp-submit-btn');
        var $notice = $('#rsp-form-notice');
        var $label  = $('#rsp-selected-label');

        if (!$form.length) { return; }

        // When a star radio changes – update label + enable button
        $form.on('change', '.rsp-star-radio', function () {
            var val = parseInt($(this).val(), 10);
            $label.text(val + ' star' + (val > 1 ? 's' : '') + ' — ' + (STAR_LABELS[val] || ''));
            $btn.prop('disabled', false);
            $notice.text('').removeClass('rsp-notice--success rsp-notice--error');
        });

        // Submit
        $form.on('submit', function (e) {
            e.preventDefault();

            var star = $form.find('.rsp-star-radio:checked').val();
            if (!star) { return; }

            $btn.prop('disabled', true).text('Submitting…');

            $.ajax({
                url    : cfg.ajax_url,
                method : 'POST',
                data   : {
                    action     : 'rsp_submit_rating',
                    nonce      : cfg.nonce,
                    product_id : $form.data('product'),
                    star       : star,
                },
                success: function (res) {
                    if (res.success) {
                        $notice.text(res.data.message)
                               .removeClass('rsp-notice--error')
                               .addClass('rsp-notice--success');
                        $btn.text('Submitted ✓');
                        // Hide form fields
                        $form.find('.rsp-star-picker, .rsp-selected-label').hide();
                        // Update live widget numbers
                        updateWidget(res.data);
                    } else {
                        $notice.text(res.data.message)
                               .removeClass('rsp-notice--success')
                               .addClass('rsp-notice--error');
                        $btn.prop('disabled', false).text('Submit Rating');
                    }
                },
                error: function () {
                    $notice.text('An error occurred. Please try again.')
                           .addClass('rsp-notice--error');
                    $btn.prop('disabled', false).text('Submit Rating');
                }
            });
        });
    }

    /* ── live-update the widget after submission ──────────────────────── */
    function updateWidget(data) {
        if (!data) { return; }

        // Average number
        $('#rsp-widget .rsp-average-number').text(data.average);

        // Total
        $('#rsp-widget .rsp-average-label').text(data.total + ' ratings');

        // Progress bars
        $('#rsp-widget .rsp-breakdown-row').each(function () {
            var $row  = $(this);
            var star  = parseInt($row.find('.rsp-breakdown-label').text(), 10);
            var pct   = data.percents[star] || 0;
            var count = data.counts[star]   || 0;

            $row.find('.rsp-bar-fill').data('width', pct).css('width', '0%');
            $row.find('.rsp-breakdown-count').text(count);

            setTimeout(function () {
                $row.find('.rsp-bar-fill').css('width', pct + '%');
            }, 80);
        });
    }

    /* ── stars-only: hide textarea ────────────────────────────────────── */
    function initStarInput() {
        $('form#commentform textarea#comment')
            .closest('p, .comment-form-comment').hide();
    }

    /* ── boot ─────────────────────────────────────────────────────────── */
    $(function () {
        initMiniSummaryClick();
        initTabBarAnimation();
        initStarPicker();
        initStarInput();
        animateBars();
    });

}(jQuery));
