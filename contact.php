<?php
$message = "";
require_once __DIR__ . "/includes/session.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
requireCsrfPost();

$name = inputString($_POST['name'] ?? "", 100);
$email = inputString($_POST['email'] ?? "", 100);
$contactMessage = inputString($_POST['message'] ?? "");
if ($name === "" || strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || $contactMessage === "") {
    http_response_code(400);
    $message = "Please provide a valid name, email, and message.";
} else {

$stmt = $conn->prepare("
INSERT INTO contact_messages(name,email,message)
VALUES(?,?,?)
");

$stmt->bind_param("sss",$name,$email,$contactMessage);

if ($stmt->execute()) {
    $message = "Message sent successfully.";
} else {
    error_log("Contact message insert failed: " . $stmt->error);
    http_response_code(500);
    $message = "Your message could not be sent. Please try again.";
}
}

}
include __DIR__ . "/includes/header.php";
?>

<section class="contact-hero">
<h1>Contact Us</h1>
<p>We’d love to hear from you</p>
</section>

<section class="contact-container">

<div class="contact-form">

<h2>Send Message</h2>
<?php if ($message !== ""): ?><p role="status"><?php echo e($message); ?></p><?php endif; ?>

<form method="POST">
<?php echo csrfInput(); ?>

<input type="text" name="name" placeholder="Your Name" required>

<input type="email" name="email" placeholder="Your Email" required>

<textarea name="message" placeholder="Your Message" required></textarea>

<button type="submit">Send Message</button>

</form>

</div>

<div class="contact-info">

<h2>Our Info</h2>

<p>📍 Location: India , </p>
<p>📧 Email: support@victory.com</p>
<p>📧 Email: ucpes@gmail.com</p>
<p>📞 Phone: +91 8144033404</p>

</div>

</section>

<?php include "includes/footer.php"; ?>