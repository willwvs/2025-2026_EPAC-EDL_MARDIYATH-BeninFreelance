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
    editingId: null,
    deletingId: null,
    editBackup: null,
    lastMsgSig: '',
    messagingAllowed: true,
    messagingBlockedMessage: '',
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

  function normalizeAssetUrl(u) {
    if (!u || u === '#') return '#';
    if (u.charAt(0) === '/') return u;
    try {
      const parsed = new URL(u, window.location.origin);
      return parsed.pathname + parsed.search;
    } catch (_) {
      return u;
    }
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

  function messagesSignature(messages) {
    return (messages || []).map(function (m) {
      return [
        m.id,
        m.kind || 'chat',
        m.deleted ? 1 : 0,
        m.edited_at || '',
        m.contenu || '',
        m.attachment && m.attachment.name ? m.attachment.name : '',
      ].join(':');
    }).join('|');
  }

  function isNearBottom(container, threshold) {
    if (!container) return true;
    threshold = threshold || 100;
    return container.scrollHeight - container.scrollTop - container.clientHeight <= threshold;
  }

  function lockBubbleWidth(bubble, minW) {
    const lockedWidth = Math.max(Math.ceil(bubble.getBoundingClientRect().width), minW || 180);
    bubble.classList.add('msg-bubble--editing');
    bubble.style.width = lockedWidth + 'px';
    bubble.style.minWidth = Math.min(lockedWidth, 220) + 'px';
    bubble.style.maxWidth = lockedWidth + 'px';
  }

  function unlockBubbleWidth(bubble) {
    if (!bubble) return;
    bubble.classList.remove('msg-bubble--editing');
    bubble.style.width = '';
    bubble.style.minWidth = '';
    bubble.style.maxWidth = '';
  }

  function renderBubbles(messages, renderOpts) {
    renderOpts = renderOpts || {};
    if (!el.bubbles) return;
    if (state.editingId || state.deletingId) return;

    const sig = messagesSignature(messages);
    const forceBottom = renderOpts.stickBottom === true;
    if (renderOpts.silent && sig === state.lastMsgSig && !forceBottom) {
      return;
    }

    const box = el.bubbles;
    const wasNearBottom = isNearBottom(box);
    const savedScrollTop = box.scrollTop;
    const shouldScrollBottom = forceBottom || (renderOpts.stickBottom === 'auto' && wasNearBottom);
    state.lastMsgSig = sig;

    if (!messages || !messages.length) {
      el.bubbles.innerHTML =
        '<div class="msg-thread-column"><p class="text-sm text-muted text-center py-8">Aucun message.</p></div>';
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
      const deleted = !!m.deleted;
      const hasText = !deleted && !!(m.contenu && m.contenu.length);
      const editedLabel = m.edited_at ? ' · modifié' : '';
      const showMenu = mine && !deleted && (m.can_edit || m.can_delete);
      const menuBtn = showMenu ? (
        '<div class="msg-menu-wrap relative shrink-0 self-center">' +
        '<button type="button" class="msg-menu-btn w-7 h-7 rounded-full flex items-center justify-center text-muted hover:text-tx hover:bg-surface/80 opacity-0 group-hover:opacity-100 max-md:opacity-60 transition-opacity" data-msg-menu-toggle="' + escapeHtml(String(m.id)) + '" aria-label="Options du message">' +
        '<i class="fa-solid fa-ellipsis-vertical text-[13px]"></i>' +
        '</button>' +
        '<div class="msg-menu-panel hidden absolute z-50 min-w-[9.5rem] py-1 rounded-xl border border-brd bg-surface2 shadow-lg ' +
        (mine ? 'right-0 bottom-full mb-1' : 'left-0 bottom-full mb-1') + '" data-msg-menu-panel="' + escapeHtml(String(m.id)) + '">' +
        (m.can_edit ? (
          '<button type="button" data-msg-action="edit" data-msg-id="' + escapeHtml(String(m.id)) + '" class="msg-menu-item w-full text-left px-3.5 py-2 text-[13px] text-tx hover:bg-surface transition-colors flex items-center gap-2">' +
          '<i class="fa-regular fa-pen-to-square text-[12px] text-muted w-4"></i> Modifier' +
          '</button>'
        ) : '') +
        (m.can_delete ? (
          '<button type="button" data-msg-action="delete" data-msg-id="' + escapeHtml(String(m.id)) + '" class="msg-menu-item w-full text-left px-3.5 py-2 text-[13px] text-red-400 hover:bg-surface transition-colors flex items-center gap-2">' +
          '<i class="fa-regular fa-trash-can text-[12px] w-4"></i> Supprimer' +
          '</button>'
        ) : '') +
        '</div></div>'
      ) : '';
      const bubbleInner =
        (deleted
          ? '<div class="italic opacity-75">Message supprimé</div>'
          : (hasText ? '<div class="msg-bubble-text">' + escapeHtml(m.contenu) + '</div>' : '')) +
        (deleted ? '' : renderAttachment(m.attachment, mine)) +
        '<p class="msg-bubble-footer text-[10px] mt-1 opacity-65 text-right tabular-nums">' + escapeHtml(formatTime(m.created_at) + editedLabel) + '</p>';
      const bubbleCls =
        'rounded-2xl px-3.5 py-2 text-[14px] leading-[1.5] whitespace-pre-wrap break-words shadow-sm min-w-0 ' +
        (mine
          ? 'bg-accent text-bg rounded-br-md'
          : 'bg-surface border border-brd/70 text-tx/95 rounded-bl-md');
      html +=
        '<div class="flex ' + (mine ? 'justify-end' : 'justify-start') + ' mb-1.5 group" data-msg-row-id="' + escapeHtml(String(m.id || '')) + '">' +
        '<div class="flex items-end gap-0.5 max-w-[92%] ' + (mine ? 'flex-row-reverse' : 'flex-row') + '">' +
        (showMenu ? menuBtn : '') +
        '<div class="max-w-[88%] ' + bubbleCls + '" data-msg-bubble="' + escapeHtml(String(m.id || '')) + '">' + bubbleInner + '</div>' +
        '</div></div>';
    });
    el.bubbles.innerHTML = '<div class="msg-thread-column">' + html + '</div>';
    bindEventCardActions();
    bindMessageActions();
    if (el.bubbles) {
      el.bubbles.onscroll = function () {
        closeAllMsgMenus();
      };
    }
    if (shouldScrollBottom) {
      el.bubbles.scrollTop = el.bubbles.scrollHeight;
    } else {
      el.bubbles.scrollTop = savedScrollTop;
    }
  }

  function closeAllMsgMenus() {
    if (!el.bubbles) return;
    el.bubbles.querySelectorAll('.msg-menu-panel').forEach(function (panel) {
      panel.classList.add('hidden');
    });
  }

  function cancelInlineActions() {
    cancelInlineEdit();
    cancelInlineDelete();
  }

  function bindMessageActions() {
    if (!el.bubbles || el.bubbles._bfMsgActionsBound) return;
    el.bubbles._bfMsgActionsBound = true;
    el.bubbles.addEventListener('click', function (ev) {
      const menuBtn = ev.target.closest('[data-msg-menu-toggle]');
      if (menuBtn) {
        ev.stopPropagation();
        const id = menuBtn.getAttribute('data-msg-menu-toggle');
        const panel = el.bubbles.querySelector('[data-msg-menu-panel="' + id + '"]');
        if (!panel) return;
        const wasOpen = !panel.classList.contains('hidden');
        closeAllMsgMenus();
        if (!wasOpen) panel.classList.remove('hidden');
        return;
      }
      const actionBtn = ev.target.closest('[data-msg-action]');
      if (actionBtn) {
        ev.stopPropagation();
        closeAllMsgMenus();
        const action = actionBtn.getAttribute('data-msg-action');
        const id = parseInt(actionBtn.getAttribute('data-msg-id') || '0', 10);
        if (!id) return;
        if (action === 'edit') startInlineEdit(id);
        if (action === 'delete') startInlineDelete(id);
        return;
      }
      if (ev.target.closest('[data-msg-edit-cancel]')) {
        ev.stopPropagation();
        cancelInlineEdit();
        return;
      }
      const saveBtn = ev.target.closest('[data-msg-edit-save]');
      if (saveBtn) {
        ev.stopPropagation();
        const id = parseInt(saveBtn.getAttribute('data-msg-edit-save') || '0', 10);
        if (id) submitInlineEdit(id);
        return;
      }
      if (ev.target.closest('[data-msg-delete-cancel]')) {
        ev.stopPropagation();
        cancelInlineDelete();
        return;
      }
      const delBtn = ev.target.closest('[data-msg-delete-confirm]');
      if (delBtn) {
        ev.stopPropagation();
        const id = parseInt(delBtn.getAttribute('data-msg-delete-confirm') || '0', 10);
        if (id) submitInlineDelete(id);
      }
    });
  }

  function getMsgRow(id) {
    return el.bubbles ? el.bubbles.querySelector('[data-msg-row-id="' + id + '"]') : null;
  }

  function getMsgBubble(id) {
    const row = getMsgRow(id);
    return row ? row.querySelector('[data-msg-bubble="' + id + '"]') : null;
  }

  function isMineBubble(bubble) {
    return bubble && bubble.classList.contains('bg-accent');
  }

  function inlineBtnCls(mine, primary) {
    if (primary) {
      return mine
        ? 'text-[12px] px-3 py-1 rounded-lg bg-bg text-accent font-semibold hover:opacity-90 disabled:opacity-50'
        : 'text-[12px] px-3 py-1 rounded-lg bg-accent text-bg font-semibold hover:opacity-90 disabled:opacity-50';
    }
    return mine
      ? 'text-[12px] px-3 py-1 rounded-lg border border-bg/40 text-bg/90 hover:bg-bg/10'
      : 'text-[12px] px-3 py-1 rounded-lg border border-brd text-muted hover:text-tx hover:border-muted';
  }

  function startInlineEdit(id) {
    cancelInlineDelete();
    const row = getMsgRow(id);
    const bubble = getMsgBubble(id);
    if (!row || !bubble || bubble.querySelector('.msg-edit-input')) return;
    const textEl = bubble.querySelector('.msg-bubble-text');
    const current = textEl ? textEl.textContent : '';
    const mine = isMineBubble(bubble);
    const menu = row.querySelector('.msg-menu-wrap');
    if (menu) menu.classList.add('invisible', 'pointer-events-none');
    state.editingId = id;
    state.editBackup = { id: id, text: current };

    lockBubbleWidth(bubble, 160);

    const host = textEl || document.createElement('div');
    if (!textEl) {
      host.className = 'msg-bubble-text';
      const footer = bubble.querySelector('.msg-bubble-footer');
      if (footer) bubble.insertBefore(host, footer);
      else bubble.prepend(host);
    }

    host.innerHTML =
      '<textarea class="msg-edit-input block w-full bg-transparent border-0 outline-none resize-none text-[14px] leading-[1.5] p-0 m-0 overflow-hidden ' +
      (mine ? 'text-bg caret-bg' : 'text-tx caret-accent') + '" maxlength="8000" rows="1"></textarea>' +
      '<p class="msg-inline-err hidden text-[11px] mt-1 ' + (mine ? 'text-bg/90' : 'text-red-400') + '"></p>' +
      '<div class="msg-inline-edit-actions flex justify-end gap-1.5 mt-1.5">' +
      '<button type="button" data-msg-edit-cancel class="' + inlineBtnCls(mine, false) + '">Annuler</button>' +
      '<button type="button" data-msg-edit-save="' + id + '" class="' + inlineBtnCls(mine, true) + '">OK</button>' +
      '</div>';

    const ta = host.querySelector('.msg-edit-input');
    if (ta) {
      ta.value = current;
      resizeEditTextarea(ta);
      ta.focus();
      ta.setSelectionRange(ta.value.length, ta.value.length);
      ta.addEventListener('input', function () { resizeEditTextarea(ta); });
      ta.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { e.preventDefault(); cancelInlineEdit(); }
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submitInlineEdit(id); }
      });
    }
  }

  function resizeEditTextarea(ta) {
    ta.style.height = '0';
    ta.style.height = Math.min(ta.scrollHeight, 200) + 'px';
  }

  function cancelInlineEdit() {
    if (!state.editingId) return;
    const id = state.editingId;
    const backup = state.editBackup;
    state.editingId = null;
    state.editBackup = null;
    const row = getMsgRow(id);
    const bubble = getMsgBubble(id);
    if (!bubble) return;
    const textEl = bubble.querySelector('.msg-bubble-text');
    if (textEl) {
      textEl.textContent = backup && backup.id === id ? backup.text : (textEl.textContent || '');
    }
    unlockBubbleWidth(bubble);
    if (row) {
      const menu = row.querySelector('.msg-menu-wrap');
      if (menu) menu.classList.remove('invisible', 'pointer-events-none');
    }
  }

  async function submitInlineEdit(id) {
    const bubble = getMsgBubble(id);
    if (!bubble) return;
    const ta = bubble.querySelector('.msg-edit-input');
    const errEl = bubble.querySelector('.msg-inline-err');
    const saveBtn = bubble.querySelector('[data-msg-edit-save="' + id + '"]');
    const text = ta ? ta.value.trim() : '';
    if (!text) {
      if (errEl) {
        errEl.textContent = 'Le message ne peut pas être vide.';
        errEl.classList.remove('hidden');
      }
      return;
    }
    if (saveBtn) saveBtn.disabled = true;
    if (errEl) errEl.classList.add('hidden');
    try {
      const token = await getCsrf();
      const r = await fetch(opts.apiSend + '/' + id, {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': token,
        },
        body: JSON.stringify({ contenu: text }),
      });
      const d = await r.json();
      if (!r.ok || !d.ok) {
        if (errEl) {
          errEl.textContent = d.message || 'Modification impossible.';
          errEl.classList.remove('hidden');
        }
        if (saveBtn) saveBtn.disabled = false;
        return;
      }
      state.editingId = null;
      state.editBackup = null;
      await loadThread(false);
    } catch (_) {
      if (errEl) {
        errEl.textContent = 'Réseau indisponible.';
        errEl.classList.remove('hidden');
      }
      if (saveBtn) saveBtn.disabled = false;
    }
  }

  function startInlineDelete(id) {
    cancelInlineEdit();
    const row = getMsgRow(id);
    const bubble = getMsgBubble(id);
    if (!row || !bubble || bubble.querySelector('.msg-inline-delete')) return;
    const mine = isMineBubble(bubble);
    const menu = row.querySelector('.msg-menu-wrap');
    if (menu) menu.classList.add('invisible', 'pointer-events-none');
    state.deletingId = id;

    lockBubbleWidth(bubble, 200);

    const textEl = bubble.querySelector('.msg-bubble-text');
    const footer = bubble.querySelector('.msg-bubble-footer');
    const attachments = bubble.querySelectorAll('a, img');
    if (textEl) textEl.classList.add('hidden');
    if (footer) footer.classList.add('hidden');
    attachments.forEach(function (n) { n.classList.add('hidden'); });

    const delHtml =
      '<div class="msg-inline-delete">' +
      '<p class="text-[13px] leading-snug ' + (mine ? 'text-bg/95' : 'text-tx/90') + '">Supprimer ce message ?</p>' +
      '<div class="flex justify-end gap-1.5 mt-2">' +
      '<button type="button" data-msg-delete-cancel class="' + inlineBtnCls(mine, false) + '">Annuler</button>' +
      '<button type="button" data-msg-delete-confirm="' + id + '" class="text-[12px] px-3 py-1 rounded-lg bg-red-500/90 text-white font-semibold hover:bg-red-500 disabled:opacity-50">Supprimer</button>' +
      '</div></div>';
    bubble.insertAdjacentHTML('beforeend', delHtml);
  }

  function cancelInlineDelete() {
    if (!state.deletingId) return;
    const id = state.deletingId;
    state.deletingId = null;
    const row = getMsgRow(id);
    const bubble = getMsgBubble(id);
    if (!bubble) return;
    const del = bubble.querySelector('.msg-inline-delete');
    if (del) del.remove();
    bubble.querySelectorAll('.hidden').forEach(function (n) {
      if (n.classList.contains('msg-menu-panel')) return;
      n.classList.remove('hidden');
    });
    unlockBubbleWidth(bubble);
    if (row) {
      const menu = row.querySelector('.msg-menu-wrap');
      if (menu) menu.classList.remove('invisible', 'pointer-events-none');
    }
  }

  async function submitInlineDelete(id) {
    const bubble = getMsgBubble(id);
    const confirmBtn = bubble && bubble.querySelector('[data-msg-delete-confirm="' + id + '"]');
    if (confirmBtn) confirmBtn.disabled = true;
    try {
      const token = await getCsrf();
      const r = await fetch(opts.apiSend + '/' + id, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': token,
        },
      });
      const d = await r.json();
      if (!r.ok || !d.ok) {
        const errRow = bubble && bubble.querySelector('.msg-inline-delete p');
        if (errRow) errRow.textContent = d.message || 'Suppression impossible.';
        if (confirmBtn) confirmBtn.disabled = false;
        return;
      }
      state.deletingId = null;
      await loadThread(false);
      try { await loadConversations(true); } catch (_) { /* silent */ }
    } catch (_) {
      const errRow = bubble && bubble.querySelector('.msg-inline-delete p');
      if (errRow) errRow.textContent = 'Réseau indisponible.';
      if (confirmBtn) confirmBtn.disabled = false;
    }
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
          const url = normalizeAssetUrl(role === 'client' ? (a.url || '#') : (a.url_prestataire || a.url || '#'));
          const imgPreview = a.is_image
            ? '<img src="' + escapeHtml(url + (url.indexOf('?') >= 0 ? '&' : '?') + 'inline=1') + '" alt="" class="w-full max-h-36 object-cover rounded-lg mb-2 bg-bg/40" loading="lazy" />'
            : '';
          return (
            '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener" class="flex flex-col rounded-xl bg-bg/60 border border-brd/70 hover:border-accent/60 px-3 py-2 text-[13px] transition-colors">' +
            imgPreview +
            '<span class="flex items-center gap-2">' +
            '<i class="fa-regular ' + (a.is_image ? 'fa-image' : 'fa-file-lines') + ' text-accent shrink-0"></i>' +
            '<span class="min-w-0 flex-1">' +
            '<span class="block truncate font-medium text-tx">' + escapeHtml(a.name || 'fichier') + '</span>' +
            '<span class="block text-[10px] opacity-65 mt-0.5">' + escapeHtml(formatBytes(a.size)) + '</span>' +
            '</span>' +
            '<i class="fa-solid fa-arrow-down text-[11px] opacity-70"></i>' +
            '</span></a>'
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
        '<div class="my-3 w-full">' +
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
        '<div class="my-3 w-full">' +
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
      '<div class="flex justify-center my-3 w-full">' +
      '<div class="w-full max-w-[95%] rounded-xl border ' + palette.cls + ' px-3.5 py-2 text-[12.5px] text-tx/90 flex items-center gap-2">' +
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
    const rawUrl = normalizeAssetUrl(att.url || '#');
    const dlUrl = escapeHtml(rawUrl);
    const previewUrl = att.preview_url
      ? normalizeAssetUrl(att.preview_url)
      : (att.is_image ? rawUrl + (rawUrl.indexOf('?') >= 0 ? '&' : '?') + 'inline=1' : null);
    const previewImg = previewUrl && att.is_image
      ? '<a href="' + dlUrl + '" target="_blank" rel="noopener" class="block mt-2 rounded-xl overflow-hidden border border-brd/60 max-w-[280px]">' +
        '<img src="' + escapeHtml(previewUrl) + '" alt="' + label + '" class="w-full max-h-48 object-cover bg-bg/40" loading="lazy" />' +
        '</a>'
      : '';
    return (
      previewImg +
      '<a href="' + dlUrl + '" target="_blank" rel="noopener" class="mt-2 flex items-center gap-2 rounded-xl px-3 py-2 text-[13px] transition-opacity hover:opacity-90 ' +
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
    if (!silent) cancelInlineActions();
    if (silent && (state.editingId || state.deletingId)) return;
    try {
      const r = await fetch(opts.apiThread + '?' + threadQuery(state.active), {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      const d = await r.json();
      if (!r.ok || !d.ok) return;
      if (d.thread) renderHeader(d.thread);
      renderBubbles(d.messages || [], {
        stickBottom: silent ? 'auto' : true,
        silent: !!silent,
      });
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
      if (typeof d.messaging_allowed === 'boolean') {
        state.messagingAllowed = d.messaging_allowed;
        state.messagingBlockedMessage = d.messaging_blocked_message || '';
      }
      renderMessagingGate();
      renderConvList();
      syncListLayout();
      notifyUnread();
    } catch (_) {
      el.list.innerHTML = '<p class="text-sm text-muted px-4 py-6">Réseau indisponible.</p>';
    }
  }

  function renderMessagingGate() {
    if (!el.form) return;
    let banner = document.getElementById('msg-trial-banner');
    if (state.messagingAllowed !== false) {
      if (banner) banner.remove();
      el.form.classList.remove('hidden');
      return;
    }
    if (!banner) {
      banner = document.createElement('div');
      banner.id = 'msg-trial-banner';
      banner.className = 'border-t border-brd px-4 py-3 text-sm text-accent bg-surface2';
      if (el.form.parentNode) {
        el.form.parentNode.insertBefore(banner, el.form);
      }
    }
    banner.textContent = state.messagingBlockedMessage ||
      'Messagerie bloquée : souscrivez à un plan pour discuter avec les clients.';
    el.form.classList.add('hidden');
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
    state.lastMsgSig = '';
    state.editingId = null;
    state.deletingId = null;
    state.editBackup = null;
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
    if (role === 'prestataire' && state.messagingAllowed === false) {
      alert(state.messagingBlockedMessage || 'Messagerie bloquée : abonnement requis.');
      return;
    }
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
    if (!window._bfMsgMenuCloser) {
      window._bfMsgMenuCloser = true;
      document.addEventListener('click', closeAllMsgMenus);
    }
    renderEmojiPanel();
    bindMessageActions();
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
