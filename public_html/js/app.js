const welcome = document.getElementById("welcome");
const logoutLink = document.getElementById("logoutLink");

async function checkUser() {
    try {
        const response = await fetch("/api/me.php");

        if (response.status === 401 || response.status === 403) {
            window.location.href = "/login.html";
            return;
        }

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.error || "Unable to load your account.");
        }

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

async function logout() {
    try {
        await fetch("/api/logout.php", {
            method: "POST"
        });
    } catch (error) {
        console.error("Logout error:", error);
    }

    window.location.href = "/login.html";
}

if (logoutLink) {
    logoutLink.addEventListener("click", function (event) {
        event.preventDefault();
        logout();
    });
}

checkUser();