# بله سفیر

ارسال از `POST https://safir.bale.ai/api/v3/send_message` با هدر `api-access-key` انجام می‌شود. شماره مقصد به `989...` تبدیل می‌شود و هر درخواست شناسه idempotency دارد.

- متن، OTP و پیام رمزدار از ساختارهای مستقل `message`، `otp_message` و `is_secure` استفاده می‌کنند.
- پیام قالبی با `template_message` فرستاده می‌شود. دکمه‌ها داخل `message.reply_markup.inline_keyboard` قرار می‌گیرند.
- پاسخ `error_data` بررسی می‌شود و تنها وجود HTTP موفق به‌تنهایی ارسال موفق محسوب نمی‌شود؛ شناسه پیام نیز لازم است.
- شماره‌های عضو نبودن در بله (`NotBaleUser`) قابل fallback هستند و جزئیات شماره در snapshot پاسخ به‌صورت ماسک‌شده ذخیره می‌شود.
