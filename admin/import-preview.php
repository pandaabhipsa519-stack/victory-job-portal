<?php
require_once __DIR__ . "/../includes/session.php";
requireAdmin();
require_once __DIR__ . "/../includes/csv-import.php";

$stage = $_SESSION['phase2_import'] ?? null;
$summary = $_SESSION['phase2_import_summary'] ?? null;
unset($_SESSION['phase2_import_summary']);
if (!$stage || time() - (int) ($stage['created_at'] ?? 0) > 1800) {
    unset($_SESSION['phase2_import']);
    $stage = null;
}
$counts = $stage ? importRowCounts($stage['rows']) : ['insert' => 0, 'skip' => 0, 'reject' => 0];
include __DIR__ . "/../includes/header.php";
?>
<main class="page-section">
    <div class="page-heading">
        <p class="eyebrow">Admin data import</p>
        <h1>CSV import preview</h1>
    </div>
    <?php if ($summary): ?>
        <section class="job-detail-card" role="status">
            <h2>Import complete</h2>
            <p>Inserted: <?php echo (int) $summary['insert']; ?> · Skipped: <?php echo (int) $summary['skip']; ?> · Rejected: <?php echo (int) $summary['reject']; ?></p>
            <p>Setup emails sent: <?php echo (int) $summary['emails_sent']; ?> · Email failures: <?php echo (int) $summary['emails_failed']; ?></p>
        </section>
    <?php endif; ?>
    <?php if (!$stage): ?>
        <p>No unexpired import preview is available.</p>
        <a href="manage-students.php">Return to account management</a>
    <?php else: ?>
        <h2><?php echo e(ucfirst($stage['type'])); ?> import</h2>
        <p>Will insert: <?php echo $counts['insert']; ?> · Duplicate skips: <?php echo $counts['skip']; ?> · Rejected: <?php echo $counts['reject']; ?></p>
        <div class="table-responsive">
            <table class="app-table">
                <thead><tr><th>CSV row</th><th>Result</th><th>Validation messages</th></tr></thead>
                <tbody>
                <?php foreach ($stage['rows'] as $row): ?>
                    <tr>
                        <td><?php echo (int) $row['row']; ?></td>
                        <td><?php echo !$row['errors'] ? 'Will insert' : (!empty($row['duplicate']) ? 'Skip' : 'Reject'); ?></td>
                        <td><?php echo e(implode(' ', $row['errors'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <form method="POST" action="import-commit.php">
            <?php echo csrfInput(); ?>
            <button type="submit" <?php echo $counts['insert'] < 1 ? 'disabled' : ''; ?>>Commit valid rows</button>
            <a href="import.php?type=<?php echo e($stage['type']); ?>">Discard and upload another file</a>
        </form>
    <?php endif; ?>
</main>
<?php include __DIR__ . "/../includes/footer.php"; ?>
