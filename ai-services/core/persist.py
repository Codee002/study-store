import json
from pathlib import Path
from typing import Any


def load_json(path: Path, default):
    if path.exists():
        with path.open("r", encoding="utf-8") as f:
            return json.load(f)
    return default


def save_json(path: Path, data: Any) -> None:
    path.parent.mkdir(exist_ok=True, parents=True)
    with path.open("w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=2)
