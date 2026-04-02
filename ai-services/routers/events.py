from fastapi import APIRouter

from core.models import Event
from services import recommend_service

router = APIRouter()


@router.post("")
def log_event(event: Event):
    return recommend_service.log_event(event)
