const resetForm = document.getElementById("resetForm");
const errorMessage = document.getElementById("errorMessage");

resetForm.addEventListener("submit", async function(event) {
    event.preventDefault();

    const email = document.getElementById("email").value.trim();

    errorMessage.style.color = "#ff6b81";
    errorMessage.textContent = "";

    // Check that the email field is completed
    if (!email) {
        errorMessage.textContent = "Please enter your university email.";
        return;
    }

    try {
        const response = await fetch("/api/password-reset-request.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                email: email
            })
        });

        const data = await response.json();

        // Handle successful response
        if (data.success) {
            errorMessage.style.color = "#4ade80";
            errorMessage.textContent = data.message;
            resetForm.reset();
        } else {
            // Handle API error
            errorMessage.textContent =
                data.error || "Unable to process your request.";
        }

    } catch (error) {
        errorMessage.textContent =
            "Unable to connect to the server. Please try again.";

        console.error("Password reset request error:", error);
    }
});