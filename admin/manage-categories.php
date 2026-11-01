<?php
require_once "../includes/session.php";
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost();

    if (isset($_POST['add'])) {
        $name = inputString($_POST['name'] ?? null, 100);
        if ($name === "") {
            http_response_code(400);
            exit("Category name is required and must be 100 characters or fewer.");
        }
        $stmt = $conn->prepare("INSERT IGNORE INTO categories (name) VALUES (?)");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        header("Location: manage-categories.php", true, 303);
        exit();
    }

    if (isset($_POST['delete'])) {
        $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($categoryId === false || $categoryId === null) {
            http_response_code(400);
            exit("Invalid category.");
        }
        $delete = $conn->prepare("DELETE FROM categories WHERE cat_id = ?");
        $delete->bind_param("i", $categoryId);
        $delete->execute();
        if ($delete->affected_rows !== 1) {
            http_response_code(404);
            exit("Category not found.");
        }
        header("Location: manage-categories.php", true, 303);
        exit();
    }
}

/* FETCH */
$cats = $conn->query("SELECT * FROM categories ORDER BY cat_id DESC");
include "../includes/header.php";
?>

<h1 class="dashboard-title">Manage Categories 📂</h1>

<!-- ADD FORM -->
<form method="POST" style="margin-bottom:20px;">
<?php echo csrfInput(); ?>

<input type="text" name="name" placeholder="Enter category name" required
style="padding:10px;width:250px;">

<button type="submit" name="add" class="btn">Add Category</button>

</form>

<!-- CATEGORY LIST -->
<table class="app-table">

<tr>
<th>ID</th>
<th>Category Name</th>
<th>Action</th>
</tr>

<?php while($c = $cats->fetch_assoc()): ?>

<tr>
<td><?php echo (int) $c['cat_id']; ?></td>
<td><?php echo e($c['name']); ?></td>
<td>
<form method="POST" action="manage-categories.php" onsubmit="return confirm('Delete this category?');">
    <?php echo csrfInput(); ?>
    <input type="hidden" name="category_id" value="<?php echo (int) $c['cat_id']; ?>">
    <button type="submit" name="delete">Delete</button>
</form>
</td>
</tr>

<?php endwhile; ?>

</table>

<?php include "../includes/footer.php"; ?>