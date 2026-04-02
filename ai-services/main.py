from fastapi import FastAPI

from routers import events, recommend, search

app = FastAPI(title="AI Services", version="0.2.0")

app.include_router(search.router, prefix="/search", tags=["search"])
app.include_router(events.router, prefix="/events", tags=["events"])
app.include_router(recommend.router, prefix="/recommend", tags=["recommend"])


@app.get("/")
def health():
    print("[AI] health check")
    return {"status": "ok"}
