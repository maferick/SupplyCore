<?php

$summary = is_array($summary ?? null) ? $summary : [];
$tableColumns = is_array($tableColumns ?? null) ? $tableColumns : [];
$tableRows = is_array($tableRows ?? null) ? $tableRows : [];
$emptyMessage = is_string($emptyMessage ?? null) ? $emptyMessage : 'No records available yet.';
$filterFields = is_array($filterFields ?? null) ? $filterFields : [];
$filterAction = is_string($filterAction ?? null) ? $filterAction : current_path();
?>
<?php if ($filterFields !== []): ?>
    <section class="mb-6 rounded-xl border border-border bg-card p-4">
        <form method="get" action="<?= htmlspecialchars($filterAction, ENT_QUOTES) ?>" class="grid gap-4 md:grid-cols-4 md:items-end">
            <?php foreach ($filterFields as $field): ?>
                <?php
                $key = (string) ($field['key'] ?? '');
                $label = (string) ($field['label'] ?? $key);
                $value = (string) ($field['value'] ?? '');
                $options = is_array($field['options'] ?? null) ? $field['options'] : [];
                ?>
                <?php if ($key !== ''): ?>
                    <label class="block text-sm text-muted">
                        <span class="mb-1 block text-xs uppercase tracking-[0.15em]"><?= htmlspecialchars($label, ENT_QUOTES) ?></span>
                        <select name="<?= htmlspecialchars($key, ENT_QUOTES) ?>" class="w-full rounded-lg border border-border bg-black/20 px-3 py-2 text-slate-100">
                            <?php foreach ($options as $optionValue => $optionLabel): ?>
                                <option value="<?= htmlspecialchars((string) $optionValue, ENT_QUOTES) ?>" <?= (string) $optionValue === $value ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $optionLabel, ENT_QUOTES) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>
            <?php endforeach; ?>
            <button type="submit" class="rounded-lg border border-border bg-accent/30 px-4 py-2 text-sm text-white hover:bg-accent/50">Apply filters</button>
        </form>
    </section>
<?php endif; ?>

<?php if ($summary !== []): ?>
    <section class="grid gap-4 md:grid-cols-3">
        <?php foreach ($summary as $card): ?>
            <article class="rounded-xl border border-border bg-card p-5 shadow-lg shadow-black/20">
                <p class="text-xs uppercase tracking-[0.2em] text-muted"><?= htmlspecialchars((string) ($card['label'] ?? ''), ENT_QUOTES) ?></p>
                <p class="mt-2 text-2xl font-semibold"><?= htmlspecialchars((string) ($card['value'] ?? ''), ENT_QUOTES) ?></p>
                <p class="mt-2 text-sm text-muted"><?= htmlspecialchars((string) ($card['context'] ?? ''), ENT_QUOTES) ?></p>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<section class="mt-6 rounded-xl border border-border bg-card p-6">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-border text-sm">
            <thead>
            <tr class="text-left text-xs uppercase tracking-[0.15em] text-muted">
                <?php foreach ($tableColumns as $column): ?>
                    <th class="px-3 py-2 font-medium"><?= htmlspecialchars((string) $column, ENT_QUOTES) ?></th>
                <?php endforeach; ?>
            </tr>
            </thead>
            <tbody class="divide-y divide-border/70">
            <?php if ($tableRows === []): ?>
                <tr>
                    <td class="px-3 py-6 text-muted" colspan="<?= max(1, count($tableColumns)) ?>"><?= htmlspecialchars($emptyMessage, ENT_QUOTES) ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($tableRows as $row): ?>
                    <tr class="text-slate-200">
                        <?php foreach (array_keys($tableColumns) as $key): ?>
                            <td class="px-3 py-3"><?= htmlspecialchars((string) ($row[$key] ?? ''), ENT_QUOTES) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
