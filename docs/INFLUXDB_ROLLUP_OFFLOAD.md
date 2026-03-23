# InfluxDB historical rollup offload plan

SupplyCore keeps **MariaDB as the canonical operational store and source of truth**. This document defines a secondary InfluxDB path for **historical rollups only** so long-range trend pages can move cold analytical reads away from MariaDB without changing transactional behavior.

## 1. Rollup audit and classification

### Keep in MariaDB only

These remain authoritative in MariaDB and are **not** exported to InfluxDB in this pass:

- Current/latest operational projections and mutable state:
  - `market_order_current_projection`
  - `market_source_snapshot_state`
  - `market_orders_current`
  - `worker_jobs`, `sync_state`, `sync_runs`, scheduler tables
- Control-plane and settings/config data:
  - `app_settings`
  - `trading_stations`
  - auth/session/config metadata
- Raw and near-raw history that may still need joins or forensic replay:
  - `market_orders_history`
  - `market_history_daily`
  - `market_hub_local_history_daily`
  - `killmail_events`, `killmail_items`, `killmail_attackers`, payload tables
  - `doctrine_activity_snapshots`, `doctrine_fit_snapshots`, `item_priority_snapshots`
- Join-heavy page reads and operational workflows:
  - settings pages
  - current dashboard cards
  - doctrine management/import flows
  - killmail detail, fit detail, and operational queue state

### Good candidates for InfluxDB rollup export

These tables already represent bounded, append-mostly analytical series and are the **recommended offload set**:

| MariaDB table | Recommendation | Why |
|---|---|---|
| `market_item_price_1h` | Export | Stable hourly trend series keyed by source/type/time. |
| `market_item_price_1d` | Export | Long-range daily price history is a classic time-series read. |
| `market_item_stock_1h` | Export | Useful for stock trend charts, not transactional reads. |
| `market_item_stock_1d` | Export | Good long-term capacity/depth analytics input. |
| `killmail_item_loss_1h` | Export | Hourly loss pressure/trend series. |
| `killmail_item_loss_1d` | Export | Daily trend analytics over long windows. |
| `killmail_hull_loss_1d` | Export | Hull loss history fits time-series semantics well. |
| `killmail_doctrine_activity_1d` | Export | Daily doctrine activity signal for long-range views. |
| `doctrine_fit_activity_1d` | Export | Daily fit readiness/activity history. |
| `doctrine_group_activity_1d` | Export | Group-level long-range readiness/activity trends. |
| `doctrine_fit_stock_pressure_1d` | Export | Daily stock pressure signal for doctrine trend pages. |

### Not suitable for InfluxDB in this pass

- `doctrine_item_stock_1d`: high-cardinality per-fit/per-item series is useful, but it is more expensive and should stay MariaDB-only until the first export path is proven.
- `market_order_snapshot_rollup_1h` / `market_order_snapshot_rollup_1d`: richer derived rollups that are still close to authoritative historical reconstruction. Keep them in MariaDB for now.
- “Dashboard trend series” as a separate export class: later dashboard trend charts should query the exported measurements below rather than duplicating another dashboard-specific measurement.

## 2. InfluxDB measurement model

### Measurement design principles

- Use **one measurement per rollup family**, not one measurement per page.
- Encode granularity in a `window` tag (`1h`, `1d`) so hourly and daily points can share schema.
- Keep tags limited to dimensions already present in the MariaDB rollup keys.
- Keep numeric analytics in fields.
- Use the MariaDB rollup `bucket_start` as the Influx timestamp.
- Keep the exporter idempotent by writing the full field set for a point every time it is re-exported.

### Proposed measurements

| Measurement | MariaDB source(s) | Tags | Fields | Timestamp source | Retention expectation |
|---|---|---|---|---|---|
| `market_item_price` | `market_item_price_1h`, `market_item_price_1d` | `window`, `source_type`, `source_id`, `type_id` | `sample_count`, `listing_count_sum`, `avg_price_sum`, `weighted_price_numerator`, `weighted_price_denominator`, `listing_count`, `min_price`, `max_price`, `avg_price`, `weighted_price` | `bucket_start` | Keep daily long-term, hourly medium-term. |
| `market_item_stock` | `market_item_stock_1h`, `market_item_stock_1d` | `window`, `source_type`, `source_id`, `type_id` | `sample_count`, `stock_units_sum`, `listing_count_sum`, `local_stock_units`, `listing_count` | `bucket_start` | Keep daily long-term, hourly medium-term. |
| `killmail_item_loss` | `killmail_item_loss_1h`, `killmail_item_loss_1d` | `window`, `type_id`, `doctrine_fit_id`, `doctrine_group_id`, `hull_type_id` | `loss_count`, `quantity_lost`, `victim_count`, `killmail_count` | `bucket_start` | Keep daily long-term, hourly medium-term. |
| `killmail_hull_loss` | `killmail_hull_loss_1d` | `window`, `hull_type_id`, `doctrine_fit_id`, `doctrine_group_id` | `loss_count`, `victim_count`, `killmail_count` | `bucket_start` | Keep long-term. |
| `killmail_doctrine_activity` | `killmail_doctrine_activity_1d` | `window`, `doctrine_fit_id`, `doctrine_group_id`, `hull_type_id` | `loss_count`, `quantity_lost`, `victim_count`, `killmail_count` | `bucket_start` | Keep long-term. |
| `doctrine_fit_activity` | `doctrine_fit_activity_1d` | `window`, `fit_id`, `doctrine_group_id`, `hull_type_id` | `hull_loss_count`, `doctrine_item_loss_count`, `complete_fits_available`, `target_fits`, `fit_gap`, `priority_score`, `readiness_state`, `resupply_pressure` | `bucket_start` | Keep long-term. |
| `doctrine_group_activity` | `doctrine_group_activity_1d` | `window`, `group_id` | `hull_loss_count`, `doctrine_item_loss_count`, `complete_fits_available`, `target_fits`, `fit_gap`, `priority_score`, `readiness_state`, `resupply_pressure` | `bucket_start` | Keep long-term. |
| `doctrine_fit_stock_pressure` | `doctrine_fit_stock_pressure_1d` | `window`, `fit_id`, `doctrine_group_id`, `bottleneck_type_id` | `complete_fits_available`, `target_fits`, `fit_gap`, `bottleneck_quantity`, `readiness_state`, `resupply_pressure` | `bucket_start` | Keep long-term. |

## 3. Export pipeline

### Implemented command

A new Python command is included:

```bash
python bin/python_orchestrator.py influx-rollup-export --app-root /var/www/SupplyCore
```

Behavior:

- Reads selected MariaDB rollup tables in batches.
- Encodes points into InfluxDB line protocol.
- Writes to InfluxDB v2 over `/api/v2/write`.
- Uses `sync_state` for checkpoints with dataset keys prefixed by `influx.rollup_export.`.
- Uses `sync_runs` for per-run observability.
- Replays a configurable overlap window on incremental runs for retry safety.
- Supports `--dataset`, `--full`, and `--dry-run` modes.

### Checkpointing model

For each exported dataset, the exporter stores:

- `sync_state.dataset_key = influx.rollup_export.<table_name>`
- `last_cursor = max(updated_at)` observed in the successful export
- `last_success_at` and `last_row_count`
- run history in `sync_runs`

Incremental runs do **not** trust the cursor exactly. They rewind by `influxdb.export_overlap_seconds` before reading again. That overlap keeps the pipeline retry-safe when:

- MariaDB updates existing buckets after initial insert,
- the exporter is interrupted after partial writes,
- a previous run wrote to InfluxDB but failed before its MariaDB checkpoint was saved.

### Idempotency notes

Practical idempotency is achieved through:

- deterministic timestamp = `bucket_start`,
- deterministic tag set = rollup primary key dimensions,
- writing the **complete field set** for each point,
- overlap-based re-export of recent windows.

That means duplicate exports of the same point are safe and late updates overwrite the same logical point.

## 4. MariaDB read/write semantics remain clean

This design intentionally does **not** move:

- current/latest operational tables,
- settings and control-plane state,
- raw transactional history,
- raw killmail payloads,
- join-heavy app logic,
- authoritative rebuild/reconciliation workflows.

MariaDB remains the canonical write path and the authoritative replay source.

## 5. Read-path strategy

### Stay on MariaDB

- dashboard summary cards that depend on the latest materialized snapshot
- current market comparison and operational “what is true now?” pages
- doctrine fit/group detail management
- settings and scheduler/admin surfaces
- raw killmail inspection and detail pages

### Later candidates for InfluxDB reads

- long-range item price trend charts
- long-range item stock depth charts
- doctrine activity charts over months/quarters
- killmail-derived hourly/daily trend pages
- dashboard trend visualizations that only need historical points, not joins back into mutable operational state

### Hybrid pattern to prefer later

- **MariaDB** for entity metadata, labels, permissions, and current-state cards
- **InfluxDB** for the historical point series feeding charts
- application layer joins series back onto MariaDB metadata by `type_id`, `fit_id`, `group_id`, or source identifiers

## 6. Retention model

### MariaDB

Keep:

- current/latest operational tables indefinitely
- raw history for the operational/authoritative window already needed for rebuild/replay
- rollup tables as the authoritative derived source through the migration period

Recommended operational target after the Influx path is proven:

- hourly rollups in MariaDB: keep roughly **30–90 days**
- daily rollups in MariaDB: keep roughly **180–365 days**
- raw history tables: keep according to existing operational rebuild requirements

### InfluxDB

Recommended buckets/retention:

- `supplycore_rollups_hot` (optional later split): hourly rollups retained **180–365 days**
- `supplycore_rollups` or cold bucket: daily rollups retained **3–5 years** or longer as storage allows

A simpler first pass is one bucket named `supplycore_rollups` with no aggressive expiry until the read path is validated.

### MariaDB pruning rule

Do **not** prune MariaDB rollup rows until all of the following are true:

1. export runs are consistently successful,
2. spot-check counts and representative chart queries match,
3. a rollback-tested backfill path exists,
4. target pages have been switched to InfluxDB reads where appropriate.

## 7. Operational setup

### Host deployment plan

Run InfluxDB on the same host as a **separate service**, but keep it logically secondary:

- package/service: InfluxDB 2.x
- default data path: `/var/lib/influxdb2`
- config path: `/etc/influxdb/config.toml`
- SupplyCore export env/config in `src/config/local.php` or environment variables

### Added systemd units

This repository now includes:

- `ops/systemd/supplycore-influx-rollup-export.service`
- `ops/systemd/supplycore-influx-rollup-export.timer`
- `ops/systemd/supplycore-influx-rollup-export.env.example`

Recommended cadence:

- timer every 15 minutes for incremental export
- use `--full` only for initial backfill or repair

### Config/env approach

Configure exporter access in `src/config/local.php`:

```php
'influxdb' => [
    'enabled' => true,
    'url' => 'http://127.0.0.1:8086',
    'org' => 'supplycore',
    'bucket' => 'supplycore_rollups',
    'token' => '...token...',
    'timeout_seconds' => 15,
    'export_batch_size' => 1000,
    'export_overlap_seconds' => 21600,
],
```

### Backup / restore

- MariaDB backups remain mandatory because MariaDB is still authoritative.
- InfluxDB backups are useful for fast chart recovery, but they are secondary and can be rebuilt by replaying MariaDB rollups.
- For restore drills:
  1. restore MariaDB,
  2. restore InfluxDB if available,
  3. otherwise run `influx-rollup-export --full` to repopulate the Influx bucket.

### Health verification

After deployment verify:

```bash
systemctl status influxdb
systemctl status supplycore-influx-rollup-export.timer
systemctl list-timers supplycore-influx-rollup-export.timer
python bin/python_orchestrator.py influx-rollup-export --dry-run --verbose
python bin/python_orchestrator.py influx-rollup-export --dataset market_item_price_1d --verbose
mysql -e "SELECT dataset_key, status, last_success_at, last_cursor, last_row_count FROM sync_state WHERE dataset_key LIKE 'influx.rollup_export.%' ORDER BY dataset_key;" supplycore
mysql -e "SELECT dataset_key, run_status, started_at, finished_at, source_rows, written_rows FROM sync_runs WHERE dataset_key LIKE 'influx.rollup_export.%' ORDER BY id DESC LIMIT 20;" supplycore
```

## 8. Phased rollout plan

### Phase 1: Export-only, no read-path change

- Enable InfluxDB.
- Run exporter on `market_item_price_*` and `market_item_stock_*` first.
- Validate counts, point coverage, and chart parity.
- Keep all application reads on MariaDB.

### Phase 2: Add killmail and doctrine daily series

- Enable killmail and doctrine daily rollup exports.
- Validate long-range dashboards and doctrine trend views against MariaDB baselines.

### Phase 3: Shift selected chart pages

- Move long-range trend/history charts to InfluxDB-backed reads.
- Keep current-state cards and drill-down joins on MariaDB.

### Phase 4: Prune only after confidence

- Reduce MariaDB hourly retention first.
- Later reduce MariaDB daily rollup retention only if rollback/backfill is proven.

## 9. Risks and rollback

### Risks

- high-cardinality growth if too many item/fit dimensions are exported without discipline,
- silent divergence if late MariaDB rollup corrections are not replayed,
- chart inconsistency during the dual-read migration period,
- operational confusion if teams forget MariaDB is still authoritative.

### Mitigations

- start with selected rollup families only,
- use overlap-based incremental exports,
- keep MariaDB as the only write authority,
- keep page migrations explicit and per-surface,
- make exporter health visible through `sync_state` and `sync_runs`.

### Rollback

Rollback is simple because no authoritative writes move to InfluxDB:

1. disable the timer/service,
2. switch any migrated chart pages back to MariaDB queries,
3. keep MariaDB rollups as the authoritative fallback,
4. optionally drop/recreate the Influx bucket and re-export later.

InfluxDB failure does not block SupplyCore’s operational behavior.
