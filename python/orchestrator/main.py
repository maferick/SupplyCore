from __future__ import annotations

import argparse
from pathlib import Path

from .config import load_php_runtime_config
from .influx_export import main as run_influx_rollup_export
from .influx_inspect import inspect_main as run_influx_rollup_inspect
from .influx_inspect import sample_main as run_influx_rollup_sample
from .logging_utils import configure_logging
from .rebuild_data_model import main as run_rebuild_data_model
from .supervisor import run_supervisor
from .worker_pool import main as run_worker_pool
from .zkill_worker import main as run_zkill_worker


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Run SupplyCore Python services")
    subparsers = parser.add_subparsers(dest="command")

    supervisor = subparsers.add_parser("supervisor", help="Run the legacy PHP scheduler supervisor")
    supervisor.add_argument("--app-root", default=str(Path(__file__).resolve().parents[2]))
    supervisor.add_argument("--verbose", action="store_true")

    worker_pool = subparsers.add_parser("worker-pool", help="Run the continuous worker pool")
    worker_pool.add_argument("--app-root", default=str(Path(__file__).resolve().parents[2]))
    worker_pool.add_argument("--worker-id", default="")
    worker_pool.add_argument("--queues", default="sync,compute")
    worker_pool.add_argument("--workload-classes", default="sync,compute")
    worker_pool.add_argument("--once", action="store_true")
    worker_pool.add_argument("--verbose", action="store_true")

    zkill = subparsers.add_parser("zkill-worker", help="Run the dedicated zKill continuous worker")
    zkill.add_argument("--app-root", default=str(Path(__file__).resolve().parents[2]))
    zkill.add_argument("--poll-sleep", type=int, default=10)
    zkill.add_argument("--once", action="store_true")
    zkill.add_argument("--verbose", action="store_true")

    rebuild = subparsers.add_parser("rebuild-data-model", help="Run the live-progress derived rebuild workflow")
    rebuild.add_argument("--app-root", default=str(Path(__file__).resolve().parents[2]))
    rebuild.add_argument("--mode", default="rebuild-all-derived")
    rebuild.add_argument("--window-days", type=int, default=30)
    rebuild.add_argument("--full-reset", action="store_true")
    rebuild.add_argument("--enable-partitioned-history", dest="enable_partitioned_history", action="store_true", default=True)
    rebuild.add_argument("--disable-partitioned-history", dest="enable_partitioned_history", action="store_false")

    influx_export = subparsers.add_parser("influx-rollup-export", help="Export selected historical rollups to InfluxDB")
    influx_export.add_argument("--app-root", default=str(Path(__file__).resolve().parents[2]))
    influx_export.add_argument("--dataset", action="append", default=[], help="Limit export to one or more dataset keys.")
    influx_export.add_argument("--full", action="store_true", help="Ignore checkpoints and export the full selected dataset(s).")
    influx_export.add_argument("--dry-run", action="store_true", help="Read and encode points without writing to InfluxDB.")
    influx_export.add_argument("--batch-size", type=int, default=0, help="Override Influx write batch size.")
    influx_export.add_argument("--verbose", action="store_true")

    influx_inspect = subparsers.add_parser("influx-rollup-inspect", help="Inspect measurement coverage in the InfluxDB rollup bucket")
    influx_inspect.add_argument("--app-root", default=str(Path(__file__).resolve().parents[2]))
    influx_inspect.add_argument("--dataset", action="append", default=[], help="Limit inspection to one or more dataset keys or measurement names.")
    influx_inspect.add_argument("--verbose", action="store_true")

    influx_sample = subparsers.add_parser("influx-rollup-sample", help="Fetch latest sample points from the InfluxDB rollup bucket")
    influx_sample.add_argument("--app-root", default=str(Path(__file__).resolve().parents[2]))
    influx_sample.add_argument("--dataset", action="append", default=[], help="Limit sample output to one or more dataset keys or measurement names.")
    influx_sample.add_argument("--limit", type=int, default=5, help="Number of latest points to return per measurement.")
    influx_sample.add_argument("--group-by", action="append", default=[], help="Optional tag keys to group summary output by.")
    influx_sample.add_argument("--verbose", action="store_true")
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    command = args.command or "supervisor"
    if command == "worker-pool":
        return run_worker_pool([
            "--app-root", args.app_root,
            "--worker-id", args.worker_id,
            "--queues", args.queues,
            "--workload-classes", args.workload_classes,
            *( ["--once"] if args.once else [] ),
            *( ["--verbose"] if args.verbose else [] ),
        ])
    if command == "zkill-worker":
        return run_zkill_worker([
            "--app-root", args.app_root,
            "--poll-sleep", str(args.poll_sleep),
            *( ["--once"] if args.once else [] ),
            *( ["--verbose"] if args.verbose else [] ),
        ])
    if command == "rebuild-data-model":
        return run_rebuild_data_model([
            "--app-root", args.app_root,
            "--mode", args.mode,
            "--window-days", str(args.window_days),
            *( ["--full-reset"] if args.full_reset else [] ),
            *( ["--enable-partitioned-history"] if args.enable_partitioned_history else ["--disable-partitioned-history"] ),
        ])
    if command == "influx-rollup-export":
        return run_influx_rollup_export([
            "--app-root", args.app_root,
            *(sum([["--dataset", dataset] for dataset in args.dataset], [])),
            *( ["--full"] if args.full else [] ),
            *( ["--dry-run"] if args.dry_run else [] ),
            *( ["--batch-size", str(args.batch_size)] if args.batch_size > 0 else [] ),
            *( ["--verbose"] if args.verbose else [] ),
        ])
    if command == "influx-rollup-inspect":
        return run_influx_rollup_inspect([
            "--app-root", args.app_root,
            *(sum([["--dataset", dataset] for dataset in args.dataset], [])),
            *( ["--verbose"] if args.verbose else [] ),
        ])
    if command == "influx-rollup-sample":
        return run_influx_rollup_sample([
            "--app-root", args.app_root,
            *(sum([["--dataset", dataset] for dataset in args.dataset], [])),
            "--limit", str(args.limit),
            *(sum([["--group-by", group] for group in args.group_by], [])),
            *( ["--verbose"] if args.verbose else [] ),
        ])

    app_root = Path(args.app_root).resolve()
    config = load_php_runtime_config(app_root)
    logger = configure_logging(verbose=args.verbose, log_file=config.log_file)
    return run_supervisor(app_root=app_root, logger=logger)
