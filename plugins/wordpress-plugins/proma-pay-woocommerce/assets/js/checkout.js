(function ($) {
    'use strict';
    const format = (value) => new Intl.NumberFormat('fa-IR').format(Number(value || 0));
    let timer;
    let quoteRevision = 0;
    let quoteController = null;
    const quote = (revision) => {
        if (revision !== quoteRevision) { return; }
        if (quoteController) { quoteController.abort(); }
        quoteController = null;
        const $form = $('form.checkout');
        const $box = $('.promapaywc-quote').first();
        const values = {
            purpose: 'checkout',
            connection_uuid: $('#promapaywc-connection').val(),
            down_payment_toman: $('#promapaywc-down-payment').val(),
            months: $('#promapaywc-months').val(),
            interest_method: $('#promapaywc-interest-method').val(),
            terms_accept: $form.find('input[name="promapaywc_terms_accept"]').is(':checked') ? '1' : '0'
        };
        if (!values.connection_uuid || !values.down_payment_toman || !values.months) {
            $box.text('برای دریافت پیشنهاد، سرویس، پیش‌پرداخت و تعداد اقساط را انتخاب کنید.');
            return;
        }
        $box.text('در حال بررسی زنده شرایط…');
        const controller = new AbortController();
        quoteController = controller;
        fetch(PromaPayWCCheckout.endpoint, {
            method: 'POST', credentials: 'same-origin',
            headers: {'Content-Type': 'application/json', 'X-WP-Nonce': PromaPayWCCheckout.nonce},
            signal: controller.signal,
            body: JSON.stringify(values)
        }).then((response) => response.json().then((body) => ({ok: response.ok, body})))
          .then(({ok, body}) => {
              if (revision !== quoteRevision) { return; }
              if (!ok || !body || !body.ok) { throw new Error('quote unavailable'); }
              const first = body.installments[0];
              const last = body.installments[body.installments.length - 1];
              $box.text(
                  'پیش‌پرداخت: ' + format(body.down_payment_toman) + ' تومان؛ مجموع اقساط: ' +
                  format(body.total_installments_toman) + ' تومان؛ قسط اول ' + format(first.amount_toman) +
                  ' تومان در ' + first.due_date + (first.amount_toman === last.amount_toman ? '' :
                  '، قسط پایانی ' + format(last.amount_toman) + ' تومان') +
                  '؛ چک صیادی: ' + format(body.cheque_amount_rial) + ' ریال، سررسید ' + body.cheque_due_date +
                  ' به نام ' + body.beneficiary_legal_name + ' (' + body.beneficiary_identifier + ')' +
                  '؛ پیش‌پرداخت با درگاه ' + body.prepayment_gateway_title + ' پرداخت می‌شود.'
              );
              quoteController = null;
          })
          .catch(() => {
              if (revision !== quoteRevision) { return; }
              quoteController = null;
              $box.text('پیشنهاد معتبر دریافت نشد. مقدارها را بررسی کنید یا روش پرداخت دیگری انتخاب کنید.');
          });
    };
    const schedule = () => {
        window.clearTimeout(timer);
        const revision = ++quoteRevision;
        if (quoteController) { quoteController.abort(); quoteController = null; }
        timer = window.setTimeout(() => quote(revision), 350);
    };
    $(document.body).on('change input', '#promapaywc-connection,#promapaywc-down-payment,#promapaywc-months,#promapaywc-interest-method,input[name="promapaywc_terms_accept"]', schedule);
    $(document.body).on('updated_checkout', schedule);
})(jQuery);
