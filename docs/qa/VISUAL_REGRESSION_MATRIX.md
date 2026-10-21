# ماتریس آزمون Visual Regression

| family | viewهای بحرانی | اندازه‌ها | موارد پذیرش |
| --- | --- | --- | --- |
| ورود | login/register/recovery | 360, 768, 1440 | فرم، خطا، focus، RTL |
| قرارداد | list/create/edit/show/print | 360, 1024, 1440 | بدون overflow، ضامن 0/1/3، preview=print |
| اقساط و پرداخت | list/panel/payment modal | 360, 768, 1440 | control هم‌راستا، summary واضح، modal قابل بستن |
| وصول | overdue/operator drawer | 390, 1024, 1440 | table/card، status، actionهای تماس |
| حقوقی | list/show/cost/attachment | 360, 768, 1440 | role visibility، هزینه و file state |
| مشتری | dashboard/portal/history | 360, 1024, 1440 | تراکم مناسب، empty state، اطلاعات masked |
| تنظیمات | general/contracts/update | 360, 1024, 1440 | tab navigation، فرم‌های طولانی، هشدارها |

برای هر مورد screenshot پیش و پس از تغییر، keyboard navigation، contrast، نام طولانی، مبلغ بزرگ، فهرست خالی و خطای سرور بررسی می‌شود.
