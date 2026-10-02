def attribute_failures(before,after):
    before=set(before or []);after=set(after or [])
    new=after-before;preexisting=after&before;fixed=before-after
    return {'new_failures':sorted(new),'preexisting_failures':sorted(preexisting),'fixed_failures':sorted(fixed),
      'classification':'PATCH_FAILURE' if new else 'PREEXISTING_FAILURE' if preexisting else 'NONE'}
