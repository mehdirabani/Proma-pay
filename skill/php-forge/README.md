# PHPForge X v1.2

**Codename:** Generalization-Hardened, Leak-Free, Semantic PHP Engineering Runtime  
**Release status:** **PILOT — NOT PRODUCTION READY**

PHPForge X v1.2 is a registry-driven PHP engineering routing/runtime layer focused on selecting the right domain, risk, repository evidence, context and verification path for PHP/Laravel/Symfony/WordPress/WooCommerce/Security/FinTech work.

## Quick start
```bash
pip install -r requirements.txt
python scripts/check_runtime_dependencies.py
python scripts/route_task.py --task "وبهوک درگاه دوبار تراکنش می‌سازد"
python scripts/validate_package.py
python scripts/run_evals.py --split development
```

## Evidence snapshot
- 1,610 normalized-unique evaluation tasks.
- Zero exact/normalized/concept split leakage; zero strict cross-split near-duplicates at threshold 0.965.
- Hidden Holdout after runtime freeze: 262/262 required-condition PASS; domain precision 91.59%.
- Final blind post-freeze red-team: 173/200 (86.5%).
- External same-Agent A/B: NOT VERIFIED.
- Deployment tokenizer/token savings: NOT VERIFIED.

See `PRODUCTION_READINESS.md`, `FINAL_RED_TEAM_AUDIT.md`, and the machine-readable JSON reports before using high autonomy.

## Evidence policy
Passing deterministic routing tests proves only the behaviors those datasets measure. PHPForge never treats README claims, character counts, self-scores or self-simulated A/B runs as production evidence.
