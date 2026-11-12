// Menu mobile
document.querySelectorAll('[data-toggle-nav]').forEach(btn => {
    btn.addEventListener('click', () => document.getElementById('navLinks').classList.toggle('open'));
});

// Les messages (toasts) disparaissent après 4 secondes
document.querySelectorAll('.toast').forEach((toast, i) => {
    setTimeout(() => {
        toast.classList.add('hide');
        setTimeout(() => toast.remove(), 400);
    }, 4000 + i * 400);
});

// Confirmation avant les actions irréversibles
document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', e => {
        if (!confirm(form.dataset.confirm)) e.preventDefault();
    });
});

// Indicateur de force du mot de passe
document.querySelectorAll('input[data-strength]').forEach(input => {
    const barre = input.parentElement.querySelector('.strength-bar');
    if (!barre) return;
    input.addEventListener('input', () => {
        const mdp = input.value;
        let score = 0;
        if (mdp.length >= 8) score++;
        if (mdp.length >= 12) score++;
        if (/[a-z]/.test(mdp) && /[A-Z]/.test(mdp)) score++;
        if (/\d/.test(mdp)) score++;
        if (/[^A-Za-z0-9]/.test(mdp)) score++;
        const couleurs = ['#F43F5E', '#F43F5E', '#F97316', '#FBBF24', '#22C55E', '#22C55E'];
        barre.style.width = (score / 5 * 100) + '%';
        barre.style.background = couleurs[score];
    });
});

// Formulaire film : note et date de visionnage seulement si le film est "Vu"
const selectStatut = document.querySelector('[data-statut]');
const blocVu = document.querySelector('.si-vu');
if (selectStatut && blocVu) {
    const majStatut = () => { blocVu.style.display = selectStatut.value === 'vu' ? '' : 'none'; };
    selectStatut.addEventListener('change', majStatut);
    majStatut();
}

// Bouton "Effacer la note"
document.querySelectorAll('[data-clear-note]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.star-input input').forEach(radio => { radio.checked = false; });
    });
});
