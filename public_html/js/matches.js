```javascript
const matchesList = document.getElementById("matchesList");
const message = document.getElementById("message");

// Fetch matches from the API
async function loadMatches() {
    matchesList.innerHTML = "<p>Loading your matches...</p>";
    message.textContent = "";

    try {
        const response = await fetch("/api/matches.php");
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.error || "Unable to load matches.");
        }

        displayMatches(data.matches);

    } catch (error) {
        matchesList.innerHTML = "<p>Unable to load your matches.</p>";
        message.textContent = error.message;
    }
}

// Display matches
function displayMatches(matches) {
    matchesList.innerHTML = "";

    if (!matches || matches.length === 0) {
        matchesList.innerHTML = "<p>You don't have any matches yet.</p>";
        return;
    }

    matches.forEach(function(match) {
        const card = document.createElement("div");
        card.className = "match-card";

        const photo = document.createElement("img");
        photo.src = match.photo_path;
        photo.alt = match.name + "'s profile photo";
        photo.width = 90;
        photo.height = 90;

        const name = document.createElement("h3");
        name.textContent = match.name;

        const age = document.createElement("p");
        age.textContent = "Age: " + match.age;

        const sex = document.createElement("p");
        sex.textContent = "Sex: " + match.sex;

        const movies = document.createElement("p");
        movies.textContent = "Shared movies: " +
            (match.shared_movies || []).join(", ");

        const acceptButton = document.createElement("button");
        acceptButton.type = "button";
        acceptButton.textContent = "Accept";
        acceptButton.addEventListener("click", function() {
            message.textContent =
                "Accept functionality is not connected yet.";
        });

        const declineButton = document.createElement("button");
        declineButton.type = "button";
        declineButton.textContent = "Decline";
        declineButton.addEventListener("click", function() {
            message.textContent =
                "Decline functionality is not connected yet.";
        });

        card.appendChild(photo);
        card.appendChild(name);
        card.appendChild(age);
        card.appendChild(sex);
        card.appendChild(movies);
        card.appendChild(acceptButton);
        card.appendChild(declineButton);

        matchesList.appendChild(card);
    });
}

// Load matches when the page opens
loadMatches();
```