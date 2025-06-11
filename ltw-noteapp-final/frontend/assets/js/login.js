document.addEventListener("DOMContentLoaded", () => {
  const loginForm = document.getElementById("loginForm");
  const loginMessage = document.getElementById("loginMessage");

  if (!loginForm) {
    console.error("Login form not found.");
    return;
  }

  loginForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    const formData = new FormData(loginForm);
    const email = formData.get("email");
    const password = formData.get("password");

    try {
      const response = await fetch("/ltw-noteapp-final/backend/api/login.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ email, password }),
      });

      const result = await response.json();

      if (result.success) {
        // Chuyển đến home nếu login thành công
        window.location.href = "/ltw-noteapp-final/frontend/views/home.php";
      } else {
        loginMessage.textContent = result.message || "Login failed.";
        loginMessage.className = "message login-message error";
      }
    } catch (err) {
      loginMessage.textContent = "Server error. Please try again later.";
      loginMessage.className = "message login-message error";
    }
  });
});
