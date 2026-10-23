(function () {
    'use strict';
    const element = window.wp.element;
    const createElement = element.createElement;
    const useEffect = element.useEffect;
    const useState = element.useState;
    const registry = window.wc.wcBlocksRegistry;
    const settingsApi = window.wc.wcSettings;
    const settings = settingsApi.getSetting('promapaywc_installments_data', {});
    const format = (value) => new Intl.NumberFormat('fa-IR').format(Number(value || 0));

    const Content = (props) => {
        const connections = settings.connections || [];
        const [connection, setConnection] = useState((connections[0] || {}).id || '');
        const [downPayment, setDownPayment] = useState('');
        const [months, setMonths] = useState('6');
        const [method, setMethod] = useState('flat');
        const [accepted, setAccepted] = useState(false);
        const [quote, setQuote] = useState('برای دریافت شرایط زنده، پیش‌پرداخت و تعداد اقساط را وارد کنید.');
        const [error, setError] = useState('');
        const billing = props.billing || {};

        useEffect(() => {
            let active = true;
            if (!connection || !downPayment || !months) {
                setQuote('برای دریافت شرایط زنده، پیش‌پرداخت و تعداد اقساط را وارد کنید.');
                return () => { active = false; };
            }
            const timer = window.setTimeout(() => {
                fetch(settings.endpoint, {
                    method: 'POST', credentials: 'same-origin',
                    headers: {'Content-Type': 'application/json', 'X-WP-Nonce': settings.nonce},
                    body: JSON.stringify({
                        purpose: 'checkout', connection_uuid: connection,
                        down_payment_toman: downPayment, months: months,
                        interest_method: method, terms_accept: '0'
                    })
                }).then((response) => response.json().then((body) => ({ok: response.ok, body})))
                  .then(({ok, body}) => {
                      if (!active) { return; }
                      if (!ok || !body || !body.ok) { throw new Error('quote unavailable'); }
                      const first = body.installments[0];
                      setQuote('مجموع اقساط ' + format(body.total_installments_toman) + ' تومان؛ قسط اول ' +
                          format(first.amount_toman) + ' تومان در ' + first.due_date + '؛ چک ' +
                          format(body.cheque_amount_rial) + ' ریال با سررسید ' + body.cheque_due_date +
                          ' به نام ' + body.beneficiary_legal_name + ' (' + body.beneficiary_identifier + ').');
                      setError('');
                  }).catch(() => {
                      if (active) { setQuote('پیشنهاد فعلی دریافت نشد.'); }
                  });
            }, 350);
            return () => { active = false; window.clearTimeout(timer); };
        }, [connection, downPayment, months, method, billing.cartTotal && billing.cartTotal.value]);

        useEffect(() => {
            const unsubscribe = props.eventRegistration.onPaymentSetup(async () => {
                if (!accepted) {
                    return {type: props.emitResponse.responseTypes.ERROR, message: 'برای ادامه باید شرایط درخواست اقساط را تأیید کنید.'};
                }
                if (!connection || !downPayment || !months) {
                    return {type: props.emitResponse.responseTypes.ERROR, message: 'سرویس، پیش‌پرداخت و تعداد اقساط را انتخاب کنید.'};
                }
                try {
                    const response = await fetch(settings.endpoint, {
                        method: 'POST', credentials: 'same-origin',
                        headers: {'Content-Type': 'application/json', 'X-WP-Nonce': settings.nonce},
                        body: JSON.stringify({
                            purpose: 'checkout', connection_uuid: connection,
                            down_payment_toman: downPayment, months: months,
                            interest_method: method, terms_accept: '1'
                        })
                    });
                    const body = await response.json();
                    if (!response.ok || !body || !body.ok) {
                        return {type: props.emitResponse.responseTypes.ERROR, message: 'پیشنهاد اقساط معتبر دریافت نشد.'};
                    }
                } catch (e) {
                    return {type: props.emitResponse.responseTypes.ERROR, message: 'اتصال به سرویس اقساط در دسترس نیست.'};
                }
                return {
                    type: props.emitResponse.responseTypes.SUCCESS,
                    meta: {paymentMethodData: {
                        promapaywc_connection_uuid: connection,
                        promapaywc_down_payment_toman: downPayment,
                        promapaywc_months: months,
                        promapaywc_interest_method: method,
                        promapaywc_terms_accept: '1'
                    }}
                };
            });
            return unsubscribe;
        }, [connection, downPayment, months, method, accepted, props.eventRegistration, props.emitResponse]);

        return createElement('div', {className: 'promapaywc-checkout promapaywc-blocks', dir: 'rtl'},
            createElement('label', null, 'سرویس اقساطی', createElement('select', {value: connection, onChange: (e) => setConnection(e.target.value), required: true},
                connections.map((item) => createElement('option', {key: item.id, value: item.id}, item.label)))),
            createElement('label', null, 'پیش‌پرداخت (تومان)', createElement('input', {type: 'number', min: '1', step: '1', value: downPayment, onChange: (e) => setDownPayment(e.target.value), required: true})),
            createElement('label', null, 'تعداد اقساط ماهانه', createElement('input', {type: 'number', min: '1', max: '480', step: '1', value: months, onChange: (e) => setMonths(e.target.value), required: true})),
            createElement('label', null, 'روش محاسبه سود', createElement('select', {value: method, onChange: (e) => setMethod(e.target.value)},
                (settings.methods || []).map((value) => createElement('option', {key: value, value}, value)))),
            createElement('p', {className: 'promapaywc-quote', 'aria-live': 'polite'}, quote),
            createElement('label', {className: 'promapaywc-terms'}, createElement('input', {type: 'checkbox', checked: accepted, onChange: (e) => setAccepted(e.target.checked)}),
                'می‌پذیرم درخواست اقساط و شرایط اعلام‌شده برای بررسی به پروما پی ارسال شود.'),
            createElement('p', {className: 'promapaywc-deposit-note'}, 'پیش‌پرداخت در یک سفارش پرداخت مستقل و با درگاه بانکی متصل دریافت می‌شود؛ سفارش اصلی پرداخت‌شده محسوب نمی‌شود.')
        );
    };

    const Label = (props) => {
        const PaymentMethodLabel = props.components.PaymentMethodLabel;
        return createElement(PaymentMethodLabel, {text: settings.title || 'خرید اقساطی پروما پی'});
    };

    registry.registerPaymentMethod({
        name: 'promapaywc_installments',
        label: createElement(Label),
        content: createElement(Content),
        edit: createElement(Content),
        canMakePayment: () => (settings.connections || []).length > 0,
        supports: {features: settings.supports || ['products']}
    });
})();
