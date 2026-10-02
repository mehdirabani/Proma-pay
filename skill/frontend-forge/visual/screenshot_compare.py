def compare_images(baseline,current,threshold=0.05):
    try:
        from PIL import Image,ImageChops
    except Exception:return {"status":"TOOL_UNAVAILABLE","reason":"Pillow unavailable"}
    a=Image.open(baseline).convert("RGB"); b=Image.open(current).convert("RGB")
    if a.size!=b.size:return {"status":"FAIL","difference":1.0,"threshold":threshold,"reason":"size mismatch"}
    diff=ImageChops.difference(a,b)
    hist=diff.histogram(); total=sum(v*(i%256) for i,v in enumerate(hist))
    maxdiff=255*3*a.size[0]*a.size[1]
    ratio=total/maxdiff if maxdiff else 0.0
    return {"status":"PASS" if ratio<=threshold else "FAIL","difference":ratio,"threshold":threshold}
