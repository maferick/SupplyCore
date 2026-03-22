from __future__ import annotations

import argparse
import json
import os
import time
from dataclasses import dataclass
from pathlib import Path
from typing import Any

from .config import load_php_runtime_config


@dataclass(slots=True)
class PythonWorkerContext:
    schedule_id: int
    app_root: Path
    raw_config: dict[str, Any]

    @property
    def db_config(self) -> dict[str, Any]:
        return dict(self.raw_config.get("db", {}))

    @property
    def scheduler_config(self) -> dict[str, Any]:
        return dict(self.raw_config.get("scheduler", {}))

    @property
    def batch_size(self) -> int:
        return 1_000

    @property
    def timeout_seconds(self) -> int:
        return int(self.scheduler_config.get("default_timeout_seconds", 300))

    @property
    def memory_abort_threshold_bytes(self) -> int:
        return int(self.scheduler_config.get("memory_abort_threshold_bytes", 400 * 1024 * 1024))


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Run a SupplyCore scheduler job in Python worker mode.")
    parser.add_argument("--schedule-id", type=int, required=True, help="Claimed sync_schedules.id to execute.")
    parser.add_argument(
        "--app-root",
        default=str(Path(__file__).resolve().parents[2]),
        help="Path to the SupplyCore repository/app root.",
    )
    return parser.parse_args()


def emit(event: str, payload: dict[str, Any]) -> None:
    print(json.dumps({"event": event, "ts": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()), **payload}))


def process_job(context: PythonWorkerContext) -> dict[str, Any]:
    start = time.time()
    emit(
        "python_worker.started",
        {
            "schedule_id": context.schedule_id,
            "batch_size": context.batch_size,
            "timeout_seconds": context.timeout_seconds,
            "memory_abort_threshold_bytes": context.memory_abort_threshold_bytes,
            "db_host": context.db_config.get("host"),
            "db_port": context.db_config.get("port"),
            "db_database": context.db_config.get("database"),
        },
    )

    # Skeleton only: this worker intentionally does not implement concrete heavy-job processors yet.
    # The scheduler execution_mode flag can be set to python now, and processors can be added incrementally
    # around this stable contract without further scheduler/plumbing changes.
    duration_ms = int((time.time() - start) * 1000)
    return {
        "status": "skipped",
        "summary": "Python worker skeleton initialized successfully; no concrete job processor is registered yet.",
        "duration_ms": duration_ms,
        "rows_seen": 0,
        "rows_written": 0,
    }


def main() -> int:
    args = parse_args()
    app_root = Path(args.app_root).resolve()
    config = load_php_runtime_config(app_root)
    context = PythonWorkerContext(schedule_id=max(0, args.schedule_id), app_root=app_root, raw_config=config.raw)

    if context.schedule_id <= 0:
        emit("python_worker.error", {"error": "Argument --schedule-id must be a positive integer."})
        return 1

    os.environ.setdefault("APP_ENV", str(config.raw.get("app", {}).get("env", "development")))
    os.environ.setdefault("APP_TIMEZONE", str(config.raw.get("app", {}).get("timezone", "UTC")))

    result = process_job(context)
    emit("python_worker.finished", {"schedule_id": context.schedule_id, **result})
    return 0 if result.get("status") != "failed" else 1
