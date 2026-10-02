from pathlib import Path
import json
def evaluate(workspace,sealed_case,execution_result):
    # Sealed truth is supplied only after execution completes.
    p=Path(workspace)/sealed_case["target"];text=p.read_text()
    ok=all(x in text for x in sealed_case.get("must_contain",[])) and all(x not in text for x in sealed_case.get("must_not_contain",[]))
    receipt=execution_result.get("patch",{}).get("change_receipt")
    return {"success":bool(ok and receipt and receipt.get("signature")),"behavior_ok":ok,"signed_change_receipt":bool(receipt and receipt.get("signature"))}
