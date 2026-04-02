import re
import unicodedata
from dataclasses import dataclass
from typing import Any, Iterable

from .embedding import normalize_text

SEARCH_STOPWORDS = {
    "muon",
    "mua",
    "can",
    "tim",
    "kiem",
    "cho",
    "toi",
    "minh",
    "loai",
    "cai",
    "chiec",
    "quyen",
    "co",
    "chuc",
    "nang",
    "de",
    "dung",
    "danh",
    "cho",
    "mot",
    "nhung",
    "hay",
    "va",
    "la",
    "cua",
}

TERM_GROUPS = {
    "tap_ve": {
        "product_type": "tập vẽ",
        "variants": [
            "tập vẽ",
            "vo ve",
            "vở vẽ",
            "quyển vẽ",
            "sổ vẽ",
            "tap my thuat",
            "mỹ thuật",
            "drawing book",
        ],
        "use_cases": ["vẽ", "mỹ thuật", "phác thảo"],
        "synonyms": ["tập vẽ", "vở vẽ", "quyển vẽ", "sổ vẽ", "tập mỹ thuật"],
    },
    "to_mau": {
        "product_type": "tập tô màu",
        "variants": ["tô màu", "to mau", "coloring", "mau sac", "màu sắc"],
        "use_cases": ["tô màu", "giải trí", "mỹ thuật"],
        "synonyms": ["tập tô màu", "vở tô màu", "sách tô màu"],
    },
    "vo_hoc_sinh": {
        "product_type": "tập học sinh",
        "variants": ["ô ly", "o ly", "kẻ ngang", "ke ngang", "học sinh", "ghi chép", "ghi chep"],
        "use_cases": ["ghi chép", "học tập"],
        "synonyms": ["tập học sinh", "vở học sinh", "vở ghi chép"],
    },
    "but_highlight": {
        "product_type": "bút highlight",
        "variants": [
            "highlight",
            "dạ quang",
            "da quang",
            "nhớ dòng",
            "nho dong",
            "nổi bật văn bản",
            "noi bat van ban",
            "lam noi bat",
        ],
        "use_cases": ["làm nổi bật văn bản", "highlight", "ghi chú"],
        "synonyms": ["bút highlight", "bút dạ quang", "bút nhớ dòng"],
    },
    "but_bi": {
        "product_type": "bút bi",
        "variants": ["bút bi", "but bi", "ballpoint"],
        "use_cases": ["ghi chép", "viết"],
        "synonyms": ["bút bi", "bút viết"],
    },
    "but_chi": {
        "product_type": "bút chì",
        "variants": ["bút chì", "but chi", "pencil"],
        "use_cases": ["viết", "vẽ phác thảo"],
        "synonyms": ["bút chì", "viết chì"],
    },
    "but_long": {
        "product_type": "bút lông",
        "variants": ["bút lông", "but long", "marker", "lông dầu", "long dau"],
        "use_cases": ["đánh dấu", "viết bảng", "trình bày"],
        "synonyms": ["bút lông", "marker"],
    },
}

CATEGORY_HINTS = {
    "bút bi": "but_bi",
    "but bi": "but_bi",
    "bút": "but_bi",
    "tập - bìa bao - nhãn": "vo_hoc_sinh",
    "tap - bia bao - nhan": "vo_hoc_sinh",
}


@dataclass
class QueryFeatures:
    raw_query: str
    normalized_query: str
    folded_query: str
    tokens: list[str]
    phrases: list[str]
    canonicals: list[str]
    expanded_terms: list[str]


def fold_text(text: str) -> str:
    normalized = normalize_text(text)
    folded = "".join(
        ch for ch in unicodedata.normalize("NFD", normalized) if unicodedata.category(ch) != "Mn"
    )
    return folded


def tokenize_folded(text: str) -> list[str]:
    return [tok for tok in re.findall(r"[a-z0-9]+", text) if tok]


def _match_variants(text: str) -> set[str]:
    matched = set()
    for canonical, spec in TERM_GROUPS.items():
        variants = [fold_text(v) for v in spec["variants"]]
        if any(variant and variant in text for variant in variants):
            matched.add(canonical)
    return matched


def enrich_product(product):
    text_parts = [
        product.title,
        product.category,
        product.description,
        " ".join(product.tags),
        " ".join(f"{k}:{v}" for k, v in product.attrs.items()),
    ]
    folded = fold_text(" ".join(p for p in text_parts if p))
    matched = _match_variants(folded)

    category_folded = fold_text(product.category)
    for hint, canonical in CATEGORY_HINTS.items():
        if fold_text(hint) in category_folded:
            matched.add(canonical)

    synonyms: list[str] = list(product.synonyms)
    use_cases: list[str] = list(product.use_cases)
    keywords: set[str] = set(product.search_terms)
    product_type = product.product_type

    for canonical in matched:
        spec = TERM_GROUPS[canonical]
        if not product_type:
            product_type = spec["product_type"]
        synonyms.extend(spec["synonyms"])
        use_cases.extend(spec["use_cases"])
        keywords.update(spec["variants"])
        keywords.add(spec["product_type"])

    keywords.update(product.tags)
    keywords.update(filter(None, [product.title, product.category]))

    deduped_synonyms = _dedupe_preserve_order(synonyms)
    deduped_use_cases = _dedupe_preserve_order(use_cases)
    deduped_terms = _dedupe_preserve_order(str(term) for term in keywords if term)
    search_text = " ".join(
        part
        for part in [
            product.title,
            product.title,
            product_type,
            product.category,
            product.category,
            " ".join(product.tags),
            " ".join(deduped_synonyms),
            " ".join(deduped_use_cases),
            product.description,
        ]
        if part
    )

    return product.model_copy(
        update={
            "product_type": product_type,
            "synonyms": deduped_synonyms,
            "use_cases": deduped_use_cases,
            "search_terms": deduped_terms,
            "search_text": search_text,
        }
    )


def extract_query_features(query: str) -> QueryFeatures:
    normalized_query = normalize_text(query)
    folded_query = fold_text(query)
    raw_tokens = tokenize_folded(folded_query)
    tokens = [tok for tok in raw_tokens if tok not in SEARCH_STOPWORDS and len(tok) > 1]
    canonicals = sorted(_match_variants(folded_query))
    phrases = [fold_text(query)]
    expanded_terms = list(tokens)

    for canonical in canonicals:
        spec = TERM_GROUPS[canonical]
        phrases.extend(fold_text(variant) for variant in spec["variants"])
        phrases.append(fold_text(spec["product_type"]))
        expanded_terms.extend(tokenize_folded(fold_text(" ".join(spec["synonyms"] + spec["use_cases"]))))

    return QueryFeatures(
        raw_query=query,
        normalized_query=normalized_query,
        folded_query=folded_query,
        tokens=_dedupe_preserve_order(tokens),
        phrases=_dedupe_preserve_order(phrase for phrase in phrases if phrase),
        canonicals=canonicals,
        expanded_terms=_dedupe_preserve_order(term for term in expanded_terms if term),
    )


def lexical_score_product(meta: dict[str, Any], features: QueryFeatures) -> float:
    title = fold_text(str(meta.get("title", "")))
    category = fold_text(str(meta.get("category", "")))
    product_type = fold_text(str(meta.get("product_type", "")))
    synonyms = " ".join(fold_text(s) for s in meta.get("synonyms", []))
    use_cases = " ".join(fold_text(s) for s in meta.get("use_cases", []))
    tags = " ".join(fold_text(s) for s in meta.get("tags", []))
    search_terms = " ".join(fold_text(s) for s in meta.get("search_terms", []))
    search_text = fold_text(str(meta.get("search_text", "")))

    title_tokens = set(tokenize_folded(title))
    category_tokens = set(tokenize_folded(category))
    meta_tokens = set(tokenize_folded(" ".join([title, category, product_type, synonyms, use_cases, tags, search_terms, search_text])))

    score = 0.0

    if features.folded_query and features.folded_query in title:
        score += 1.0
    if features.folded_query and features.folded_query in " ".join([product_type, synonyms, use_cases, search_terms]):
        score += 0.8

    for canonical in features.canonicals:
        spec = TERM_GROUPS.get(canonical)
        if not spec:
            continue
        product_type_match = fold_text(spec["product_type"])
        if product_type_match and product_type_match == product_type:
            score += 0.9
        if any(fold_text(item) in " ".join([title, synonyms, use_cases, search_terms]) for item in spec["synonyms"]):
            score += 0.5

    title_hits = sum(1 for token in features.tokens if token in title_tokens)
    category_hits = sum(1 for token in features.tokens if token in category_tokens)
    meta_hits = sum(1 for token in features.tokens if token in meta_tokens)

    if features.tokens:
        score += min(0.9, 0.35 * title_hits)
        score += min(0.4, 0.2 * category_hits)
        score += min(0.8, 0.12 * meta_hits)
        score += 0.4 * (title_hits / len(features.tokens))

    phrase_pool = " ".join([title, category, product_type, synonyms, use_cases, tags, search_terms])
    for phrase in features.phrases:
        if len(phrase) < 3:
            continue
        if phrase in title:
            score += 0.65
        elif phrase in phrase_pool:
            score += 0.35

    return min(score, 4.0) / 4.0


def rerank_score(meta: dict[str, Any], features: QueryFeatures) -> float:
    title = fold_text(str(meta.get("title", "")))
    product_type = fold_text(str(meta.get("product_type", "")))
    use_cases = " ".join(fold_text(s) for s in meta.get("use_cases", []))
    search_terms = " ".join(fold_text(s) for s in meta.get("search_terms", []))
    score = 0.0

    if features.canonicals:
        matched = 0
        for canonical in features.canonicals:
            spec = TERM_GROUPS.get(canonical)
            if not spec:
                continue
            canonical_product_type = fold_text(spec["product_type"])
            if canonical_product_type == product_type:
                matched += 1
            elif any(fold_text(term) in " ".join([title, use_cases, search_terms]) for term in spec["synonyms"]):
                matched += 0.5
        score += min(0.45, 0.2 * matched)

    if features.folded_query and features.folded_query in title:
        score += 0.2

    return min(score, 0.5)


def _dedupe_preserve_order(values: Iterable[str]) -> list[str]:
    seen = set()
    output = []
    for value in values:
        if value not in seen:
            seen.add(value)
            output.append(value)
    return output
