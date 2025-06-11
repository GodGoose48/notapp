<?php
session_start();
session_unset(); 
session_destroy(); 

// Quay về trang login
header("Location: /ltw-noteapp-final/frontend/views/login.html");
exit();
?>
