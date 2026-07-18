# قرارداد رابط فرم افزونه‌ها

افزونه باید از tokenهای `--proma-field-*` هسته استفاده کند و selectorهای خود را زیر root افزونه محدود کند؛ نمونه‌ها: `.proma-accounting` و `.proma-zarinpal`.

ممنوع است:

- `input { ... }`، `select { ... }` یا `textarea { ... }` بدون scope.
- border دائمی یا focus ring خارجی که با هسته رقابت کند.
- بارگذاری icon یا font از CDN برای فیلدها.

هر افزونه باید ارتفاع 44px، label، error message، حالت disabled و focus داخلی هسته را حفظ کند. dropdown یا datepicker شناور می‌تواند shadow/مرز خودش را داشته باشد.
