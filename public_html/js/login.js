const loginForm = document.getElementById("loginForm");
const errorMessage = document.getElementById("errorMessage");

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
    if (password.length < 5) {
        errorMessage.textContent =
            "Password must contain at least 5 characters.";
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