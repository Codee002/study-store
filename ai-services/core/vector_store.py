import threading
from pathlib import Path

import faiss  # type: ignore
import numpy as np

from .config import EMBED_DIM

_index_lock = threading.Lock()


def build_index(dimension: int = EMBED_DIM) -> faiss.IndexIDMap2:
    base = faiss.IndexFlatIP(dimension)
    return faiss.IndexIDMap2(base)


def load_index(path: Path, dimension: int = EMBED_DIM) -> faiss.IndexIDMap2:
    if path.exists():
        idx = faiss.read_index(str(path))
        if getattr(idx, "d", dimension) != dimension:
            return build_index(dimension)
        return faiss.IndexIDMap2(idx) if not isinstance(idx, faiss.IndexIDMap2) else idx
    return build_index(dimension)


def save_index(index: faiss.Index, path: Path) -> None:
    faiss.write_index(index, str(path))


def add_vectors(index: faiss.IndexIDMap2, vectors: np.ndarray, ids: np.ndarray, path: Path) -> None:
    with _index_lock:
        index.remove_ids(ids)
        index.add_with_ids(vectors, ids)
        save_index(index, path)


def search(index: faiss.IndexIDMap2, query_vecs: np.ndarray, k: int):
    with _index_lock:
        return index.search(query_vecs, k)


def reconstruct(index: faiss.IndexIDMap2, internal_id: int):
    with _index_lock:
        return index.reconstruct(internal_id)


def remove_ids(index: faiss.IndexIDMap2, ids: np.ndarray, path: Path) -> None:
    with _index_lock:
        index.remove_ids(ids)
        save_index(index, path)
