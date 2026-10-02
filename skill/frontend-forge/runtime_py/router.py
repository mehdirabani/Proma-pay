from .io_utils import load_json

STACK_CAPS = {
    "html-css":{"components","accessibility","maintainability"},
    "tailwind":{"components","design","accessibility","maintainability"},
    "javascript":{"components","security","maintainability"},
    "typescript":{"components","security","maintainability"},
    "react":{"components","accessibility","performance","maintainability"},
    "nextjs":{"components","accessibility","performance","seo","maintainability"}
}

KEYWORDS = [
    (("screenshot","figma","wireframe"), {"design"}),
    (("refactor","legacy"), {"regression","maintainability"}),
    (("accessibility","a11y","wcag"), {"accessibility"}),
    (("seo",), {"seo"}),
    (("performance","slow","bundle"), {"performance"}),
    (("security","auth","payment"), {"security"}),
    (("ecommerce","product page","checkout"), {"ecommerce","seo","performance"}),
    (("dashboard","admin"), {"dashboard","components"})
]

def route(task, project, stack):
    project_map = load_json("profiles/project-routing.json")
    active = set(project_map.get(project, []))
    active.update(STACK_CAPS.get(stack, set()))
    text = task.lower()
    for words, caps in KEYWORDS:
        if any(w in text for w in words):
            active.update(caps)
    # Generic frontend capability is always active for frontend tasks.
    active.add("frontend")
    return sorted(active)
