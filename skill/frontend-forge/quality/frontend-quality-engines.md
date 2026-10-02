# Frontend Quality Engines

## Performance
Inspect JS/CSS weight, render blocking, images, fonts, hydration, rerenders,
caching, lazy loading, critical resources and third-party scripts.
Use project-specific budgets and baseline regression.

## Accessibility
Target WCAG 2.2 AA for applicable user-facing interfaces unless the project defines
a stricter standard. Check semantics, keyboard, focus, contrast, labels, forms,
motion, ARIA, screen-reader behavior and touch targets.

## Security
Check XSS/unsafe HTML, client secrets, sensitive data exposure, dependency risk,
unsafe redirects, external scripts, auth UI assumptions and CSP-relevant behavior.

## Technical SEO
When relevant: semantic hierarchy, metadata, canonical, structured data,
internal linking, renderability, crawlability, Core Web Vitals and JS SEO.

A quality engine is activated only if relevant to the task/profile, except baseline
accessibility/security checks which remain mandatory for interactive production UI.
