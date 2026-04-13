import re
import unicodedata
from dataclasses import dataclass
from typing import Any, Iterable

from .embedding import normalize_text

MODEL_TOKEN_RE = re.compile(r"\b[a-z]{1,8}(?:[-/][a-z0-9]{1,8})+\b", re.IGNORECASE)
MEASURE_TOKEN_RE = re.compile(
    r"\b(?:a3|a4|a5|b5|hb|2b|5b|\d+(?:[.,]\d+)?\s?(?:mm|cm|gsm|trang|to|la|mau|ngan|mat))\b",
    re.IGNORECASE,
)

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

CATEGORY_ENRICHMENTS = {
    "giay in": {
        "synonyms": ["giay in", "giay photocopy", "giay copy", "ream giay", "giay van phong"],
        "use_cases": ["in an", "photocopy", "in tai lieu", "van phong"],
        "search_terms": ["giay a4", "giay a5", "giay a3", "photo copy", "copy paper"],
    },
    "hop viet - bop viet": {
        "synonyms": ["hop but", "hop viet", "bop viet", "tui but", "tui dung but"],
        "use_cases": ["dung but", "dung hoc cu", "sap xep dung cu hoc tap"],
        "search_terms": ["pencil case", "pen case", "tui dung do dung hoc tap"],
    },
    "bang - phan": {
        "synonyms": ["bang hoc sinh", "bang viet", "bang phan", "phan viet", "phan khong bui"],
        "use_cases": ["viet bang", "luyen viet", "hoc tap tren lop"],
        "search_terms": ["chalk board", "dustless chalk", "bang viet phan", "bang viet but long"],
    },
    "dao roc giay": {
        "synonyms": ["dao roc giay", "dao cat giay", "cutter"],
        "use_cases": ["cat giay", "mo hop", "thu cong", "van phong"],
        "search_terms": ["utility knife", "paper cutter"],
    },
    "keo van phong": {
        "synonyms": ["keo", "keo hoc sinh", "keo van phong"],
        "use_cases": ["cat giay", "thu cong", "van phong"],
        "search_terms": ["scissors", "keo cat giay"],
    },
    "bang keo - keo dan": {
        "synonyms": ["keo dan", "keo kho", "keo nuoc", "ho dan", "glue stick"],
        "use_cases": ["dan giay", "thu cong", "hoc tap", "van phong"],
        "search_terms": ["liquid glue", "glue", "dan thu cong"],
    },
    "but xoa": {
        "synonyms": ["but xoa", "xoa muc", "xoa but bi", "correction pen"],
        "use_cases": ["xoa loi viet", "chinh sua bai viet"],
        "search_terms": ["correction fluid", "xoa muc nuoc"],
    },
    "but mau": {
        "synonyms": ["but mau", "sap mau", "crayons", "pastels", "mau ve"],
        "use_cases": ["to mau", "ve", "my thuat", "sang tao"],
        "search_terms": ["color pen", "oil pastels", "washable crayons", "erasable crayons"],
    },
    "may tinh": {
        "synonyms": ["may tinh", "may tinh khoa hoc", "calculator"],
        "use_cases": ["tinh toan", "hoc tap", "giai bai tap"],
        "search_terms": ["scientific calculator", "may tinh hoc sinh", "may tinh sinh vien"],
    },
    "bia ho so": {
        "synonyms": ["bia ho so", "bia dung tai lieu", "file tai lieu", "cap tai lieu"],
        "use_cases": ["luu tru tai lieu", "sap xep giay to", "van phong"],
        "search_terms": ["document file", "folder a4", "file folder", "tui tai lieu"],
    },
    "thuoc - compa": {
        "synonyms": ["thuoc ke", "thuoc thang", "thuoc do goc", "compa", "thuoc compa"],
        "use_cases": ["do luong", "ve hinh", "hoc tap", "ky thuat"],
        "search_terms": ["ruler", "protractor", "pencil compass", "do goc"],
    },
}

BRAND_TERMS = [
    "thien long",
    "flexoffice",
    "flexio",
    "colokit",
    "diem 10",
    "hoshi",
    "ik copy",
    "akooland",
    "demon slayer",
    "doraemon",
    "doremi",
    "spiderman",
    "marvel",
    "strive",
]

TERM_GROUPS.update(
    {
        "tap_ve": {
            **TERM_GROUPS["tap_ve"],
            "variants": TERM_GROUPS["tap_ve"]["variants"] + ["sketch book", "sketchbook"],
            "use_cases": TERM_GROUPS["tap_ve"]["use_cases"] + ["luyá»‡n váº½"],
            "synonyms": TERM_GROUPS["tap_ve"]["synonyms"] + ["sketch book"],
        },
        "to_mau": {
            **TERM_GROUPS["to_mau"],
            "variants": TERM_GROUPS["to_mau"]["variants"] + ["crayons", "pastel"],
            "use_cases": TERM_GROUPS["to_mau"]["use_cases"] + ["sáng tạo"],
            "synonyms": TERM_GROUPS["to_mau"]["synonyms"] + ["sách tô màu", "tập tô màu", "vở tô màu"],
        },
        "vo_hoc_sinh": {
            **TERM_GROUPS["vo_hoc_sinh"],
            "variants": TERM_GROUPS["vo_hoc_sinh"]["variants"] + ["luyen viet"],
            "use_cases": TERM_GROUPS["vo_hoc_sinh"]["use_cases"] + ["luyệt viết", "làm bài"],
            "synonyms": TERM_GROUPS["vo_hoc_sinh"]["synonyms"] + ["tập luyện viết"],
        },
        "but_highlight": {
            **TERM_GROUPS["but_highlight"],
            "use_cases": TERM_GROUPS["but_highlight"]["use_cases"] + ["đánh dấu nội dung"],
            "synonyms": TERM_GROUPS["but_highlight"]["synonyms"] + ["bút tô sáng"],
        },
        "but_bi": {
            **TERM_GROUPS["but_bi"],
            "variants": TERM_GROUPS["but_bi"]["variants"] + ["pen"],
            "use_cases": TERM_GROUPS["but_bi"]["use_cases"] + ["ký tên", "viết bài"],
            "synonyms": TERM_GROUPS["but_bi"]["synonyms"] + ["ballpoint pen"],
        },
        "but_chi": {
            **TERM_GROUPS["but_chi"],
            "variants": TERM_GROUPS["but_chi"]["variants"] + ["graphite"],
            "use_cases": TERM_GROUPS["but_chi"]["use_cases"] + ["luyệt viết"],
            "synonyms": TERM_GROUPS["but_chi"]["synonyms"] + ["bút chì gỗ"],
        },
        "but_long": {
            **TERM_GROUPS["but_long"],
            "variants": TERM_GROUPS["but_long"]["variants"] + ["felt tip"],
            "use_cases": TERM_GROUPS["but_long"]["use_cases"] + ["ghi chú to"],
            "synonyms": TERM_GROUPS["but_long"]["synonyms"] + ["bút đánh dấu"],
        },
    }
)

CATEGORY_ENRICHMENTS.update(
    {
        "giay in": {
            "synonyms": CATEGORY_ENRICHMENTS["giay in"]["synonyms"] + ["giay van ban", "giay photo", "giay van phong pham"],
            "use_cases": CATEGORY_ENRICHMENTS["giay in"]["use_cases"] + ["in hop dong", "in de thi", "photo tai lieu"],
            "search_terms": CATEGORY_ENRICHMENTS["giay in"]["search_terms"] + ["ream a4", "ream a5", "ream a3", "500 to giay"],
        },
        "hop viet - bop viet": {
            "synonyms": CATEGORY_ENRICHMENTS["hop viet - bop viet"]["synonyms"] + ["hop dung but", "tui viet", "bop but"],
            "use_cases": CATEGORY_ENRICHMENTS["hop viet - bop viet"]["use_cases"] + ["mang theo dung cu hoc tap", "giu gon ban hoc"],
            "search_terms": CATEGORY_ENRICHMENTS["hop viet - bop viet"]["search_terms"] + ["pencil pouch", "stationery case", "hop but hoc sinh"],
        },
        "bang - phan": {
            "synonyms": CATEGORY_ENRICHMENTS["bang - phan"]["synonyms"] + ["bang hoc tap", "bang con", "but phan"],
            "use_cases": CATEGORY_ENRICHMENTS["bang - phan"]["use_cases"] + ["luyen chu", "viet phan", "day hoc"],
            "search_terms": CATEGORY_ENRICHMENTS["bang - phan"]["search_terms"] + ["bang con hoc sinh", "phan trang", "phan mau", "chalk"],
        },
        "dao roc giay": {
            "synonyms": CATEGORY_ENRICHMENTS["dao roc giay"]["synonyms"] + ["dao cat hop", "dao lam van phong"],
            "use_cases": CATEGORY_ENRICHMENTS["dao roc giay"]["use_cases"] + ["cat decal", "cat bia", "mo kien hang"],
            "search_terms": CATEGORY_ENRICHMENTS["dao roc giay"]["search_terms"] + ["dao cutter", "dao 9mm", "dao 18mm"],
        },
        "keo van phong": {
            "synonyms": CATEGORY_ENRICHMENTS["keo van phong"]["synonyms"] + ["keo cat giay", "keo thu cong"],
            "use_cases": CATEGORY_ENRICHMENTS["keo van phong"]["use_cases"] + ["cat thu cong", "cat hoc lieu"],
            "search_terms": CATEGORY_ENRICHMENTS["keo van phong"]["search_terms"] + ["scissor", "keo hoc sinh", "keo cat"],
        },
        "bang keo - keo dan": {
            "synonyms": CATEGORY_ENRICHMENTS["bang keo - keo dan"]["synonyms"] + ["keo sua", "ho kho", "ho nuoc"],
            "use_cases": CATEGORY_ENRICHMENTS["bang keo - keo dan"]["use_cases"] + ["dan thu cong", "dan bai tap", "dan tai lieu"],
            "search_terms": CATEGORY_ENRICHMENTS["bang keo - keo dan"]["search_terms"] + ["glue bottle", "keo dán giấy", "keo học sinh", "hồ dán"],
        },
        "but xoa": {
            "synonyms": CATEGORY_ENRICHMENTS["but xoa"]["synonyms"] + ["xoa keo", "but sua loi"],
            "use_cases": CATEGORY_ENRICHMENTS["but xoa"]["use_cases"] + ["xoa chinh ta", "sua loi viet tay"],
            "search_terms": CATEGORY_ENRICHMENTS["but xoa"]["search_terms"] + ["correction", "xoa but", "xoa muc but bi"],
        },
        "but mau": {
            "synonyms": CATEGORY_ENRICHMENTS["but mau"]["synonyms"] + ["sap dau", "sap nhua", "mau sap", "but sap"],
            "use_cases": CATEGORY_ENRICHMENTS["but mau"]["use_cases"] + ["ve tranh", "hoc my thuat", "to tranh"],
            "search_terms": CATEGORY_ENRICHMENTS["but mau"]["search_terms"] + ["crayon", "coloring pen", "mau nuoc", "mau pastel"],
        },
        "may tinh": {
            "synonyms": CATEGORY_ENRICHMENTS["may tinh"]["synonyms"] + ["casio hoc sinh", "may tinh cam tay"],
            "use_cases": CATEGORY_ENRICHMENTS["may tinh"]["use_cases"] + ["thi cu", "giai toan", "hoc sinh sinh vien"],
            "search_terms": CATEGORY_ENRICHMENTS["may tinh"]["search_terms"] + ["fx 509", "fx 799", "calculator hoc sinh", "scientific fx"],
        },
        "bia ho so": {
            "synonyms": CATEGORY_ENRICHMENTS["bia ho so"]["synonyms"] + ["bia nut", "tui dung ho so", "cap dung tai lieu"],
            "use_cases": CATEGORY_ENRICHMENTS["bia ho so"]["use_cases"] + ["mang tai lieu", "phan loai ho so", "luu giay a4"],
            "search_terms": CATEGORY_ENRICHMENTS["bia ho so"]["search_terms"] + ["clear bag", "file a4", "folder tai lieu", "bia la a4"],
        },
        "thuoc - compa": {
            "synonyms": CATEGORY_ENRICHMENTS["thuoc - compa"]["synonyms"] + ["thuoc hoc sinh", "bo do hinh"],
            "use_cases": CATEGORY_ENRICHMENTS["thuoc - compa"]["use_cases"] + ["ke duong thang", "do goc", "ve duong tron"],
            "search_terms": CATEGORY_ENRICHMENTS["thuoc - compa"]["search_terms"] + ["thuoc 15cm", "thuoc 20cm", "compa hoc sinh", "bo thuoc"],
        },
    }
)


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


def _extract_code_terms(*values: str) -> list[str]:
    terms: list[str] = []
    for value in values:
        if not value:
            continue
        for match in MODEL_TOKEN_RE.findall(value):
            token = normalize_text(match)
            if len(token) < 3:
                continue
            terms.append(token)
            if "-" in token or "/" in token:
                terms.append(token.replace("-", " ").replace("/", " "))
                parts = re.split(r"[-/]", token)
                terms.extend(part for part in parts if len(part) > 1)
        for match in MEASURE_TOKEN_RE.findall(value):
            token = normalize_text(match)
            if token:
                terms.append(token)
                compact = token.replace(" ", "")
                if compact != token:
                    terms.append(compact)
    return _dedupe_preserve_order(terms)


def _derive_brand_terms(*values: str) -> list[str]:
    folded = " ".join(fold_text(value) for value in values if value)
    matched = [brand for brand in BRAND_TERMS if brand in folded]
    return _dedupe_preserve_order(matched)


def _apply_enrichment_specs(target_text: str, specs: dict[str, dict[str, list[str]]]) -> tuple[list[str], list[str], list[str]]:
    synonyms: list[str] = []
    use_cases: list[str] = []
    search_terms: list[str] = []
    for hint, spec in specs.items():
        if hint not in target_text:
            continue
        synonyms.extend(spec.get("synonyms", []))
        use_cases.extend(spec.get("use_cases", []))
        search_terms.extend(spec.get("search_terms", []))
    return (
        _dedupe_preserve_order(synonyms),
        _dedupe_preserve_order(use_cases),
        _dedupe_preserve_order(search_terms),
    )


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
    attrs_values = [str(v) for k, v in product.attrs.items() if v and k != "product_id"]
    attrs_text = " ".join(attrs_values)
    for hint, canonical in CATEGORY_HINTS.items():
        if fold_text(hint) in category_folded:
            matched.add(canonical)

    synonyms: list[str] = list(product.synonyms)
    use_cases: list[str] = list(product.use_cases)
    keywords: set[str] = set(product.search_terms)
    product_type = product.product_type

    category_synonyms, category_use_cases, category_terms = _apply_enrichment_specs(
        category_folded,
        CATEGORY_ENRICHMENTS,
    )

    synonyms.extend(category_synonyms)
    use_cases.extend(category_use_cases)
    keywords.update(category_terms)
    keywords.update(_derive_brand_terms(product.title, product.description, product.category))
    keywords.update(_extract_code_terms(product.title, product.description, attrs_text))

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
    keywords.update(filter(None, attrs_values))

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
            attrs_text,
            " ".join(deduped_terms),
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

    # Nếu query xuất hiện tiltle thì cộng điểm cao nhất
    if features.folded_query and features.folded_query in title:
        score += 1.0
        
    # Nếu query xuất hiện trong các trường khác thì cộng điểm vừa
    if features.folded_query and features.folded_query in " ".join([product_type, synonyms, use_cases, search_terms]):
        score += 0.8

    for canonical in features.canonicals:
        spec = TERM_GROUPS.get(canonical)
        if not spec:
            continue
        product_type_match = fold_text(spec["product_type"])
        # Nếu có canonical match về loại sản phẩm thì cộng điểm cao
        if product_type_match and product_type_match == product_type:
            score += 0.9
        # Nếu có match về từ khóa liên quan thì cộng điểm vừa
        if any(fold_text(item) in " ".join([title, synonyms, use_cases, search_terms]) for item in spec["synonyms"]):
            score += 0.5

    # Đếm số token khớp nhau giữa query
    title_hits = sum(1 for token in features.tokens if token in title_tokens)
    category_hits = sum(1 for token in features.tokens if token in category_tokens)
    meta_hits = sum(1 for token in features.tokens if token in meta_tokens)

    # Cộng điểm theo token hit
    if features.tokens:
        score += min(0.9, 0.35 * title_hits)
        score += min(0.4, 0.2 * category_hits)
        score += min(0.8, 0.12 * meta_hits)
        score += 0.4 * (title_hits / len(features.tokens))

    # Cộng điểm theo cụm
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

    # Nếu query có phận loại sản phẩm
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

    # Cộng thêm điểm nếu query có cụm nguyên văn trong title
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
