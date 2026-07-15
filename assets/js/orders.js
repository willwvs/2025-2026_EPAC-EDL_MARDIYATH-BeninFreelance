/**
 * Commandes — vue client et prestataire (workroom style Upwork).
 *
 * Expose :
 *   - window.BFOrders({role, openCommandeId, onProposed?}) → liste + détail
 *   - window.BFOrdersOpenModal({prestataire_id, service_id, service_titre?}) → modale "Proposer"
 *
 * Émet sur `document` :
 *   - 'bf:order-proposed'  → après création réussie
 *   - 'bf:order-updated'   → après une action (paiement, livraison, validation, etc.)
 */
(function () {
  const STATUT_LABELS = {
    attente_prestataire: 'En attente du prestataire',
    attente_paiement: 'En attente de paiement',
    payee: 'Travail en cours',
    livree: 'À valider',
    en_revision: 'Retouche demandée',
    terminee: 'Terminée',
    paid_out: 'Clôturée',
    annulee: 'Annulée',
    remboursee: 'Remboursée',
    litige: 'En litige',
  };

  const STATUT_TONE = {
    attente_prestataire: 'bg-amber-500/15 text-amber-300 border-amber-500/30',
    attente_paiement: 'bg-orange-500/15 text-orange-300 border-orange-500/30',
    payee: 'bg-sky-500/15 text-sky-300 border-sky-500/30',
    livree: 'bg-indigo-500/15 text-indigo-300 border-indigo-500/30',
    en_revision: 'bg-rose-500/15 text-rose-300 border-rose-500/30',
    terminee: 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
    paid_out: 'bg-emerald-600/15 text-emerald-200 border-emerald-600/30',
    annulee: 'bg-zinc-500/15 text-zinc-300 border-zinc-500/30',
    remboursee: 'bg-zinc-500/15 text-zinc-300 border-zinc-500/30',
    litige: 'bg-orange-600/15 text-orange-200 border-orange-600/40',
  };

  const STAGE_ORDER = ['attente_prestataire', 'attente_paiement', 'payee', 'livree', 'terminee'];

  const ACCEPTED_MIME = '.jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.zip,.rar';
  const MAX_FILES = 8;
  const MAX_FILE_SIZE = 10 * 1024 * 1024;

  function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function formatMoney(n) {
    return Number(n || 0).toLocaleString('fr-FR') + ' FCFA';
  }

  function formatBytes(n) {
    const v = Number(n || 0);
    if (!v) return '';
    if (v < 1024) return v + ' o';
    if (v < 1024 * 1024) return Math.round(v / 1024) + ' Ko';
    return (v / (1024 * 1024)).toFixed(1).replace('.', ',') + ' Mo';
  }

  function formatDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
  }

  function formatDateTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' }) +
      ' à ' + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
  }

  function daysBetween(iso) {
    if (!iso) return null;
    const target = new Date(iso).getTime();
    if (Number.isNaN(target)) return null;
    const diffMs = target - Date.now();
    return Math.ceil(diffMs / (1000 * 60 * 60 * 24));
  }

  function fileIcon(mime, name) {
    const m = (mime || '').toLowerCase();
    const n = (name || '').toLowerCase();
    if (m.startsWith('image/')) return 'fa-image';
    if (m === 'application/pdf' || n.endsWith('.pdf')) return 'fa-file-pdf';
    if (m.includes('word') || n.match(/\.docx?$/)) return 'fa-file-word';
    if (m.includes('sheet') || m.includes('excel') || n.match(/\.xlsx?$/) || n.endsWith('.csv')) return 'fa-file-excel';
    if (m.includes('presentation') || n.match(/\.pptx?$/)) return 'fa-file-powerpoint';
    if (m.startsWith('text/') || n.endsWith('.txt')) return 'fa-file-lines';
    if (m.includes('zip') || n.match(/\.(zip|rar)$/)) return 'fa-file-zipper';
    return 'fa-file';
  }

  async function getCsrf() {
    const r = await fetch('/csrf-token', { headers: { Accept: 'application/json' } });
    return (await r.json()).token;
  }

  function toast(message, ok) {
    let host = document.getElementById('bf-toast-host');
    if (!host) {
      host = document.createElement('div');
      host.id = 'bf-toast-host';
      host.className = 'fixed top-4 right-4 z-[100] flex flex-col gap-2 max-w-sm w-full pointer-events-none';
      document.body.appendChild(host);
    }
    const el = document.createElement('div');
    el.className = 'pointer-events-auto rounded-xl border shadow-lg px-4 py-3 text-sm font-medium flex items-start gap-3 transition-all duration-300 translate-y-2 opacity-0 ' +
      (ok ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-100' : 'bg-rose-500/15 border-rose-500/40 text-rose-100');
    el.innerHTML =
      '<i class="fa-solid ' + (ok ? 'fa-circle-check' : 'fa-circle-exclamation') + ' mt-0.5 text-base"></i>' +
      '<span class="flex-1 whitespace-pre-line">' + escapeHtml(message) + '</span>';
    host.appendChild(el);
    requestAnimationFrame(function () {
      el.classList.remove('translate-y-2', 'opacity-0');
    });
    setTimeout(function () {
      el.classList.add('opacity-0');
      setTimeout(function () { el.remove(); }, 250);
    }, 4200);
  }

  // ─── Factory : page Commandes (liste + détail) ───────────────────────────────
  window.BFOrders = function (opts) {
    const role = opts.role;
    const apiBase = role === 'client' ? '/api/client/commandes' : '/api/prestataire/commandes';
    let list = [];
    let activeId = null;
    let detailCache = null;

    const el = {
      list: document.getElementById('orders-list'),
      detail: document.getElementById('orders-detail'),
    };

    function placeholder(text, icon) {
      if (!el.detail) return;
      el.detail.innerHTML =
        '<div class="h-full w-full flex items-center justify-center p-10">' +
        '<div class="text-center max-w-sm">' +
        '<i class="fa-solid ' + (icon || 'fa-file-contract') + ' text-4xl text-muted/40 mb-4"></i>' +
        '<p class="text-sm text-muted leading-relaxed">' + escapeHtml(text) + '</p>' +
        '</div></div>';
    }

    async function loadList() {
      if (!el.list) return;
      if (!list.length) el.list.innerHTML = '<p class="text-sm text-muted px-4 py-8 text-center">Chargement…</p>';
      try {
        const r = await fetch(apiBase, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const d = await r.json();
        if (!r.ok || !d.ok) {
          el.list.innerHTML = '<p class="text-sm text-rose-300 px-4 py-8 text-center">Impossible de charger.</p>';
          return;
        }
        list = d.commandes || [];
        renderList();
        if (activeId) await openDetail(activeId, true);
        else if (opts.openCommandeId) await openDetail(parseInt(opts.openCommandeId, 10), true);
      } catch (_) {
        el.list.innerHTML = '<p class="text-sm text-rose-300 px-4 py-8 text-center">Réseau indisponible.</p>';
      }
    }

    function renderList() {
      if (!el.list) return;
      if (!list.length) {
        const hint = role === 'client'
          ? 'Aucune commande. Ouvrez une conversation dans Messages et cliquez sur « Commander ».'
          : 'Aucune commande. Les propositions de vos clients apparaîtront ici.';
        el.list.innerHTML =
          '<div class="px-4 py-10 text-center text-muted">' +
          '<i class="fa-solid fa-folder-open text-3xl text-muted/40 mb-3"></i>' +
          '<p class="text-sm leading-relaxed">' + escapeHtml(hint) + '</p>' +
          '</div>';
        placeholder('Pas de commande sélectionnée.', 'fa-folder-open');
        return;
      }
      el.list.innerHTML = list.map(function (c) {
        const active = c.id === activeId;
        const tone = STATUT_TONE[c.statut] || 'bg-zinc-500/15 text-zinc-300 border-zinc-500/30';
        return (
          '<button type="button" data-id="' + c.id + '" class="orders-item block w-full text-left px-4 py-3 border-b border-brd/60 hover:bg-surface transition-colors ' +
          (active ? 'bg-surface border-l-2 border-l-accent' : '') + '">' +
          '<div class="flex items-start justify-between gap-2">' +
          '<p class="font-syne font-bold text-sm text-tx flex-1 min-w-0 truncate">' + escapeHtml(c.titre) + '</p>' +
          '<span class="shrink-0 text-[10px] px-1.5 py-0.5 rounded-full border ' + tone + '">' +
          escapeHtml(STATUT_LABELS[c.statut] || c.statut) + '</span>' +
          '</div>' +
          '<p class="text-xs text-muted mt-1.5 truncate">' + escapeHtml(c.peer_label || '') + '</p>' +
          '<p class="text-[11px] text-muted/80 mt-0.5">' + escapeHtml(formatMoney(c.montant_fcfa)) + '</p>' +
          '</button>'
        );
      }).join('');
      el.list.querySelectorAll('.orders-item').forEach(function (btn) {
        btn.addEventListener('click', function () {
          openDetail(parseInt(btn.getAttribute('data-id'), 10));
        });
      });
    }

    async function openDetail(id, silent) {
      activeId = id;
      renderList();
      if (!el.detail) return;
      if (!silent) placeholder('Chargement…', 'fa-spinner fa-spin');
      try {
        const r = await fetch(apiBase + '/' + id, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const d = await r.json();
        if (!r.ok || !d.ok) {
          placeholder('Commande introuvable.', 'fa-triangle-exclamation');
          return;
        }
        detailCache = d.commande;
        renderDetail(d.commande);
      } catch (_) {
        placeholder('Réseau indisponible.', 'fa-wifi');
      }
    }

    function timelineHtml(c) {
      const stages = [
        { key: 'attente_prestataire', label: 'Proposée', date: c.created_at },
        { key: 'attente_paiement', label: 'Acceptée', date: c.acceptee_at },
        { key: 'payee', label: 'Payée', date: c.payee_at },
        { key: 'livree', label: 'Livrée', date: c.livree_at },
        { key: 'terminee', label: 'Terminée', date: c.terminee_at },
      ];
      const currentIdx = STAGE_ORDER.indexOf(c.statut);
      const livreeIdx = STAGE_ORDER.indexOf('livree');
      const failed = c.statut === 'annulee' || c.statut === 'remboursee';

      return (
        '<div class="rounded-2xl border border-brd bg-surface/40 p-4 sm:p-5">' +
        '<p class="text-[11px] uppercase tracking-wider text-muted font-bold mb-3">Progression</p>' +
        '<ol class="flex items-start justify-between gap-2">' +
        stages.map(function (s, i) {
          const sIdx = STAGE_ORDER.indexOf(s.key);
          const done = !failed && (sIdx < currentIdx || (sIdx === currentIdx && (s.date || c.statut === 'paid_out' || c.statut === 'terminee'))) ||
            (c.statut === 'paid_out' && sIdx <= STAGE_ORDER.indexOf('terminee'));
          const active = !failed && sIdx === currentIdx;
          const reachedLivree = sIdx <= livreeIdx && (c.statut === 'en_revision');
          const isDone = done || reachedLivree;
          const dot =
            isDone ? 'bg-accent text-bg border-accent' :
            active ? 'bg-bg text-accent border-accent ring-4 ring-accent/20' :
            'bg-bg text-muted border-brd';
          return (
            '<li class="flex-1 flex flex-col items-center text-center min-w-0">' +
            '<div class="w-7 h-7 rounded-full border-2 flex items-center justify-center text-[11px] font-bold ' + dot + '">' +
            (isDone ? '<i class="fa-solid fa-check"></i>' : (i + 1)) +
            '</div>' +
            '<p class="text-[10px] font-bold mt-1.5 ' + (isDone || active ? 'text-tx' : 'text-muted') + '">' + escapeHtml(s.label) + '</p>' +
            (s.date ? '<p class="text-[9px] text-muted mt-0.5 hidden sm:block">' + escapeHtml(formatDate(s.date)) + '</p>' : '') +
            '</li>'
          );
        }).join('') +
        '</ol>' +
        (failed ? '<p class="mt-3 text-[11px] text-rose-300 text-center"><i class="fa-solid fa-circle-xmark mr-1"></i> Commande ' + escapeHtml(STATUT_LABELS[c.statut] || c.statut).toLowerCase() + '</p>' : '') +
        '</div>'
      );
    }

    function reviewCountdownHtml(c) {
      if (c.statut !== 'livree' || !c.review_until) return '';
      const days = daysBetween(c.review_until);
      if (days === null) return '';
      const tone = days <= 1 ? 'border-rose-500/40 bg-rose-500/10 text-rose-100'
        : days <= 3 ? 'border-amber-500/40 bg-amber-500/10 text-amber-100'
        : 'border-emerald-500/40 bg-emerald-500/10 text-emerald-100';
      const label = role === 'client'
        ? (days > 1 ? 'Vous avez ' + days + ' jours pour valider ou demander une retouche.' :
           days === 1 ? 'Plus qu\'1 jour pour valider — sans réponse, le paiement sera automatiquement libéré.' :
           'Délai dépassé — le paiement va être libéré automatiquement.')
        : (days > 1 ? 'Le client a ' + days + ' jours pour valider votre livraison.' :
           days === 1 ? 'Plus qu\'1 jour avant validation automatique.' :
           'Validation automatique imminente.');
      return (
        '<div class="rounded-xl border ' + tone + ' px-4 py-3 flex items-center gap-3">' +
        '<i class="fa-regular fa-clock text-lg shrink-0"></i>' +
        '<div class="flex-1 min-w-0">' +
        '<p class="text-sm font-bold leading-tight">' + escapeHtml(label) + '</p>' +
        '<p class="text-[11px] opacity-80 mt-0.5">Délai jusqu\'au ' + escapeHtml(formatDate(c.review_until)) + '</p>' +
        '</div></div>'
      );
    }

    function livraisonsHtml(c) {
      if (!c.livraisons || !c.livraisons.length) return '';
      const last = c.livraisons[c.livraisons.length - 1];
      const attCount = (last.attachments && last.attachments.length) || (last.attachment ? 1 : 0);
      return (
        '<div class="rounded-2xl border border-brd bg-surface/40 p-4 sm:p-5 flex items-start gap-3">' +
        '<div class="w-10 h-10 rounded-xl bg-accent/15 text-accent flex items-center justify-center shrink-0"><i class="fa-solid fa-truck-fast"></i></div>' +
        '<div class="flex-1 min-w-0">' +
        '<p class="font-syne font-bold text-sm text-tx">' + c.livraisons.length + ' livraison' + (c.livraisons.length > 1 ? 's' : '') + ' soumise' + (c.livraisons.length > 1 ? 's' : '') + '</p>' +
        '<p class="text-[12px] text-muted mt-0.5">Dernière le ' + escapeHtml(formatDateTime(last.created_at)) + (attCount ? ' · ' + attCount + ' fichier' + (attCount > 1 ? 's' : '') : '') + '</p>' +
        '<p class="text-[11px] text-muted/80 mt-2 leading-relaxed">Le contenu et les fichiers sont disponibles dans la conversation.</p>' +
        '</div>' +
        '<button type="button" data-action="open-thread" class="shrink-0 text-[12px] px-3 py-1.5 rounded-lg border border-brd hover:border-accent/60 text-tx self-center"><i class="fa-regular fa-comments mr-1"></i> Voir le fil</button>' +
        '</div>'
      );
    }

    function disputeBoxHtml(c) {
      if (c.statut !== 'litige' && !c.has_dispute) return '';
      const resolved = !!c.litige_resolved_at;
      const tone = resolved
        ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-100'
        : 'border-orange-500/40 bg-orange-500/10 text-orange-100';
      return (
        '<div class="rounded-2xl border ' + tone + ' p-4 sm:p-5 flex items-start gap-3">' +
        '<i class="fa-solid ' + (resolved ? 'fa-scale-balanced' : 'fa-gavel') + ' text-xl shrink-0 mt-0.5"></i>' +
        '<div class="flex-1 min-w-0">' +
        '<p class="font-syne font-bold text-sm">' + (resolved ? 'Litige clos par la médiation' : 'Litige en cours d\'examen') + '</p>' +
        '<p class="text-[12px] opacity-90 mt-1">Ouvert le ' + escapeHtml(formatDateTime(c.litige_at)) + '.</p>' +
        (c.litige_motif ? '<p class="text-[12px] mt-2 italic">« ' + escapeHtml(c.litige_motif) + ' »</p>' : '') +
        (resolved ? '<p class="text-[12px] mt-2"><strong>Décision :</strong> ' + escapeHtml(disputeResolutionLabel(c.litige_resolution)) + '</p>' : '<p class="text-[12px] mt-2 opacity-90">L\'équipe administrateur va examiner les échanges et trancher.</p>') +
        '</div></div>'
      );
    }

    function avisStarsHtml(n) {
      var v = Math.max(0, Math.min(5, Number(n) || 0));
      var out = '';
      for (var i = 1; i <= 5; i++) {
        out += i <= v
          ? '<i class="fa-solid fa-star text-amber-400 text-[12px]"></i>'
          : '<i class="fa-regular fa-star text-muted/60 text-[12px]"></i>';
      }
      return out;
    }

    function avisBoxHtml(c) {
      if (!c.avis) return '';
      const a = c.avis;
      const body = a.commentaire
        ? '<p class="text-sm leading-relaxed text-tx/90 mt-2 whitespace-pre-wrap">' + escapeHtml(a.commentaire) + '</p>'
        : '<p class="text-xs text-muted italic mt-2">Note laissée sans commentaire.</p>';
      const who = role === 'prestataire' ? 'Avis du client' : 'Votre avis';
      return (
        '<div class="rounded-2xl border border-amber-400/30 bg-amber-500/5 p-4 sm:p-5">' +
        '<div class="flex items-center justify-between flex-wrap gap-2">' +
        '<div class="flex items-center gap-2">' +
        '<i class="fa-solid fa-star text-amber-400"></i>' +
        '<p class="font-syne font-bold text-sm text-tx">' + who + '</p>' +
        '<div class="flex items-center gap-0.5 ml-2">' + avisStarsHtml(a.note) + '</div>' +
        '</div>' +
        '<p class="text-[11px] text-muted">' + escapeHtml(formatDate(a.created_at)) + '</p>' +
        '</div>' +
        body +
        '</div>'
      );
    }

    function disputeResolutionLabel(r) {
      if (r === 'refund_full') return 'Remboursement intégral du client.';
      if (r === 'refund_partial') return 'Remboursement partiel du client + paiement partiel du prestataire.';
      if (r === 'release_to_provider') return 'Paiement libéré au prestataire.';
      return 'Décision rendue.';
    }

    function actionsHtml(c) {
      const buttons = [];
      if (role === 'client') {
        if (c.statut === 'attente_paiement') {
          buttons.push('<button type="button" data-action="pay" class="bg-accent text-bg font-syne font-bold text-sm px-5 py-2.5 rounded-xl hover:opacity-90 inline-flex items-center gap-2"><i class="fa-solid fa-credit-card"></i> Payer maintenant</button>');
          buttons.push('<button type="button" data-action="cancel" class="border border-brd text-muted text-sm px-4 py-2.5 rounded-xl hover:border-rose-500/50 hover:text-rose-300">Annuler</button>');
        }
        if (c.can_validate) {
          buttons.push('<button type="button" data-action="validate" class="bg-emerald-500 text-bg font-syne font-bold text-sm px-5 py-2.5 rounded-xl hover:opacity-90 inline-flex items-center gap-2"><i class="fa-solid fa-circle-check"></i> Approuver et libérer le paiement</button>');
          buttons.push('<button type="button" data-action="revision" class="border border-amber-500/40 text-amber-300 text-sm px-4 py-2.5 rounded-xl hover:bg-amber-500/10 inline-flex items-center gap-2"><i class="fa-solid fa-rotate-left"></i> Demander une retouche</button>');
        }
      }
      if (role === 'prestataire') {
        if (c.statut === 'attente_prestataire') {
          buttons.push('<button type="button" data-action="accept" class="bg-accent text-bg font-syne font-bold text-sm px-5 py-2.5 rounded-xl hover:opacity-90 inline-flex items-center gap-2"><i class="fa-solid fa-handshake"></i> Accepter la commande</button>');
          buttons.push('<button type="button" data-action="refuse" class="border border-brd text-muted text-sm px-4 py-2.5 rounded-xl hover:border-rose-500/50 hover:text-rose-300">Refuser</button>');
        }
        if (c.can_deliver) {
          const label = c.statut === 'en_revision' ? 'Soumettre la nouvelle version' : 'Soumettre le travail';
          buttons.push('<button type="button" data-action="deliver" class="bg-accent text-bg font-syne font-bold text-sm px-5 py-2.5 rounded-xl hover:opacity-90 inline-flex items-center gap-2"><i class="fa-solid fa-paper-plane"></i> ' + label + '</button>');
        }
      }
      if (c.can_review && role === 'client') {
        buttons.push('<button type="button" data-action="review" class="border border-amber-400/40 text-amber-200 text-sm px-4 py-2.5 rounded-xl hover:bg-amber-500/10 inline-flex items-center gap-2"><i class="fa-solid fa-star"></i> Laisser un avis</button>');
      }
      if (role === 'client' && c.can_open_dispute) {
        buttons.push('<button type="button" data-action="dispute" class="border border-orange-500/40 text-orange-200 text-sm px-4 py-2.5 rounded-xl hover:bg-orange-500/10 inline-flex items-center gap-2"><i class="fa-solid fa-gavel"></i> Ouvrir un litige</button>');
      }
      if (!buttons.length) return '';
      return '<div class="flex flex-wrap gap-2">' + buttons.join('') + '</div>';
    }

    function summaryHtml(c) {
      const commission = Number(c.commission_fcfa || 0);
      const fees = role === 'prestataire'
        ? ('<div><p class="text-[11px] uppercase tracking-wider text-muted font-bold">Vous recevez</p><p class="text-sm font-bold text-emerald-300 mt-1">' + escapeHtml(formatMoney(c.net_prestataire_fcfa || c.montant_fcfa)) + '</p>' +
          (commission > 0 ? '<p class="text-[10px] text-muted mt-0.5">après commission ' + escapeHtml(formatMoney(commission)) + '</p>' : '<p class="text-[10px] text-muted mt-0.5">100 % du montant (sans commission)</p>') + '</div>')
        : ('<div><p class="text-[11px] uppercase tracking-wider text-muted font-bold">' + (commission > 0 ? 'Commission plateforme' : 'Frais plateforme') + '</p><p class="text-sm font-bold text-tx mt-1">' + escapeHtml(formatMoney(commission)) + '</p>' +
          (commission > 0 ? '<p class="text-[10px] text-muted mt-0.5">prestataire reçoit ' + escapeHtml(formatMoney(c.net_prestataire_fcfa)) + '</p>' : '<p class="text-[10px] text-muted mt-0.5">financé par l’abonnement prestataire</p>') + '</div>');
      return (
        '<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 rounded-2xl border border-brd bg-surface/40 p-4 sm:p-5">' +
        '<div><p class="text-[11px] uppercase tracking-wider text-muted font-bold">Montant</p><p class="text-sm font-bold text-tx mt-1">' + escapeHtml(formatMoney(c.montant_fcfa)) + '</p></div>' +
        '<div><p class="text-[11px] uppercase tracking-wider text-muted font-bold">Délai</p><p class="text-sm font-bold text-tx mt-1">' + escapeHtml(String(c.delai_jours)) + ' jours</p></div>' +
        fees +
        '<div><p class="text-[11px] uppercase tracking-wider text-muted font-bold">' + (role === 'client' ? 'Prestataire' : 'Client') + '</p><p class="text-sm font-bold text-tx mt-1 truncate">' + escapeHtml(c.peer_label || '—') + '</p></div>' +
        '</div>'
      );
    }

    function renderDetail(c) {
      if (!el.detail || !c) return;
      const tone = STATUT_TONE[c.statut] || 'bg-zinc-500/15 text-zinc-300 border-zinc-500/30';

      el.detail.innerHTML =
        '<div class="overflow-y-auto h-full">' +
        '<div class="max-w-3xl mx-auto p-4 sm:p-6 lg:p-8 space-y-5">' +
        '<header class="space-y-2">' +
        '<div class="flex items-center gap-2 text-[11px]">' +
        '<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full border ' + tone + ' font-bold">' +
        '<span class="w-1.5 h-1.5 rounded-full bg-current"></span>' +
        escapeHtml(STATUT_LABELS[c.statut] || c.statut) +
        '</span>' +
        '<span class="text-muted">#' + c.id + ' · créée le ' + escapeHtml(formatDate(c.created_at)) + '</span>' +
        '</div>' +
        '<h1 class="font-syne font-extrabold text-2xl sm:text-3xl text-tx leading-tight">' + escapeHtml(c.titre) + '</h1>' +
        '<p class="text-xs text-accent/90">' + escapeHtml(c.service_titre || '') + '</p>' +
        '</header>' +
        timelineHtml(c) +
        reviewCountdownHtml(c) +
        summaryHtml(c) +
        '<section class="rounded-2xl border border-brd bg-surface/40 p-4 sm:p-5">' +
        '<p class="text-[11px] uppercase tracking-wider text-muted font-bold mb-2">Description</p>' +
        '<p class="text-sm text-tx whitespace-pre-wrap leading-relaxed">' + escapeHtml(c.description) + '</p>' +
        '</section>' +
        disputeBoxHtml(c) +
        livraisonsHtml(c) +
        avisBoxHtml(c) +
        actionsHtml(c) +
        '</div></div>';

      bindActions(c);
    }

    function bindActions(c) {
      el.detail.querySelectorAll('[data-action]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          const action = btn.getAttribute('data-action');
          handleAction(action, c, btn);
        });
      });
    }

    async function handleAction(action, c, btn) {
      const lock = function (busy) {
        if (!btn) return;
        btn.disabled = !!busy;
        btn.classList.toggle('opacity-60', !!busy);
        btn.classList.toggle('cursor-not-allowed', !!busy);
      };

      if (action === 'pay') {
        lock(true);
        try {
          const token = await getCsrf();
          const r = await fetch(apiBase + '/' + c.id + '/payer', {
            method: 'POST', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token },
          });
          const d = await r.json();
          if (d.ok && d.payment_url) { window.location.href = d.payment_url; return; }
          toast(d.message || 'Paiement indisponible.', false);
        } catch (_) { toast('Réseau indisponible.', false); }
        lock(false);
        return;
      }

      if (action === 'cancel') {
        if (!confirm('Annuler définitivement cette commande ?')) return;
        await postAction(c.id, '/annuler', {}, 'Commande annulée.');
        return;
      }

      if (action === 'accept') {
        await postAction(c.id, '/accepter', {}, 'Commande acceptée. Le client peut maintenant payer.');
        return;
      }

      if (action === 'refuse') {
        const motif = window.prompt('Motif du refus (optionnel)') || '';
        await postAction(c.id, '/refuser', { motif: motif }, 'Commande refusée.');
        return;
      }

      if (action === 'deliver') {
        openDeliverModal(c);
        return;
      }

      if (action === 'validate') {
        openValidateModal(c);
        return;
      }

      if (action === 'revision') {
        openRevisionModal(c);
        return;
      }

      if (action === 'review') {
        openReviewModal(c);
        return;
      }

      if (action === 'dispute') {
        openDisputeModal(c);
        return;
      }

      if (action === 'open-thread') {
        if (typeof window.BFOpenThreadForOrder === 'function') {
          window.BFOpenThreadForOrder(c);
        } else {
          // fallback : naviguer vers la section Messages
          const evt = new CustomEvent('bf:request-open-thread', { detail: c });
          document.dispatchEvent(evt);
        }
        return;
      }
    }

    async function postAction(id, path, body, okText) {
      try {
        const token = await getCsrf();
        const r = await fetch(apiBase + '/' + id + path, {
          method: 'POST', credentials: 'same-origin',
          headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
          body: JSON.stringify(body || {}),
        });
        const d = await r.json();
        if (d.ok) {
          toast(okText || 'OK.', true);
          await loadList();
          if (id === activeId) await openDetail(id, true);
          document.dispatchEvent(new CustomEvent('bf:order-updated', { detail: { id: id } }));
        } else {
          toast(d.message || 'Erreur.', false);
        }
      } catch (_) {
        toast('Réseau indisponible.', false);
      }
    }

    // ─── Modale livraison (multi-fichiers, drag-drop, prévisualisation) ───────
    function openDeliverModal(c) {
      const isRevision = c.statut === 'en_revision';
      const html =
        modalShell('deliver', {
          icon: 'fa-paper-plane',
          title: isRevision ? 'Soumettre une nouvelle version' : 'Soumettre le travail',
          subtitle: isRevision
            ? 'Joignez la version corrigée et précisez les changements apportés.'
            : 'Joignez vos livrables et décrivez ce que vous avez réalisé. Le client aura ' + (c.delai_jours || 14) + ' j pour valider ou demander une retouche.',
          body:
            '<div id="bf-dropzone" class="relative rounded-xl border-2 border-dashed border-brd hover:border-accent/60 hover:bg-accent/5 transition-colors p-6 text-center cursor-pointer">' +
            '<input id="bf-files" type="file" multiple accept="' + ACCEPTED_MIME + '" class="absolute inset-0 opacity-0 cursor-pointer" />' +
            '<i class="fa-solid fa-cloud-arrow-up text-3xl text-accent/70 mb-2"></i>' +
            '<p class="text-sm font-bold text-tx">Glissez vos fichiers ici, ou cliquez pour parcourir</p>' +
            '<p class="text-[11px] text-muted mt-1">Jusqu\'à ' + MAX_FILES + ' fichiers · 10 Mo max chacun · images, PDF, Office, ZIP</p>' +
            '</div>' +
            '<div id="bf-files-list" class="mt-3 space-y-2 hidden"></div>' +
            '<label class="block mt-4">' +
            '<span class="text-[11px] uppercase tracking-wider text-muted font-bold">Note pour le client</span>' +
            '<textarea id="bf-deliver-msg" rows="4" maxlength="8000" placeholder="Détaillez ce que vous avez livré, les choix réalisés, les éléments à vérifier en priorité…" class="mt-1 w-full bg-surface border border-brd rounded-xl px-3 py-2.5 text-sm text-tx placeholder:text-muted/70 focus:border-accent transition-colors"></textarea>' +
            '<p class="text-[11px] text-muted mt-1"><span id="bf-deliver-count">0</span> / 8000</p>' +
            '</label>' +
            '<div class="rounded-xl border border-emerald-500/30 bg-emerald-500/5 px-4 py-3 mt-4 flex items-start gap-3">' +
            '<i class="fa-solid fa-shield-halved text-emerald-300 mt-0.5"></i>' +
            '<p class="text-[12px] text-emerald-100/90 leading-relaxed">Vos fichiers sont stockés sur la plateforme. Le paiement sera libéré dès que le client valide, ou automatiquement après <strong>14 jours</strong> sans réponse.</p>' +
            '</div>',
          footer:
            '<button type="button" data-modal-close class="border border-brd text-muted px-4 py-2 rounded-xl text-sm hover:text-tx">Annuler</button>' +
            '<button type="button" id="bf-deliver-submit" class="bg-accent text-bg font-syne font-bold px-5 py-2 rounded-xl text-sm inline-flex items-center gap-2 disabled:opacity-60"><i class="fa-solid fa-paper-plane"></i> Envoyer pour validation</button>',
        });
      mountModal(html);

      const files = [];
      const drop = document.getElementById('bf-dropzone');
      const input = document.getElementById('bf-files');
      const listEl = document.getElementById('bf-files-list');
      const msg = document.getElementById('bf-deliver-msg');
      const cnt = document.getElementById('bf-deliver-count');
      const submit = document.getElementById('bf-deliver-submit');

      function renderFiles() {
        if (!files.length) {
          listEl.classList.add('hidden');
          listEl.innerHTML = '';
          return;
        }
        listEl.classList.remove('hidden');
        listEl.innerHTML = files.map(function (f, i) {
          const isImg = f.type.startsWith('image/');
          return (
            '<div class="flex items-center gap-3 rounded-lg border border-brd bg-bg/40 px-3 py-2">' +
            '<i class="fa-regular ' + fileIcon(f.type, f.name) + ' text-accent text-lg shrink-0"></i>' +
            '<div class="min-w-0 flex-1">' +
            '<p class="text-xs font-medium text-tx truncate">' + escapeHtml(f.name) + '</p>' +
            '<p class="text-[10px] text-muted">' + escapeHtml(formatBytes(f.size)) + (isImg ? ' · image' : '') + '</p>' +
            '</div>' +
            '<button type="button" data-rm="' + i + '" class="w-7 h-7 rounded-md text-muted hover:text-rose-300 hover:bg-rose-500/10" aria-label="Retirer">' +
            '<i class="fa-solid fa-xmark text-xs"></i></button>' +
            '</div>'
          );
        }).join('');
        listEl.querySelectorAll('[data-rm]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            files.splice(parseInt(btn.getAttribute('data-rm'), 10), 1);
            renderFiles();
          });
        });
      }

      function addFiles(picked) {
        for (let i = 0; i < picked.length; i++) {
          const f = picked[i];
          if (files.length >= MAX_FILES) { toast('Maximum ' + MAX_FILES + ' fichiers.', false); break; }
          if (f.size > MAX_FILE_SIZE) { toast(f.name + ' dépasse 10 Mo.', false); continue; }
          files.push(f);
        }
        renderFiles();
      }

      input.addEventListener('change', function () { addFiles(input.files); input.value = ''; });
      ['dragenter', 'dragover'].forEach(function (ev) {
        drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('border-accent', 'bg-accent/10'); });
      });
      ['dragleave', 'drop'].forEach(function (ev) {
        drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('border-accent', 'bg-accent/10'); });
      });
      drop.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files) addFiles(e.dataTransfer.files); });

      msg.addEventListener('input', function () { cnt.textContent = msg.value.length; });

      submit.addEventListener('click', async function () {
        if (!msg.value.trim() && !files.length) { toast('Ajoutez un message ou un fichier.', false); return; }
        submit.disabled = true;
        submit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Envoi en cours…';
        try {
          const token = await getCsrf();
          const fd = new FormData();
          if (msg.value.trim()) fd.append('message', msg.value.trim());
          files.forEach(function (f) { fd.append('attachments[]', f); });
          const r = await fetch(apiBase + '/' + c.id + '/livrer', {
            method: 'POST', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token },
            body: fd,
          });
          const d = await r.json();
          if (d.ok) {
            closeModal();
            toast('Livraison envoyée. Le client a 14 jours pour valider.', true);
            await loadList();
            await openDetail(c.id, true);
            document.dispatchEvent(new CustomEvent('bf:order-updated', { detail: { id: c.id } }));
          } else {
            toast(d.message || 'Envoi impossible.', false);
            submit.disabled = false;
            submit.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Envoyer pour validation';
          }
        } catch (_) {
          toast('Réseau indisponible.', false);
          submit.disabled = false;
          submit.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Envoyer pour validation';
        }
      });
    }

    // ─── Modale validation client ─────────────────────────────────────────────
    function openValidateModal(c) {
      const html = modalShell('validate', {
        icon: 'fa-circle-check',
        iconTone: 'text-emerald-300',
        title: 'Approuver et libérer le paiement',
        subtitle: 'Confirmez que la livraison correspond à ce que vous attendiez.',
        body:
          '<div class="rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-4 space-y-3">' +
          '<div class="flex items-center justify-between text-sm"><span class="text-muted">Montant total payé</span><span class="font-bold text-tx">' + escapeHtml(formatMoney(c.montant_fcfa)) + '</span></div>' +
          (Number(c.commission_fcfa || 0) > 0
            ? '<div class="flex items-center justify-between text-sm"><span class="text-muted">Commission plateforme</span><span class="text-tx">− ' + escapeHtml(formatMoney(c.commission_fcfa)) + '</span></div>'
            : '') +
          '<div class="flex items-center justify-between text-base font-bold border-t border-emerald-500/20 pt-3"><span class="text-emerald-200">Versé au prestataire</span><span class="text-emerald-200">' + escapeHtml(formatMoney(c.net_prestataire_fcfa || c.montant_fcfa)) + '</span></div>' +
          '</div>' +
          '<p class="text-xs text-muted mt-3 leading-relaxed"><i class="fa-solid fa-circle-info mr-1"></i> Cette action est <strong>irréversible</strong>. Pour des modifications, choisissez « Demander une retouche » à la place.</p>',
        footer:
          '<button type="button" data-modal-close class="border border-brd text-muted px-4 py-2 rounded-xl text-sm hover:text-tx">Annuler</button>' +
          '<button type="button" id="bf-validate-submit" class="bg-emerald-500 text-bg font-syne font-bold px-5 py-2 rounded-xl text-sm inline-flex items-center gap-2"><i class="fa-solid fa-circle-check"></i> Approuver et payer</button>',
      });
      mountModal(html);
      document.getElementById('bf-validate-submit').addEventListener('click', async function () {
        closeModal();
        await postAction(c.id, '/valider', {}, 'Livraison approuvée. Le paiement est libéré au prestataire.');
      });
    }

    // ─── Modale retouche client ───────────────────────────────────────────────
    function openRevisionModal(c) {
      const html = modalShell('revision', {
        icon: 'fa-rotate-left',
        iconTone: 'text-amber-300',
        title: 'Demander une retouche',
        subtitle: 'Indiquez précisément ce qui doit être modifié pour aider le prestataire.',
        body:
          '<label class="block">' +
          '<span class="text-[11px] uppercase tracking-wider text-muted font-bold">Ce qui doit changer</span>' +
          '<textarea id="bf-rev-msg" rows="5" maxlength="2000" required placeholder="Ex : la couleur principale doit être plus foncée, le logo doit être centré…" class="mt-1 w-full bg-surface border border-brd rounded-xl px-3 py-2.5 text-sm text-tx placeholder:text-muted/70 focus:border-accent transition-colors"></textarea>' +
          '<p class="text-[11px] text-muted mt-1"><span id="bf-rev-count">0</span> / 2000</p>' +
          '</label>' +
          '<p class="text-xs text-muted mt-3 leading-relaxed"><i class="fa-solid fa-lightbulb mr-1 text-amber-300"></i> Le prestataire pourra soumettre une nouvelle version. Vous gardez vos ' + (daysBetween(c.review_until) || 14) + ' jours pour valider.</p>',
        footer:
          '<button type="button" data-modal-close class="border border-brd text-muted px-4 py-2 rounded-xl text-sm hover:text-tx">Annuler</button>' +
          '<button type="button" id="bf-rev-submit" class="bg-amber-500 text-bg font-syne font-bold px-5 py-2 rounded-xl text-sm inline-flex items-center gap-2"><i class="fa-solid fa-rotate-left"></i> Envoyer la demande</button>',
      });
      mountModal(html);
      const msg = document.getElementById('bf-rev-msg');
      const cnt = document.getElementById('bf-rev-count');
      msg.addEventListener('input', function () { cnt.textContent = msg.value.length; });
      document.getElementById('bf-rev-submit').addEventListener('click', async function () {
        if (msg.value.trim().length < 5) { toast('Décrivez ce qui doit changer (5 caractères minimum).', false); return; }
        closeModal();
        await postAction(c.id, '/retouche', { message: msg.value.trim() }, 'Demande de retouche envoyée.');
      });
    }

    // ─── Modale litige client (style ComeUp/Upwork) ────────────────────────────
    function openDisputeModal(c) {
      const html = modalShell('dispute', {
        icon: 'fa-gavel',
        iconTone: 'text-orange-300',
        title: 'Ouvrir un litige',
        subtitle: 'À utiliser en dernier recours. Tentez d\'abord une retouche ou un échange direct avec le prestataire.',
        body:
          '<div class="rounded-xl border border-orange-500/40 bg-orange-500/10 p-4">' +
          '<p class="text-[13px] text-orange-100 leading-relaxed"><i class="fa-solid fa-circle-info mr-1"></i> En ouvrant un litige, la commande est <strong>gelée</strong> jusqu\'à la décision de l\'équipe BéninFreelance. L\'admin pourra rembourser intégralement, partiellement, ou libérer le paiement au prestataire selon les éléments fournis.</p>' +
          '</div>' +
          '<label class="block mt-4">' +
          '<span class="text-[11px] uppercase tracking-wider text-muted font-bold">Décrivez le problème en détail</span>' +
          '<textarea id="bf-dispute-msg" rows="6" maxlength="2000" required placeholder="Expliquez ce qui ne va pas, références au cahier des charges, exemples concrets de la livraison non conforme, échanges déjà tentés…" class="mt-1 w-full bg-surface border border-brd rounded-xl px-3 py-2.5 text-sm text-tx placeholder:text-muted/70 focus:border-accent transition-colors"></textarea>' +
          '<p class="text-[11px] text-muted mt-1"><span id="bf-dispute-count">0</span> / 2000 · min. 10 caractères</p>' +
          '</label>',
        footer:
          '<button type="button" data-modal-close class="border border-brd text-muted px-4 py-2 rounded-xl text-sm hover:text-tx">Annuler</button>' +
          '<button type="button" id="bf-dispute-submit" class="bg-orange-500 text-bg font-syne font-bold px-5 py-2 rounded-xl text-sm inline-flex items-center gap-2"><i class="fa-solid fa-gavel"></i> Ouvrir le litige</button>',
      });
      mountModal(html);
      const msg = document.getElementById('bf-dispute-msg');
      const cnt = document.getElementById('bf-dispute-count');
      msg.addEventListener('input', function () { cnt.textContent = msg.value.length; });
      document.getElementById('bf-dispute-submit').addEventListener('click', async function () {
        if (msg.value.trim().length < 10) { toast('Donnez au moins 10 caractères pour décrire le problème.', false); return; }
        closeModal();
        await postAction(c.id, '/litige', { motif: msg.value.trim() }, 'Litige ouvert. Notre équipe va examiner les échanges.');
      });
    }

    // ─── Modale avis client ───────────────────────────────────────────────────
    function openReviewModal(c) {
      const html = modalShell('review', {
        icon: 'fa-star',
        iconTone: 'text-amber-300',
        title: 'Laisser un avis',
        subtitle: 'Votre retour aide la communauté à choisir le bon prestataire.',
        body:
          '<div class="space-y-3">' +
          '<div class="flex items-center justify-center gap-1.5" id="bf-stars" data-note="5">' +
          [1, 2, 3, 4, 5].map(function (i) {
            return '<button type="button" data-i="' + i + '" class="text-3xl text-amber-300 transition-transform hover:scale-110">★</button>';
          }).join('') +
          '</div>' +
          '<label class="block">' +
          '<span class="text-[11px] uppercase tracking-wider text-muted font-bold">Commentaire (optionnel)</span>' +
          '<textarea id="bf-avis-comment" rows="3" maxlength="2000" placeholder="Qu\'avez-vous apprécié ?" class="mt-1 w-full bg-surface border border-brd rounded-xl px-3 py-2.5 text-sm text-tx placeholder:text-muted/70 focus:border-accent transition-colors"></textarea>' +
          '</label>' +
          '</div>',
        footer:
          '<button type="button" data-modal-close class="border border-brd text-muted px-4 py-2 rounded-xl text-sm hover:text-tx">Annuler</button>' +
          '<button type="button" id="bf-avis-submit" class="bg-amber-500 text-bg font-syne font-bold px-5 py-2 rounded-xl text-sm inline-flex items-center gap-2"><i class="fa-solid fa-star"></i> Publier l\'avis</button>',
      });
      mountModal(html);
      const starsEl = document.getElementById('bf-stars');
      starsEl.querySelectorAll('[data-i]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          const i = parseInt(btn.getAttribute('data-i'), 10);
          starsEl.setAttribute('data-note', String(i));
          starsEl.querySelectorAll('[data-i]').forEach(function (b) {
            const idx = parseInt(b.getAttribute('data-i'), 10);
            b.classList.toggle('opacity-30', idx > i);
          });
        });
      });
      document.getElementById('bf-avis-submit').addEventListener('click', async function () {
        const note = parseInt(starsEl.getAttribute('data-note'), 10) || 5;
        const comment = document.getElementById('bf-avis-comment').value.trim();
        try {
          const token = await getCsrf();
          const r = await fetch('/api/client/commandes/' + c.id + '/avis', {
            method: 'POST', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify({ note: note, commentaire: comment }),
          });
          const d = await r.json();
          if (d.ok) {
            closeModal();
            toast('Merci pour votre avis !', true);
            await openDetail(c.id, true);
          } else {
            toast(d.message || 'Erreur.', false);
          }
        } catch (_) {
          toast('Réseau indisponible.', false);
        }
      });
    }

    // Expose helpers spécifiques à cette instance via le hub global
    window.__BFOrdersInstance = window.__BFOrdersInstance || {};
    window.__BFOrdersInstance[role] = {
      role: role,
      openDetail: openDetail,
      loadList: loadList,
      openModalById: async function (action, id) {
        try {
          const r = await fetch(apiBase + '/' + id, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
          const d = await r.json();
          if (!r.ok || !d.ok) { toast('Commande introuvable.', false); return; }
          const c = d.commande;
          if (action === 'validate') openValidateModal(c);
          else if (action === 'revision') openRevisionModal(c);
          else if (action === 'redeliver' || action === 'deliver') openDeliverModal(c);
          else if (action === 'review') openReviewModal(c);
          else if (action === 'dispute') openDisputeModal(c);
        } catch (_) { toast('Réseau indisponible.', false); }
      },
    };

    return { load: loadList, open: openDetail };
  };

  // Point d'entrée global utilisé par la messagerie (cartes d'événement).
  window.BFOrdersOpenAction = function (action, commandeId) {
    const inst = window.__BFOrdersInstance || {};
    const target = inst.client || inst.prestataire;
    if (target && typeof target.openModalById === 'function') {
      target.openModalById(action, commandeId);
    }
  };

  // ─── Modale infrastructure (utilisée par tous) ──────────────────────────────
  function modalShell(id, parts) {
    const iconTone = parts.iconTone || 'text-accent';
    return (
      '<div class="bf-modal-overlay fixed inset-0 z-[80] flex items-end sm:items-center justify-center p-0 sm:p-6 bg-black/60 backdrop-blur-sm" data-modal-id="' + id + '">' +
      '<div class="bf-modal-card w-full max-w-xl bg-surface2 border border-brd rounded-t-3xl sm:rounded-3xl shadow-2xl max-h-[92vh] flex flex-col">' +
      '<header class="flex items-start gap-3 p-5 border-b border-brd/60">' +
      '<div class="w-10 h-10 rounded-xl bg-accent/15 flex items-center justify-center ' + iconTone + ' shrink-0"><i class="fa-solid ' + parts.icon + ' text-lg"></i></div>' +
      '<div class="flex-1 min-w-0">' +
      '<h2 class="font-syne font-bold text-lg text-tx leading-tight">' + escapeHtml(parts.title) + '</h2>' +
      (parts.subtitle ? '<p class="text-xs text-muted mt-1 leading-relaxed">' + escapeHtml(parts.subtitle) + '</p>' : '') +
      '</div>' +
      '<button type="button" data-modal-close class="shrink-0 w-9 h-9 rounded-lg text-muted hover:bg-bg/60 hover:text-tx" aria-label="Fermer"><i class="fa-solid fa-xmark"></i></button>' +
      '</header>' +
      '<div class="p-5 overflow-y-auto flex-1">' + parts.body + '</div>' +
      '<footer class="p-5 border-t border-brd/60 flex flex-wrap justify-end gap-2 bg-bg/30">' + parts.footer + '</footer>' +
      '</div></div>'
    );
  }

  function mountModal(html) {
    closeModal();
    const wrap = document.createElement('div');
    wrap.innerHTML = html;
    const node = wrap.firstChild;
    document.body.appendChild(node);
    node.addEventListener('click', function (ev) {
      if (ev.target === node || (ev.target instanceof Element && ev.target.closest('[data-modal-close]'))) {
        closeModal();
      }
    });
    document.addEventListener('keydown', escListener);
    document.body.style.overflow = 'hidden';
  }

  function escListener(ev) { if (ev.key === 'Escape') closeModal(); }

  function closeModal() {
    document.querySelectorAll('.bf-modal-overlay').forEach(function (n) { n.remove(); });
    document.removeEventListener('keydown', escListener);
    document.body.style.overflow = '';
  }

  // ─── Modale "Proposer une commande" (client, depuis la messagerie) ──────────
  (function setupProposeModal() {
    if (window.__BFOrdersModalReady) return;
    window.__BFOrdersModalReady = true;

    window.BFOrdersOpenModal = function (ctx) {
      const html = modalShell('propose', {
        icon: 'fa-file-contract',
        title: 'Proposer une commande',
        subtitle: 'Décrivez précisément le travail attendu, le budget et le délai.',
        body:
          '<form id="bf-propose-form" class="space-y-3">' +
          '<input type="hidden" id="bf-prop-prestataire" />' +
          '<input type="hidden" id="bf-prop-service" />' +
          '<label class="block">' +
          '<span class="text-[11px] uppercase tracking-wider text-muted font-bold">Titre</span>' +
          '<input id="bf-prop-titre" required maxlength="200" class="mt-1 w-full bg-surface border border-brd rounded-xl px-3 py-2.5 text-sm text-tx focus:border-accent" placeholder="Ex : Création d\'un logo pour ma boutique" />' +
          '</label>' +
          '<label class="block">' +
          '<span class="text-[11px] uppercase tracking-wider text-muted font-bold">Description du travail</span>' +
          '<textarea id="bf-prop-desc" required rows="5" maxlength="8000" class="mt-1 w-full bg-surface border border-brd rounded-xl px-3 py-2.5 text-sm text-tx focus:border-accent" placeholder="Objectifs, contraintes, livrables attendus, références…"></textarea>' +
          '</label>' +
          '<div class="grid grid-cols-2 gap-3">' +
          '<label class="block">' +
          '<span class="text-[11px] uppercase tracking-wider text-muted font-bold">Budget (FCFA)</span>' +
          '<input id="bf-prop-montant" type="number" required min="500" class="mt-1 w-full bg-surface border border-brd rounded-xl px-3 py-2.5 text-sm text-tx focus:border-accent" placeholder="50 000" />' +
          '</label>' +
          '<label class="block">' +
          '<span class="text-[11px] uppercase tracking-wider text-muted font-bold">Délai (jours)</span>' +
          '<input id="bf-prop-delai" type="number" required min="1" max="365" class="mt-1 w-full bg-surface border border-brd rounded-xl px-3 py-2.5 text-sm text-tx focus:border-accent" placeholder="7" />' +
          '</label>' +
          '</div>' +
          '<div class="rounded-xl border border-accent/30 bg-accent/5 px-4 py-3 flex items-start gap-3">' +
          '<i class="fa-solid fa-shield-halved text-accent mt-0.5"></i>' +
          '<p class="text-[12px] text-tx/90 leading-relaxed">Votre paiement sera <strong>séquestré</strong> sur la plateforme. Il n\'est libéré au prestataire qu\'après votre validation (ou 14 j sans réponse).</p>' +
          '</div>' +
          '</form>',
        footer:
          '<button type="button" data-modal-close class="border border-brd text-muted px-4 py-2 rounded-xl text-sm hover:text-tx">Annuler</button>' +
          '<button type="button" id="bf-prop-submit" class="bg-accent text-bg font-syne font-bold px-5 py-2 rounded-xl text-sm inline-flex items-center gap-2"><i class="fa-solid fa-paper-plane"></i> Envoyer la proposition</button>',
      });
      mountModal(html);

      document.getElementById('bf-prop-prestataire').value = ctx.prestataire_id || '';
      document.getElementById('bf-prop-service').value = ctx.service_id || '';
      if (ctx.service_titre) document.getElementById('bf-prop-titre').value = ctx.service_titre;

      const submit = document.getElementById('bf-prop-submit');
      submit.addEventListener('click', async function () {
        const body = {
          prestataire_id: parseInt(document.getElementById('bf-prop-prestataire').value, 10),
          service_id: parseInt(document.getElementById('bf-prop-service').value, 10),
          titre: document.getElementById('bf-prop-titre').value.trim(),
          description: document.getElementById('bf-prop-desc').value.trim(),
          montant_fcfa: parseInt(document.getElementById('bf-prop-montant').value, 10),
          delai_jours: parseInt(document.getElementById('bf-prop-delai').value, 10),
        };
        if (!body.prestataire_id || !body.service_id) {
          toast('Conversation introuvable. Ouvrez d\'abord un fil de messages.', false);
          return;
        }
        if (!body.titre || !body.description || !(body.montant_fcfa >= 500) || !(body.delai_jours >= 1)) {
          toast('Remplissez tous les champs (montant min 500 FCFA).', false);
          return;
        }
        submit.disabled = true;
        submit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Envoi…';
        try {
          const token = await getCsrf();
          const r = await fetch('/api/client/commandes', {
            method: 'POST', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify(body),
          });
          const d = await r.json();
          if (d.ok) {
            closeModal();
            toast('Proposition envoyée au prestataire.', true);
            document.dispatchEvent(new CustomEvent('bf:order-proposed', { detail: { commande: d.commande || null } }));
          } else {
            toast(d.message || 'Erreur.', false);
            submit.disabled = false;
            submit.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Envoyer la proposition';
          }
        } catch (_) {
          toast('Réseau indisponible.', false);
          submit.disabled = false;
          submit.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Envoyer la proposition';
        }
      });
    };
  })();
})();
