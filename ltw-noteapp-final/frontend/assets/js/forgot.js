document.addEventListener("DOMContentLoaded", () => {
  const emailInput = document.getElementById("email");
  const otpInput = document.getElementById("otpInput");
  const sendOtpBtn = document.getElementById("sendOtpBtn");
  const verifyOtpBtn = document.getElementById("verifyOtpBtn");
  const forgotForm = document.getElementById("forgotForm");
  const messageBox = document.getElementById("forgotMessage");
  const otpSection = document.getElementById("otpSection");
  const passwordSection = document.getElementById("passwordSection");

  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('error')) {
    messageBox.textContent = urlParams.get('error');
    messageBox.className = "message error";
  }

  sendOtpBtn.addEventListener("click", async () => {
    const email = emailInput.value.trim();
    if (!email) {
      messageBox.textContent = "Please enter your email address.";
      messageBox.className = "message error";
      return;
    }

    sendOtpBtn.disabled = true;
    sendOtpBtn.textContent = "Sending...";

    try {
      const formData = new FormData();
      formData.append("email", email);

      const res = await fetch("/ltw-noteapp-final/backend/api/send_otp.php", {
        method: "POST",
        body: formData,
      });

      const result = await res.json();

      if (result.success) {
        // Clear any previous messages
        messageBox.textContent = result.message;
        messageBox.className = "message success";
        
        // Show OTP section after successful email send
        otpSection.style.display = "block";
        emailInput.disabled = true;
        sendOtpBtn.style.display = "none";
        
        // Focus on OTP input for better UX
        setTimeout(() => {
          otpInput.focus();
        }, 100);
      } else {
        messageBox.textContent = result.message;
        messageBox.className = "message error";
      }
    } catch (error) {
      console.error('Error sending OTP:', error);
      messageBox.textContent = "Network error. Please try again.";
      messageBox.className = "message error";
    } finally {
      sendOtpBtn.disabled = false;
      sendOtpBtn.textContent = "Send OTP";
    }
  });

  verifyOtpBtn.addEventListener("click", async () => {
    const otp = otpInput.value.trim();
    if (!otp) {
      messageBox.textContent = "Please enter the OTP code.";
      messageBox.className = "message error";
      return;
    }

    if (otp.length !== 6) {
      messageBox.textContent = "OTP code must be 6 digits.";
      messageBox.className = "message error";
      return;
    }

    verifyOtpBtn.disabled = true;
    verifyOtpBtn.textContent = "Verifying...";

    try {
      const formData = new FormData();
      formData.append("otp", otp);

      const res = await fetch("/ltw-noteapp-final/backend/api/verify_otp.php", {
        method: "POST",
        body: formData,
      });

      const result = await res.json();
      if (result.success) {
        messageBox.textContent = result.message;
        messageBox.className = "message success";
        
        // Hide OTP section and show password section
        otpSection.style.display = "none";
        passwordSection.style.display = "block";
        
        // Focus on new password input
        setTimeout(() => {
          document.getElementById("new_password").focus();
        }, 100);
      } else {
        messageBox.textContent = result.message;
        messageBox.className = "message error";
      }
    } catch (error) {
      console.error('Error verifying OTP:', error);
      messageBox.textContent = "Network error. Please try again.";
      messageBox.className = "message error";
    } finally {
      verifyOtpBtn.disabled = false;
      verifyOtpBtn.textContent = "Verify OTP";
    }
  });

  forgotForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    const newPassword = document.getElementById("new_password").value;
    const confirmPassword = document.getElementById("confirm_password").value;

    if (!newPassword || !confirmPassword) {
      messageBox.textContent = "Please fill in all password fields.";
      messageBox.className = "message error";
      return;
    }

    if (newPassword !== confirmPassword) {
      messageBox.textContent = "Passwords do not match.";
      messageBox.className = "message error";
      return;
    }

    if (newPassword.length < 5) {
      messageBox.textContent = "Password must be at least 5 characters long.";
      messageBox.className = "message error";
      return;
    }

    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = "Resetting...";

    try {
      const formData = new FormData();
      formData.append("new_password", newPassword);
      formData.append("confirm_password", confirmPassword);

      const res = await fetch("/ltw-noteapp-final/backend/api/reset_password.php", {
        method: "POST",
        body: formData,
      });

      const result = await res.json();
      if (result.success) {
        messageBox.textContent = result.message;
        messageBox.className = "message success";
        passwordSection.style.display = "none";
        
        // Redirect to login after 3 seconds
        setTimeout(() => {
          window.location.href = "login.html";
        }, 2000);
      } else {
        messageBox.textContent = result.message;
        messageBox.className = "message error";
      }
    } catch (error) {
      console.error('Error resetting password:', error);
      messageBox.textContent = "Network error. Please try again.";
      messageBox.className = "message error";
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = "Reset Password";
    }
  });
});
