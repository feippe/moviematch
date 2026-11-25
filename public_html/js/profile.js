const profileForm = document.getElementById("profileForm");
const errorMessage = document.getElementById("errorMessage");
const submitButton = profileForm.querySelector("button[type='submit']");

const nameInput = document.getElementById("name");
const birthDateInput = document.getElementById("birthDate");
const sexSelect = document.getElementById("sex");
const matchWithSelect = document.getElementById("matchWith");
const photoInput = document.getElementById("photo");
const photoPreview = document.getElementById("photoPreview");

// the same rules as the server
const MIN_AGE = 18;
const MAX_AGE = 100;
const MAX_NAME_LENGTH = 60;
const MAX_PHOTO_SIZE = 5 * 1024 * 1024; // 5 mb
const OK_PHOTO_TYPES = ["image/jpeg", "image/png", "image/webp"];

// we need to know if the user already has a photo saved
let hasPhoto = false;

// the first input with an error (we put the cursor there)
let firstInvalidInput = null;

// the date picker cannot choose a day after today
birthDateInput.max = getTodayText();

// when the page opens, we load the saved profile (if there is one)
loadProfile();

// today as text, like 2026-11-24
function getTodayText() {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, "0");
    const day = String(today.getDate()).padStart(2, "0");
    return year + "-" + month + "-" + day;
}

// gets the age in years from a text like 2000-05-20
function getAge(birthDateText) {
    const parts = birthDateText.split("-");
    const birthYear = Number(parts[0]);
    const birthMonth = Number(parts[1]);
    const birthDay = Number(parts[2]);

    const today = new Date();
    let age = today.getFullYear() - birthYear;

    // if the birthday did not happen yet this year, we take one year off
    const monthDifference = (today.getMonth() + 1) - birthMonth;
    if (monthDifference < 0 || (monthDifference === 0 && today.getDate() < birthDay)) {
        age = age - 1;
    }

    return age;
}

// shows a red message under one field
function showFieldError(input, errorId, message) {
    document.getElementById(errorId).textContent = message;
    input.classList.add("input-error");

    if (firstInvalidInput === null) {
        firstInvalidInput = input;
    }
}

// removes all the error messages
function clearErrors() {
    const errors = document.querySelectorAll(".field-error");
    for (const error of errors) {
        error.textContent = "";
    }

    const inputs = document.querySelectorAll(".input-error");
    for (const input of inputs) {
        input.classList.remove("input-error");
    }

    errorMessage.style.color = "#ff6b81";
    errorMessage.textContent = "";
    firstInvalidInput = null;
}

// gives an error message if the photo is not ok, or an empty text if it is ok
function getPhotoError(file) {
    if (!OK_PHOTO_TYPES.includes(file.type)) {
        return "Please use a JPG, PNG or WEBP photo.";
    }
    if (file.size > MAX_PHOTO_SIZE) {
        return "The photo is too big. The maximum is 5 MB.";
    }
    return "";
}

// checks every field. returns true if all is ok.
function validateForm() {
    const name = nameInput.value.trim();
    const birthDate = birthDateInput.value;
    const photoFile = photoInput.files[0];

    // name
    if (name === "") {
        showFieldError(nameInput, "nameError", "Please enter your name.");
    } else if (name.length > MAX_NAME_LENGTH) {
        showFieldError(nameInput, "nameError", "Your name must have " + MAX_NAME_LENGTH + " characters or less.");
    }

    // date of birth
    if (birthDate === "") {
        showFieldError(birthDateInput, "birthDateError", "Please enter your date of birth.");
    } else if (birthDate.length !== 10) {
        showFieldError(birthDateInput, "birthDateError", "Please enter a valid date of birth.");
    } else if (birthDate > getTodayText()) {
        showFieldError(birthDateInput, "birthDateError", "Your date of birth cannot be in the future.");
    } else if (getAge(birthDate) < MIN_AGE) {
        showFieldError(birthDateInput, "birthDateError", "You must be at least " + MIN_AGE + " years old to use MovieMatch.");
    } else if (getAge(birthDate) > MAX_AGE) {
        showFieldError(birthDateInput, "birthDateError", "Please check your date of birth.");
    }

    // sex and match preference
    if (sexSelect.value === "") {
        showFieldError(sexSelect, "sexError", "Please choose your sex.");
    }
    if (matchWithSelect.value === "") {
        showFieldError(matchWithSelect, "matchWithError", "Please choose who you want to match with.");
    }

    // photo: needed, unless the user already has one
    if (!photoFile && !hasPhoto) {
        showFieldError(photoInput, "photoError", "Please choose a photo.");
    } else if (photoFile && getPhotoError(photoFile) !== "") {
        showFieldError(photoInput, "photoError", getPhotoError(photoFile));
    }

    // if no input has an error, the form is ok
    return firstInvalidInput === null;
}

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
            nameInput.value = profile.name;
            birthDateInput.value = profile.birth_date;
            sexSelect.value = profile.sex;
            matchWithSelect.value = profile.match_with;

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

// when the user chooses a photo, we check it and show it right away
photoInput.addEventListener("change", function() {
    const file = photoInput.files[0];

    // remove the old photo message
    document.getElementById("photoError").textContent = "";
    photoInput.classList.remove("input-error");

    if (!file) {
        return;
    }

    // a bad photo: show the message and empty the field
    const photoError = getPhotoError(file);
    if (photoError !== "") {
        document.getElementById("photoError").textContent = photoError;
        photoInput.classList.add("input-error");
        photoInput.value = "";
        return;
    }

    photoPreview.src = URL.createObjectURL(file);
    photoPreview.hidden = false;
});

profileForm.addEventListener("submit", async function(event) {
    event.preventDefault();

    clearErrors();

    // stop here if something is wrong, and put the cursor on the first bad field
    if (!validateForm()) {
        firstInvalidInput.focus();
        return;
    }

    const name = nameInput.value.trim();
    const birthDate = birthDateInput.value;
    const sex = sexSelect.value;
    const matchWith = matchWithSelect.value;
    const photoFile = photoInput.files[0];

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
                    "Your profile was saved, but the photo could not be uploaded. " +
                    (photoData.error || "Please try again.");
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