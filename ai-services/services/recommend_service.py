import numpy as np
from fastapi import HTTPException

from core import embedding
from core.config import EVENTS_PATH, PRODUCT_INDEX_PATH, PRODUCT_META_PATH, USER_INDEX_PATH
from core.ids import to_int_id
from core.models import Event, RecommendRequest
from core.persist import load_json, save_json
from core.vector_store import (
    add_vectors,
    load_index,
    reconstruct,
    search,
    remove_ids,
)
from services.search_service import product_index, product_meta, _reverse_lookup_pid

# Events and user index
events: list[dict] = load_json(EVENTS_PATH, [])
user_index = load_index(USER_INDEX_PATH)


def log_event(event: Event):
    events.append(event.dict())
    save_json(EVENTS_PATH, events)
    user_vec = build_user_vector(event.user_id)
    if user_vec is not None:
        upsert_user_vector(event.user_id, user_vec)
    return {"stored": True}


def recommend_content(req: RecommendRequest):
    user_vec = build_user_vector(req.user_id)
    if user_vec is None and req.recent_product_ids:
        vecs = [product_vector(pid) for pid in req.recent_product_ids]
        vecs = [v for v in vecs if v is not None]
        if vecs:
            mat = np.vstack(vecs)
            user_vec = mat.mean(axis=0, keepdims=True)
            _normalize(user_vec)
    if user_vec is None:
        # Không có lịch sử và không có recent ids => trả rỗng thay vì 404
        return {"results": []}
    scores, ids = search(product_index, user_vec, req.top_k)
    return _products_from_scores(scores, ids)


def recommend_cf(req: RecommendRequest):
    uid = to_int_id(req.user_id)
    try:
        user_vec = reconstruct(user_index, uid)
    except RuntimeError as exc:
        return {"results": []}
    scores, neighbor_ids = search(user_index, np.expand_dims(user_vec, 0), 20)
    neighbor_user_ids = [nid for nid in neighbor_ids[0] if nid not in (-1, uid)]
    neighbor_products = []
    for nid in neighbor_user_ids:
        # map back to string user ids
        uids = [e["user_id"] for e in events if to_int_id(e["user_id"]) == nid]
        if not uids:
            continue
        evs = [e for e in events if e["user_id"] == uids[0]]
        neighbor_products.extend(e["product_id"] for e in evs)
    if not neighbor_products:
        return {"results": []}
    freq = {}
    for pid in neighbor_products:
        freq[pid] = freq.get(pid, 0) + 1
    ranked = sorted(freq.items(), key=lambda x: x[1], reverse=True)[: req.top_k]
    results = []
    for pid, count in ranked:
        meta = product_meta.get(pid, {})
        if meta.get("status", "actived") != "actived":
            continue
        item = meta.copy()
        item["id"] = pid
        item["score"] = count
        results.append(item)
    return {"results": results}


# ---------- Helpers ----------
def product_vector(pid: str):
    pid_int = to_int_id(pid)
    try:
        vec = reconstruct(product_index, pid_int)
        return np.expand_dims(vec, 0)
    except RuntimeError:
        return None


def build_user_vector(user_id: str):
    evs = [e for e in events if e["user_id"] == user_id]
    if not evs:
        return None
    weights = {"view": 1.0, "cart": 3.0, "purchase": 5.0}
    vecs = []
    wts = []
    for e in evs:
        v = product_vector(e["product_id"])
        if v is None:
            continue
        weight = weights.get(e.get("action", "view"), 1.0)
        vecs.append(v)
        wts.append(weight)
    if not vecs:
        return None
    mat = np.vstack(vecs)
    w = np.array(wts).reshape(-1, 1)
    user_vec = (mat * w).sum(axis=0, keepdims=True) / w.sum()
    _normalize(user_vec)
    return user_vec


def upsert_user_vector(user_id: str, vector: np.ndarray) -> None:
    uid = to_int_id(user_id)
    add_vectors(user_index, vector, np.array([uid], dtype=np.int64), USER_INDEX_PATH)


def _products_from_scores(scores, ids):
    results = []
    for score, pid_int in zip(scores[0], ids[0]):
        if pid_int == -1:
            continue
        pid = _reverse_lookup_pid(pid_int)
        meta = product_meta.get(pid, {})
        if meta.get("status", "actived") != "actived":
            continue
        results.append(
            {
                "id": pid,
                "title": meta.get("title"),
                "category": meta.get("category"),
                "price": meta.get("price"),
                "image": meta.get("image"),
                "score": float(score),
            }
        )
    return {"results": results}


def _normalize(vec: np.ndarray):
    import faiss  # local import to avoid global dependency cycle

    faiss.normalize_L2(vec)
