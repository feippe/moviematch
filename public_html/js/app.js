const welcome = document.getElementById("welcome");
const logoutLink = document.getElementById("logoutLink");

// when the page opens, we check who is logged in
checkUser();

async function checkUser() {
    try {
        const response = await fetch("/api/me.php");

        // not logged in (or blocked), so go to the login page
        if (response.status === 401 || response.status === 403) {
            window.location.href = "/login.html";
            return;
        }

        const data = await response.json();

        // no profile yet, so go to the profile page
        if (!data.user.profile_complete) {
            window.location.href = "/profile.html";
            return;
        }

        welcome.textContent = "Hi, " + data.user.profile.name + "!";

    } catch (error) {
        welcome.textContent = "Unable to connect to the server.";
        console.error("Check user error:", error);
    }
}

logoutLink.addEventListener("click", async function(event) {
    event.preventDefault();

    try {
        await fetch("/api/logout.php", { method: "POST" });
    } catch (error) {
        console.error("Logout error:", error);
    }

    window.location.href = "/login.html";
});
