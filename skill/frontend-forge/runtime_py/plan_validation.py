def validate_plan_graph(plan):
 ids={s["id"] for s in plan["steps"]};g={s["id"]:s.get("depends_on",[]) for s in plan["steps"]}
 if any(d not in ids for deps in g.values() for d in deps):return {"status":"FAIL","reason":"MISSING_DEPENDENCY"}
 visiting=set();done=set()
 def f(n):
  if n in visiting:return False
  if n in done:return True
  visiting.add(n)
  for d in g[n]:
   if not f(d):return False
  visiting.remove(n);done.add(n);return True
 return {"status":"PASS"} if all(f(n) for n in ids) else {"status":"FAIL","reason":"CYCLE"}
