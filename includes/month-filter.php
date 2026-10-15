<?php
/**
 * Month filter helpers, shared by the dashboard, the expense records page and
 * api/category-data.php so all three filter the same way.
 *
 * The month travels as ?month=YYYY-MM. A missing or invalid value means "all months".
 */

/**
 * Reads ?month= and turns it into a date range.
 * Returns: selected (YYYY-MM or ''), sql (extra WHERE fragment), types (bind types), params.
 * The range is [first day of month, first day of next month) so an index on
 * expense_date can still be used.
 */
function ecx_month_range(): array
{
    $selected = $_GET['month'] ?? '';
    if (is_string($selected) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $selected)) {
        $start = new DateTime($selected . '-01');
        $end   = (clone $start)->modify('+1 month');
        return [
            'selected' => $selected,
            'sql'      => ' AND expense_date >= ? AND expense_date < ?',
            'types'    => 'ss',
            'params'   => [$start->format('Y-m-d'), $end->format('Y-m-d')],
        ];
    }
    return ['selected' => '', 'sql' => '', 'types' => '', 'params' => []];
}

/** Human label for a YYYY-MM value, e.g. "October 2026". */
function ecx_month_label(string $ym): string
{
    $d = DateTime::createFromFormat('!Y-m', $ym);
    return $d ? $d->format('F Y') : $ym;
}

/**
 * Month range plus the list of months (newest first) that have personal expenses,
 * ready for the dropdown. Shared-record expenses are excluded, like everywhere else
 * on the personal pages.
 */
function ecx_month_filter(mysqli $conn, int $user_id): array
{
    $mf = ecx_month_range();

    $options = [];
    $stmt = $conn->prepare(
        "SELECT DISTINCT DATE_FORMAT(expense_date, '%Y-%m') AS ym
           FROM expenses
          WHERE user_id = ? AND shared_record_id IS NULL
       ORDER BY ym DESC"
    );
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if ($row['ym']) {
                $options[$row['ym']] = ecx_month_label($row['ym']);
            }
        }
        $stmt->close();
    }

    // Keep a manually typed ?month= selectable even if it has no expenses.
    if ($mf['selected'] !== '' && !isset($options[$mf['selected']])) {
        $options[$mf['selected']] = ecx_month_label($mf['selected']);
        krsort($options);
    }

    $mf['options'] = $options;
    return $mf;
}

/** Prints the month dropdown (auto-submits on change; works without JS via the button). */
function ecx_render_month_filter(array $mf): void
{
    ?>
    <form method="get" class="month-filter">
        <label class="sr-only" for="monthFilter">Filter by month</label>
        <svg class="month-filter-icon" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="16" y1="2" x2="16" y2="6"></line>
            <line x1="8" y1="2" x2="8" y2="6"></line>
            <line x1="3" y1="10" x2="21" y2="10"></line>
        </svg>
        <select id="monthFilter" name="month" onchange="this.form.submit()">
            <option value="">All months</option>
            <?php foreach ($mf['options'] as $ym => $label): ?>
                <option value="<?= htmlspecialchars($ym) ?>"<?= $ym === $mf['selected'] ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="btn btn-secondary">Apply</button></noscript>
    </form>
    <?php
}
