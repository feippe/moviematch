const loginForm = document.getElementById("loginForm");
const errorMessage = document.getElementById("errorMessage");

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
    if (password.length < 8 || password.length > 72) {
        errorMessage.textContent =
            "Password must contain at least 8 and 72 characters.";
        return;
    }

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
            errorMessage.style.color = "#4ade80";
            errorMessage.textContent =
                "Welcome! You are logged in as " + data.user.email;
        } else {
            errorMessage.textContent =
                data.error || "Login failed. Please try again.";
        }

    } catch (error) {
        errorMessage.textContent =
            "Unable to connect to the server. Please try again.";

        console.error("Login error:", error);
    }
});