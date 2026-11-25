const matchesList = document.getElementById("matchesList");
const message = document.getElementById("message");

// Fetch matches from the API
async function loadMatches() {
    matchesList.innerHTML = "<p>Loading your matches...</p>";
    message.textContent = "";

    try {
        const response = await fetch("/api/matches.php");
        const data = await response.json();

        // not logged in (or blocked), so go to the login page
        if (response.status === 401 || response.status === 403) {
            window.location.href = "/login.html";
            return;
        }

        if (!response.ok || !data.success) {
            throw new Error(data.error || "Unable to load matches.");
        }

        displayMatches(data.matches);

    } catch (error) {
        matchesList.innerHTML = "<p>Unable to load your matches.</p>";
        message.textContent = error.message;
    }
}

// sends the answer of the user to the server. action is "accept" or "decline".
async function answerMatch(matchId, action) {
    message.textContent = "";

    try {
        const response = await fetch("/api/match-" + action + ".php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                match_id: matchId
            })
        });

        const data = await response.json();

        // not logged in (or blocked), so go to the login page
        if (response.status === 401 || response.status === 403) {
            window.location.href = "/login.html";
            return;
        }

        // load the list again, so it shows the new state of every match
        await loadMatches();

        // then show the answer of the server
        if (data.success) {
            message.textContent = data.message;
        } else {
            message.textContent = data.error || "Unable to save your answer.";
        }

    } catch (error) {
        console.error("Answer match error:", error);

        // load the list again, so the buttons work again
        await loadMatches();
        message.textContent = "Unable to connect to the server. Please try again.";
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

        card.appendChild(photo);
        card.appendChild(name);
        card.appendChild(age);
        card.appendChild(sex);
        card.appendChild(movies);

        // what the user can do depends on the answers
        if (match.both_accepted) {
            // both said yes, so the user can see the contact
            const contactButton = document.createElement("button");
            contactButton.type = "button";
            contactButton.textContent = "See contact";
            contactButton.addEventListener("click", function() {
                window.location.href = "contact.html?match_id=" + match.id;
            });

            card.appendChild(contactButton);

        } else if (match.my_response === "accepted") {
            // the user already said yes, so we wait for the other person
            const waiting = document.createElement("p");
            waiting.textContent = "You accepted. Waiting for the other person...";

            card.appendChild(waiting);

        } else {
            // the user did not answer yet
            const acceptButton = document.createElement("button");
            acceptButton.type = "button";
            acceptButton.textContent = "Accept";

            const declineButton = document.createElement("button");
            declineButton.type = "button";
            declineButton.textContent = "Decline";

            // the buttons stop working while we send the answer (no double click)
            acceptButton.addEventListener("click", function() {
                acceptButton.disabled = true;
                declineButton.disabled = true;
                answerMatch(match.id, "accept");
            });

            declineButton.addEventListener("click", function() {
                acceptButton.disabled = true;
                declineButton.disabled = true;
                answerMatch(match.id, "decline");
            });

            card.appendChild(acceptButton);
            card.appendChild(declineButton);
        }

        matchesList.appendChild(card);
    });
}

// Load matches when the page opens
loadMatches();