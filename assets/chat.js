(() => {
  const roots = Array.from(document.querySelectorAll('.jcb-chat-root'));
  if (!roots.length) return;

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    "'": '&#039;',
    '"': '&quot;',
  }[char]));

  // Escape a value for use inside an HTML attribute.
  const escapeAttr = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');

  // Validate a link target and return a safe href, or null when not allowed.
  const safeUrl = (raw) => {
    const decoded = String(raw ?? '')
      .replace(/&amp;/g, '&')
      .replace(/&#039;/g, "'")
      .replace(/&quot;/g, '"')
      .replace(/&lt;/g, '<')
      .replace(/&gt;/g, '>')
      .trim();
    if (/^(https?:\/\/|mailto:|tel:)/i.test(decoded) || /^\//.test(decoded) || /^#/.test(decoded)) {
      return decoded;
    }
    return null;
  };

  // Minimal, safe Markdown to HTML: links, bold, italic, inline code and lists.
  const renderMarkdown = (text) => {
    let src = escapeHtml(String(text ?? '')).replace(/\r\n/g, '\n');
    const stash = [];
    const keep = (html) => {
      stash.push(html);
      return `\u0000${stash.length - 1}\u0000`;
    };

    // Inline code first so its contents are not transformed.
    src = src.replace(/`([^`\n]+)`/g, (m, code) => keep(`<code>${code}</code>`));

    // Markdown links [label](target).
    src = src.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, (match, label, target) => {
      const href = safeUrl(target);
      if (!href) return label;
      const external = /^https?:\/\//i.test(href);
      const attrs = external ? ' target="_blank" rel="noopener noreferrer"' : '';
      return keep(`<a href="${escapeAttr(href)}"${attrs}>${label}</a>`);
    });

    // Bold then italic.
    src = src.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
    src = src.replace(/__([^_\n]+)__/g, '<strong>$1</strong>');
    src = src.replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>');
    src = src.replace(/(^|[^_])_([^_\n]+)_/g, '$1<em>$2</em>');

    // Build block structure: group list items, keep other lines as paragraphs.
    const lines = src.split('\n');
    let html = '';
    let listType = null;
    const closeList = () => {
      if (listType) {
        html += `</${listType}>`;
        listType = null;
      }
    };
    lines.forEach((line) => {
      const unordered = line.match(/^\s*[-*+]\s+(.*)$/);
      const ordered = line.match(/^\s*\d+\.\s+(.*)$/);
      if (unordered) {
        if (listType !== 'ul') { closeList(); html += '<ul>'; listType = 'ul'; }
        html += `<li>${unordered[1]}</li>`;
      } else if (ordered) {
        if (listType !== 'ol') { closeList(); html += '<ol>'; listType = 'ol'; }
        html += `<li>${ordered[1]}</li>`;
      } else if (line.trim() === '') {
        closeList();
      } else {
        closeList();
        html += `${line}<br>`;
      }
    });
    closeList();
    html = html.replace(/(<br>)+$/, '');

    // Restore stashed inline HTML.
    html = html.replace(/\u0000(\d+)\u0000/g, (m, index) => stash[Number(index)] || '');
    return html;
  };

  const icons = {
    chat: '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-8.5 8.5 8.5 8.5 0 0 1-3.8-.9L3 21l1.9-5.7A8.38 8.38 0 0 1 4 11.5 8.5 8.5 0 0 1 12.5 3 8.38 8.38 0 0 1 21 11.5z"/></svg>',
    question: '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12" y2="17"/></svg>',
    sparkle: '<svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor"><path d="M12 2l1.9 5.6L19.5 9l-5.6 1.9L12 16l-1.9-5.1L4.5 9l5.6-1.4L12 2z"/></svg>',
    bot: '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="8" width="16" height="11" rx="3"/><path d="M12 8V4"/><circle cx="9" cy="13.5" r="1"/><circle cx="15" cy="13.5" r="1"/></svg>',
  };
  const iconSvg = (name) => icons[name] || icons.chat;

  const sessionId = () => {
    const key = 'jcb_session_id';
    let id = window.localStorage.getItem(key);
    if (!id) {
      id = (window.crypto?.randomUUID && window.crypto.randomUUID()) || `jcb_${Date.now()}_${Math.random().toString(16).slice(2)}`;
      window.localStorage.setItem(key, id);
    }
    return id;
  };

  const createSources = (sources, strings) => {
    if (!Array.isArray(sources) || !sources.length) return null;
    const wrap = document.createElement('div');
    wrap.className = 'jcb-chat-sources';
    const items = sources.map((source) => {
      const title = escapeHtml(source.title || strings.source || 'Source');
      const url = source.url ? escapeAttr(source.url) : '';
      return url ? `<a href="${url}" target="_blank" rel="noopener noreferrer">${title}</a>` : `<span>${title}</span>`;
    }).join('');
    wrap.innerHTML = `<div>${escapeHtml(strings.sources || 'Sources')}</div>${items}`;
    return wrap;
  };

  const createFeedback = (config, strings) => {
    const wrap = document.createElement('div');
    wrap.className = 'jcb-chat-feedback';
    wrap.innerHTML = `<button type="button" data-rating="up">${escapeHtml(strings.helpful || 'Helpful')}</button><button type="button" data-rating="down">${escapeHtml(strings.notHelpful || 'Not helpful')}</button>`;
    wrap.addEventListener('click', async (event) => {
      const button = event.target.closest('button[data-rating]');
      if (!button) return;
      button.disabled = true;
      try {
        await fetch(config.feedbackUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
          body: JSON.stringify({ sessionId: sessionId(), rating: button.dataset.rating }),
        });
        wrap.innerHTML = `<span>${escapeHtml(strings.feedbackThanks || 'Thanks for your feedback.')}</span>`;
      } catch (error) {
        button.disabled = false;
      }
    });
    return wrap;
  };

  const init = (root) => {
    const inlineConfig = root.dataset.config ? JSON.parse(root.dataset.config) : {};
    const config = { ...(window.JCB_CHAT || {}), ...inlineConfig };
    const strings = {
      send: 'Send',
      close: 'Close',
      typing: 'Typing...',
      sources: 'Sources',
      source: 'Source',
      helpful: 'Helpful',
      notHelpful: 'Not helpful',
      feedbackThanks: 'Thanks for your feedback.',
      errorAnswer: 'The chatbox could not answer right now.',
      noAnswer: 'No answer returned.',
      ...(config.strings || {}),
    };

    const hasAvatar = Boolean(config.avatarUrl);
    const avatarShape = config.avatarShape || 'circle';
    const useMarkdown = config.enableMarkdown !== false;

    root.style.setProperty('--jcb-chat-accent', config.accentColor || '#6f5bd6');
    root.style.setProperty('--jcb-chat-font-color', config.fontColor || '#111827');
    root.style.setProperty('--jcb-chat-background-color', config.backgroundColor || '#f8fafc');
    root.style.setProperty('--jcb-chat-bg', config.backgroundColor || '#ffffff');
    root.style.setProperty('--jcb-chat-user-bubble-color', config.userBubbleColor || config.accentColor || '#6f5bd6');
    root.style.setProperty('--jcb-chat-user-bubble-text-color', config.userBubbleTextColor || '#ffffff');
    root.style.setProperty('--jcb-chat-assistant-bubble-color', config.assistantBubbleColor || '#ffffff');
    root.style.setProperty('--jcb-chat-assistant-bubble-text-color', config.assistantBubbleTextColor || '#111827');
    root.style.setProperty('--jcb-chat-z-index', String(config.zIndex || 99999));
    root.dataset.position = config.position || 'right';
    root.dataset.bubbleStyle = config.bubbleStyle || 'soft';
    root.dataset.avatarSize = config.avatarSize || 'medium';
    root.dataset.launcherSize = config.launcherSize || 'medium';

    // Launcher contents based on the selected style.
    const launcherStyle = config.launcherStyle || 'label';
    root.dataset.launcherStyle = launcherStyle;
    let launcherInner;
    if (launcherStyle === 'icon') {
      launcherInner = iconSvg(config.launcherIcon);
    } else if (launcherStyle === 'avatar' && hasAvatar) {
      launcherInner = `<img class="jcb-chat-launcher-avatar" src="${escapeAttr(config.avatarUrl)}" alt="">`;
    } else if (launcherStyle === 'avatar') {
      launcherInner = iconSvg(config.launcherIcon);
    } else {
      launcherInner = escapeHtml(config.launcherLabel || 'Chat');
    }

    const headerAvatar = (config.showAvatarInHeader && hasAvatar)
      ? `<span class="jcb-chat-header-avatar" data-shape="${escapeAttr(avatarShape)}"><img src="${escapeAttr(config.avatarUrl)}" alt=""></span>`
      : '';

    root.innerHTML = `
      <button class="jcb-chat-launcher" type="button" aria-label="${escapeAttr(config.launcherLabel || strings.send || 'Chat')}">${launcherInner}</button>
      <section class="jcb-chat-window" hidden aria-live="polite">
        <header class="jcb-chat-header">
          ${headerAvatar}
          <div class="jcb-chat-title">${escapeHtml(config.assistantName || "Jeroen's Chatbox")}</div>
          <button class="jcb-chat-close" type="button" aria-label="${escapeAttr(strings.close)}">&times;</button>
        </header>
        <div class="jcb-chat-messages"></div>
        <form class="jcb-chat-form">
          <input class="jcb-chat-input" type="text" autocomplete="off" placeholder="${escapeAttr(config.placeholder || 'Ask a question...')}">
          <button class="jcb-chat-send" type="submit">${escapeHtml(strings.send)}</button>
        </form>
      </section>
    `;

    const launcher = root.querySelector('.jcb-chat-launcher');
    const windowNode = root.querySelector('.jcb-chat-window');
    const close = root.querySelector('.jcb-chat-close');
    const messages = root.querySelector('.jcb-chat-messages');
    const form = root.querySelector('.jcb-chat-form');
    const input = root.querySelector('.jcb-chat-input');
    const send = root.querySelector('.jcb-chat-send');

    // Create a message row (with assistant avatar when enabled) and return the bubble.
    const addMessage = (role, content, asHtml = false) => {
      const row = document.createElement('div');
      row.className = `jcb-chat-row ${role}`;
      let inner = '';
      if (role === 'assistant' && config.showAvatarOnMessages && hasAvatar) {
        inner += `<span class="jcb-chat-avatar" data-shape="${escapeAttr(avatarShape)}"><img src="${escapeAttr(config.avatarUrl)}" alt=""></span>`;
      }
      inner += `<div class="jcb-chat-message ${role}"></div>`;
      row.innerHTML = inner;
      const bubble = row.querySelector('.jcb-chat-message');
      if (asHtml) bubble.innerHTML = content;
      else bubble.textContent = content;
      messages.appendChild(row);
      messages.scrollTop = messages.scrollHeight;
      return bubble;
    };

    if (config.startOpen) {
      windowNode.hidden = false;
    }

    addMessage('assistant', config.welcomeMessage || 'Hi. How can I help you?');

    // Quick reply suggestion chips.
    const quickReplies = Array.isArray(config.quickReplies) ? config.quickReplies : [];
    let quickWrap = null;
    if (quickReplies.length) {
      quickWrap = document.createElement('div');
      quickWrap.className = 'jcb-chat-quick-replies';
      quickWrap.innerHTML = quickReplies.map((reply) => `<button type="button" class="jcb-chat-chip">${escapeHtml(reply)}</button>`).join('');
      messages.appendChild(quickWrap);
      quickWrap.addEventListener('click', (event) => {
        const chip = event.target.closest('.jcb-chat-chip');
        if (!chip) return;
        sendMessage(chip.textContent);
      });
    }

    const hideQuickReplies = () => {
      if (quickWrap) {
        quickWrap.remove();
        quickWrap = null;
      }
    };

    launcher.addEventListener('click', () => {
      windowNode.hidden = !windowNode.hidden;
      if (!windowNode.hidden) input.focus();
    });
    close.addEventListener('click', () => {
      windowNode.hidden = true;
    });

    const sendMessage = async (rawMessage) => {
      const message = String(rawMessage || '').trim();
      if (!message || send.disabled) return;
      hideQuickReplies();
      input.value = '';
      addMessage('user', message);

      const typing = addMessage('assistant', '', true);
      typing.classList.add('is-typing');
      typing.innerHTML = '<span class="jcb-typing"><i></i><i></i><i></i></span>';
      messages.scrollTop = messages.scrollHeight;
      send.disabled = true;

      try {
        const response = await fetch(config.apiUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
          body: JSON.stringify({
            message,
            sessionId: sessionId(),
            pageUrl: window.location.href,
          }),
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(json.message || strings.errorAnswer || 'The chatbox could not answer right now.');
        if (json.security && json.security.message && ['warning', 'flagged'].includes(json.security.action)) {
          addMessage('system', json.security.message);
        }
        const answer = json.answer || strings.noAnswer || 'No answer returned.';
        typing.classList.remove('is-typing');
        if (useMarkdown) typing.innerHTML = renderMarkdown(answer);
        else typing.textContent = answer;
        const sources = createSources(json.sources || [], strings);
        if (sources) messages.appendChild(sources);
        messages.appendChild(createFeedback(config, strings));
      } catch (error) {
        typing.classList.remove('is-typing');
        typing.textContent = error.message;
      } finally {
        send.disabled = false;
        messages.scrollTop = messages.scrollHeight;
      }
    };

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      sendMessage(input.value);
    });
  };

  roots.forEach(init);
})();
