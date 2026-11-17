// Temporary sample matches for testing
const matches = [
    {
        id: 1,
        name: "Gabriel",
        photo: "https://i.pravatar.cc/150?img=12",
        age: 24,
        sex: "Male",
        sharedMovies: ["Merlin", "Avatar"]
    },
    {
        id: 2,
        name: "Abiodun",
        photo: "https://i.pravatar.cc/150?img=47",
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

    matches.forEach(function(match) {
        const card = document.createElement("div");
        card.className = "match-card";

        const photo = document.createElement("img");
        photo.src = match.photo;
        photo.alt = match.name + "'s profile photo";
        photo.width = 120;
        photo.height = 120;
        photo.style.borderRadius = "50%";
        photo.style.objectFit = "cover";

        const name = document.createElement("h3");
        name.textContent = match.name;

        const age = document.createElement("p");
        age.textContent = "Age: " + match.age;

        const sex = document.createElement("p");
        sex.textContent = "Sex: " + match.sex;

        const movies = document.createElement("p");
        movies.textContent = "Shared movies: " + match.sharedMovies.join(", ");

        const acceptButton = document.createElement("button");
        acceptButton.type = "button";
        acceptButton.textContent = "Accept";
        acceptButton.addEventListener("click", function() {
            acceptMatch(match.id);
        });

        const declineButton = document.createElement("button");
        declineButton.type = "button";
        declineButton.textContent = "Decline";
        declineButton.addEventListener("click", function() {
            declineMatch(match.id);
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

// Accept a match
function acceptMatch(id) {
    const match = matches.find(function(item) {
        return item.id === id;
    });

    message.textContent = "You accepted " + match.name + "'s match!";
}

// Decline a match
function declineMatch(id) {
    const match = matches.find(function(item) {
        return item.id === id;
    });

    message.textContent = "You declined " + match.name + "'s match.";
}

// Display matches when the page loads
displayMatches();