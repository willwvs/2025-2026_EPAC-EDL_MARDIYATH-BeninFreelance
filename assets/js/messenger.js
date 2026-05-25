/**
 * Messagerie client / prestataire (liste + fil de discussion).
 * @param {object} opts
 * @param {'client'|'prestataire'} opts.role
 * @param {string} opts.apiList - GET conversations
 * @param {string} opts.apiThread - GET thread (query params selon rôle)
 * @param {string} opts.apiSend - POST message
 */
window.BFMessenger = function (opts) {
  const role = opts.role;
  const state = {
    conversations: [],
    active: null,
    pollTimer: null,
    sending: false,
    attachment: null,
  };

  const el = {
    layout: document.getElementById('msg-layout'),
    sidebar: document.getElementById('msg-sidebar'),
    list: document.getElementById('msg-conv-list'),
    chatPanel: document.getElementById('msg-chat-panel'),
    chatActive: document.getElementById('msg-chat-active'),
    chatHeader: document.getElementById('msg-chat-header'),
    bubbles: document.getElementById('msg-bubbles'),
    form: document.getElementById('msg-compose-form'),
    input: document.getElementById('msg-compose-input'),
    fileInput: document.getElementById('msg-file-input'),
    attachBtn: document.getElementById('msg-attach-btn'),
    attachmentPreview: document.getElementById('msg-attachment-preview'),
    emojiBtn: document.getElementById('msg-emoji-btn'),
    emojiPanel: document.getElementById('msg-emoji-panel'),
    sendBtn: document.getElementById('msg-send-btn'),
    btnBack: document.getElementById('msg-btn-back'),
  };
  const emojis = ['😀', '😁', '😂', '😊', '😍', '👍', '🙏', '👏', '🔥', '✅', '💡', '📌', '📎', '⏱️', '💰', '🤝'];

  function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function formatTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return String(iso);
    const now = new Date();
    const sameDay = d.toDateString() === now.toDateString();
    if (sameDay) {
      return d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    }
    return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' }) + ' ' +
      d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
  }

  function formatBytes(bytes) {
    const n = Number(bytes || 0);
    if (!n) return '';
    if (n < 1024) return n + ' o';
    if (n < 1024 * 1024) return Math.round(n / 1024) + ' Ko';
    return (n / (1024 * 1024)).toFixed(1).replace('.', ',') + ' Mo';
  }

  function notifyUnread() {
    if (typeof opts.onUnreadChange !== 'function') return;
    const total = state.conversations.reduce(function (sum, c) {
      return sum + Number(c.unread_count || 0);
    }, 0);
    opts.onUnreadChange(total);
  }

  function threadKey(c) {
    if (role === 'client') {
      return c.prestataire_id + '-' + c.service_id;
    }
    return c.client_id + '-' + c.service_id;
  }

  function activeKey() {
    if (!state.active) return '';
    return threadKey(state.active);
  }

  async function getCsrf() {
    const r = await fetch('/csrf-token', { headers: { Accept: 'application/json' } });
    return (await r.json()).token;
  }

  function showMobileChat(show) {
    if (!el.layout) return;
    el.layout.classList.toggle('msg-chat-open', !!show);
  }

  function syncListLayout() {
    if (!el.layout) return;
    const chatOpen = el.chatActive && !el.chatActive.classList.contains('hidden');
    el.layout.classList.toggle('msg-list-only', !chatOpen);
  }

  function renderConvList() {
    if (!el.list) return;
    if (!state.conversations.length) {
      el.list.innerHTML =
        '<p class="text-sm text-muted px-4 py-10 text-center leading-relaxed">Aucune conversation.<br/>Contactez un prestataire via <strong class="text-tx/90">Message</strong> sur un service.</p>';
      return;
    }
    const key = activeKey();
    el.list.innerHTML = state.conversations.map(function (c) {
      const k = threadKey(c);
      const active = k === key;
      const unread = (c.unread_count || 0) > 0;
      return (
        '<button type="button" data-thread="' + escapeHtml(k) + '" class="msg-conv-item w-full text-left px-4 py-3.5 border-b border-brd/60 hover:bg-surface transition-colors ' +
        (active ? 'bg-surface border-l-2 border-l-accent' : '') + '">' +
        '<div class="flex items-start justify-between gap-3 w-full">' +
        '<p class="msg-conv-peer font-syne font-bold text-sm text-tx min-w-0 flex-1">' + escapeHtml(c.peer_label || '') + '</p>' +
        (unread ? '<span class="shrink-0 min-w-[1.25rem] h-5 px-1.5 rounded-full bg-accent text-bg text-[10px] font-bold flex items-center justify-center">' +
          (c.unread_count > 9 ? '9+' : c.unread_count) + '</span>' : '') +
        '</div>' +
        '<p class="msg-conv-service text-[11px] text-accent mt-0.5">' + escapeHtml(c.service_titre || '') + '</p>' +
        '<p class="msg-conv-excerpt text-xs text-muted mt-1 leading-snug">' + escapeHtml(c.last_excerpt || '') + '</p>' +
        '<p class="text-[10px] text-muted/80 mt-1">' + escapeHtml(formatTime(c.last_at)) + '</p>' +
        '</button>'
      );
    }).join('');

    el.list.querySelectorAll('.msg-conv-item').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const k = btn.getAttribute('data-thread');
        const c = state.conversations.find(function (x) { return threadKey(x) === k; });
        if (c) openThread(c);
      });
    });
  }

  function renderHeader(meta) {
    if (!el.chatHeader || !meta) return;
    const initials = (meta.peer_label || '?').trim().split(/\s+/).slice(0, 2)
      .map(function (w) { return w.charAt(0); }).join('').toUpperCase() || '?';
    el.chatHeader.innerHTML =
      '<div class="flex items-center gap-3 min-w-0 flex-1">' +
      '<div class="shrink-0 w-9 h-9 rounded-full bg-accent/15 text-accent font-syne font-bold text-sm flex items-center justify-center">' +
      escapeHtml(initials) +
      '</div>' +
      '<div class="min-w-0 flex-1">' +
      '<p class="font-syne font-bold text-[15px] text-tx truncate leading-tight">' + escapeHtml(meta.peer_label || '') + '</p>' +
      '<p class="text-[11px] text-accent/90 truncate mt-0.5">' + escapeHtml(meta.service_titre || '') + '</p>' +
      '</div>' +
      '</div>';
  }

  function renderBubbles(messages) {
    if (!el.bubbles) return;
    if (!messages || !messages.length) {
      el.bubbles.innerHTML = '<p class="text-sm text-muted text-center py-8">Aucun message.</p>';
      return;
    }
    let html = '';
    let lastDay = '';
    messages.forEach(function (m) {
      const day = m.created_at ? new Date(m.created_at).toDateString() : '';
      if (day && day !== lastDay) {
        lastDay = day;
        html +=
          '<div class="flex justify-center my-3">' +
          '<span class="text-[11px] text-muted bg-surface/80 border border-brd/60 rounded-full px-3 py-0.5">' +
          escapeHtml(new Date(m.created_at).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })) +
          '</span></div>';
      }

      const kind = (m.kind || 'chat').toString();
      if (kind === 'event' || kind === 'delivery' || kind === 'revision_request') {
        html += renderEventCard(m);
        return;
      }

      const mine = !!m.mine;
      const hasText = !!(m.contenu && m.contenu.length);
      html +=
        '<div class="flex ' + (mine ? 'justify-end' : 'justify-start') + ' mb-1.5">' +
        '<div class="max-w-[85%] sm:max-w-[65%] rounded-2xl px-3.5 py-2 text-[14px] leading-[1.5] whitespace-pre-wrap break-words shadow-sm ' +
        (mine
          ? 'bg-accent text-bg rounded-br-md'
          : 'bg-surface border border-brd/70 text-tx/95 rounded-bl-md') + '">' +
        (hasText ? '<div>' + escapeHtml(m.contenu) + '</div>' : '') +
        renderAttachment(m.attachment, mine) +
        '<p class="text-[10px] mt-1 opacity-65 text-right tabular-nums">' + escapeHtml(formatTime(m.created_at)) + '</p>' +
        '</div></div>';
    });
    el.bubbles.innerHTML = html;
    bindEventCardActions();
    el.bubbles.scrollTop = el.bubbles.scrollHeight;
  }

  function renderEventCard(m) {
    const meta = m.meta || {};
    const kind = m.kind || 'event';
    const ev = (meta.event || '').toString();
    const titre = escapeHtml(meta.commande_titre || '');
    const cmdId = parseInt(meta.commande_id || 0, 10);
    const time = formatTime(m.created_at);
    const bodyTxt = (m.contenu || '').trim();

    if (kind === 'delivery') {
      const atts = Array.isArray(meta.attachments) ? meta.attachments : [];
      const isMine = !!m.mine;
      const attsHtml = atts.length ? (
        '<div class="grid sm:grid-cols-2 gap-2 mt-3">' +
        atts.map(function (a) {
          const url = role === 'client' ? (a.url || '#') : (a.url_prestataire || a.url || '#');
          return (
            '<a href="' + escapeHtml(url) + '" target="_blank" class="flex items-center gap-2 rounded-xl bg-bg/60 border border-brd/70 hover:border-accent/60 px-3 py-2 text-[13px] transition-colors">' +
            '<i class="fa-regular ' + (a.is_image ? 'fa-image' : 'fa-file-lines') + ' text-accent shrink-0"></i>' +
            '<span class="min-w-0 flex-1">' +
            '<span class="block truncate font-medium text-tx">' + escapeHtml(a.name || 'fichier') + '</span>' +
            '<span class="block text-[10px] opacity-65 mt-0.5">' + escapeHtml(formatBytes(a.size)) + '</span>' +
            '</span>' +
            '<i class="fa-solid fa-arrow-down text-[11px] opacity-70"></i>' +
            '</a>'
          );
        }).join('') +
        '</div>'
      ) : '';
      const noteHtml = bodyTxt
        ? '<p class="mt-2 text-[13px] text-tx/95 whitespace-pre-wrap leading-relaxed">' + escapeHtml(bodyTxt) + '</p>'
        : '';
      const reviewUntil = meta.review_until ? new Date(meta.review_until) : null;
      const daysLeft = reviewUntil ? Math.max(0, Math.ceil((reviewUntil - new Date()) / 86400000)) : null;
      const actions = (role === 'client' && cmdId) ? (
        '<div class="mt-3 flex flex-wrap gap-2">' +
        '<button type="button" data-bf-event-action="validate" data-cmd="' + cmdId + '" class="text-[12px] px-3 py-1.5 rounded-lg bg-accent text-bg font-semibold hover:opacity-90">' +
        '<i class="fa-solid fa-circle-check mr-1"></i> Approuver le travail' +
        '</button>' +
        '<button type="button" data-bf-event-action="revision" data-cmd="' + cmdId + '" class="text-[12px] px-3 py-1.5 rounded-lg border border-brd hover:border-accent/60 text-tx">' +
        '<i class="fa-solid fa-rotate mr-1"></i> Demander une retouche' +
        '</button>' +
        '</div>'
      ) : '';
      return (
        '<div class="my-3">' +
        '<div class="rounded-2xl border border-accent/40 bg-accent/10 px-4 py-3.5">' +
        '<div class="flex items-center gap-2">' +
        '<span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-accent text-bg text-[12px]"><i class="fa-solid fa-truck-fast"></i></span>' +
        '<div class="min-w-0 flex-1">' +
        '<p class="font-syne font-bold text-[13px] text-tx">Livraison soumise' + (meta.version ? ' · v' + meta.version : '') + '</p>' +
        '<p class="text-[11px] text-muted">' + (titre ? titre + ' · ' : '') + escapeHtml(time) + (daysLeft !== null ? ' · ' + daysLeft + ' j pour valider' : '') + '</p>' +
        '</div>' +
        '</div>' +
        noteHtml + attsHtml + actions +
        '</div></div>'
      );
    }

    if (kind === 'revision_request') {
      const actions = (role === 'prestataire' && cmdId) ? (
        '<div class="mt-3 flex flex-wrap gap-2">' +
        '<button type="button" data-bf-event-action="redeliver" data-cmd="' + cmdId + '" class="text-[12px] px-3 py-1.5 rounded-lg bg-accent text-bg font-semibold hover:opacity-90">' +
        '<i class="fa-solid fa-paper-plane mr-1"></i> Soumettre la nouvelle livraison' +
        '</button>' +
        '</div>'
      ) : '';
      return (
        '<div class="my-3">' +
        '<div class="rounded-2xl border border-yellow-500/40 bg-yellow-500/10 px-4 py-3.5">' +
        '<div class="flex items-center gap-2">' +
        '<span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-yellow-500 text-bg text-[12px]"><i class="fa-solid fa-rotate"></i></span>' +
        '<div class="min-w-0 flex-1">' +
        '<p class="font-syne font-bold text-[13px] text-tx">Demande de retouche</p>' +
        '<p class="text-[11px] text-muted">' + (titre ? titre + ' · ' : '') + escapeHtml(time) + '</p>' +
        '</div>' +
        '</div>' +
        (bodyTxt ? '<p class="mt-2 text-[13px] text-tx/95 whitespace-pre-wrap leading-relaxed">' + escapeHtml(bodyTxt) + '</p>' : '') +
        actions +
        '</div></div>'
      );
    }

    // Évènements génériques (paiement, validation, refund, litige…)
    const palette = eventPalette(ev);
    return (
      '<div class="flex justify-center my-3">' +
      '<div class="max-w-[92%] rounded-xl border ' + palette.cls + ' px-3.5 py-2 text-[12.5px] text-tx/90 flex items-center gap-2">' +
      '<i class="fa-solid ' + palette.icon + ' text-[12px] ' + palette.iconCls + '"></i>' +
      '<span class="min-w-0">' + escapeHtml(bodyTxt || 'Mise à jour de la commande.') + '</span>' +
      '<span class="text-[10px] text-muted ml-1 tabular-nums">' + escapeHtml(time) + '</span>' +
      '</div></div>'
    );
  }

  function eventPalette(ev) {
    const map = {
      'order.proposed': { cls: 'border-blue-500/40 bg-blue-500/10', icon: 'fa-file-circle-plus', iconCls: 'text-blue-400' },
      'order.accepted': { cls: 'border-emerald-500/40 bg-emerald-500/10', icon: 'fa-handshake', iconCls: 'text-emerald-400' },
      'order.refused': { cls: 'border-red-500/40 bg-red-500/10', icon: 'fa-ban', iconCls: 'text-red-400' },
      'order.cancelled': { cls: 'border-zinc-500/40 bg-zinc-500/10', icon: 'fa-circle-xmark', iconCls: 'text-zinc-300' },
      'order.paid': { cls: 'border-emerald-500/40 bg-emerald-500/10', icon: 'fa-shield-halved', iconCls: 'text-emerald-400' },
      'order.completed': { cls: 'border-emerald-500/40 bg-emerald-500/10', icon: 'fa-circle-check', iconCls: 'text-emerald-400' },
      'order.auto_released': { cls: 'border-emerald-500/40 bg-emerald-500/10', icon: 'fa-stopwatch', iconCls: 'text-emerald-400' },
      'order.refunded': { cls: 'border-red-500/40 bg-red-500/10', icon: 'fa-rotate-left', iconCls: 'text-red-400' },
      'order.dispute_opened': { cls: 'border-orange-500/40 bg-orange-500/10', icon: 'fa-gavel', iconCls: 'text-orange-400' },
      'order.dispute_resolved': { cls: 'border-emerald-500/40 bg-emerald-500/10', icon: 'fa-scale-balanced', iconCls: 'text-emerald-400' },
    };
    return map[ev] || { cls: 'border-brd/70 bg-surface/60', icon: 'fa-circle-info', iconCls: 'text-accent' };
  }

  function bindEventCardActions() {
    if (!el.bubbles) return;
    el.bubbles.querySelectorAll('[data-bf-event-action]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const action = btn.getAttribute('data-bf-event-action');
        const cmd = parseInt(btn.getAttribute('data-cmd') || '0', 10);
        if (!cmd || typeof window.BFOrdersOpenAction !== 'function') return;
        window.BFOrdersOpenAction(action, cmd);
      });
    });
  }

  function renderAttachment(att, mine) {
    if (!att) return '';
    const label = escapeHtml(att.name || 'Pièce jointe');
    const meta = escapeHtml(formatBytes(att.size));
    const icon = att.is_image ? 'fa-image' : 'fa-file-lines';
    return (
      '<a href="' + escapeHtml(att.url || '#') + '" target="_blank" class="mt-2 flex items-center gap-2 rounded-xl px-3 py-2 text-[13px] transition-opacity hover:opacity-90 ' +
      (mine ? 'bg-bg/15 text-bg' : 'bg-bg/40 text-tx/95 border border-brd/60') + '">' +
      '<i class="fa-regular ' + icon + ' shrink-0 text-[14px]"></i>' +
      '<span class="min-w-0 flex-1">' +
      '<span class="block truncate font-medium">' + label + '</span>' +
      (meta ? '<span class="block text-[10px] opacity-70 mt-0.5">' + meta + '</span>' : '') +
      '</span>' +
      '<i class="fa-solid fa-arrow-down text-[11px] opacity-70"></i>' +
      '</a>'
    );
  }

  function threadQuery(c) {
    const p = new URLSearchParams();
    if (role === 'client') {
      p.set('prestataire_id', String(c.prestataire_id));
      p.set('service_id', String(c.service_id));
    } else {
      p.set('client_id', String(c.client_id));
      p.set('service_id', String(c.service_id));
    }
    return p.toString();
  }

  async function loadThread(silent) {
    if (!state.active) return;
    try {
      const r = await fetch(opts.apiThread + '?' + threadQuery(state.active), {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      const d = await r.json();
      if (!r.ok || !d.ok) return;
      if (d.thread) renderHeader(d.thread);
      renderBubbles(d.messages || []);
      if (!silent) await loadConversations(true);
    } catch (_) { /* ignore poll errors */ }
  }

  async function loadConversations(keepActive) {
    if (!el.list) return;
    if (!keepActive) el.list.innerHTML = '<p class="text-sm text-muted px-4 py-6">Chargement…</p>';
    try {
      const r = await fetch(opts.apiList, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      const d = await r.json();
      if (!r.ok || !d.ok) {
        el.list.innerHTML = '<p class="text-sm text-muted px-4 py-6">Impossible de charger.</p>';
        return;
      }
      state.conversations = d.conversations || [];
      renderConvList();
      syncListLayout();
      notifyUnread();
    } catch (_) {
      el.list.innerHTML = '<p class="text-sm text-muted px-4 py-6">Réseau indisponible.</p>';
    }
  }

  function stopPoll() {
    if (state.pollTimer) {
      clearInterval(state.pollTimer);
      state.pollTimer = null;
    }
  }

  function startPoll() {
    stopPoll();
    state.pollTimer = setInterval(function () {
      loadThread(true);
    }, 5000);
  }

  async function openThread(c) {
    state.active = c;
    if (el.chatActive) el.chatActive.classList.remove('hidden');
    syncListLayout();
    showMobileChat(true);
    renderConvList();
    renderHeader(c);
    if (el.bubbles) el.bubbles.innerHTML = '<p class="text-sm text-muted text-center py-8">Chargement…</p>';
    await loadThread(false);
    startPoll();
    if (el.input) {
      el.input.focus();
      el.input.style.height = 'auto';
    }
  }

  function closeThread() {
    state.active = null;
    stopPoll();
    showMobileChat(false);
    if (el.chatActive) el.chatActive.classList.add('hidden');
    syncListLayout();
    renderConvList();
  }

  async function sendMessage(ev) {
    ev.preventDefault();
    if (!state.active || state.sending || !el.input) return;
    const text = el.input.value.trim();
    if (text.length < 1 && !state.attachment) return;

    state.sending = true;
    if (el.sendBtn) el.sendBtn.disabled = true;
    const body = new FormData();
    body.append('contenu', text);
    body.append('service_id', String(state.active.service_id));
    if (role === 'client') {
      body.append('prestataire_id', String(state.active.prestataire_id));
    } else {
      body.append('client_id', String(state.active.client_id));
    }
    if (state.attachment) {
      body.append('attachment', state.attachment);
    }

    try {
      const token = await getCsrf();
      const r = await fetch(opts.apiSend, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': token,
        },
        body: body,
      });
      const d = await r.json();
      if (!r.ok || !d.ok) {
        alert(d.message || 'Envoi impossible.');
        state.sending = false;
        updateSendState();
        return;
      }
      el.input.value = '';
      el.input.style.height = 'auto';
      clearAttachment();
      await loadThread(false);
      try { await loadConversations(); } catch (_) { /* badge silent */ }
    } catch (_) {
      alert('Réseau indisponible.');
    }
    state.sending = false;
    updateSendState();
  }

  function bindCompose() {
    if (!el.form || !el.input) return;
    renderEmojiPanel();
    el.form.addEventListener('submit', sendMessage);
    el.input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        el.form.requestSubmit();
      }
    });
    el.input.addEventListener('input', function () {
      resizeInput();
      updateSendState();
    });
    if (el.attachBtn && el.fileInput) {
      el.attachBtn.addEventListener('click', function () { el.fileInput.click(); });
      el.fileInput.addEventListener('change', function () {
        const file = el.fileInput.files && el.fileInput.files[0];
        if (!file) return;
        if (file.size > 10 * 1024 * 1024) {
          alert('Le fichier ne doit pas dépasser 10 Mo.');
          el.fileInput.value = '';
          return;
        }
        state.attachment = file;
        renderAttachmentPreview();
        updateSendState();
      });
    }
    if (el.emojiBtn && el.emojiPanel) {
      el.emojiBtn.addEventListener('click', function (ev) {
        ev.stopPropagation();
        el.emojiPanel.classList.toggle('hidden');
      });
      document.addEventListener('click', function (ev) {
        if (el.emojiPanel.classList.contains('hidden')) return;
        if (el.emojiPanel.contains(ev.target) || el.emojiBtn.contains(ev.target)) return;
        el.emojiPanel.classList.add('hidden');
      });
    }
    resizeInput();
    updateSendState();
  }

  function resizeInput() {
    if (!el.input) return;
    el.input.style.height = 'auto';
    el.input.style.height = Math.min(el.input.scrollHeight, 180) + 'px';
  }

  function updateSendState() {
    if (!el.sendBtn || !el.input) return;
    el.sendBtn.disabled = state.sending || (el.input.value.trim().length < 1 && !state.attachment);
  }

  function renderEmojiPanel() {
    if (!el.emojiPanel) return;
    el.emojiPanel.innerHTML = emojis.map(function (emoji) {
      return '<button type="button" data-emoji="' + escapeHtml(emoji) + '" class="w-9 h-9 rounded-lg hover:bg-bg/50 text-lg">' + escapeHtml(emoji) + '</button>';
    }).join('');
    el.emojiPanel.querySelectorAll('[data-emoji]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        insertAtCursor(btn.getAttribute('data-emoji') || '');
        el.emojiPanel.classList.add('hidden');
      });
    });
  }

  function insertAtCursor(text) {
    if (!el.input) return;
    const start = el.input.selectionStart || 0;
    const end = el.input.selectionEnd || 0;
    el.input.value = el.input.value.slice(0, start) + text + el.input.value.slice(end);
    el.input.focus();
    el.input.selectionStart = el.input.selectionEnd = start + text.length;
    resizeInput();
    updateSendState();
  }

  function renderAttachmentPreview() {
    if (!el.attachmentPreview || !state.attachment) return;
    el.attachmentPreview.classList.remove('hidden');
    el.attachmentPreview.innerHTML =
      '<div class="flex items-center gap-2 rounded-xl bg-bg/60 border border-brd/70 px-2.5 py-1.5 text-[13px]">' +
      '<i class="fa-solid fa-paperclip text-accent text-[12px]"></i>' +
      '<span class="min-w-0 flex-1 truncate text-tx/90">' + escapeHtml(state.attachment.name) + '</span>' +
      '<span class="text-[11px] text-muted shrink-0">' + escapeHtml(formatBytes(state.attachment.size)) + '</span>' +
      '<button type="button" id="msg-remove-attachment" class="shrink-0 w-6 h-6 rounded-md text-muted hover:text-tx hover:bg-bg/60 flex items-center justify-center" aria-label="Retirer le fichier">' +
      '<i class="fa-solid fa-xmark text-[11px]"></i>' +
      '</button>' +
      '</div>';
    const remove = document.getElementById('msg-remove-attachment');
    if (remove) remove.addEventListener('click', clearAttachment);
  }

  function clearAttachment() {
    state.attachment = null;
    if (el.fileInput) el.fileInput.value = '';
    if (el.attachmentPreview) {
      el.attachmentPreview.innerHTML = '';
      el.attachmentPreview.classList.add('hidden');
    }
    updateSendState();
  }

  if (el.btnBack) {
    el.btnBack.addEventListener('click', closeThread);
  }

  bindCompose();
  syncListLayout();

  return {
    load: loadConversations,
    getActive: function () {
      return state.active;
    },
    openByParams: function (params) {
      if (role === 'client' && params.prestataire_id && params.service_id) {
        const c = {
          prestataire_id: parseInt(params.prestataire_id, 10),
          service_id: parseInt(params.service_id, 10),
          peer_label: '',
          service_titre: '',
        };
        return loadConversations(false).then(function () {
          const found = state.conversations.find(function (x) {
            return x.prestataire_id === c.prestataire_id && x.service_id === c.service_id;
          });
          if (found) return openThread(found);
          return openThread(c);
        });
      }
      if (role === 'prestataire' && params.client_id && params.service_id) {
        return loadConversations(false).then(function () {
          const found = state.conversations.find(function (x) {
            return x.client_id === parseInt(params.client_id, 10) && x.service_id === parseInt(params.service_id, 10);
          });
          if (found) return openThread(found);
        });
      }
      return loadConversations(false);
    },
    stop: stopPoll,
  };
};
