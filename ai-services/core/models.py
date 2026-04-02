from typing import Dict, List, Optional

from pydantic import BaseModel, Field


class Product(BaseModel):
    id: str
    title: str
    description: str = ""
    status: str = "actived"  # actived|disabled
    category: str = ""
    tags: List[str] = Field(default_factory=list)
    synonyms: List[str] = Field(default_factory=list)
    use_cases: List[str] = Field(default_factory=list)
    search_terms: List[str] = Field(default_factory=list)
    product_type: str = ""
    search_text: str = ""
    attrs: Dict[str, str] = Field(default_factory=dict)
    price: Optional[float] = None
    price_min: Optional[float] = None
    price_max: Optional[float] = None
    discount_price: Optional[float] = None
    rating: Optional[float] = None
    sold: Optional[int] = None
    image: Optional[str] = None
    images: List[str] = Field(default_factory=list)

    def to_passage_text(self) -> str:
        numeric_parts = []
        if self.price is not None:
            numeric_parts.append(f"gia:{self.price}")
        if self.price_min is not None:
            numeric_parts.append(f"gia_tu:{self.price_min}")
        if self.price_max is not None:
            numeric_parts.append(f"gia_den:{self.price_max}")
        if self.discount_price is not None:
            numeric_parts.append(f"gia_khuyen_mai:{self.discount_price}")
        if self.rating is not None:
            numeric_parts.append(f"danh_gia:{self.rating}")
        if self.sold is not None:
            numeric_parts.append(f"da_ban:{self.sold}")

        parts = [
            self.title,
            self.title,
            self.product_type,
            self.description,
            self.category,
            self.category,
            " ".join(self.tags),
            " ".join(self.synonyms),
            " ".join(self.use_cases),
            " ".join(self.search_terms),
            " ".join(f"{k}:{v}" for k, v in self.attrs.items()),
            self.search_text,
            " ".join(numeric_parts),
        ]
        return " ".join(p for p in parts if p)


class ProductIngestRequest(BaseModel):
    products: List[Product]


class SemanticQuery(BaseModel):
    query: str
    top_k: int = 10
    score_threshold: float = 0.3


class Event(BaseModel):
    user_id: str
    product_id: str
    action: str = Field(description="view|cart|purchase")
    ts: Optional[str] = None


class RecommendRequest(BaseModel):
    user_id: str
    top_k: int = 10
    recent_product_ids: List[str] = Field(default_factory=list)
