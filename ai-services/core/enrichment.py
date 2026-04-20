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
            "drawing book",
            "sketch book",
            "sketchbook",
        ],
        "use_cases": ["vẽ", "mỹ thuật", "phác thảo", "luyện vẽ"],
        "synonyms": ["tập vẽ", "vở vẽ", "quyển vẽ", "sổ vẽ", "tập mỹ thuật", "sketch book"],
    },
    "to_mau": {
        "product_type": "tập tô màu",
        "variants": [
            "tập tô màu",
            "tap to mau",
            "vở tô màu",
            "vo to mau",
            "sách tô màu",
            "sach to mau",
            "coloring book",
        ],
        "use_cases": ["tô màu", "giải trí", "mỹ thuật", "sáng tạo"],
        "synonyms": ["tập tô màu", "vở tô màu", "sách tô màu"],
    },
    "vo_hoc_sinh": {
        "product_type": "tập học sinh",
        "variants": [
            "tập học sinh",
            "tap hoc sinh",
            "vở học sinh",
            "vo hoc sinh",
            "vở ghi chép",
            "vo ghi chep",
            "ô ly",
            "o ly",
            "kẻ ngang",
            "ke ngang",
            "luyện viết",
            "luyen viet",
        ],
        "use_cases": ["ghi chép", "học tập", "luyện viết", "làm bài"],
        "synonyms": ["tập học sinh", "vở học sinh", "vở ghi chép", "tập luyện viết"],
    },
    "but_highlight": {
        "product_type": "bút highlight",
        "variants": [
            "bút dạ quang",
            "but da quang",
            "highlight",
            "highlighter",
            "nhớ dòng",
            "nho dong",
            "nổi bật văn bản",
            "noi bat van ban",
        ],
        "use_cases": ["làm nổi bật văn bản", "highlight", "ghi chú", "đánh dấu nội dung", "đánh dấu văn bản"],
        "synonyms": ["bút highlight", "bút dạ quang", "bút nhớ dòng", "highlighter", "marker nhớ dòng"],
    },
    "but_bi": {
        "product_type": "bút bi",
        "variants": ["bút bi", "but bi", "ballpoint"],
        "use_cases": ["ghi chép", "viết", "ký tên", "viết bài"],
        "synonyms": ["bút bi", "bút viết", "ballpoint pen"],
    },
    "but_chi": {
        "product_type": "bút chì",
        "variants": ["bút chì", "but chi", "pencil", "graphite"],
        "use_cases": ["viết", "vẽ phác thảo", "luyện viết"],
        "synonyms": ["bút chì", "viết chì", "bút chì gỗ"],
    },
    "but_long": {
        "product_type": "bút lông",
        "variants": [
            "bút lông",
            "but long",
            "bút lông dầu",
            "but long dau",
            "bút lông viết chữ",
            "but long viet chu",
            "marker",
            "permanent marker",
            "felt tip",
        ],
        "use_cases": ["đánh dấu", "viết bảng", "trình bày", "ghi chú to"],
        "synonyms": ["bút lông", "marker", "bút đánh dấu"],
    },
    "bang_hoc_sinh": {
        "product_type": "bảng học sinh",
        "variants": [
            "bảng học sinh",
            "bang hoc sinh",
            "bảng dẻo",
            "bang deo",
            "bảng con",
            "bang con",
        ],
        "use_cases": ["viết bảng", "luyện viết", "học tập trên lớp", "dạy học"],
        "synonyms": ["bảng học sinh", "bảng viết", "bảng con", "bảng 2 mặt"],
    },
    "phan_viet": {
        "product_type": "phấn viết",
        "variants": [
            "phấn trắng",
            "phan trang",
            "phấn màu",
            "phan mau",
            "phấn không bụi",
            "phan khong bui",
            "dustless chalk",
            "chalk",
        ],
        "use_cases": ["viết bảng", "giảng dạy", "học tập trên lớp"],
        "synonyms": ["phấn viết", "phấn trắng", "phấn màu", "phấn không bụi"],
    },
    "nhan_vo": {
        "product_type": "nhãn vở",
        "variants": ["nhãn vở", "nhan vo", "nhãn tên", "nhan ten"],
        "use_cases": ["dán tên vở", "phân loại sách vở", "đánh dấu đồ dùng"],
        "synonyms": ["nhãn vở", "nhãn tên", "tem nhãn vở"],
    },
    "bia_bao_vo": {
        "product_type": "bìa bao vở",
        "variants": ["bìa bao vở", "bia bao vo", "bìa bao tập", "bia bao tap"],
        "use_cases": ["bảo vệ vở", "bọc sách vở", "giữ sách sạch sẽ"],
        "synonyms": ["bìa bao vở", "bìa bao tập", "bao vở"],
    },
    "chuot_but_chi": {
        "product_type": "chuốt bút chì",
        "variants": ["chuốt viết chì", "chuot viet chi", "gọt bút chì", "got but chi", "sharpener"],
        "use_cases": ["chuốt bút", "làm nhọn bút chì"],
        "synonyms": ["chuốt bút chì", "gọt bút chì", "sharpener"],
    },
    "but_mau": {
        "product_type": "bút màu",
        "variants": [
            "bút màu",
            "but mau",
            "bút sáp",
            "but sap",
            "sáp màu",
            "sap mau",
            "sáp dầu",
            "sap dau",
            "sáp nhựa",
            "sap nhua",
            "crayon",
            "crayons",
            "oil pastels",
            "washable crayons",
            "erasable crayons",
        ],
        "use_cases": ["tô màu", "vẽ", "mỹ thuật", "sáng tạo"],
        "synonyms": ["bút màu", "sáp màu", "crayons", "pastels", "bút sáp"],
    },
    "but_xoa": {
        "product_type": "bút xóa",
        "variants": ["bút xóa", "but xoa", "xóa mực", "xoa muc", "correction pen", "correction fluid"],
        "use_cases": ["xóa lỗi viết", "chỉnh sửa bài viết"],
        "synonyms": ["bút xóa", "xóa mực", "correction pen"],
    },
    "giay_in": {
        "product_type": "giấy in",
        "variants": ["giấy in", "giay in", "giấy photocopy", "giay photocopy", "ream giấy", "ream giay", "copy paper"],
        "use_cases": ["in ấn", "photocopy", "in tài liệu", "văn phòng"],
        "synonyms": ["giấy in", "giấy photocopy", "ream giấy", "copy paper"],
    },
    "hop_viet": {
        "product_type": "hộp bút",
        "variants": ["hộp bút", "hop but", "hộp viết", "hop viet", "bóp viết", "bop viet", "túi bút", "tui but"],
        "use_cases": ["đựng bút", "sắp xếp học cụ", "mang theo dụng cụ học tập"],
        "synonyms": ["hộp bút", "hộp viết", "bóp viết", "túi bút"],
    },
    "dao_roc_giay": {
        "product_type": "dao rọc giấy",
        "variants": ["dao rọc giấy", "dao roc giay", "dao cắt giấy", "dao cat giay", "cutter"],
        "use_cases": ["cắt giấy", "mở hộp", "thủ công", "văn phòng"],
        "synonyms": ["dao rọc giấy", "dao cắt giấy", "cutter"],
    },
    "keo_van_phong": {
        "product_type": "kéo văn phòng",
        "variants": ["kéo văn phòng", "keo van phong", "kéo cắt giấy", "keo cat giay", "scissors"],
        "use_cases": ["cắt giấy", "thủ công", "văn phòng"],
        "synonyms": ["kéo văn phòng", "kéo cắt giấy", "scissors"],
    },
    "keo_dan": {
        "product_type": "keo dán",
        "variants": ["keo dán", "keo dan", "keo khô", "keo kho", "keo nước", "keo nuoc", "glue stick", "liquid glue"],
        "use_cases": ["dán giấy", "thủ công", "học tập", "văn phòng"],
        "synonyms": ["keo dán", "keo khô", "keo nước", "glue stick"],
    },
    "bang_keo": {
        "product_type": "băng keo",
        "variants": ["băng keo", "bang keo", "tape"],
        "use_cases": ["dán gói hàng", "dán tài liệu", "cố định vật dụng"],
        "synonyms": ["băng keo", "tape", "băng dính"],
    },
    "may_tinh": {
        "product_type": "máy tính khoa học",
        "variants": ["máy tính", "may tinh", "máy tính khoa học", "may tinh khoa hoc", "calculator"],
        "use_cases": ["tính toán", "giải bài tập", "học tập", "thi cử"],
        "synonyms": ["máy tính", "máy tính khoa học", "calculator"],
    },
    "bia_ho_so": {
        "product_type": "bìa hồ sơ",
        "variants": ["bìa hồ sơ", "bia ho so", "bìa nút", "bia nut", "file tài liệu", "file tai lieu", "clear bag"],
        "use_cases": ["lưu trữ tài liệu", "sắp xếp giấy tờ", "mang tài liệu"],
        "synonyms": ["bìa hồ sơ", "bìa nút", "file tài liệu", "clear bag"],
    },
    "thuoc_ke": {
        "product_type": "thước kẻ",
        "variants": ["thước kẻ", "thuoc ke", "thước thẳng", "thuoc thang", "thước đo độ", "thuoc do do", "ruler", "protractor"],
        "use_cases": ["đo lường", "vẽ hình", "học tập", "kỹ thuật"],
        "synonyms": ["thước kẻ", "thước thẳng", "thước đo độ", "ruler", "protractor"],
    },
    "compa": {
        "product_type": "compa",
        "variants": ["compa", "pencil compass"],
        "use_cases": ["vẽ đường tròn", "đồ hình", "học tập"],
        "synonyms": ["compa", "pencil compass", "compa học sinh"],
    },
    "tap_so": {
        "product_type": "tap so",
        "variants": ["tap so", "so ghi chep", "so tay", "notebook", "exercise book"],
        "use_cases": ["ghi chep", "hoc tap", "ghi chu", "lam bai"],
        "synonyms": ["tap so", "so ghi chep", "so tay", "notebook"],
    },
    "cuc_tay": {
        "product_type": "cuc tay",
        "variants": ["cuc tay", "gom tay", "eraser", "gom xoa"],
        "use_cases": ["xoa but chi", "chinh sua bai viet", "hoc tap"],
        "synonyms": ["cuc tay", "gom tay", "eraser", "gom xoa"],
    },
}

CATEGORY_HINTS = {
    "but bi": "but_bi",
    "but chi": "but_chi",
    "but da quang": "but_highlight",
    "but long": "but_long",
    "but mau": "but_mau",
    "but xoa": "but_xoa",
    "giay in": "giay_in",
    "hop viet - bop viet": "hop_viet",
    "dao roc giay": "dao_roc_giay",
    "keo van phong": "keo_van_phong",
    "may tinh": "may_tinh",
    "bia ho so": "bia_ho_so",
    "cuc tay": "cuc_tay",
}

CATEGORY_ENRICHMENTS = {
    "but bi": {
        "synonyms": ["but bi", "but viet", "ballpoint pen", "viet muc"],
        "use_cases": ["ghi chep", "viet bai", "ky ten", "van phong"],
        "search_terms": ["ballpoint", "but muc", "muc xanh", "muc do", "muc den"],
    },
    "but chi": {
        "synonyms": ["but chi", "viet chi", "pencil", "but chi go"],
        "use_cases": ["viet", "phac thao", "luyen viet", "my thuat"],
        "search_terms": ["graphite", "2b", "5b", "hb", "chuot viet chi", "got but chi"],
    },
    "but da quang": {
        "synonyms": ["but da quang", "but highlight", "highlighter", "but nho dong", "marker nho dong"],
        "use_cases": ["lam noi bat van ban", "ghi chu", "danh dau noi dung", "hoc tap"],
        "search_terms": ["highlight pen", "free ink", "muc da quang", "marker highlight", "but danh dau van ban"],
    },
    "but long": {
        "synonyms": ["but long", "marker", "permanent marker", "but long dau"],
        "use_cases": ["viet bang", "trinh bay", "danh dau", "ghi chu to"],
        "search_terms": ["felt tip", "viet chu dep", "2 dau muc", "bam muc", "permanent ink"],
    },
    "giay in": {
        "synonyms": ["giay in", "giay photocopy", "giay copy", "ream giay", "giay van phong", "giay van ban", "giay photo", "giay van phong pham"],
        "use_cases": ["in an", "photocopy", "in tai lieu", "van phong", "in hop dong", "in de thi", "photo tai lieu"],
        "search_terms": ["giay a4", "giay a5", "giay a3", "photo copy", "copy paper", "ream a4", "ream a5", "ream a3", "500 to giay"],
    },
    "hop viet - bop viet": {
        "synonyms": ["hop but", "hop viet", "bop viet", "tui but", "tui dung but", "hop dung but", "tui viet", "bop but"],
        "use_cases": ["dung but", "dung hoc cu", "sap xep dung cu hoc tap", "mang theo dung cu hoc tap", "giu gon ban hoc"],
        "search_terms": ["pencil case", "pen case", "tui dung do dung hoc tap", "pencil pouch", "stationery case", "hop but hoc sinh"],
    },
    "bang - phan": {
        "synonyms": ["bang hoc sinh", "bang viet", "bang phan", "phan viet", "phan khong bui", "bang hoc tap", "bang con", "but phan"],
        "use_cases": ["viet bang", "luyen viet", "hoc tap tren lop", "luyen chu", "viet phan", "day hoc"],
        "search_terms": ["chalk board", "dustless chalk", "bang viet phan", "bang viet but long", "bang con hoc sinh", "phan trang", "phan mau", "chalk"],
    },
    "dao roc giay": {
        "synonyms": ["dao roc giay", "dao cat giay", "cutter", "dao cat hop", "dao lam van phong"],
        "use_cases": ["cat giay", "mo hop", "thu cong", "van phong", "cat decal", "cat bia", "mo kien hang"],
        "search_terms": ["utility knife", "paper cutter", "dao cutter", "dao 9mm", "dao 18mm"],
    },
    "keo van phong": {
        "synonyms": ["keo", "keo hoc sinh", "keo van phong", "keo cat giay", "keo thu cong"],
        "use_cases": ["cat giay", "thu cong", "van phong", "cat thu cong", "cat hoc lieu"],
        "search_terms": ["scissors", "keo cat giay", "scissor", "keo hoc sinh", "keo cat"],
    },
    "bang keo - keo dan": {
        "synonyms": ["keo dan", "keo kho", "keo nuoc", "ho dan", "glue stick", "keo sua", "ho kho", "ho nuoc"],
        "use_cases": ["dan giay", "thu cong", "hoc tap", "van phong", "dan thu cong", "dan bai tap", "dan tai lieu"],
        "search_terms": ["liquid glue", "glue", "dan thu cong", "glue bottle", "keo dán giấy", "keo học sinh", "hồ dán"],
    },
    "but xoa": {
        "synonyms": ["but xoa", "xoa muc", "xoa but bi", "correction pen", "xoa keo", "but sua loi"],
        "use_cases": ["xoa loi viet", "chinh sua bai viet", "xoa chinh ta", "sua loi viet tay"],
        "search_terms": ["correction fluid", "xoa muc nuoc", "correction", "xoa but", "xoa muc but bi"],
    },
    "but mau": {
        "synonyms": ["but mau", "but sap", "sap mau", "sap dau", "sap nhua", "crayon", "crayons", "oil pastels", "mau sap"],
        "use_cases": ["to mau", "ve", "my thuat", "sang tao", "ve tranh", "hoc my thuat", "to tranh"],
        "search_terms": ["color pen", "but sap", "oil pastels", "washable crayons", "erasable crayons", "crayon", "sap mau", "sap dau", "sap nhua"],
    },
    "may tinh": {
        "synonyms": ["may tinh", "may tinh khoa hoc", "calculator", "casio hoc sinh", "may tinh cam tay"],
        "use_cases": ["tinh toan", "hoc tap", "giai bai tap", "thi cu", "giai toan", "hoc sinh sinh vien"],
        "search_terms": ["scientific calculator", "may tinh hoc sinh", "may tinh sinh vien", "fx 509", "fx 799", "calculator hoc sinh", "scientific fx"],
    },
    "bia ho so": {
        "synonyms": ["bia ho so", "bia dung tai lieu", "file tai lieu", "cap tai lieu", "bia nut", "tui dung ho so", "cap dung tai lieu"],
        "use_cases": ["luu tru tai lieu", "sap xep giay to", "van phong", "mang tai lieu", "phan loai ho so", "luu giay a4"],
        "search_terms": ["document file", "folder a4", "file folder", "tui tai lieu", "clear bag", "file a4", "folder tai lieu", "bia la a4"],
    },
    "thuoc - compa": {
        "synonyms": ["thuoc ke", "thuoc thang", "thuoc do goc", "compa", "thuoc compa", "thuoc hoc sinh", "bo do hinh"],
        "use_cases": ["do luong", "ve hinh", "hoc tap", "ky thuat", "ke duong thang", "do goc", "ve duong tron"],
        "search_terms": ["ruler", "protractor", "pencil compass", "do goc", "thuoc 15cm", "thuoc 20cm", "compa hoc sinh", "bo thuoc"],
    },
    "tap - bia bao - nhan": {
        "synonyms": ["tap hoc sinh", "vo hoc sinh", "nhan vo", "bia bao vo"],
        "use_cases": ["ghi chep", "hoc tap", "dan ten vo", "bao ve sach vo"],
        "search_terms": ["o ly", "ke ngang", "luyen viet", "label vo", "bia bao tap"],
    },
    "tap ve - tap to mau": {
        "synonyms": ["tap ve", "vo ve", "tap to mau", "vo to mau", "so to mau", "sach to mau", "sketch book"],
        "use_cases": ["ve", "to mau", "my thuat", "giai tri", "phac thao"],
        "search_terms": ["sketch book", "drawing book", "coloring book", "a4 sketch book", "tap to net", "hoa trai", "art book", "tap to mau", "vo to mau", "sach to mau"],
    },
    "tap - so": {
        "synonyms": ["tap so", "so ghi chep", "so tay", "notebook", "exercise book"],
        "use_cases": ["ghi chep", "hoc tap", "ghi chu", "lam bai"],
        "search_terms": ["o ly", "ke ngang", "so hoc sinh", "so tay hoc tap", "tap ghi chu"],
    },
    "cuc tay": {
        "synonyms": ["cuc tay", "gom tay", "eraser", "gom xoa"],
        "use_cases": ["xoa but chi", "chinh sua bai viet", "hoc tap"],
        "search_terms": ["eraser pencil", "gom trang", "gom den", "xoa but chi"],
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
