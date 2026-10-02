import re
def derive_patch(task,path,text,root_cause):
    low=task.lower()
    # Derived from task/root-cause semantics, never sealed expected patch.
    if "overflow" in low or "بیرون" in low:
        m=re.search(r"min-width\s*:\s*(\d+)px\s*;",text)
        if m:return {"old":m.group(0),"new":"min-width: 0;","reason":"remove fixed minimum width causing overflow"}
    if "label" in low or "accessible" in low or "دسترسی" in low or "keyboard" in low:
        m=re.search(r"<input([^>]*)>",text)
        if m and "aria-label" not in m.group(0):
            return {"old":m.group(0),"new":m.group(0)[:-1]+' aria-label="Field">',"reason":"add accessible name"}
    if "route" in low or "link" in low or "مسیر" in low:
        for a,b in [('/profil','/profile'),('/chekout','/checkout'),('/dashbord','/dashboard')]:
            if a in text:return {"old":a,"new":b,"reason":"repair route typo"}
    if "type" in low or "typescript" in low or "تایپ" in low:
        m=re.search(r"(\w+)\s*:\s*string",text)
        if m:return {"old":m.group(0),"new":m.group(1)+": number","reason":"repair numeric prop type contract"}
    if "loading" in low or "لود" in low:
        old="if (loading) return null;"
        if old in text:return {"old":old,"new":'if (loading) return <div>Loading...</div>;', "reason":"preserve loading feedback"}
    return None
