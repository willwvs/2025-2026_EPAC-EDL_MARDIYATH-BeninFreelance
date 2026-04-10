/**
 * Commun aux pages profil-client et profil-prestataire (session + déconnexion).
 */
(function () {
  async function initProfile() {
    const r = await fetch('/api/profile', { headers: { Accept: 'application/json' } });
    const d = await r.json();
    if (!r.ok || !d.ok) {
      window.location = '/login';
      return;
    }
    const el = document.getElementById('nom');
    if (el) {
      el.textContent = d.name || '';
    }
  }

  async function logout() {
    const t = await fetch('/csrf-token')
      .then(function (res) {
        return res.json();
      })
      .then(function (data) {
        return data.token;
      });
    const r = await fetch('/logout', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': t, Accept: 'application/json' },
    });
    const d = await r.json();
    window.location.href = d.redirect || '/login';
  }

  window.logout = logout;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProfile);
  } else {
    initProfile();
  }
})();

