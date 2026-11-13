const newPasswordForm = document.getElementById("newPasswordForm");
const errorMessage = document.getElementById("errorMessage");
const submitButton = newPasswordForm.querySelector("button[type='submit']");

newPasswordForm.addEventListener("submit", async function(event) {
    event.preventDefault();

    const password = document.getElementById("password").value;
    const confirmPassword = document.getElementById("confirmPassword").value;

    // Get the reset token from the URL
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get("token");

    errorMessage.style.color = "#ff6b81";
    errorMessage.textContent = "";

    // Check that the token exists
    if (!token) {
        errorMessage.textContent =
            "Invalid or missing password reset link.";
        return;
    }

    // Check that both fields are completed
    if (!password || !confirmPassword) {
        errorMessage.textContent = "Please fill in all fields.";
        return;
    }

    // Check password length
    if (password.length < 5 || password.length > 72) {
    errorMessage.textContent =
        "Password must contain between 5 and 72 characters.";
    return;
}

    // Check that passwords match
    if (password !== confirmPassword) {
        errorMessage.textContent = "Passwords do not match.";
        return;
    }

    const originalText = submitButton.textContent;
    submitButton.disabled = true;
    submitButton.textContent = "Saving...";

    try {
        const response = await fetch("/api/password-reset.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                token: token,
                password: password
            })
        });

        const data = await response.json();

        if (data.success) {
            errorMessage.style.color = "#4ade80";
            errorMessage.textContent = data.message;
            newPasswordForm.reset();
        } else {
            errorMessage.textContent =
                data.error || "Unable to reset your password.";
        }

    } catch (error) {
        errorMessage.textContent =
            "Unable to connect to the server. Please try again.";

        console.error("Password reset error:", error);
    } finally {
        submitButton.disabled = false;
        submitButton.textContent = originalText;
    }
});