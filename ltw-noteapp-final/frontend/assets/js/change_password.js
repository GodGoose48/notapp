function verifyCurrentPassword() {
  const currentPassword = document.getElementById("currentPassword").value;

  fetch("/ltw-noteapp-final/backend/api/verify_password.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: "password=" + encodeURIComponent(currentPassword)
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        document.getElementById("step1").style.display = "none";
        document.getElementById("step2").style.display = "block";
        // lưu tạm mật khẩu cũ vào biến global
        window.currentPassword = currentPassword;
      } else {
        document.getElementById("error1").textContent = "Incorrect password!";
      }
    });
}

function changePassword() {
  const newPassword = document.getElementById("newPassword").value;
  const confirmPassword = document.getElementById("confirmPassword").value;
  const error2 = document.getElementById("error2");
  const success = document.getElementById("success");

  error2.textContent = "";
  success.textContent = "";

  if (!newPassword || !confirmPassword) {
    error2.textContent = "Please fill in all fields.";
    return;
  }

  if (newPassword !== confirmPassword) {
    error2.textContent = "Passwords do not match.";
    return;
  }

  // Ràng buộc: không trùng mật khẩu cũ
  if (newPassword === window.currentPassword) {
    error2.textContent = "New password must be different from current password.";
    return;
  }

  fetch("/ltw-noteapp-final/backend/api/change_password.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: "new_password=" + encodeURIComponent(newPassword)
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        success.textContent = "Password changed successfully!";
        document.getElementById("newPassword").value = "";
        document.getElementById("confirmPassword").value = "";
      } else {
        error2.textContent = "Failed to change password.";
      }
    });
}
