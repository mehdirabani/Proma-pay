# Important Clause Markup

Use two asterisks around important content:

```text
مشتری متعهد است **اقساط را در موعد مقرر پرداخت کند**.
```

A complete marked paragraph receives `contract-important-paragraph`; marked text receives `contract-important-clause`. The output is 7px and bold in the official profile. Source is escaped/sanitized before controlled markup is applied, so the syntax cannot execute HTML, JavaScript, CSS, PHP, or template code.

An unmatched marker is printed as ordinary escaped text and produces the administrator warning «یک علامت ** بدون جفت در قالب پیدا شد.».

