
(() => {
    'use strict';

    const card = document.getElementById('movieCard');
    const poster = document.getElementById('moviePoster');
    const title = document.getElementById('movieTitle');
    const overview = document.getElementById('movieOverview');
    const releaseDate = document.getElementById('movieReleaseDate');
    const yesButton = document.getElementById('swipeYes');
    const noButton = document.getElementById('swipeNo');
    const status = document.getElementById('swipeStatus');

    if (!card || !poster || !title || !overview || !yesButton || !noButton || !status) {
        return;
    }

    let activeMovie = null;
    let busy = false;

    function showStatus(message, isError = false) {
        status.textContent = message;
        status.classList.toggle('is-error', isError);
    }

    function setButtonsDisabled(disabled) {
        yesButton.disabled = disabled;
        noButton.disabled = disabled;
    }

    async function loadNextMovie() {
        if (busy) return;

        busy = true;
        activeMovie = null;
        setButtonsDisabled(true);
        card.classList.add('is-loading');
        showStatus('Loading your next movie...');

        try {
            const response = await fetch('api/movies-next.php', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json'
                }
            });

            const result = await response.json();

            if (response.status === 401 || response.status === 403) {
                window.location.href = 'login.html';
                return;
            }

            if (!response.ok || !result.success || !result.movie) {
                throw new Error(result.error || 'Unable to load a movie.');
            }

            activeMovie = result.movie;

            title.textContent = activeMovie.title;
            overview.textContent = activeMovie.overview || 'No description is available.';

            poster.hidden = !activeMovie.poster_url;
            poster.alt = activeMovie.poster_url
                ? `${activeMovie.title} poster`
                : '';

            if (activeMovie.poster_url) {
                poster.src = activeMovie.poster_url;
            } else {
                poster.removeAttribute('src');
            }

            if (releaseDate) {
                releaseDate.textContent = activeMovie.release_date
                    ? new Date(`${activeMovie.release_date}T00:00:00`)
                        .toLocaleDateString(undefined, {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        })
                    : '';
            }

            showStatus('Would you watch this movie?');
            setButtonsDisabled(false);

        } catch (error) {
            title.textContent = 'Unable to load a movie';
            overview.textContent = error.message;

            poster.removeAttribute('src');
            poster.hidden = true;

            if (releaseDate) {
                releaseDate.textContent = '';
            }

            showStatus(error.message, true);

        } finally {
            busy = false;
            card.classList.remove('is-loading');

            if (!activeMovie) {
                setButtonsDisabled(true);
            }
        }
    }

    async function submitSwipe(decision) {
        if (!activeMovie || busy) return;

        busy = true;
        setButtonsDisabled(true);
        showStatus('Saving your choice...');

        try {
            const response = await fetch('api/swipe.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json'
                },
                body: JSON.stringify({
                    movie_id: activeMovie.id,
                    decision: decision
                })
            });

            const result = await response.json();

            if (response.status === 401 || response.status === 403) {
                window.location.href = 'login.html';
                return;
            }

            if (!response.ok || !result.success) {
                throw new Error(
                    result.error || 'Your swipe could not be saved.'
                );
            }

            activeMovie = null;
            busy = false;

            await loadNextMovie();

        } catch (error) {
            showStatus(error.message, true);
            setButtonsDisabled(false);
            busy = false;
        }
    }

    yesButton.addEventListener('click', () => {
        submitSwipe('yes');
    });

    noButton.addEventListener('click', () => {
        submitSwipe('no');
    });

    loadNextMovie();
})();
