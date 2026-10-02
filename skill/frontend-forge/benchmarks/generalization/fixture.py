from pathlib import Path
FEATURES={
 'MerchandisePanel':('catalog merchandise listing','کادر کالا برای فهرست فروش','CatalogPage'),
 'PurchasePane':('purchase keyboard payment pane','فرم خرید پرداخت با کیبورد','CheckoutPage'),
 'TopLinks':('header navigation links','لینک های سربرگ','HomePage'),
 'OrderSummary':('order recap totals','خلاصه سفارش','OrderPage'),
 'NotificationCenter':('notification alerts center','بخش اعلان ها','AccountPage'),
 'AddressBook':('saved delivery addresses','نشانی های ذخیره شده','AccountPage'),
 'InvoiceList':('billing invoice history','فهرست فاکتور','BillingPage'),
 'WishlistPanel':('saved favorite products','علاقه مندی ها','WishlistPage')}
def generate(root,noise=120):
 root=Path(root);(root/'src/components').mkdir(parents=True,exist_ok=True);(root/'src/styles').mkdir(parents=True,exist_ok=True);(root/'src/pages').mkdir(parents=True,exist_ok=True);(root/'tests').mkdir(parents=True,exist_ok=True)
 for name,(en,fa,page) in FEATURES.items():
  (root/f'src/styles/{name}.module.css').write_text('.root{display:block}\n')
  (root/f'src/components/{name}.tsx').write_text(f"import styles from '../styles/{name}.module.css'\nexport function {name}(){{return <section aria-label='{en} {fa}'>{fa}</section>}}")
  (root/f'tests/{name}.test.tsx').write_text(f"import {{{name}}} from '../src/components/{name}'\ntest('{en}',()=>{{}})")
  pp=root/f'src/pages/{page}.tsx';old=pp.read_text() if pp.exists() else '';pp.write_text(old+f"\nimport {{{name}}} from '../components/{name}'\nexport const Use{name}=()=> <{name}/>\n")
 for base in ['OrderSummary','MerchandisePanel']:
  for suffix in ['Legacy','Admin','Skeleton','Helper']:(root/f'src/components/{base}{suffix}.tsx').write_text(f'export const {base}{suffix}=()=>null')
 for i in range(noise):(root/f'src/components/Neutral{i}.tsx').write_text(f'export const Neutral{i}=()=> <div>neutral module {i}</div>')
 return root
