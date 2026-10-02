from pathlib import Path
FEATURES={
 'ProductCard':('product item card','کارت محصول','SearchPage'),
 'CheckoutForm':('checkout payment form','فرم پرداخت','CheckoutPage'),
 'Navbar':('navigation menu','منوی ناوبری','HomePage'),
 'Authentication':('login authentication form','فرم ورود','LoginPage'),
 'SearchFilters':('search filter controls','فیلتر جستجو','SearchPage'),
 'DashboardTable':('dashboard data table','جدول داشبورد','DashboardPage'),
 'Modal':('dialog modal popup','مودال','HomePage'),
 'CartDrawer':('shopping cart drawer','سبد خرید','ProductPage'),
 'UserProfile':('user account profile','پروفایل کاربر','ProfilePage'),
 'ProductGallery':('product image gallery','گالری محصول','ProductPage'),
}
def generate(root,noise=80):
 root=Path(root);(root/'src/components').mkdir(parents=True,exist_ok=True);(root/'src/pages').mkdir(parents=True,exist_ok=True);(root/'src/styles').mkdir(parents=True,exist_ok=True);(root/'tests').mkdir(parents=True,exist_ok=True)
 for name,(en,fa,page) in FEATURES.items():
  css=f'src/styles/{name}.module.css';test=f'tests/{name}.test.tsx';comp=f'src/components/{name}.tsx'
  (root/css).write_text(f'.root{{display:block}}\n/* {en} {fa} */')
  (root/comp).write_text(f"import styles from '../styles/{name}.module.css'\nexport function {name}(){{return <section aria-label='{en}'>{fa}</section>}}")
  (root/test).write_text(f"import {{{name}}} from '../src/components/{name}'\ntest('{en} responsive behavior',()=>{{}})")
  pp=root/'src/pages'/f'{page}.tsx'
  old=pp.read_text() if pp.exists() else ''
  pp.write_text(old+f"\nimport {{{name}}} from '../components/{name}'\nexport const Use{name}=()=> <{name}/>\n")
 # adversarial decoys
 for n in ['ProductCardLegacy','ProductCardSkeleton','ProductCardTestHelper','ProductCardAdmin','ProductItem','SearchProductCard']:
  (root/'src/components'/f'{n}.tsx').write_text(f'export function {n}(){{return null}}')
 for i in range(noise):
  (root/'src/components'/f'Noise{i}.tsx').write_text(f"export const Noise{i}=()=> <div>unrelated helper {i}</div>")
 return root
