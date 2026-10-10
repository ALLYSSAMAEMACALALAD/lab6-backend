
<?php

// Paste the admin password hash from Aiven here.
$hash = '$2y$10$tl0UBEFh8r0QCfXTxtjHG.Jmb.EbFEUwYSsKqj1xb9yCDt.saMIwC';

// Test the password you expect to use.
$password = 'admin123';

if (password_verify($password, $hash)) {
    echo "MATCH: The password matches the hash.";
} else {
    echo "NO MATCH: The password does not match the hash.";
}
