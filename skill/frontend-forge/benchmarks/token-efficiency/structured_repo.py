from pathlib import Path
import random
def generate(root,n=100,seed=42):
    random.seed(seed);root=Path(root);(root/'src/components').mkdir(parents=True,exist_ok=True);(root/'src/styles').mkdir(parents=True,exist_ok=True);(root/'tests').mkdir(parents=True,exist_ok=True)
    (root/'src/components/ProductCard.tsx').write_text("import styles from '../styles/ProductCard.module.css'\nimport {formatPrice} from '../utils/price'\nexport function ProductCard(){return null}\n")
    (root/'src/styles/ProductCard.module.css').write_text('.card{display:grid;overflow:hidden}\n')
    (root/'src/utils').mkdir(parents=True,exist_ok=True);(root/'src/utils/price.ts').write_text('export const formatPrice=(x:number)=>String(x)\n')
    (root/'tests/ProductCard.test.tsx').write_text("import {ProductCard} from '../src/components/ProductCard'\ntest('responsive',()=>{})\n")
    (root/'src/pages').mkdir(parents=True,exist_ok=True);(root/'src/pages/Search.tsx').write_text("import {ProductCard} from '../components/ProductCard'\nexport const Search=()=>null\n")
    fixed=5
    for i in range(max(0,n-fixed)):
        folder=root/'src'/('features' if i%3 else 'shared');folder.mkdir(parents=True,exist_ok=True)
        dep=max(0,i-1);txt=f"export const Feature{i}=()=>{i};\n"
        if i>0 and i%5==0:txt=f"import {{Feature{dep}}} from './Feature{dep}'\n"+txt
        (folder/f'Feature{i}.ts').write_text(txt+'// structured unrelated implementation\n'*4)
    return root
