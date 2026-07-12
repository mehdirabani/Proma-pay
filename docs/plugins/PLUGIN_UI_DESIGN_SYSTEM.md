# قرارداد رابط کاربری پلاگین‌ها

تمام پلاگین‌های Proma Pay باید component مشترک `assets/css/components/forms.css` را از layout هسته مصرف کنند. پلاگین نباید نسخه‌ای کپی‌شده از قواعد عمومی فرم داشته باشد.

## ساختار فرم

- شبکه اصلی: `.proma-form-grid` یا compatibility class موجود `.form-grid`
- فیلد: `.proma-form-field`
- label مستقل: `.proma-form-label`
- کنترل: `.proma-form-control`
- متن راهنما: `.proma-form-help`
- خطا: `.proma-form-error`
- input group: `.proma-input-group`
- اکشن کنار فیلد: `.proma-field-actions`
- checkbox و radio: `.proma-checkbox-field` و `.proma-radio-field`

کنترل تک‌خطی باید ارتفاع `44px` داشته باشد. این ارتفاع نباید به textarea، select چندانتخابی، file dropzone، rich editor، checkbox یا radio اعمال شود.

## قواعد پلاگین

CSS اختصاصی پلاگین باید زیر یک ریشه منحصربه‌فرد مانند `.proma-accounting-plugin` scope شود. تعریف global برای `input`، `select`، `textarea`، `label` یا `.btn` مجاز نیست.

فرم‌ها باید RTL، keyboard-friendly، responsive و دارای focus قابل مشاهده باشند. فیلد الزامی باید `required` واقعی داشته باشد تا marker کنار label توسط هسته اضافه شود. placeholder جای label را نمی‌گیرد.

در موبایل همه ستون‌ها باید به یک ستون تبدیل شوند و input group بدون overflow باقی بماند. modal باید header و footer ثابت و body قابل اسکرول داشته باشد.

## نمونه

```html
<div class="proma-form-grid two proma-accounting-plugin">
  <div class="proma-form-field">
    <label class="proma-form-label" for="account-title">عنوان حساب</label>
    <input class="proma-form-control" id="account-title" name="title" required>
    <small class="proma-form-help">عنوان قابل نمایش در دفترکل</small>
  </div>
</div>
```
