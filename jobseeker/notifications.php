<?php
require_once __DIR__ . "/../includes/session.php";
requireJobseeker();

$userId = (int) $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT message, sent_date FROM notifications WHERE user_id = ? ORDER BY sent_date DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$notes = $stmt->get_result();
include __DIR__ . "/../includes/header.php";
include __DIR__ . "/layout.php";
?>

<h2>Notifications 🔔</h2>

<?php while ($note = $notes->fetch_assoc()): ?>

<div class="notification-item">

<p><?php echo e($note['message']); ?></p>

<small><?php echo e($note['sent_date']); ?></small>

</div>

<?php endwhile; ?>

</div>
</div>

<?php include "../includes/footer.php"; ?>