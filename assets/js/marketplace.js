/**
 * Utilitaires partagés — recherche publique de services (/search) et fiches service.
 */
window.BFMarketplace = (function () {
  function escapeHtml(s) {
    const el = document.createElement('div');
    el.textContent = s === null || s === undefined ? '' : String(s);
    return el.innerHTML;
  }

  function initials(prenom, nom) {
    return ((prenom && prenom[0] ? prenom[0] : '') + (nom && nom[0] ? nom[0] : '')).toUpperCase() || '?';
  }

  function formatPrix(v) {
    if (v == null || v === '') return 'Sur devis';
    return Number(v).toLocaleString('fr-FR') + ' F';
  }

  function ratingLine(note, nbAvis) {
    const n = Number(note || 0).toFixed(1).replace('.', ',');
    if (nbAvis > 0) return n + ' · ' + nbAvis + ' avis';
    return n + ' · nouveau';
  }

  function fillSelect(id, values, placeholder) {
    const sel = document.getElementById(id);
    if (!sel) return;
    const cur = sel.value;
    sel.innerHTML = '';
    const o0 = document.createElement('option');
    o0.value = '';
    o0.textContent = placeholder;
    sel.appendChild(o0);
    (values || []).forEach(function (c) {
      const o = document.createElement('option');
      o.value = c;
      o.textContent = c;
      sel.appendChild(o);
    });
    if (cur && Array.from(sel.options).some(function (opt) { return opt.value === cur; })) {
      sel.value = cur;
    }
  }

  function readFiltersFromForm(ids) {
    ids = ids || {};
    return {
      q: (document.getElementById(ids.q || 'flt-q') || {}).value || '',
      categorie: (document.getElementById(ids.categorie || 'flt-cat') || {}).value || '',
      localite: (document.getElementById(ids.localite || 'flt-loc') || {}).value || '',
      note_min: (document.getElementById(ids.note || 'flt-note') || {}).value || '',
    };
  }

  function applyFiltersToForm(filters, ids) {
    ids = ids || {};
    const q = document.getElementById(ids.q || 'flt-q');
    const cat = document.getElementById(ids.categorie || 'flt-cat');
    const loc = document.getElementById(ids.localite || 'flt-loc');
    const note = document.getElementById(ids.note || 'flt-note');
    if (q) q.value = filters.q || '';
    if (cat) cat.value = filters.categorie || '';
    if (loc) loc.value = filters.localite || '';
    if (note) note.value = filters.note_min || '';
  }

  function filtersToParams(filters, page) {
    const p = new URLSearchParams();
    if (filters.q) p.set('q', filters.q.trim());
    if (filters.categorie) p.set('categorie', filters.categorie);
    if (filters.localite) p.set('localite', filters.localite);
    if (filters.note_min !== '') p.set('note_min', filters.note_min);
    if (page > 1) p.set('page', String(page));
    return p;
  }

  function filtersFromLocation() {
    const sp = new URLSearchParams(window.location.search);
    return {
      q: sp.get('q') || '',
      categorie: sp.get('categorie') || '',
      localite: sp.get('localite') || '',
      note_min: sp.get('note_min') || '',
      page: Math.max(1, parseInt(sp.get('page') || '1', 10) || 1),
    };
  }

  function pushFiltersToLocation(filters, page) {
    const p = filtersToParams(filters, page);
    const qs = p.toString();
    history.replaceState(null, '', qs ? '/search?' + qs : '/search');
  }

  /** Carte service type Upwork : bannière 16/9 en tête, contenu en dessous. */
  function serviceBannerCard(row, opts) {
    opts = opts || {};
    const p = row.prestataire || {};
    const detailUrl = row.detail_url || '/service/' + row.id;
    const contactBase = opts.contactBase || '/contact/prestataire/';
    const showContact = !!opts.showContact;
    const img = row.photo_url
      ? '<img src="' + escapeHtml(row.photo_url) + '" alt="" class="absolute inset-0 w-full h-full object-cover transition-transform duration-300 group-hover:scale-[1.02]" loading="lazy" />'
      : '<div class="absolute inset-0 bg-gradient-to-br from-accent/25 via-surface2 to-bg flex items-center justify-center"><span class="font-syne font-bold text-3xl text-accent/80">' + escapeHtml(initials(p.prenom, p.nom)) + '</span></div>';
    const name = escapeHtml(((p.prenom || '') + ' ' + (p.nom || '')).trim());
    const prix = escapeHtml(formatPrix(row.prix_indicatif));
    const contactBtn = showContact && p.id
      ? '<a href="' + escapeHtml(contactBase + p.id + '?service=' + row.id) + '" class="inline-flex items-center justify-center rounded-xl border border-accent/50 text-accent font-syne font-bold text-xs px-4 py-2 hover:bg-accent/10">Contacter</a>'
      : '';
    return (
      '<article class="group flex flex-col overflow-hidden rounded-2xl border border-brd bg-surface2 hover:border-accent/35 transition-colors">' +
      '<a href="' + escapeHtml(detailUrl) + '" class="block relative aspect-[16/9] w-full overflow-hidden bg-surface shrink-0">' + img + '</a>' +
      '<div class="flex flex-1 flex-col p-4 sm:p-5">' +
      '<a href="' + escapeHtml(detailUrl) + '" class="font-syne font-bold text-base sm:text-lg text-tx leading-snug mb-1 hover:text-accent">' + escapeHtml(row.titre) + '</a>' +
      '<p class="text-xs text-muted mb-2">' + name + ' · ' + escapeHtml(ratingLine(p.note_moyenne, p.nb_avis)) + '</p>' +
      '<p class="text-sm text-tx/80 leading-relaxed mb-3 line-clamp-2">' + escapeHtml(row.snippet || '') + '</p>' +
      '<p class="text-xs text-muted mb-4 flex flex-wrap gap-x-2 gap-y-1">' +
      (row.categorie ? '<span>' + escapeHtml(row.categorie) + '</span>' : '') +
      (p.localite ? '<span>· ' + escapeHtml(p.localite) + '</span>' : '') +
      (row.delai_execution ? '<span>· ' + escapeHtml(row.delai_execution) + '</span>' : '') +
      '</p>' +
      '<div class="mt-auto flex flex-wrap items-center justify-between gap-2">' +
      '<span class="text-accent font-syne font-bold text-sm">' + prix + '</span>' +
      '<div class="flex flex-wrap gap-2">' +
      '<a href="' + escapeHtml(detailUrl) + '" class="inline-flex items-center justify-center rounded-xl bg-accent text-bg font-syne font-bold text-xs px-4 py-2 hover:opacity-90">Voir</a>' +
      contactBtn +
      '</div></div></div></article>'
    );
  }

  return {
    escapeHtml: escapeHtml,
    initials: initials,
    formatPrix: formatPrix,
    ratingLine: ratingLine,
    fillSelect: fillSelect,
    readFiltersFromForm: readFiltersFromForm,
    applyFiltersToForm: applyFiltersToForm,
    filtersToParams: filtersToParams,
    filtersFromLocation: filtersFromLocation,
    pushFiltersToLocation: pushFiltersToLocation,
    serviceBannerCard: serviceBannerCard,
  };
})();
