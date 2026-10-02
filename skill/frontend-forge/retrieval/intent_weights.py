def edge_weights(domains):
    d=set(domains or [])
    w={'imports':1.0,'reverse_imports':.8,'tests':.6,'styles':.55,'routes':.45,'reexports':.9}
    if 'accessibility' in d:w.update({'tests':1.0,'reverse_imports':.8,'styles':.35})
    if 'performance' in d:w.update({'imports':1.0,'reverse_imports':.9,'routes':.75,'styles':.6})
    if 'css-tailwind' in d:w.update({'styles':1.0,'reverse_imports':.8,'tests':.35})
    if 'security' in d:w.update({'imports':1.0,'reverse_imports':1.0,'tests':.8})
    return w
