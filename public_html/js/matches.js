// Temporary sample matches for testing
const matches = [
    {
        id: 1,
        name: "Gabriel",
        photo: "",
        age: 24,
        sex: "Male",
        sharedMovies: ["Merlin", "Avatar"]
    },
    {
        id: 2,
        name: "Abiodun",
        photo: "",
        age: 22,
        sex: "Female",
        sharedMovies: ["Titanic", "Legend of the seeker"]
    }
];

const matchesList = document.getElementById("matchesList");
const message = document.getElementById("message");

// Display matches
function displayMatches() {
    matchesList.innerHTML = "";

    matches.forEach(match => {
        const card = document.createElement("div");
        card.className = "match-card";

        card.innerHTML = `
            <h3>${match.name}</h3>
            <p>Age: ${match.age}</p>
            <p>Sex: ${match.sex}</p>
            <p>Shared movies: ${match.sharedMovies.join(", ")}</p>

            <button type="button" onclick="acceptMatch(${match.id})">
                Accept
            </button>

            <button type="button" onclick="declineMatch(${match.id})">
                Decline
            </button>
        `;

        matchesList.appendChild(card);
    });
}

// Accept a match
function acceptMatch(id) {
    message.textContent = "You accepted this match!";
}

// Decline a match
function declineMatch(id) {
    message.textContent = "You declined this match.";
}

// Display matches when the page loads
displayMatches();