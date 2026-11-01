<?php
require_once __DIR__ . "/../includes/session.php";
requireAdmin();

$query = inputString($_GET['q'] ?? '', 100);
$status = inputString($_GET['status'] ?? '', 20);
if (!in_array($status, ['', 'active', 'inactive'], true)) {
    $status = '';
}
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = $page === false ? 1 : $page;
$limit = 20;
$where = [];
$values = [];
$types = '';
if ($query !== '') {
    $where[] = "(company_name LIKE ? OR industry LIKE ? OR official_email LIKE ? OR location LIKE ?)";
    $like = '%' . $query . '%';
    $values = [$like, $like, $like, $like];
    $types = 'ssss';
}
if ($status !== '') {
    $where[] = "status = ?";
    $values[] = $status;
    $types .= 's';
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$count = $conn->prepare("SELECT COUNT(*) AS total FROM companies" . $whereSql);
$countValues = $values;
bindStatementParams($count, $types, $countValues);
$count->execute();
$total = (int) $count->get_result()->fetch_assoc()['total'];
$pages = max(1, (int) ceil($total / $limit));
$page = min($page, $pages);
$offset = ($page - 1) * $limit;
$list = $conn->prepare("SELECT company_id,company_name,industry,location,official_email,status FROM companies" . $whereSql . " ORDER BY company_id DESC LIMIT ? OFFSET ?");
$listValues = $values;
$listValues[] = $limit;
$listValues[] = $offset;
bindStatementParams($list, $types . 'ii', $listValues);
$list->execute();
$companies = $list->get_result();
$flash = (string) ($_SESSION['management_flash'] ?? '');
unset($_SESSION['management_flash']);

include __DIR__ . "/../includes/header.php";
?>
<main class="page-section">
    <div class="page-heading">
        <p class="eyebrow">Admin directory</p>
        <h1>Manage Companies</h1>
    </div>
    <?php if ($flash !== ''): ?><p role="status"><?php echo e($flash); ?></p><?php endif; ?>
    <p><a class="button button-primary" href="company-form.php">Add company</a> <a href="import.php?type=company">Import CSV</a></p>
    <form method="GET" class="job-search-form">
        <label>Search <input type="search" name="q" value="<?php echo e($query); ?>" maxlength="100"></label>
        <label>Status
            <select name="status">
                <option value="">All statuses</option>
                <?php foreach (['active', 'inactive'] as $option): ?>
                    <option value="<?php echo e($option); ?>" <?php echo $status === $option ? 'selected' : ''; ?>><?php echo e(ucfirst($option)); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit">Filter</button>
    </form>
    <div class="table-responsive">
        <table class="app-table">
            <thead><tr><th>Company</th><th>Industry</th><th>Location</th><th>Official email</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php while ($company = $companies->fetch_assoc()): ?>
                <tr>
                    <td><?php echo e($company['company_name']); ?></td>
                    <td><?php echo e($company['industry']); ?></td>
                    <td><?php echo e($company['location']); ?></td>
                    <td><?php echo e($company['official_email']); ?></td>
                    <td><?php echo e($company['status']); ?></td>
                    <td>
                        <a href="company-details.php?id=<?php echo (int) $company['company_id']; ?>">Details</a>
                        <a href="company-form.php?id=<?php echo (int) $company['company_id']; ?>">Edit</a>
                        <form method="POST" action="company-status.php">
                            <?php echo csrfInput(); ?>
                            <input type="hidden" name="company_id" value="<?php echo (int) $company['company_id']; ?>">
                            <input type="hidden" name="status" value="<?php echo $company['status'] === 'active' ? 'inactive' : 'active'; ?>">
                            <button type="submit"><?php echo $company['status'] === 'active' ? 'Deactivate' : 'Reactivate'; ?></button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
            <?php if ($total === 0): ?><tr><td colspan="6">No matching companies found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
        <nav aria-label="Company pages">
            <?php for ($n = 1; $n <= $pages; $n++): ?>
                <a href="?<?php echo e(http_build_query(['q' => $query, 'status' => $status, 'page' => $n])); ?>" <?php echo $n === $page ? 'aria-current="page"' : ''; ?>><?php echo $n; ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
</main>
<?php include __DIR__ . "/../includes/footer.php"; ?>
