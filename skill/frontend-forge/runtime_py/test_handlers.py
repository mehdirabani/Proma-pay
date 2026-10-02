import time
def wrong_output(ctx):return {"unexpected":True}
def sleep_output(ctx):time.sleep(float(ctx.get("seconds",10)));return {"sleep_report":{"done":True}}
