<?php
require_once __DIR__ . "/../includes/session.php";
requireAdmin();
require_once __DIR__ . "/../includes/admin-management.php";

$type = $directoryType ?? '';
$config = [
    'student' => ['title' => 'Manage Students', 'create' => 'account-form.php?type=student'],
    'professional' => ['title' => 'Manage Employees / Professionals', 'create' => 'account-form.php?type=professional'],
    'employer' => ['title' => 'Manage Employers / Recruiters', 'create' => 'account-form.php?type=employer'],
];
if (!isset($config[$type])) {
    http_response_code(404);
    exit("Directory not found.");
}
$title = $config[$type]['title'];
$query = inputString($_GET['q'] ?? '', 100);
$statusFilter = inputString($_GET['status'] ?? '', 20);
if (!in_array($statusFilter, ['', 'active', 'inactive', 'pending_setup'], true)) {
    $statusFilter = '';
}
$classification = $type === 'employer' ? '' : inputString($_GET['classification'] ?? $type, 20);
if ($type !== 'employer' && !in_array($classification, ['student', 'professional', 'unclassified'], true)) {
    $classification = $type;
}
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$page = $page === false ? 1 : $page;
$limit = 20;
$offset = ($page - 1) * $limit;
$values = [];
$types = '';
$where = [];

if ($type === 'employer') {
    $base = " FROM users u JOIN employers e ON e.user_id=u.user_id LEFT JOIN companies c ON c.company_id=e.company_id WHERE u.role='employer'";
    $searchSql = "(u.email LIKE ? OR e.recruiter_name LIKE ? OR e.company_name LIKE ?)";
    $select = "SELECT u.user_id,u.email,u.account_status,e.recruiter_name,e.designation,e.company_name,c.status AS company_status";
} else {
    $profileType = $classification;
    $base = " FROM users u JOIN job_seekers js ON js.user_id=u.user_id WHERE u.role='jobseeker' AND js.profile_type=?";
    $values[] = $profileType;
    $types .= 's';
    $searchSql = "(u.email LIKE ? OR js.name LIKE ? OR js.skills LIKE ? OR js.location LIKE ?)";
    $select = "SELECT u.user_id,u.email,u.account_status,js.name,js.phone,js.location,js.profile_type";
}

if ($query !== '') {
    $base .= " AND " . $searchSql;
    $like = '%' . $query . '%';
    $repeat = $type === 'employer' ? 3 : 4;
    for ($i = 0; $i < $repeat; $i++) {
        $values[] = $like;
        $types .= 's';
    }
}
if ($statusFilter !== '') {
    $base .= " AND u.account_status=?";
    $values[] = $statusFilter;
    $types .= 's';
}

$count = $conn->prepare("SELECT COUNT(*) AS total" . $base);
$countValues = $values;
bindStatementParams($count, $types, $countValues);
$count->execute();
$total = (int) $count->get_result()->fetch_assoc()['total'];
$pages = max(1, (int) ceil($total / $limit));
$page = min($page, $pages);
$offset = ($page - 1) * $limit;
$list = $conn->prepare($select . $base . " ORDER BY u.user_id DESC LIMIT ? OFFSET ?");
$listValues = $values;
$listValues[] = $limit;
$listValues[] = $offset;
bindStatementParams($list, $types . 'ii', $listValues);
$list->execute();
$rows = $list->get_result();
$flash = (string) ($_SESSION['management_flash'] ?? '');
unset($_SESSION['management_flash']);

include __DIR__ . "/../includes/header.php";
?>
<main class="page-section">
    <div class="page-heading">
        <p class="eyebrow">Admin directory</p>
        <h1><?php echo e($title); ?></h1>
    </div>
    <?php if ($flash !== ''): ?><p role="status"><?php echo e($flash); ?></p><?php endif; ?>
    <p>
        <a class="button button-primary" href="<?php echo e($config[$type]['create']); ?>">Add account</a>
        <a href="import.php?type=<?php echo e($type); ?>">Import CSV</a>
    </p>
    <form method="GET" class="job-search-form">
        <label>Search <input type="search" name="q" value="<?php echo e($query); ?>" maxlength="100"></label>
        <?php if ($type !== 'employer'): ?>
            <label>Profile classification
                <select name="classification">
                    <?php foreach (['student', 'professional', 'unclassified'] as $option): ?>
                        <option value="<?php echo e($option); ?>" <?php echo $classification === $option ? 'selected' : ''; ?>><?php echo e(ucfirst($option)); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <label>Account status
            <select name="status">
                <option value="">All statuses</option>
                <?php foreach (['active', 'inactive', 'pending_setup'] as $status): ?>
                    <option value="<?php echo e($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>><?php echo e(ucwords(str_replace('_', ' ', $status))); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit">Filter</button>
    </form>
    <div class="table-responsive">
        <table class="app-table">
            <thead>
            <tr>
                <th>Email</th>
                <?php if ($type === 'employer'): ?>
                    <th>Recruiter</th><th>Company</th><th>Company status</th>
                <?php else: ?>
                    <th>Name</th><th>Location</th><th>Profile type</th>
                <?php endif; ?>
                <th>Account status</th><th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = $rows->fetch_assoc()): ?>
                <tr>
                    <td><?php echo e($row['email']); ?></td>
                    <?php if ($type === 'employer'): ?>
                        <td><?php echo e($row['recruiter_name']); ?></td>
                        <td><?php echo e($row['company_name']); ?></td>
                        <td><?php echo e($row['company_status'] ?? 'Unlinked'); ?></td>
                    <?php else: ?>
                        <td><?php echo e($row['name']); ?></td>
                        <td><?php echo e($row['location']); ?></td>
                        <td><?php echo e($row['profile_type']); ?></td>
                    <?php endif; ?>
                    <td><?php echo e(str_replace('_', ' ', $row['account_status'])); ?></td>
                    <td>
                        <a href="account-details.php?type=<?php echo e($type); ?>&amp;id=<?php echo (int) $row['user_id']; ?>">Details</a>
                        <a href="account-form.php?type=<?php echo e($type); ?>&amp;id=<?php echo (int) $row['user_id']; ?>">Edit</a>
                        <?php if ($row['account_status'] === 'pending_setup'): ?>
                            <form method="POST" action="resend-setup.php">
                                <?php echo csrfInput(); ?>
                                <input type="hidden" name="user_id" value="<?php echo (int) $row['user_id']; ?>">
                                <input type="hidden" name="return_to" value="<?php echo e(['student' => 'manage-students.php', 'professional' => 'manage-employees.php', 'employer' => 'manage-employers.php'][$type]); ?>">
                                <button type="submit">Resend setup</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($row['account_status'] !== 'pending_setup'): ?>
                            <form method="POST" action="account-status.php">
                                <?php echo csrfInput(); ?>
                                <input type="hidden" name="user_id" value="<?php echo (int) $row['user_id']; ?>">
                                <input type="hidden" name="status" value="<?php echo $row['account_status'] === 'active' ? 'inactive' : 'active'; ?>">
                                <input type="hidden" name="return_to" value="<?php echo e(['student' => 'manage-students.php', 'professional' => 'manage-employees.php', 'employer' => 'manage-employers.php'][$type]); ?>">
                                <button type="submit"><?php echo $row['account_status'] === 'active' ? 'Deactivate' : 'Reactivate'; ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            <?php if ($total === 0): ?><tr><td colspan="6">No matching accounts found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
        <nav aria-label="Directory pages">
            <?php for ($n = 1; $n <= $pages; $n++): ?>
                <a href="?<?php echo e(http_build_query(['q' => $query, 'status' => $statusFilter, 'classification' => $classification, 'page' => $n])); ?>" <?php echo $n === $page ? 'aria-current="page"' : ''; ?>><?php echo $n; ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
</main>
<?php include __DIR__ . "/../includes/footer.php"; ?>
