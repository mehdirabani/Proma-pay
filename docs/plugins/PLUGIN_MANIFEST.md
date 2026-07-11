# قرارداد Manifest افزونه

فایل `plugin.json` حداقل این کلیدها را دارد:

```json
{
  "id": "example-plugin",
  "name": "نمونه افزونه",
  "description": "توضیح کوتاه",
  "version": "1.0.0",
  "author": "Proma Pay",
  "plugin_api_version": "1.0",
  "requires_core": "1.0.25",
  "requires_php": ">=8.1",
  "provider": "Example\\Plugin\\Provider",
  "permissions": [],
  "dependencies": [],
  "routes": [],
  "migrations": [],
  "assets": [],
  "settings": []
}
```

شناسه فقط حروف کوچک، عدد، نقطه، خط تیره و زیرخط دارد. مسیرهای Manifest نباید `..` یا مسیر مطلق داشته باشند و provider باید namespace معتبر داشته باشد.
