<?php
$email = "noreply.news@e-mail.hoyoverse.com";
$isValid = filter_var($email, FILTER_VALIDATE_EMAIL);
echo "Email: $email\n";
echo "Is Valid: " . ($isValid ? "YES" : "NO") . "\n";
