from .tool_adapters import GitAdapter,TypeScriptAdapter,ESLintAdapter,BuildAdapter,LighthouseAdapter,PlaywrightAdapter,AxeAdapter
REGISTRY={"git":GitAdapter,"typescript":TypeScriptAdapter,"eslint":ESLintAdapter,"build":BuildAdapter,"lighthouse":LighthouseAdapter,"playwright":PlaywrightAdapter,"axe":AxeAdapter}
def get_adapter(name):
    if name not in REGISTRY: raise KeyError(f"unknown adapter:{name}")
    return REGISTRY[name]()
