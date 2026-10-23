(function ($) {
    'use strict';
    const format = (value) => new Intl.NumberFormat('fa-IR').format(Number(value || 0));
    const load = ($widget, variationId, quantity) => {
        const connectionUuid = $widget.data('connection-uuid');
        if (!connectionUuid) {
            $widget.find('.promapaywc-product-message').text('در حال حاضر سرویس اقساطی فعالی برای این فروشگاه تعریف نشده است.');
            return;
        }
        $widget.find('.promapaywc-product-message').text('در حال دریافت پیشنهاد معتبر از پروما پی…');
        fetch(PromaPayWCProduct.endpoint, {
            method: 'POST', credentials: 'same-origin',
            headers: {'Content-Type': 'application/json', 'X-WP-Nonce': PromaPayWCProduct.nonce},
            body: JSON.stringify({
                purpose: 'product', connection_uuid: connectionUuid,
                product_id: Number($widget.data('product-id')),
                variation_id: Number(variationId || 0), quantity: Number(quantity || 1)
            })
        }).then((response) => response.json().then((body) => ({ok: response.ok, body})))
          .then(({ok, body}) => {
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
          .catch(() => $widget.find('.promapaywc-product-message').text('دریافت پیشنهاد زنده ممکن نشد؛ شرایط نهایی فقط در مرحله پرداخت و با اتصال به سرویس مرکزی نمایش داده می‌شود.'));
    };
    $(function () {
        $('.promapaywc-product-widget').each(function () {
            const $widget = $(this);
            const $form = $('form.cart').first();
            const quantity = () => Number($form.find('input.qty').val() || 1);
            if (String($widget.data('variable')) !== '1') {
                load($widget, 0, quantity());
            } else {
                $widget.find('.promapaywc-product-message').text('ابتدا یک مدل از محصول انتخاب کنید تا پیشنهاد همان مدل دریافت شود.');
            }
            $form.on('found_variation', (_event, variation) => load($widget, variation && variation.variation_id, quantity()));
            $form.on('reset_data', () => load($widget, 0, quantity()));
            $form.on('change', 'input.qty', () => load($widget, $form.find('input[name="variation_id"]').val(), quantity()));
        });
    });
})(jQuery);
