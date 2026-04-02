import threading
from typing import List

import numpy as np
from sentence_transformers import SentenceTransformer

from .config import EMBEDDING_MODEL_NAME

_model_lock = threading.Lock()
_model: SentenceTransformer | None = None


def load_model() -> SentenceTransformer:
    global _model
    with _model_lock:
        if _model is None:
            _model = SentenceTransformer(EMBEDDING_MODEL_NAME)
        return _model


def normalize_text(text: str) -> str:
    return " ".join(text.strip().split()).lower()


def wrap_query(q: str) -> str:
    return f"query: {normalize_text(q)}"


def wrap_passage(p: str) -> str:
    return f"passage: {normalize_text(p)}"


def encode_texts(texts: List[str]) -> np.ndarray:
    model = load_model()
    # Normalize embeddings so inner product = cosine similarity.
    return model.encode(texts, normalize_embeddings=True)
