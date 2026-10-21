# Proma Design System

## Foundations

منبع توکن‌ها: `assets/css/design-system/tokens.css`.

- Brand: `--proma-brand-*`
- Surface/text/border: `--proma-surface`, `--proma-canvas`, `--proma-ink`, `--proma-muted`, `--proma-line`
- State: `--proma-positive`, `--proma-danger`
- Controls: `--ui-control-height: 48px`, `--ui-compact-control-height: 42px`, `--ui-control-radius: 12px`

## Component contract

- Button: یک action اصلی در هر context؛ حالت‌های secondary/success/danger با متن روشن.
- FormField: label، helper/error و control با ارتفاع مشترک؛ money همیشه تومان صحیح و سمت سرور معتبر می‌شود.
- Card: border ظریف، radius مشترک، shadow کم، padding برابر و title/action در header.
- Table: heading ثابت، `vertical-align: middle`، حالت empty، و responsive wrapper.
- Modal/Drawer: عنوان، close قابل‌دسترسی، focus قابل مشاهده، Escape و action footer قابل دسترس.

## Patternها

1. Page header: breadcrumb، عنوان، توضیح کوتاه، action اصلی و overflow action.
2. Financial summary: amount server-calculated، status text، timestamp و هشدار محاسبه.
3. Operational list: filter ثابت، pagination، loading/empty/error state.
4. Document workspace: navigation بخش‌ها، editor، preview چاپ و نسخه‌ها.

## ممنوعیت‌ها

- رنگ به‌تنهایی معنی status نیست.
- `!important` ابزار معماری نیست.
- محاسبه authoritative مالی در JavaScript ممنوع است.
- CSS صفحه‌ایِ انباشته خارج از لایهٔ page/component پذیرفته نیست.
