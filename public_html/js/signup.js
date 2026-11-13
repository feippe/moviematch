const signupForm = document.getElementById("signupForm");
const errorMessage = document.getElementById("errorMessage");

signupForm.addEventListener("submit", async function(event) {
    event.preventDefault();

    const email = document.getElementById("email").value.trim();
    const password = document.getElementById("password").value;
    const confirmPassword = document.getElementById("confirmPassword").value;

    errorMessage.style.color = "#ff6b81";
    errorMessage.textContent = "";

    if (!email || !password || !confirmPassword) {
        errorMessage.textContent = "Please fill in all fields.";
        return;
    }

    if (password.length < 5 || password.length > 72) {
        errorMessage.textContent =
            "Password must contain between 5 and 72 characters.";
        return;
    }

    if (password !== confirmPassword) {
        errorMessage.textContent = "Passwords do not match.";
        return;
    }

    try {
        const response = await fetch("/api/register.php", {
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
            errorMessage.textContent = data.message;
            signupForm.reset();
        } else {
            errorMessage.textContent =
                data.error || "Registration failed. Please try again.";
        }

    } catch (error) {
        errorMessage.textContent =
            "Unable to connect to the server. Please try again.";
        console.error("Sign-up error:", error);
    }
});