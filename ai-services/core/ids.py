import hashlib


def to_int_id(text_id: str) -> int:
    """
    Deterministic, stable integer ID for FAISS.
    Uses md5 (consistent across processes) and fits into signed 64-bit.
    """
    h = hashlib.md5(text_id.encode("utf-8")).digest()
    val = int.from_bytes(h[:8], byteorder="big", signed=False)
    return val % (2**63 - 1)  # fit in int64 range
