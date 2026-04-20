from fastapi import APIRouter

from core.models import RecommendRequest
from services import recommend_service

router = APIRouter()


@router.post("/content")
def recommend_content(req: RecommendRequest):
    return recommend_service.recommend_content(req)


@router.post("/hybrid")
def recommend_hybrid(req: RecommendRequest):
    return recommend_service.recommend_hybrid(req)


@router.post("/cf")
def recommend_cf(req: RecommendRequest):
    return recommend_service.recommend_cf(req)
