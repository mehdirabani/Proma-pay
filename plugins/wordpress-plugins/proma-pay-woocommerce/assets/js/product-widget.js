(function ($) {
    'use strict';
    const format = (value) => new Intl.NumberFormat('fa-IR').format(Number(value || 0));
    const requests = new WeakMap();
    let sequence = 0;

    const clear = ($widget, message) => {
        const state = requests.get($widget[0]);
        if (state && state.controller) {
            state.controller.abort();
        }
        requests.set($widget[0], {id: ++sequence, controller: null});
        $widget.find('.promapaywc-product-message').text(message);
        $widget.find('.promapaywc-product-details').empty();
    };

    const load = ($widget, variationId, quantity, inStock) => {
        if (inStock === false) {
            clear($widget, 'این مدل موجود نیست و امکان دریافت پیشنهاد اقساطی ندارد.');
            return;
        }
        if (String($widget.data('variable')) === '1' && Number(variationId || 0) < 1) {
            clear($widget, 'ابتدا یک مدل از محصول انتخاب کنید تا پیشنهاد همان مدل دریافت شود.');
            return;
        }
        const connectionUuid = $widget.data('connection-uuid');
        if (!connectionUuid) {
            clear($widget, 'در حال حاضر سرویس اقساطی فعالی برای این فروشگاه تعریف نشده است.');
            return;
        }
        const previous = requests.get($widget[0]);
        if (previous && previous.controller) {
            previous.controller.abort();
        }
        const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        const state = {id: ++sequence, controller};
        requests.set($widget[0], state);
        $widget.find('.promapaywc-product-message').text('در حال دریافت پیشنهاد معتبر از پروما پی…');
        fetch(PromaPayWCProduct.endpoint, {
            method: 'POST', credentials: 'same-origin',
            headers: {'Content-Type': 'application/json', 'X-WP-Nonce': PromaPayWCProduct.nonce},
            signal: state.controller ? state.controller.signal : undefined,
            body: JSON.stringify({
                purpose: 'product', connection_uuid: connectionUuid,
                product_id: Number($widget.data('product-id')),
                variation_id: Number(variationId || 0), quantity: Number(quantity || 1)
            })
        }).then((response) => response.json().then((body) => ({ok: response.ok, body})))
          .then(({ok, body}) => {
              if (requests.get($widget[0]) !== state) { return; }
              if (!ok || !body || !body.ok) { throw new Error('quote unavailable'); }
              const final = body.installments[body.installments.length - 1];
              const first = body.installments[0];
              const monthly = body.installments.every((item) => item.amount_toman === first.amount_toman)
                  ? format(first.amount_toman)
                  : format(first.amount_toman) + ' تا ' + format(final.amount_toman);
              $widget.find('.promapaywc-product-message').text('در طرح پیش‌پرداخت حداقل، هر قسط ' + monthly + ' تومان است.');
              $widget.find('.promapaywc-product-details').text(
                  'پیش‌پرداخت: ' + format(body.down_payment_toman) + ' تومان؛ ' +
                  body.installments.length + ' قسط؛ مجموع اقساط: ' + format(body.total_installments_toman) +
                  ' تومان؛ اولین سررسید: ' + body.first_installment_due_date +
                  '؛ مبلغ اسمی چک: ' + format(body.cheque_amount_rial) + ' ریال؛ ذی‌نفع: ' +
                  body.beneficiary_legal_name + ' (' + body.beneficiary_identifier + ').'
              );
          })
          .catch((error) => {
              if (error && error.name === 'AbortError') { return; }
              if (requests.get($widget[0]) === state) {
                  $widget.find('.promapaywc-product-message').text('دریافت پیشنهاد زنده ممکن نشد؛ شرایط نهایی فقط در مرحله پرداخت و با اتصال به سرویس مرکزی نمایش داده می‌شود.');
                  $widget.find('.promapaywc-product-details').empty();
              }
          });
    };

    const initialize = (root) => {
        $(root).find('.promapaywc-product-widget').addBack('.promapaywc-product-widget').each(function () {
            const $widget = $(this);
            if ($widget.data('promapaywcReady')) { return; }
            $widget.data('promapaywcReady', true);
            const $product = $widget.closest('.product');
            const $form = ($product.length ? $product.find('form.cart').first() : $('form.cart').first());
            const quantity = () => Number($form.find('input.qty').val() || 1);
            let selectedVariationStock = null;
            if (String($widget.data('variable')) !== '1') {
                load($widget, 0, quantity());
            } else {
                clear($widget, 'ابتدا یک مدل از محصول انتخاب کنید تا پیشنهاد همان مدل دریافت شود.');
            }
            $form.on('found_variation.promapaywc', (_event, variation) => {
                const variationId = Number(variation && variation.variation_id || 0);
                selectedVariationStock = {
                    variationId,
                    isInStock: Boolean(variation && variation.is_in_stock === true)
                };
                load($widget, variationId, quantity(), selectedVariationStock.isInStock);
            });
            $form.on('reset_data.promapaywc hide_variation.promapaywc', () => {
                selectedVariationStock = null;
                clear($widget, 'ابتدا یک مدل از محصول انتخاب کنید تا پیشنهاد همان مدل دریافت شود.');
            });
            $form.on('change.promapaywc input.promapaywc', 'input.qty', () => {
                const selectedVariation = Number($form.find('input[name="variation_id"]').val() || 0);
                const stockKnown = selectedVariationStock && selectedVariationStock.variationId === selectedVariation;
                load($widget, selectedVariation, quantity(), stockKnown ? selectedVariationStock.isInStock : false);
            });
        });
    };

    $(function () {
        initialize(document);
        if (typeof MutationObserver !== 'undefined' && document.body) {
            const observer = new MutationObserver((records) => {
                records.forEach((record) => record.addedNodes.forEach((node) => {
                    if (node && node.nodeType === 1) { initialize(node); }
                }));
            });
            observer.observe(document.body, {childList: true, subtree: true});
        }
    });
})(jQuery);
