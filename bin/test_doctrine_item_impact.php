<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

function guardrail_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$fits = [
    [
        'id' => 101,
        'fit_name' => 'Naglfar Armor',
        'ship_type_id' => 19720,
        'group_names' => ['Capital Spearhead'],
        'supply' => [
            'recommended_target_fit_count' => 2,
            'complete_fits_available' => 0,
            'bottleneck_type_id' => 9001,
            'bottleneck_is_stock_tracked' => true,
            'recent_hull_losses_24h' => 1,
            'recent_hull_losses_7d' => 1,
            'recent_item_fit_losses_24h' => 0,
            'recent_item_fit_losses_7d' => 1,
            'resupply_pressure_state' => 'resupply_soon',
            'readiness_trend_direction' => 'down',
        ],
    ],
    [
        'id' => 202,
        'fit_name' => 'Muninn Fleet',
        'ship_type_id' => 12015,
        'group_names' => ['Muninn Mainline'],
        'supply' => [
            'recommended_target_fit_count' => 17,
            'complete_fits_available' => 15,
            'bottleneck_type_id' => 9200,
            'bottleneck_is_stock_tracked' => true,
            'recent_hull_losses_24h' => 3,
            'recent_hull_losses_7d' => 8,
            'recent_item_fit_losses_24h' => 1,
            'recent_item_fit_losses_7d' => 3,
            'resupply_pressure_state' => 'elevated',
            'readiness_trend_direction' => 'flat',
        ],
    ],
];

$itemsByFitId = [
    101 => [
        ['doctrine_fit_id' => 101, 'type_id' => 9001, 'item_name' => 'Capital Armor Rig I', 'quantity' => 1, 'is_stock_tracked' => true, 'source_role' => 'fit', 'slot_category' => 'Rig Slots'],
        ['doctrine_fit_id' => 101, 'type_id' => 9100, 'item_name' => 'Slave Alpha', 'quantity' => 1, 'is_stock_tracked' => true, 'source_role' => 'implant', 'slot_category' => 'Implants'],
    ],
    202 => [
        ['doctrine_fit_id' => 202, 'type_id' => 9200, 'item_name' => '720mm Howitzer Artillery II', 'quantity' => 6, 'is_stock_tracked' => true, 'source_role' => 'fit', 'slot_category' => 'High Slots'],
    ],
];

$marketByTypeId = [
    9001 => ['alliance_total_sell_volume' => 0],
    9100 => ['alliance_total_sell_volume' => 1],
    9200 => ['alliance_total_sell_volume' => 96],
];

$metadataByType = [
    19720 => ['type_id' => 19720, 'type_name' => 'Naglfar', 'group_name' => 'Dreadnought', 'category_name' => 'Ship', 'market_group_name' => 'Dreadnoughts', 'market_group_path_names' => ['Ships', 'Capital Ships', 'Dreadnoughts']],
    12015 => ['type_id' => 12015, 'type_name' => 'Muninn', 'group_name' => 'Heavy Assault Cruiser', 'category_name' => 'Ship', 'market_group_name' => 'Heavy Assault Cruisers', 'market_group_path_names' => ['Ships', 'Cruisers']],
];

$impact = buy_all_item_impact_map($fits, $itemsByFitId, $marketByTypeId, $metadataByType);

$capitalRig = $impact[9001] ?? [];
guardrail_assert((int) ($capitalRig['deterministic_blocked_fits'] ?? -1) === 2, 'Capital rig blocked fits should match its own 2-fit target shortfall.');
guardrail_assert((int) ($capitalRig['deterministic_blocked_fits'] ?? 0) <= (int) ($capitalRig['total_target_shortfall'] ?? 0), 'Blocked fits must never exceed target shortfall.');
guardrail_assert((int) ($capitalRig['valid_doctrine_count'] ?? 0) === 1 && (int) ($capitalRig['valid_fits_count'] ?? 0) === 1, 'Capital rig should only count its valid doctrine/fit relationship.');
guardrail_assert((string) ($capitalRig['hull_class'] ?? '') === 'capital', 'Capital module should retain capital hull class.');

$implant = $impact[9100] ?? [];
guardrail_assert((int) ($implant['deterministic_blocked_fits'] ?? -1) === 0, 'Implant should not become blocked when another item is the true bottleneck.');
guardrail_assert((int) ($implant['deterministic_blocked_fits'] ?? 0) < 17, 'Implant should not inherit fleet-scale blocked-fit counts from unrelated subcaps.');

$subcapGun = $impact[9200] ?? [];
guardrail_assert((int) ($subcapGun['deterministic_blocked_fits'] ?? -1) === 0, 'Subcap weapon should not be marked blocking when it is above current fit-ready capacity.');
guardrail_assert((int) ($subcapGun['exact_deficit_quantity'] ?? 0) === 6, 'Exact deficit quantity should still reflect the truthful module deficit for the remaining target.');
guardrail_assert((int) ($subcapGun['valid_doctrine_count'] ?? 0) === 1, 'Doctrine impact should count only valid doctrine relationships.');

$capitalProfile = doctrine_recommended_target_fit_count(
    ['complete_fits_available' => 0],
    ['direction' => 'down'],
    ['hull_losses_7d' => 1, 'item_equivalent_fit_losses_7d' => 1, 'hull_losses_24h' => 1, 'item_equivalent_fit_losses_24h' => 0],
    ['depletion_signal' => ['classification' => 'stable', 'fit_equivalent_7d' => 0.0]],
    'capital'
);
$subcapProfile = doctrine_recommended_target_fit_count(
    ['complete_fits_available' => 0],
    ['direction' => 'down'],
    ['hull_losses_7d' => 1, 'item_equivalent_fit_losses_7d' => 1, 'hull_losses_24h' => 1, 'item_equivalent_fit_losses_24h' => 0],
    ['depletion_signal' => ['classification' => 'stable', 'fit_equivalent_7d' => 0.0]],
    'subcap'
);
guardrail_assert(
    (int) ($capitalProfile['recommended_target_fit_count'] ?? 0) < (int) ($subcapProfile['recommended_target_fit_count'] ?? 0),
    'Capital readiness targets must stay lower than subcap readiness targets under comparable pressure.'
);

fwrite(STDOUT, "Doctrine item impact guardrails passed.\n");
