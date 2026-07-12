# موتور قالب قرارداد

قالب مؤثر ابتدا آخرین نسخه `published` را برمی‌گرداند و در نبود آن از قالب پیش‌فرض سامانه استفاده می‌کند. کنترلرها نباید مستقیماً `contract_template_body` را بخوانند و باید از `ContractTemplateService::getEffectiveTemplate()` استفاده کنند.

`ContractTemplateRenderer` دو فرمت `plain_text_v1` و `structured_html_v1` را پشتیبانی می‌کند. متن ساده به paragraph، heading و list معنایی تبدیل می‌شود و خطوط خالی متوالی به یک شکست پاراگراف محدود می‌شوند.
