
const movieCard = document.getElementById("movieCard");
const moviePoster = document.getElementById("moviePoster");
const movieTitle = document.getElementById("movieTitle");
const movieDate = document.getElementById("movieDate");
const movieOverview = document.getElementById("movieOverview");
const statusMessage = document.getElementById("statusMessage");

const likeButton = document.getElementById("likeButton");
const passButton = document.getElementById("passButton");
const logoutLink = document.getElementById("logoutLink");

let currentMovie = null;

async function loadMovie() {
    movieCard.hidden = true;
    statusMessage.textContent = "Loading next movie...";

    try {
        const response = await fetch("/api/movies-next.php");
        const data = await response.json();

        if (response.status === 401 || response.status === 403) {
            window.location.href = "/login.html";
            return;
        }

        if (response.status === 404) {
            currentMovie = null;
            statusMessage.textContent =
                data.error || "No more movies available. Please check back later.";
            return;
        }

        if (!response.ok || !data.success) {
            throw new Error(data.error || "Unable to load movie.");
        }

        currentMovie = data.movie;

        movieTitle.textContent = currentMovie.title;
        movieOverview.textContent =
            currentMovie.overview || "No description available.";

        movieDate.textContent =
            currentMovie.release_date
                ? "Release date: " + currentMovie.release_date
                : "Release date unavailable";

        if (currentMovie.poster_url) {
            moviePoster.src = currentMovie.poster_url;
            moviePoster.hidden = false;
        } else {
            moviePoster.removeAttribute("src");
            moviePoster.hidden = true;
        }

        movieCard.hidden = false;
        statusMessage.textContent = "";

    } catch (error) {
        console.error("Movie loading error:", error);
        statusMessage.textContent = error.message;
    }
}

async function submitSwipe(decision) {
    if (!currentMovie) return;

    likeButton.disabled = true;
    passButton.disabled = true;

    try {
        const response = await fetch("/api/swipe.php", {
            method: "POST",
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
            throw new Error(data.error || "Unable to save your swipe.");
        }

        currentMovie = null;
        await loadMovie();

    } catch (error) {
        console.error("Swipe error:", error);
        statusMessage.textContent = error.message;

    } finally {
        likeButton.disabled = false;
        passButton.disabled = false;
    }
}

likeButton.addEventListener("click", () => submitSwipe("yes"));
passButton.addEventListener("click", () => submitSwipe("no"));

logoutLink.addEventListener("click", async (event) => {
    event.preventDefault();

    try {
        await fetch("/api/logout.php", { method: "POST" });
    } catch (error) {
        console.error("Logout error:", error);
    }

    window.location.href = "/login.html";
});

async function checkUser() {
    try {
        const response = await fetch("/api/me.php");

        if (response.status === 401 || response.status === 403) {
            window.location.href = "/login.html";
            return;
        }

        if (!response.ok) {
            throw new Error("Unable to verify your account.");
        }

        const data = await response.json();

        if (!data.user.profile_complete) {
            window.location.href = "/profile.html";
            return;
        }

        await loadMovie();

    } catch (error) {
        console.error("Account check error:", error);
        statusMessage.textContent = error.message;
    }
}

checkUser();
