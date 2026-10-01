/* assets/app.js */

document.addEventListener('DOMContentLoaded', () => {

    // ==========================================
    // 1. ÉLÉMENTS PRINCIPAUX DES MODALES
    // ==========================================
    const reviewModal = document.getElementById('review-modal');
    const modalTitle = document.getElementById('modal-title');
    const modalBody = document.getElementById('modal-body');

    // Déclencheurs d'ouverture pour la création de review (supporte les deux ID possibles)
    const btnOpenReview = document.getElementById('btn-open-review') || document.getElementById('open-new-review-modal');

    if (btnOpenReview && reviewModal) {
        btnOpenReview.addEventListener('click', () => {
            if (modalTitle) modalTitle.innerText = 'Créer une review';
            openModal(reviewModal);
            loadModalContent('/modal/search-form');
        });
    }

    // ==========================================
    // 2. MODALE « À PROPOS / AIDE »
    // ==========================================
    const btnAbout = document.getElementById('btn-about-trigger') || document.getElementById('floating-help-btn');
    const aboutModal = document.getElementById('about-modal') || document.getElementById('help-modal');
    const btnCloseAbout = document.getElementById('btn-close-about') || document.getElementById('close-help-modal');

    if (btnAbout && aboutModal) {
        btnAbout.addEventListener('click', () => openModal(aboutModal));
    }

    if (btnCloseAbout && aboutModal) {
        btnCloseAbout.addEventListener('click', () => closeModal(aboutModal));
    }

    // ==========================================
    // 3. DÉLÉGATION GLOBALE DES CLICS (Modales & Likes)
    // ==========================================
    document.addEventListener('click', async (e) => {

        // --- 3.1. Modifier une review ---
        const btnEdit = e.target.closest('.btn-edit-review');
        if (btnEdit && reviewModal) {
            if (modalTitle) modalTitle.innerText = 'Modifier la review';
            openModal(reviewModal);
            loadModalContent(`/modal/edit/${btnEdit.getAttribute('data-id')}`);
            return;
        }

        // --- 3.2. Choisir un album / Écrire une review ---
        const btnChoose = e.target.closest('.btn-choose-album');
        if (btnChoose && reviewModal) {
            if (modalTitle) modalTitle.innerText = 'Écrire une review';
            openModal(reviewModal);
            loadModalContent(`/modal/write/${btnChoose.getAttribute('data-spotify-id')}`);
            return;
        }

        // --- 3.3. Bouton Retour dans la recherche ---
        if (e.target.id === 'btn-back-search') {
            if (modalTitle) modalTitle.innerText = 'Créer une review';
            loadModalContent('/modal/search-form');
            return;
        }

        // --- 3.4. Historique des révisions ---
        const btnHistory = e.target.closest('.btn-history-review');
        if (btnHistory && reviewModal) {
            if (modalTitle) modalTitle.innerText = 'Historique des révisions';
            openModal(reviewModal);
            loadModalContent(`/modal/history/${btnHistory.getAttribute('data-id')}`);
            return;
        }

        // --- 3.5. Gestion des Likes ---
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
                    if (countSpan) countSpan.textContent = data.count;

                    if (heartIcon) {
                        if (data.isLiked) {
                            btnLike.style.color = '#e63946';
                            heartIcon.setAttribute('fill', '#e63946');
                        } else {
                            btnLike.style.color = '#ffffff';
                            heartIcon.setAttribute('fill', 'none');
                        }
                    }
                }
            } catch (err) {
                console.error('Erreur réseau lors du like :', err);
            } finally {
                delete btnLike.dataset.loading;
            }
        }
    });

    // ==========================================
    // 4. DÉLÉGATION GLOBALE DES SOUMISSIONS DE FORMULAIRES
    // ==========================================
    document.body.addEventListener('submit', (e) => {
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

    // ==========================================
    // 5. FERMETURE AU CLIC SUR L'ARRIÈRE-PLAN (Backdrop)
    // ==========================================
    document.querySelectorAll('dialog').forEach((dialog) => {
        dialog.addEventListener('click', (e) => {
            const rect = dialog.getBoundingClientRect();
            const isInDialog = (
                rect.top <= e.clientY &&
                e.clientY <= rect.top + rect.height &&
                rect.left <= e.clientX &&
                e.clientX <= rect.left + rect.width
            );
            if (!isInDialog) {
                closeModal(dialog);
            }
        });
    });

    // ==========================================
    // FONCTIONS UTILITAIRES INTERNES
    // ==========================================
    function openModal(dialogEl) {
        if (!dialogEl) return;
        if (typeof dialogEl.showModal === 'function') {
            dialogEl.showModal();
        } else {
            dialogEl.classList.add('is-open');
        }
    }

    function closeModal(dialogEl) {
        if (!dialogEl) return;
        if (typeof dialogEl.close === 'function') {
            dialogEl.close();
        } else {
            dialogEl.classList.remove('is-open');
        }
    }

    function loadModalContent(url) {
        if (modalBody) modalBody.innerHTML = '<p class="loading-text">Chargement...</p>';

        fetch(url)
            .then(response => {
                if (!response.ok) throw new Error('Erreur HTTP ' + response.status);
                return response.text();
            })
            .then(html => {
                if (modalBody) modalBody.innerHTML = html;
            })
            .catch(err => {
                if (modalBody) modalBody.innerHTML = `<p style="color:#e63946;">Erreur : ${err.message}</p>`;
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
                if (reviewModal) closeModal(reviewModal);
                window.location.href = data.redirect;
            }
        })
        .catch(err => console.error('Erreur traitement formulaire AJAX :', err));
    }

});