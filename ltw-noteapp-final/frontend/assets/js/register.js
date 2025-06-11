document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("registerForm");
  const messageBox = document.getElementById("registerMessage");

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const formData = new FormData(form);
    const password = formData.get("password");
    const confirmPassword = formData.get("confirm_password");

    if (password !== confirmPassword) {
      messageBox.textContent = "Passwords do not match.";
      messageBox.className = "message error";
      return;
    }

    try {
      const response = await fetch("/ltw-noteapp-final/backend/api/register.php", {
        method: "POST",
        body: formData,
      });

      const result = await response.json();

      if (result.success) {
        messageBox.textContent = "Registration successful! Redirecting to login...";
        messageBox.className = "message success";
        setTimeout(() => {
          window.location.href = "login.html"; // Redirect to login page
        }, 2000);
      } else {
        messageBox.textContent = result.message || "Registration failed.";
        messageBox.className = "message error";
      }
    } catch (error) {
      messageBox.textContent = "Server error. Please try again later.";
      messageBox.className = "message error";
    }
  });
});
