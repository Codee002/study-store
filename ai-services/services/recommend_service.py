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


def recommend_hybrid(req: RecommendRequest):
    candidate_k = max(req.top_k * 4, 40)
    content_results = _recommend_content_candidates(req, candidate_k)
    cf_results = _recommend_cf_candidates(req, candidate_k)

    if not content_results and not cf_results:
        return {"results": []}

    content_scores = _normalize_result_scores(content_results)
    cf_scores = _normalize_result_scores(cf_results)
    active_sources = int(bool(content_scores)) + int(bool(cf_scores))
    divisor = active_sources if active_sources > 0 else 1

    merged_ids = list(dict.fromkeys([*content_scores.keys(), *cf_scores.keys()]))
    results = []
    for pid in merged_ids:
        meta = product_meta.get(pid, {})
        if meta.get("status", "actived") != "actived":
            continue

        content_score = content_scores.get(pid, 0.0)
        cf_score = cf_scores.get(pid, 0.0)
        combined_score = (content_score + cf_score) / divisor

        results.append(
            {
                "id": pid,
                "title": meta.get("title"),
                "category": meta.get("category"),
                "price": meta.get("price"),
                "image": meta.get("image"),
                "score": float(combined_score),
                "content_score": float(content_score),
                "cf_score": float(cf_score),
            }
        )

    results.sort(
        key=lambda item: (
            item.get("score", 0.0),
            item.get("content_score", 0.0),
            item.get("cf_score", 0.0),
        ),
        reverse=True,
    )
    return {"results": results[: req.top_k]}


def recommend_content(req: RecommendRequest):
    return recommend_hybrid(req)


def recommend_cf(req: RecommendRequest):
    results = _recommend_cf_candidates(req, req.top_k)
    normalized_scores = _normalize_result_scores(results)
    output = []
    for item in results:
        pid = item["id"]
        meta = product_meta.get(pid, {})
        if meta.get("status", "actived") != "actived":
            continue
        enriched = meta.copy()
        enriched["id"] = pid
        enriched["score"] = float(normalized_scores.get(pid, 0.0))
        output.append(enriched)
    return {"results": output[: req.top_k]}


def _recommend_content_candidates(req: RecommendRequest, top_k: int):
    user_vec = build_user_vector(req.user_id)
    if user_vec is None and req.recent_product_ids:
        vecs = [product_vector(pid) for pid in req.recent_product_ids]
        vecs = [v for v in vecs if v is not None]
        if vecs:
            mat = np.vstack(vecs)
            user_vec = mat.mean(axis=0, keepdims=True)
            user_vec = _normalize(user_vec)
    if user_vec is None:
        return []
    scores, ids = search(product_index, user_vec, top_k)
    return _products_from_scores(scores, ids)["results"]


def _recommend_cf_candidates(req: RecommendRequest, top_k: int):
    uid = to_int_id(req.user_id)
    try:
        user_vec = reconstruct(user_index, uid)
    except RuntimeError as exc:
        return []
    scores, neighbor_ids = search(user_index, np.expand_dims(user_vec, 0), 20)
    neighbor_user_ids = [nid for nid in neighbor_ids[0] if nid not in (-1, uid)]
    neighbor_products = []
    for nid in neighbor_user_ids:
        uids = [e["user_id"] for e in events if to_int_id(e["user_id"]) == nid]
        if not uids:
            continue
        evs = [e for e in events if e["user_id"] == uids[0]]
        neighbor_products.extend(e["product_id"] for e in evs)
    if not neighbor_products:
        return []
    freq = {}
    for pid in neighbor_products:
        freq[pid] = freq.get(pid, 0) + 1
    ranked = sorted(
        ((pid, _rebalance_signal(count)) for pid, count in freq.items()),
        key=lambda x: x[1],
        reverse=True,
    )[: top_k]
    results = []
    for pid, score in ranked:
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
    return results


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
    product_weights: dict[str, float] = {}
    for e in evs:
        weight = weights.get(e.get("action", "view"), 1.0)
        pid = str(e["product_id"])
        product_weights[pid] = product_weights.get(pid, 0.0) + weight

    vecs = []
    wts = []
    for pid, raw_weight in product_weights.items():
        v = product_vector(pid)
        if v is None:
            continue
        vecs.append(v)
        wts.append(_rebalance_signal(raw_weight))
    if not vecs:
        return None
    mat = np.vstack(vecs)
    w = np.array(wts).reshape(-1, 1)
    user_vec = (mat * w).sum(axis=0, keepdims=True) / w.sum()
    return _normalize(user_vec)


def upsert_user_vector(user_id: str, vector: np.ndarray) -> None:
    uid = to_int_id(user_id)
    normalized_vector = _normalize(vector)
    add_vectors(user_index, normalized_vector, np.array([uid], dtype=np.int64), USER_INDEX_PATH)


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


def _normalize_result_scores(results: list[dict]) -> dict[str, float]:
    if not results:
        return {}
    max_score = max(float(item.get("score", 0.0)) for item in results)
    if max_score <= 0:
        return {str(item["id"]): 0.0 for item in results if item.get("id")}
    return {
        str(item["id"]): float(item.get("score", 0.0)) / max_score
        for item in results
        if item.get("id")
    }


def _rebalance_signal(raw_score: float) -> float:
    if raw_score <= 0:
        return 0.0
    return float(np.log1p(raw_score))


def _normalize(vec: np.ndarray):
    import faiss  

    normalized = np.ascontiguousarray(vec, dtype=np.float32)
    faiss.normalize_L2(normalized)
    return normalized
