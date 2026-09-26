document.addEventListener('DOMContentLoaded', () => {
    const btnOpen = document.getElementById('btn-open-review');
    const modal = document.getElementById('review-modal');
    const modalTitle = document.getElementById('modal-title');

    // Ouverture initiale de la modale de création
    if (btnOpen && modal) {
        btnOpen.addEventListener('click', () => {
            modalTitle.innerText = 'Créer une review';
            modal.showModal();
            loadModalContent('/modal/search-form');
        });
    }

    // Délégation globale des CLICS (Modales & Likes)
    document.addEventListener('click', async function (e) {
        
        // 1. Modifier une review
        const btnEdit = e.target.closest('.btn-edit-review');
        if (btnEdit && modal) {
            modalTitle.innerText = 'Modifier la review';
            modal.showModal();
            loadModalContent(`/modal/edit/${btnEdit.getAttribute('data-id')}`);
            return;
        }

        // 2. Choisir un album / Nouvelle révision
        const btnChoose = e.target.closest('.btn-choose-album');
        if (btnChoose && modal) {
            modalTitle.innerText = 'Écrire une review';
            modal.showModal();
            loadModalContent(`/modal/write/${btnChoose.getAttribute('data-spotify-id')}`);
            return;
        }

        // 3. Bouton Retour dans la recherche
        if (e.target.id === 'btn-back-search') {
            modalTitle.innerText = 'Créer une review';
            loadModalContent('/modal/search-form');
            return;
        }

        // 4. Historique des révisions
        const btnHistory = e.target.closest('.btn-history-review');
        if (btnHistory && modal) {
            modalTitle.innerText = 'Historique des révisions';
            modal.showModal();
            loadModalContent(`/modal/history/${btnHistory.getAttribute('data-id')}`);
            return;
        }

        // 5. Gestion des Likes
        const btnLike = e.target.closest('.like-btn');
        if (btnLike) {
            e.preventDefault();

            if (btnLike.dataset.loading === 'true') return;
            btnLike.dataset.loading = 'true';

            const url = btnLike.dataset.url;
            const heartIcon = btnLike.querySelector('.heart-icon');
            const countSpan = btnLike.querySelector('.like-count');

            try {
                const response = await fetch(url, { method: 'POST' });

                if (response.status === 401) {
                    window.location.href = '/login';
                    return;
                }

                if (response.ok) {
                    const data = await response.json();
                    countSpan.textContent = data.count;

                    if (data.isLiked) {
                        btnLike.style.color = '#e63946';
                        heartIcon.setAttribute('fill', '#e63946');
                    } else {
                        btnLike.style.color = '#ffffff';
                        heartIcon.setAttribute('fill', 'none');
                    }
                }
            } catch (err) {
                console.error('Erreur réseau lors du like :', err);
            } finally {
                delete btnLike.dataset.loading;
            }
        }
    });

    // Délégation globale des SOUMISSIONS de formulaires
    document.body.addEventListener('submit', function (e) {
        if (e.target.id === 'modal-search-form') {
            e.preventDefault();
            const q = new FormData(e.target).get('q');
            loadModalContent(`/modal/search-results?q=${encodeURIComponent(q)}`);
        }

        if (e.target.id === 'modal-write-form' || e.target.id === 'modal-edit-form') {
            e.preventDefault();
            handleAjaxFormPost(e.target);
        }
    });
});

// Fonctions utilitaires
function loadModalContent(url) {
    const modalBody = document.getElementById('modal-body');
    if (modalBody) modalBody.innerHTML = '<p>Chargement...</p>';

    fetch(url)
        .then(response => {
            if (!response.ok) throw new Error('Erreur HTTP ' + response.status);
            return response.text();
        })
        .then(html => {
            if (modalBody) modalBody.innerHTML = html;
        })
        .catch(err => {
            if (modalBody) modalBody.innerHTML = `<p style="color:red;">Erreur : ${err.message}</p>`;
            console.error('Fetch error:', err);
        });
}

function handleAjaxFormPost(form) {
    const formData = new FormData(form);
    fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            document.getElementById('review-modal').close();
            window.location.href = data.redirect;
        }
    })
    .catch(err => console.error('Erreur traitement formulaire AJAX:', err));
}


// Dans document.addEventListener('DOMContentLoaded', () => { ...

const btnAbout = document.getElementById('btn-about-trigger');
const aboutModal = document.getElementById('about-modal');
const btnCloseAbout = document.getElementById('btn-close-about');

if (btnAbout && aboutModal) {
    btnAbout.addEventListener('click', () => {
        aboutModal.showModal();
    });
}

if (btnCloseAbout && aboutModal) {
    btnCloseAbout.addEventListener('click', () => {
        aboutModal.close();
    });
}

// Fermeture si on clique à l'extérieur de la modale (sur l'arrière-plan obscurci)
if (aboutModal) {
    aboutModal.addEventListener('click', (e) => {
        const rect = aboutModal.getBoundingClientRect();
        const isInDialog = (
            rect.top <= e.clientY &&
            e.clientY <= rect.top + rect.height &&
            rect.left <= e.clientX &&
            e.clientX <= rect.left + rect.width
        );
        if (!isInDialog) {
            aboutModal.close();
        }
    });
}