const profileForm = document.getElementById("profileForm");
const errorMessage = document.getElementById("errorMessage");
const submitButton = profileForm.querySelector("button[type='submit']");

// when the page opens, we load the saved profile (if there is one)
loadProfile();

async function loadProfile() {
    try {
        const response = await fetch("/api/me.php");

        // not logged in (or blocked), so go to the login page
        if (response.status === 401 || response.status === 403) {
            window.location.href = "/login.html";
            return;
        }

        const data = await response.json();

        // if the user already has a profile, we put the saved values in the form
        if (data.success && data.user.profile) {
            const profile = data.user.profile;
            document.getElementById("name").value = profile.name;
            document.getElementById("birthDate").value = profile.birth_date;
            document.getElementById("sex").value = profile.sex;
            document.getElementById("matchWith").value = profile.match_with;
        }

    } catch (error) {
        errorMessage.textContent =
            "Unable to connect to the server. Please try again.";

        console.error("Load profile error:", error);
    }
}

profileForm.addEventListener("submit", async function(event) {
    event.preventDefault();

    const name = document.getElementById("name").value.trim();
    const birthDate = document.getElementById("birthDate").value;
    const sex = document.getElementById("sex").value;
    const matchWith = document.getElementById("matchWith").value;

    errorMessage.style.color = "#ff6b81";
    errorMessage.textContent = "";

    // check that the fields are completed
    if (!name || !birthDate || !sex || !matchWith) {
        errorMessage.textContent = "Please fill in all fields.";
        return;
    }

    const originalText = submitButton.textContent;
    submitButton.disabled = true;
    submitButton.textContent = "Saving...";

    try {
        const response = await fetch("/api/profile-save.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                name: name,
                birth_date: birthDate,
                sex: sex,
                match_with: matchWith
            })
        });

        // the session ended, so go to the login page
        if (response.status === 401 || response.status === 403) {
            window.location.href = "/login.html";
            return;
        }

        const data = await response.json();

        if (data.success) {
            // profile saved, so go to the app
            window.location.href = "/app.html";
        } else {
            errorMessage.textContent =
                data.error || "Unable to save your profile.";
        }

    } catch (error) {
        errorMessage.textContent =
            "Unable to connect to the server. Please try again.";

        console.error("Save profile error:", error);
    } finally {
        submitButton.disabled = false;
        submitButton.textContent = originalText;
    }
});
