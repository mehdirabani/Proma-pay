from pathlib import Path
FEATURES={
 'CatalogSurface':{'en':'merchandise listing entry narrow viewport','fa':'نمای کالا فهرست فروش نمایشگر کوچک','page':'BrowsePage','kind':'catalog'},
 'PaymentJourney':{'en':'purchase payment fields keyboard focus','fa':'ورودی های خرید پرداخت کیبورد','page':'PayPage','kind':'checkout'},
 'MastheadCluster':{'en':'top site navigation links narrow header','fa':'لینک های بالای سایت ناوبری سربرگ','page':'HomePage','kind':'nav'},
 'FulfillmentBook':{'en':'shipping delivery address locations','fa':'آدرس ارسال تحویل نشانی','page':'AccountPage','kind':'address'},
 'AlertStream':{'en':'notification alerts messages','fa':'اعلان هشدار پیام','page':'AccountPage','kind':'notification'},
 'BillingLedger':{'en':'billing invoice receipt history','fa':'فاکتور صورتحساب سابقه پرداخت','page':'BillingPage','kind':'invoice'},
 'SavedItemsShelf':{'en':'favorite saved products wishlist','fa':'محصولات ذخیره علاقه مندی','page':'SavedPage','kind':'wishlist'},
 'IdentityPane':{'en':'account profile personal details','fa':'پروفایل حساب اطلاعات شخصی','page':'AccountPage','kind':'profile'},
 'MediaCarousel':{'en':'product images gallery zoom slides','fa':'تصاویر گالری محصول بزرگنمایی','page':'DetailPage','kind':'gallery'},
 'DataGridView':{'en':'dashboard records table horizontal scroll','fa':'جدول داشبورد رکورد اسکرول افقی','page':'AdminPage','kind':'dashboard'},
 'OrderRecap':{'en':'order total summary tax lines','fa':'خلاصه سفارش مبلغ مالیات','page':'OrderPage','kind':'order'},
 'FilterRail':{'en':'search filter controls category price','fa':'فیلتر جستجو دسته قیمت','page':'SearchPage','kind':'filter'},
}
DECOY_SUFFIXES=['Legacy','Admin','Skeleton','Helper','Story','Demo']
def generate(root,noise=180):
 root=Path(root);(root/'src/widgets').mkdir(parents=True,exist_ok=True);(root/'src/styles').mkdir(parents=True,exist_ok=True);(root/'src/screens').mkdir(parents=True,exist_ok=True);(root/'tests').mkdir(parents=True,exist_ok=True)
 for name,m in FEATURES.items():
  (root/f'src/styles/{name}.module.css').write_text(f'.root{{display:block}} /* {m["en"]} */\n')
  (root/f'src/widgets/{name}.tsx').write_text(f"import styles from '../styles/{name}.module.css'\nexport function {name}(){{return <section aria-label='{m['en']} {m['fa']}' data-kind='{m['kind']}'>{m['fa']}</section>}}")
  (root/f'tests/{name}.test.tsx').write_text(f"import {{{name}}} from '../src/widgets/{name}'\ntest('{m['en']} {m['fa']}',()=>{{}})")
  pp=root/f'src/screens/{m["page"]}.tsx';old=pp.read_text() if pp.exists() else '';pp.write_text(old+f"\nimport {{{name}}} from '../widgets/{name}'\nexport const Use{name}=()=> <{name}/>\n")
  for suff in DECOY_SUFFIXES[:4]:(root/f'src/widgets/{name}{suff}.tsx').write_text(f"export const {name}{suff}=()=> <div aria-label='legacy {m['kind']} helper'>x</div>")
 # deliberately confusing false positive decoys
 (root/'src/widgets/FavoriteLocationPanel.tsx').write_text("export const FavoriteLocationPanel=()=> <div>saved favorite product location</div>")
 (root/'src/widgets/GenericForm.tsx').write_text("export const GenericForm=()=> <form aria-label='generic account form'>x</form>")
 (root/'src/widgets/WeatherPlaceholder.tsx').write_text("export const WeatherPlaceholder=()=> <div>placeholder unrelated climate text</div>")
 for i in range(noise):(root/f'src/widgets/Noise{i}.tsx').write_text(f"export const Noise{i}=()=> <div>generic page item section module {i%13}</div>")
 return root
