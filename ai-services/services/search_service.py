import numpy as np
from fastapi import HTTPException

from core import embedding
from core.config import PRODUCT_INDEX_PATH, PRODUCT_META_PATH
from core.enrichment import extract_query_features, enrich_product, lexical_score_product, rerank_score
from core.ids import to_int_id
from core.models import Product, SemanticQuery
from core.persist import load_json, save_json
from core.vector_store import add_vectors, load_index, search, remove_ids

# Metadata store (in-memory dict persisted to JSON)
product_meta = load_json(PRODUCT_META_PATH, {})

# FAISS index for products
product_index = load_index(PRODUCT_INDEX_PATH)


def upsert_products(products: list[Product]) -> int:
    # Filter out products without id
    products = [p for p in products if p.id]
    products = [enrich_product(p) for p in products]
    texts = [embedding.wrap_passage(p.to_passage_text()) for p in products]
    vectors = embedding.encode_texts(texts)
    ids = np.array([to_int_id(str(p.id)) for p in products], dtype=np.int64)
    meta_update = {p.id: p.model_dump() for p in products}
    add_vectors(product_index, vectors, ids, PRODUCT_INDEX_PATH)
    product_meta.update(meta_update)
    save_json(PRODUCT_META_PATH, product_meta)
    print(f"[AI] Ingested products count={len(products)} sample_ids={[p.id for p in products[:5]]}")
    return len(products)


def semantic_search(body: SemanticQuery):
    if product_index.ntotal == 0 and not product_meta:
        raise HTTPException(status_code=404, detail="Index empty")
    features = extract_query_features(body.query)
    semantic_scores: dict[str, float] = {}
    candidate_ids: set[str] = set()

    if product_index.ntotal > 0:
        qvec = embedding.encode_texts([embedding.wrap_query(body.query)])
        semantic_k = max(body.top_k * 8, 50)
        semantic_k = min(semantic_k, max(product_index.ntotal, body.top_k))
        scores, ids = search(product_index, qvec, semantic_k)
        for score, pid_int in zip(scores[0], ids[0]):
            if pid_int == -1:
                continue
            pid = _reverse_lookup_pid(pid_int)
            semantic_scores[pid] = float(score)
            candidate_ids.add(pid)

    lexical_scores = {
        pid: lexical_score_product(meta, features)
        for pid, meta in product_meta.items()
    }
    lexical_candidates = sorted(
        ((pid, score) for pid, score in lexical_scores.items() if score > 0),
        key=lambda item: item[1],
        reverse=True,
    )[: max(body.top_k * 10, 80)]
    candidate_ids.update(pid for pid, _ in lexical_candidates)

    results = []
    for pid in candidate_ids:
        meta = product_meta.get(pid, {})
        if meta.get("status", "actived") != "actived":
            continue
        semantic_score = semantic_scores.get(pid, 0.0)
        lexical_score = lexical_scores.get(pid, 0.0)
        rerank = rerank_score(meta, features)
        combined_score = (semantic_score * 0.62) + (lexical_score * 0.38) + rerank
        if semantic_score < body.score_threshold and lexical_score < 0.28 and combined_score < 0.34:
            continue
        meta_with_score = meta.copy()
        meta_with_score["id"] = pid
        meta_with_score["score"] = float(combined_score)
        meta_with_score["semantic_score"] = float(semantic_score)
        meta_with_score["lexical_score"] = float(lexical_score)
        results.append(meta_with_score)
    results.sort(key=lambda item: item.get("score", 0), reverse=True)
    return {"results": results[: body.top_k]}


def _reverse_lookup_pid(pid_int: int) -> str:
    for pid in product_meta.keys():
        if to_int_id(pid) == pid_int:
            return pid
    return str(pid_int)


def delete_products(ids: list[str]) -> int:
    if not ids:
        return 0
    internal_ids = np.array([to_int_id(pid) for pid in ids], dtype=np.int64)
    remove_ids(product_index, internal_ids, PRODUCT_INDEX_PATH)
    for pid in ids:
        product_meta.pop(pid, None)
    save_json(PRODUCT_META_PATH, product_meta)
    print(f"[AI] Deleted products count={len(ids)} sample_ids={ids[:5]}")
    return len(ids)
