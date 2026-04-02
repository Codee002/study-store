from pathlib import Path

DATA_DIR = Path(__file__).resolve().parent.parent / "data"
DATA_DIR.mkdir(exist_ok=True)

PRODUCT_INDEX_PATH = DATA_DIR / "vector_products.index"
USER_INDEX_PATH = DATA_DIR / "vector_users.index"
PRODUCT_META_PATH = DATA_DIR / "products_meta.json"
EVENTS_PATH = DATA_DIR / "events.json"

# Embedding model
EMBEDDING_MODEL_NAME = "intfloat/multilingual-e5-large"
# Published embedding dimension for multilingual-e5-large.
EMBED_DIM = 1024
