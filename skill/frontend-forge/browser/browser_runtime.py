from pathlib import Path
import importlib.util
from security.path_policy import resolve_in_workspace
from security.url_policy import validate_url
class BrowserRuntime:
    def __init__(self,workspace):self.workspace=Path(workspace).resolve()
    def availability(self):
        if importlib.util.find_spec("playwright") is None:return {"status":"TOOL_UNAVAILABLE","reason":"playwright python package missing"}
        try:
            from playwright.sync_api import sync_playwright
            with sync_playwright() as p:
                exe=Path(p.chromium.executable_path)
                if not exe.exists():return {"status":"TOOL_UNAVAILABLE","reason":"playwright browser binary missing"}
            return {"status":"AVAILABLE"}
        except Exception as e:return {"status":"TOOL_UNAVAILABLE","reason":str(e)}
    def inspect(self,url,viewport=None,timeout_ms=30000,interactions=None,screenshot_path=None):
        sp=None
        if screenshot_path is not None:
            sp=resolve_in_workspace(self.workspace,screenshot_path)
        validate_url(url, allow_private=False)
        avail=self.availability()
        if avail["status"]!="AVAILABLE":return avail
        from playwright.sync_api import sync_playwright
        viewport=viewport or {"width":1440,"height":900};interactions=interactions or []
        with sync_playwright() as p:
            browser=p.chromium.launch(headless=True);page=browser.new_page(viewport=viewport)
            console=[];failed=[];exceptions=[]
            page.on("console",lambda msg:console.append({"type":msg.type,"text":msg.text}))
            page.on("requestfailed",lambda req:failed.append({"url":req.url,"failure":req.failure}))
            page.on("pageerror",lambda err:exceptions.append(str(err)))
            page.goto(url,wait_until="networkidle",timeout=timeout_ms)
            for action in interactions:
                typ=action["type"];sel=action.get("selector")
                if typ=="click":page.locator(sel).click()
                elif typ=="fill":page.locator(sel).fill(action.get("value",""))
                elif typ=="press":page.locator(sel).press(action["key"])
                else:raise ValueError(f"unsupported interaction:{typ}")
            responsive=page.evaluate("""() => {const els=[...document.querySelectorAll('*')];const tiny=els.filter(e=>{const r=e.getBoundingClientRect();return ['A','BUTTON','INPUT','SELECT','TEXTAREA'].includes(e.tagName)&&r.width>0&&r.height>0&&(r.width<24||r.height<24)}).slice(0,20).map(e=>e.tagName);return {horizontal_overflow:document.documentElement.scrollWidth>document.documentElement.clientWidth,tiny_touch_targets:tiny};}""")
            if sp:sp.parent.mkdir(parents=True,exist_ok=True);page.screenshot(path=str(sp),full_page=True)
            data={"status":"PASS","title":page.title(),"url":page.url,"dom_snapshot":page.content(),"console":console,"uncaught_exceptions":exceptions,"network_failures":failed,"viewport":viewport,"responsive":responsive,"screenshot":str(sp) if sp else None}
            browser.close();return data
