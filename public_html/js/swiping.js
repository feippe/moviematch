
// MovieMatch - Movie Swiping

const movieCard = document.getElementById("movieCard");
const moviePoster = document.getElementById("moviePoster");
const movieTitle = document.getElementById("movieTitle");
const movieDate = document.getElementById("movieDate");
const movieOverview = document.getElementById("movieOverview");
const statusMessage = document.getElementById("statusMessage");

const likeButton = document.getElementById("likeButton");
const passButton = document.getElementById("passButton");
const logoutLink = document.getElementById("logoutLink");
const retryButton = document.getElementById("retryButton");

let currentMovie = null;
let isSubmitting = false;

let pointerStartX = 0;
let pointerStartY = 0;
let isDragging = false;

const SWIPE_THRESHOLD = 100;

// Load the next movie
async function loadMovie() {

    movieCard.hidden = true;
    retryButton.hidden = true;

    statusMessage.textContent = "Loading next movie...";

    currentMovie = null;

    try {

        const response = await fetch("/api/movies-next.php", {
            method: "GET",
            credentials: "same-origin",
            cache: "no-store"
        });

        const data = await response.json();

        if (response.status === 401 || response.status === 403) {
            window.location.href = "/login.html";
            return;
        }

        if (response.status === 404) {

            statusMessage.textContent =
                data.error || "No more movies available. Please check back later.";

            return;
        }

        if (!response.ok || !data.success || !data.movie) {
            throw new Error(data.error || "Unable to load movie.");
        }

        currentMovie = data.movie;

        movieTitle.textContent = currentMovie.title || "Untitled movie";

        movieOverview.textContent =
            currentMovie.overview || "No description available.";

        movieDate.textContent = currentMovie.release_date
            ? "Release date: " + currentMovie.release_date
            : "Release date unavailable";

        if (currentMovie.poster_url) {

            moviePoster.src = currentMovie.poster_url;
            moviePoster.alt = currentMovie.title + " movie poster";
            moviePoster.hidden = false;

        } else {

            moviePoster.removeAttribute("src");
            moviePoster.alt = "";
            moviePoster.hidden = true;

        }

        resetCardPosition();

        movieCard.hidden = false;
        statusMessage.textContent = "";

    } catch (error) {

        console.error("Movie loading error:", error);

        statusMessage.textContent =
            error.message || "Unable to load movies. Please try again.";

        retryButton.hidden = false;
    }
}

// Reset card after a swipe
function resetCardPosition() {

    movieCard.style.transform = "";
    movieCard.style.transition = "";

    movieCard.classList.remove("swiping-left", "swiping-right");
}

// Enable and disable buttons
function setButtonsDisabled(disabled) {

    likeButton.disabled = disabled;
    passButton.disabled = disabled;

}

// Save a user's movie decision
async function submitSwipe(decision) {

    if (!currentMovie || isSubmitting) {
        return;
    }

    isSubmitting = true;

    setButtonsDisabled(true);

    statusMessage.textContent =
        decision === "yes" ? "Saving your like..." : "Saving your pass...";

    try {

        const response = await fetch("/api/swipe.php", {

            method: "POST",

            credentials: "same-origin",

            headers: {
                "Content-Type": "application/json"
            },

            body: JSON.stringify({

                movie_id: currentMovie.id,

                decision: decision

            })

        });

        const data = await response.json();

        if (response.status === 401 || response.status === 403) {

            window.location.href = "/login.html";

            return;
        }

        if (!response.ok || !data.success) {

            throw new Error(
                data.error || "Unable to save your swipe."
            );

        }

        currentMovie = null;

        await loadMovie();

    } catch (error) {

        console.error("Swipe error:", error);

        statusMessage.textContent =
            error.message || "Unable to save your swipe. Please try again.";

    } finally {

        isSubmitting = false;

        setButtonsDisabled(false);

    }
}

// Like and Pass buttons
likeButton.addEventListener("click", function () {

    submitSwipe("yes");

});

passButton.addEventListener("click", function () {

    submitSwipe("no");

});

// Touch and mouse swipe support
movieCard.addEventListener("pointerdown", function (event) {

    if (
        event.target.closest("button") ||
        isSubmitting ||
        !currentMovie
    ) {
        return;
    }

    pointerStartX = event.clientX;
    pointerStartY = event.clientY;

    isDragging = true;

    movieCard.style.transition = "none";

    movieCard.setPointerCapture(event.pointerId);

});

movieCard.addEventListener("pointermove", function (event) {

    if (!isDragging || isSubmitting) {
        return;
    }

    const differenceX = event.clientX - pointerStartX;
    const differenceY = event.clientY - pointerStartY;

    // Ignore vertical movement
    if (Math.abs(differenceY) > Math.abs(differenceX)) {
        return;
    }

    const rotation = differenceX * 0.04;

    movieCard.style.transform =
        `translateX(${differenceX}px) rotate(${rotation}deg)`;

    movieCard.classList.toggle("swiping-right", differenceX > 30);

    movieCard.classList.toggle("swiping-left", differenceX < -30);

});

movieCard.addEventListener("pointerup", function (event) {

    if (!isDragging) {
        return;
    }

    isDragging = false;

    const differenceX = event.clientX - pointerStartX;

    if (differenceX >= SWIPE_THRESHOLD) {

        animateSwipe("right");

    } else if (differenceX <= -SWIPE_THRESHOLD) {

        animateSwipe("left");

    } else {

        resetCardPosition();

    }

});

movieCard.addEventListener("pointercancel", function () {

    isDragging = false;

    resetCardPosition();

});

// Animate the card before saving the decision
function animateSwipe(direction) {

    if (!currentMovie || isSubmitting) {
        resetCardPosition();
        return;
    }

    const destination =
        direction === "right" ? "110%" : "-110%";

    movieCard.style.transition = "transform 0.3s ease";

    movieCard.style.transform =
        `translateX(${destination}) rotate(${direction === "right" ? 20 : -20}deg)`;

    setTimeout(function () {

        submitSwipe(direction === "right" ? "yes" : "no");

    }, 250);

}

// Keyboard controls
document.addEventListener("keydown", function (event) {

    if (
        event.target.matches("input, textarea, select") ||
        movieCard.hidden ||
        isSubmitting
    ) {
        return;
    }

    if (event.key === "ArrowRight") {

        event.preventDefault();

        submitSwipe("yes");

    } else if (event.key === "ArrowLeft") {

        event.preventDefault();

        submitSwipe("no");

    }

});

// Retry loading movies
retryButton.addEventListener("click", function () {

    loadMovie();

});

// Logout
logoutLink.addEventListener("click", async function (event) {

    event.preventDefault();

    try {

        await fetch("/api/logout.php", {

            method: "POST",

            credentials: "same-origin"

        });

    } catch (error) {

        console.error("Logout error:", error);

    }

    window.location.href = "/login.html";

});

// Check authentication and profile completion
async function checkUser() {

    try {

        const response = await fetch("/api/me.php", {
            credentials: "same-origin",
            cache: "no-store"
        });

        if (response.status === 401 || response.status === 403) {

            window.location.href = "/login.html";

            return;
        }

        if (!response.ok) {

            throw new Error("Unable to verify your account.");

        }

        const data = await response.json();

        if (!data.success || !data.user) {

            throw new Error("Unable to verify your account.");

        }

        if (!data.user.profile_complete) {

            window.location.href = "/profile.html";

            return;
        }

        await loadMovie();

    } catch (error) {

        console.error("Account check error:", error);

        statusMessage.textContent =
            error.message || "Unable to connect to the server.";

        retryButton.hidden = false;

    }

}

// Start the application
checkUser();
