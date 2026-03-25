from __future__ import annotations

from pathlib import Path

_package_dir = Path(__file__).resolve().parent
_repo_root = _package_dir.parent
_python_orchestrator_dir = _repo_root / "python" / "orchestrator"

if _python_orchestrator_dir.is_dir():
    __path__.append(str(_python_orchestrator_dir))
