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
            if (modalTitle) modalTitle.innerText = 'Nouvelle critique';
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
    // 2 bis. POP-UP « BÊTA FERMÉE » (une seule fois par appareil)
    // ==========================================
    const betaModal = document.querySelector('[data-beta-notice]');
    if (betaModal) {
        const BETA_KEY = 'album-list:beta-notice-seen';
        let seen = false;
        try {
            seen = localStorage.getItem(BETA_KEY) === '1';
        } catch (err) { /* stockage indisponible : la pop-up s'affichera à chaque visite */ }

        if (!seen) {
            openModal(betaModal);
            betaModal.querySelector('.beta-notice__ok')?.focus();
        }
        // Fermeture par le bouton, la croix ou Échap : on ne la remontre plus
        betaModal.addEventListener('close', () => {
            try {
                localStorage.setItem(BETA_KEY, '1');
            } catch (err) { /* stockage indisponible */ }
        });
    }

    // ==========================================
    // 3. DÉLÉGATION GLOBALE DES CLICS (Modales & Likes)
    // ==========================================
    document.addEventListener('click', async (e) => {

        // --- 3.0. Boutons de fermeture des modales ---
        const btnClose = e.target.closest('[data-close-dialog]');
        if (btnClose) {
            closeModal(document.getElementById(btnClose.dataset.closeDialog));
            return;
        }

        // --- 3.1. Modifier une review ---
        const btnEdit = e.target.closest('.btn-edit-review');
        if (btnEdit && reviewModal) {
            if (modalTitle) modalTitle.innerText = 'Modifier la critique';
            openModal(reviewModal);
            loadModalContent(`/modal/edit/${btnEdit.getAttribute('data-id')}`);
            return;
        }

        // --- 3.2. Choisir un album / Écrire une review ---
        const btnChoose = e.target.closest('.btn-choose-album');
        if (btnChoose && reviewModal) {
            if (modalTitle) modalTitle.innerText = 'Nouvelle critique';
            openModal(reviewModal);
            loadModalContent(`/modal/write/${btnChoose.getAttribute('data-spotify-id')}`);
            return;
        }

        // --- 3.3. Bouton Retour dans la recherche ---
        if (e.target.id === 'btn-back-search') {
            if (modalTitle) modalTitle.innerText = 'Nouvelle critique';
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

        // --- 3.5. « Écouter » : choix de la plateforme ---
        const btnListen = e.target.closest('[data-listen]');
        if (btnListen && listenModal) {
            e.preventDefault();
            openListenModal(btnListen.dataset);
            return;
        }
        if (e.target.closest('.listen-link') && listenModal) {
            closeModal(listenModal);
            return;
        }

        // --- 3.5 bis. Sélecteur d'étoiles (formulaires d'écriture / édition) ---
        const starBtn = e.target.closest('.star-picker__half');
        if (starBtn) {
            setStarRating(starBtn.closest('.star-field'), Number(starBtn.dataset.value));
            return;
        }

        // --- 3.6. « Lire la suite » sur les cartes ---
        const btnMore = e.target.closest('.review-card__more');
        if (btnMore) {
            const text = document.getElementById(btnMore.getAttribute('aria-controls'));
            const expanded = btnMore.getAttribute('aria-expanded') === 'true';
            if (text) text.classList.toggle('is-clamped', expanded);
            btnMore.setAttribute('aria-expanded', String(!expanded));
            btnMore.textContent = expanded ? 'Lire la suite ↓' : 'Réduire ↑';
            return;
        }

        // --- 3.7. Gestion des Likes ---
        const btnLike = e.target.closest('.like-btn');
        if (btnLike) {
            e.preventDefault();

            if (btnLike.dataset.loading === 'true') return;
            btnLike.dataset.loading = 'true';

            const url = btnLike.dataset.url;
            const heartIcon = btnLike.querySelector('.heart-icon');
            const countSpan = btnLike.querySelector('.like-count');

            try {
                const csrf = document.querySelector('meta[name="csrf-like"]');
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrf ? csrf.content : '' }
                });

                if (response.status === 401) {
                    window.location.href = '/login';
                    return;
                }

                if (response.ok) {
                    const data = await response.json();
                    if (countSpan) countSpan.textContent = data.count;

                    btnLike.classList.toggle('is-liked', data.isLiked);
                    btnLike.setAttribute('aria-pressed', String(data.isLiked));
                    if (heartIcon) {
                        heartIcon.setAttribute('fill', data.isLiked ? 'currentColor' : 'none');
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
        // Confirmation avant les actions destructrices (ex. suppression d'une critique)
        if (e.target.dataset.confirm && !confirm(e.target.dataset.confirm)) {
            e.preventDefault();
            return;
        }

        if (e.target.id === 'modal-search-form') {
            e.preventDefault();
            const q = new FormData(e.target).get('q');
            loadModalContent(`/modal/search-results?q=${encodeURIComponent(q)}`);
        }

        if (e.target.id === 'modal-write-form' || e.target.id === 'modal-edit-form') {
            e.preventDefault();
            const ratingInput = e.target.querySelector('.rating-input');
            if (ratingInput && !ratingInput.value) {
                const errors = e.target.querySelector('.star-field .form-errors');
                if (errors) errors.textContent = 'Choisis une note.';
                return;
            }
            handleAjaxFormPost(e.target);
        }
    });

    // ==========================================
    // 4 bis. CLAVIER : flèches dans le sélecteur d'étoiles
    // ==========================================
    document.addEventListener('keydown', (e) => {
        const half = e.target.closest('.star-picker__half');
        if (!half) return;

        const current = Number(half.dataset.value);
        const next = {
            ArrowRight: current + 1,
            ArrowUp: current + 1,
            ArrowLeft: current - 1,
            ArrowDown: current - 1,
            Home: 1,
            End: 10,
        }[e.key];
        if (next === undefined) return;

        e.preventDefault();
        const value = Math.min(10, Math.max(1, next));
        const field = half.closest('.star-field');
        setStarRating(field, value);
        const target = field.querySelector(`.star-picker__half[data-value="${value}"]`);
        if (target) target.focus();
    });

    // ==========================================
    // 4 ter. CLAVIER : Entrée valide les zones de texte courtes (Maj+Entrée = retour à la ligne)
    // ==========================================
    // Les champs d'une ligne valident déjà leur formulaire avec Entrée (comportement natif).
    // La critique n'est pas concernée : Entrée y fait toujours un retour à la ligne.
    document.addEventListener('keydown', (e) => {
        const area = e.target.closest('textarea[data-submit-on-enter]');
        if (!area || e.key !== 'Enter' || e.shiftKey || e.isComposing) return;
        e.preventDefault();
        if (area.form) area.form.requestSubmit();
    });

    // ==========================================
    // 5. FERMETURE AU CLIC SUR L'ARRIÈRE-PLAN (Backdrop)
    // ==========================================
    // Le clic doit commencer ET finir hors de la fenêtre : sélectionner du texte dans la critique
    // et relâcher la souris à l'extérieur ne ferme plus la modale.
    document.querySelectorAll('dialog').forEach((dialog) => {
        const isOutside = (e) => {
            const rect = dialog.getBoundingClientRect();
            return e.clientY < rect.top || e.clientY > rect.bottom || e.clientX < rect.left || e.clientX > rect.right;
        };
        let pressedOutside = false;

        dialog.addEventListener('pointerdown', (e) => {
            pressedOutside = e.target === dialog && isOutside(e);
        });
        dialog.addEventListener('click', (e) => {
            const shouldClose = pressedOutside && e.target === dialog && isOutside(e);
            pressedOutside = false;
            // Une critique en cours d'écriture ne se ferme jamais par un clic extérieur
            if (shouldClose && !dialog.querySelector('.review-form')) {
                closeModal(dialog);
            }
        });
    });

    // ==========================================
    // 5 bis. BROUILLONS DES CRITIQUES (sauvegardés sur l'appareil)
    // ==========================================
    // Texte et note sont enregistrés à chaque modification et restaurés à la réouverture du formulaire ;
    // le brouillon n'est effacé qu'une fois la critique publiée.
    const DRAFT_PREFIX = 'album-list:draft:';

    function draftKey(form) {
        return DRAFT_PREFIX + new URL(form.action, window.location.href).pathname;
    }

    function saveDraft(form) {
        const content = form.querySelector('textarea');
        const rating = form.querySelector('.rating-input');
        try {
            localStorage.setItem(draftKey(form), JSON.stringify({
                content: content ? content.value : '',
                rating: rating ? rating.value : '',
            }));
        } catch (err) {
            // Stockage indisponible (navigation privée…) : pas de brouillon, sans bloquer l'écriture
        }
    }

    function clearDraft(form) {
        try {
            localStorage.removeItem(draftKey(form));
        } catch (err) { /* stockage indisponible */ }
    }

    function restoreDraft(form) {
        let draft = null;
        try {
            draft = JSON.parse(localStorage.getItem(draftKey(form)) || 'null');
        } catch (err) { /* stockage indisponible ou brouillon illisible */ }
        if (!draft || (!draft.content && !draft.rating)) return;

        const content = form.querySelector('textarea');
        const field = form.querySelector('.star-field');
        if (content && draft.content) content.value = draft.content;
        if (field && draft.rating) setStarRating(field, Number(draft.rating));

        const note = document.createElement('p');
        note.className = 'draft-note';
        note.setAttribute('role', 'status');
        note.textContent = 'Brouillon restauré : tu reprends là où tu t\'étais arrêté.';
        form.prepend(note);
    }

    document.addEventListener('input', (e) => {
        const form = e.target.closest('.review-form');
        if (form) saveDraft(form);
    });

    // ==========================================
    // 6. « LIRE LA SUITE » : bouton affiché seulement si le texte déborde
    // ==========================================
    function initReadMore() {
        document.querySelectorAll('.review-card__more').forEach((btn) => {
            if (btn.getAttribute('aria-expanded') === 'true') return;
            const text = document.getElementById(btn.getAttribute('aria-controls'));
            btn.hidden = !text || text.scrollHeight <= text.clientHeight + 1;
        });
    }

    initReadMore();
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(initReadMore, 150);
    });
    if (document.fonts) document.fonts.ready.then(initReadMore);

    // ==========================================
    // FONCTIONS UTILITAIRES INTERNES
    // ==========================================
    const listenModal = document.getElementById('listen-modal');

    // Spotify : lien direct vers l'album ; autres plateformes : recherche « artiste titre »
    const LISTEN_URLS = {
        spotify: (d) => `https://open.spotify.com/album/${encodeURIComponent(d.spotifyId)}`,
        apple: (d, q) => `https://music.apple.com/fr/search?term=${q}`,
        deezer: (d, q) => `https://www.deezer.com/fr/search/${q}`,
        youtube: (d, q) => `https://music.youtube.com/search?q=${q}`,
        tidal: (d, q) => `https://listen.tidal.com/search?q=${q}`,
        amazon: (d, q) => `https://music.amazon.fr/search/${q}`,
        soundcloud: (d, q) => `https://soundcloud.com/search?q=${q}`,
    };

    function openListenModal(data) {
        const q = encodeURIComponent(`${data.artist} ${data.title}`);
        listenModal.querySelector('.listen-album__title').textContent = data.title;
        listenModal.querySelector('.listen-album__artist').textContent = data.artist;
        listenModal.querySelectorAll('.listen-link').forEach((link) => {
            const build = LISTEN_URLS[link.dataset.platform];
            if (build) link.href = build(data, q);
        });
        openModal(listenModal);
        focusFirst(listenModal.querySelector('.listen-list'));
    }

    // Place le focus sur le premier champ (ou à défaut le premier élément interactif) d'un conteneur
    function focusFirst(container) {
        if (!container) return;
        const target = container.querySelector('.star-picker__half[tabindex="0"], input:not([type="hidden"]), textarea, select')
            || container.querySelector('a[href], button:not([disabled]), [tabindex="0"]');
        if (target) target.focus();
    }

    const VERDICTS = ['', 'À fuir', 'À fuir', 'Bof', 'Bof', 'Correct', 'Correct', 'Solide', 'Solide', 'Indispensable', "Chef-d'œuvre"];

    function setStarRating(field, value) {
        if (!field) return;
        const input = field.querySelector('.rating-input');
        if (input && value) input.value = value;

        field.querySelectorAll('.star-picker .star').forEach((star, i) => {
            star.classList.toggle('is-full', value >= (i + 1) * 2);
            star.classList.toggle('is-half', value === i * 2 + 1);
        });

        const display = field.querySelector('.rating-display__value');
        const verdict = field.querySelector('.rating-display__verdict');
        if (display) display.textContent = value || '–';
        if (verdict) verdict.textContent = VERDICTS[value] || '';

        const errors = field.querySelector('.form-errors');
        if (errors && value) errors.textContent = '';

        const reviewForm = field.closest('.review-form');
        if (reviewForm && value) saveDraft(reviewForm);

        // Groupe radio : une seule demi-étoile atteignable au Tab (la note choisie, sinon la première)
        const halves = [...field.querySelectorAll('.star-picker__half')];
        halves.forEach((half) => {
            const checked = Number(half.dataset.value) === value;
            half.setAttribute('aria-checked', String(checked));
            half.tabIndex = checked || (!value && half === halves[0]) ? 0 : -1;
        });
    }

    function initStarFields(root) {
        root.querySelectorAll('.star-field').forEach((field) => {
            const input = field.querySelector('.rating-input');
            setStarRating(field, input ? Number(input.value) || 0 : 0);
        });
    }
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
            .then(async (response) => {
                // 503 : le serveur renvoie un message prêt à afficher (Spotify indisponible)
                if (!response.ok && response.status !== 503) throw new Error('Erreur HTTP ' + response.status);
                return response.text();
            })
            .then(html => {
                if (modalBody) {
                    modalBody.innerHTML = html;
                    initStarFields(modalBody);
                    modalBody.querySelectorAll('.review-form').forEach(restoreDraft);
                    focusFirst(modalBody);
                }
            })
            .catch(err => {
                if (modalBody) modalBody.innerHTML = `<p class="form-alert">Erreur : ${err.message}</p>`;
                console.error('Fetch error:', err);
            });
    }

    function setFormBusy(form, busy) {
        const submit = form.querySelector('[type="submit"]');
        form.dataset.submitting = busy ? 'true' : '';
        form.setAttribute('aria-busy', String(busy));
        if (!submit) return;
        if (busy) {
            submit.dataset.label = submit.textContent;
            submit.innerHTML = '<span class="spinner" aria-hidden="true"></span> Publication…';
        } else if (submit.dataset.label) {
            submit.textContent = submit.dataset.label;
        }
        submit.disabled = busy;
    }

    function showFormAlert(form, message) {
        let alert = form.querySelector('.form-alert');
        if (!alert) {
            alert = document.createElement('p');
            alert.className = 'form-alert';
            alert.setAttribute('role', 'alert');
            form.prepend(alert);
        }
        alert.textContent = message;
    }

    function handleAjaxFormPost(form) {
        // Empêche les envois multiples (double clic / spam du bouton)
        if (form.dataset.submitting === 'true') return;
        setFormBusy(form, true);

        const formData = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(async (res) => {
            // Formulaire invalide : le serveur renvoie le HTML du formulaire avec ses erreurs
            if (!(res.headers.get('Content-Type') || '').includes('application/json')) {
                if (modalBody) {
                    modalBody.innerHTML = await res.text();
                    initStarFields(modalBody);
                }
                return;
            }
            const data = await res.json();
            if (data.status === 'success') {
                clearDraft(form);
                // On garde le bouton désactivé jusqu'à la redirection
                if (reviewModal) closeModal(reviewModal);
                window.location.href = data.redirect;
                return;
            }
            setFormBusy(form, false);
            showFormAlert(form, data.message || 'La publication a échoué, réessaie.');
        })
        .catch((err) => {
            console.error('Erreur traitement formulaire AJAX :', err);
            setFormBusy(form, false);
            showFormAlert(form, 'Connexion impossible, vérifie ton réseau et réessaie.');
        });
    }

});