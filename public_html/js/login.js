const loginForm = document.getElementById("loginForm");
const errorMessage = document.getElementById("errorMessage");
const submitButton = loginForm.querySelector("button[type='submit']");

// Display email verification messages
const urlParams = new URLSearchParams(window.location.search);
const verified = urlParams.get("verified");

if (verified === "1") {
    errorMessage.style.color = "#4ade80";
    errorMessage.textContent =
        "Your email has been verified successfully. You can now log in.";
} else if (verified === "0") {
    errorMessage.style.color = "#ff6b81";
    errorMessage.textContent =
        "Email verification failed or the link has expired. Please try again.";
}

// after login, we send the user to the profile page or to the app
async function goToNextPage() {
    const response = await fetch("/api/me.php");
    const data = await response.json();

    if (data.success && data.user.profile_complete) {
        window.location.href = "/app.html";
    } else {
        window.location.href = "/profile.html";
    }
}

loginForm.addEventListener("submit", async function(event) {
    event.preventDefault();

    const email = document.getElementById("email").value.trim();
    const password = document.getElementById("password").value;

    errorMessage.style.color = "#ff6b81";
    errorMessage.textContent = "";

    // Check that the fields are completed
    if (!email || !password) {
        errorMessage.textContent = "Please fill in all fields.";
        return;
    }

    // Check password length
    if (password.length < 5 || password.length > 72) {
        errorMessage.textContent =
            "Password must contain between 5 and 72 characters.";
        return;
    }

    const originalText = submitButton.textContent;
    submitButton.disabled = true;
    submitButton.textContent = "Logging in...";

    try {
        const response = await fetch("/api/login.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                email: email,
                password: password
            })
        });

        const data = await response.json();

        if (data.success) {
            await goToNextPage();
        } else {
            errorMessage.textContent =
                data.error || "Login failed. Please try again.";
        }

    } catch (error) {
        errorMessage.textContent =
            "Unable to connect to the server. Please try again.";

        console.error("Login error:", error);
    } finally {
        submitButton.disabled = false;
        submitButton.textContent = originalText;
    }
});