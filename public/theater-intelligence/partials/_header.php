<section class="surface-primary">
    <a href="/theater-intelligence" class="text-sm text-accent">&#8592; Back to Theater Overview</a>

    <div class="mt-3 flex items-start justify-between gap-4">
        <div class="flex-1">
            <p class="text-xs uppercase tracking-[0.16em] text-muted">Battle Intelligence — Theater Detail</p>
            <h1 class="mt-1 text-2xl font-semibold text-slate-50">
                <?= htmlspecialchars((string) ($theater['primary_system_name'] ?? 'Unknown'), ENT_QUOTES) ?>
                <?php if ((int) ($theater['system_count'] ?? 0) > 1): ?>
                    <span class="text-sm text-muted font-normal">+<?= (int) ($theater['system_count'] ?? 0) - 1 ?> systems</span>
                <?php endif; ?>
            </h1>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-base">
                <span class="text-blue-300 font-semibold"><?= htmlspecialchars($sideLabels['friendly'] ?? 'Friendlies', ENT_QUOTES) ?></span>
                <span class="text-[10px] uppercase tracking-wider bg-blue-900/60 text-blue-300 rounded-full px-1.5 py-0.5">Friendly</span>
                <span class="text-slate-500">vs</span>
                <span class="text-red-300 font-semibold"><?= htmlspecialchars($sideLabels['opponent'] ?? 'Opposition', ENT_QUOTES) ?></span>
                <span class="text-[10px] uppercase tracking-wider bg-red-900/60 text-red-300 rounded-full px-1.5 py-0.5">Hostile</span>
                <?php $thirdPartyCount = count($sideAlliancesByPilots['third_party'] ?? []); ?>
                <?php if ($thirdPartyCount > 0): ?>
                    <span class="text-slate-400 text-sm">(+<?= $thirdPartyCount ?> unidentified)</span>
                <?php endif; ?>
            </div>
            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
                <span><?= htmlspecialchars((string) ($theater['region_name'] ?? ''), ENT_QUOTES) ?></span>
                <span><?= htmlspecialchars($theaterStartActual, ENT_QUOTES) ?> — <?= htmlspecialchars($theaterEndActual, ENT_QUOTES) ?></span>
                <span>Friendly: <?= number_format(count($sideAlliancesByPilots['friendly'] ?? [])) ?> alliances</span>
                <span>Hostile: <?= number_format(count($sideAlliancesByPilots['opponent'] ?? [])) ?> alliances</span>
            </div>
        </div>
    </div>

    <div class="mt-3 grid gap-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6">
        <div class="surface-tertiary">
            <p class="text-xs text-muted">Battles</p>
            <p class="text-lg text-slate-50 font-semibold"><?= (int) ($theater['battle_count'] ?? 0) ?></p>
        </div>
        <div class="surface-tertiary">
            <p class="text-xs text-muted">Systems</p>
            <p class="text-lg text-slate-50 font-semibold"><?= (int) ($theater['system_count'] ?? 0) ?></p>
        </div>
        <div class="surface-tertiary">
            <p class="text-xs text-muted">Unique Pilots</p>
            <p class="text-lg text-slate-50 font-semibold"><?= number_format((int) ($theater['participant_count'] ?? 0)) ?></p>
        </div>
        <div class="surface-tertiary">
            <p class="text-xs text-muted">Distinct Killmails</p>
            <p class="text-lg text-slate-50 font-semibold"><?= number_format($displayKillTotal) ?></p>
            <?php if ($reportedKillTotal !== $observedKillTotal): ?>
                <p class="mt-1 text-[10px] text-muted">Stored: <?= number_format($reportedKillTotal) ?> · Observed: <?= number_format($observedKillTotal) ?></p>
            <?php endif; ?>
        </div>
        <div class="surface-tertiary">
            <p class="text-xs text-muted">Duration</p>
            <p class="text-lg text-slate-50 font-semibold"><?= $durationLabel ?></p>
        </div>
        <div class="surface-tertiary">
            <p class="text-xs text-muted">ISK Destroyed</p>
            <p class="text-lg text-slate-50 font-semibold"><?= supplycore_format_isk($totalIskDestroyed) ?></p>
        </div>
    </div>

    <?php if ($dataQualityNotes !== []): ?>
        <div class="mt-3 rounded-lg border border-amber-400/30 bg-amber-500/10 p-3">
            <p class="text-xs uppercase tracking-[0.15em] text-amber-300">Data quality notes</p>
            <ul class="mt-1 list-disc space-y-1 pl-5 text-sm text-amber-100/90">
                <?php foreach ($dataQualityNotes as $note): ?>
                    <li><?= htmlspecialchars($note, ENT_QUOTES) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</section>
