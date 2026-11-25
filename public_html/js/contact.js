const contactDetails = document.getElementById("contactDetails");
const contactSubtitle = document.getElementById("contactSubtitle");
const message = document.getElementById("message");

// the match id is in the url, like contact.html?match_id=12
const urlParams = new URLSearchParams(window.location.search);
const matchId = urlParams.get("match_id");

loadContact();

async function loadContact() {
    // no match id in the url
    if (!matchId) {
        contactSubtitle.textContent = "We could not show this contact.";
        message.textContent = "No match was chosen. Please go back to your matches.";
        return;
    }

    try {
        const response = await fetch("/api/match-contact.php?match_id=" + encodeURIComponent(matchId));
        const data = await response.json();

        // not logged in, so go to the login page
        if (response.status === 401) {
            window.location.href = "/login.html";
            return;
        }

        // a blocked account also goes to the login page
        if (response.status === 403 && data.error === "This account is blocked.") {
            window.location.href = "/login.html";
            return;
        }

        // for example: both users must accept first, or the match does not exist
        if (!data.success) {
            contactSubtitle.textContent = "We could not show this contact.";
            message.textContent = data.error || "Unable to load the contact.";
            return;
        }

        // everything is fine, so we show the name and the email
        contactSubtitle.textContent = "You both accepted! You can now connect.";
        contactDetails.innerHTML = "";

        const title = document.createElement("h3");
        title.textContent = "Match Contact Information";

        const name = document.createElement("p");
        name.textContent = data.contact.name;

        const email = document.createElement("p");
        email.className = "contact-email";

        const emailLink = document.createElement("a");
        emailLink.href = "mailto:" + data.contact.email;
        emailLink.textContent = data.contact.email;
        email.appendChild(emailLink);

        const note = document.createElement("p");
        note.className = "contact-status";
        note.textContent = "Write to each other to choose a movie and plan your day at the cinema.";

        contactDetails.appendChild(title);
        contactDetails.appendChild(name);
        contactDetails.appendChild(email);
        contactDetails.appendChild(note);

    } catch (error) {
        contactSubtitle.textContent = "We could not show this contact.";
        message.textContent = "Unable to connect to the server. Please try again.";
        console.error("Contact error:", error);
    }
}