const profileForm = document.getElementById("profileForm");
const errorMessage = document.getElementById("errorMessage");
const submitButton = profileForm.querySelector("button[type='submit']");
const photoInput = document.getElementById("photo");
const photoPreview = document.getElementById("photoPreview");

// 5 mb, the same limit as the server
const MAX_PHOTO_SIZE = 5 * 1024 * 1024;

// we need to know if the user already has a photo saved
let hasPhoto = false;

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

            // show the saved photo
            if (profile.photo_path) {
                photoPreview.src = profile.photo_path;
                photoPreview.hidden = false;
                hasPhoto = true;
            }
        }

    } catch (error) {
        errorMessage.textContent =
            "Unable to connect to the server. Please try again.";

        console.error("Load profile error:", error);
    }
}

// when the user chooses a photo, we show it right away
photoInput.addEventListener("change", function() {
    const file = photoInput.files[0];

    if (file) {
        photoPreview.src = URL.createObjectURL(file);
        photoPreview.hidden = false;
    }
});

profileForm.addEventListener("submit", async function(event) {
    event.preventDefault();

    const name = document.getElementById("name").value.trim();
    const birthDate = document.getElementById("birthDate").value;
    const sex = document.getElementById("sex").value;
    const matchWith = document.getElementById("matchWith").value;
    const photoFile = photoInput.files[0];

    errorMessage.style.color = "#ff6b81";
    errorMessage.textContent = "";

    // check that the fields are completed
    if (!name || !birthDate || !sex || !matchWith) {
        errorMessage.textContent = "Please fill in all fields.";
        return;
    }

    // a photo is needed, unless the user already has one
    if (!photoFile && !hasPhoto) {
        errorMessage.textContent = "Please choose a photo.";
        return;
    }

    // check the photo size
    if (photoFile && photoFile.size > MAX_PHOTO_SIZE) {
        errorMessage.textContent = "The photo is too big. The maximum is 5 MB.";
        return;
    }

    const originalText = submitButton.textContent;
    submitButton.disabled = true;
    submitButton.textContent = "Saving...";

    try {
        // step 1: save the profile
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

        if (!data.success) {
            errorMessage.textContent =
                data.error || "Unable to save your profile.";
            return;
        }

        // step 2: upload the photo (only if the user chose a new one)
        if (photoFile) {
            const formData = new FormData();
            formData.append("photo", photoFile);

            const photoResponse = await fetch("/api/photo-upload.php", {
                method: "POST",
                body: formData
            });

            const photoData = await photoResponse.json();

            if (!photoData.success) {
                errorMessage.textContent =
                    photoData.error || "Unable to upload your photo.";
                return;
            }
        }

        // everything is saved, so go to the app
        window.location.href = "/app.html";

    } catch (error) {
        errorMessage.textContent =
            "Unable to connect to the server. Please try again.";

        console.error("Save profile error:", error);
    } finally {
        submitButton.disabled = false;
        submitButton.textContent = originalText;
    }
});
