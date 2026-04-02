from fastapi import APIRouter

from core.models import ProductIngestRequest, SemanticQuery
from services import search_service

router = APIRouter()


@router.post("/ingest/products")
def ingest_products(payload: ProductIngestRequest):
    print(f"[AI] Received ingest request count={len(payload.products)}")
    ingested = search_service.upsert_products(payload.products)
    return {"ingested": ingested}


@router.post("/semantic")
def semantic_search(body: SemanticQuery):
    return search_service.semantic_search(body)


@router.post("/ingest/delete")
def delete_products(body: dict):
    """
    Body: {"ids": ["p1", "p2", ...]}
    """
    ids = body.get("ids", [])
    print(f"[AI] Received delete request count={len(ids)}")
    deleted = search_service.delete_products(ids)
    return {"deleted": deleted}
