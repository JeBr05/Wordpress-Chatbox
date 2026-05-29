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

  const sessionId = () => {
    const key = 'jcb_session_id';
    let id = window.localStorage.getItem(key);
    if (!id) {
      id = (window.crypto?.randomUUID && window.crypto.randomUUID()) || `jcb_${Date.now()}_${Math.random().toString(16).slice(2)}`;
      window.localStorage.setItem(key, id);
    }
    return id;
  };

  const createMessage = (role, text) => {
    const node = document.createElement('div');
    node.className = `jcb-chat-message ${role}`;
    node.innerHTML = escapeHtml(text);
    return node;
  };


  const createSources = (sources, strings) => {
    if (!Array.isArray(sources) || !sources.length) return null;
    const wrap = document.createElement('div');
    wrap.className = 'jcb-chat-sources';
    const items = sources.map((source) => {
      const title = escapeHtml(source.title || strings.source || 'Source');
      const url = source.url ? escapeHtml(source.url) : '';
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

    root.innerHTML = `
      <button class="jcb-chat-launcher" type="button">${escapeHtml(config.launcherLabel || 'Chat')}</button>
      <section class="jcb-chat-window" hidden aria-live="polite">
        <header class="jcb-chat-header">
          <div class="jcb-chat-title">${escapeHtml(config.assistantName || "Jeroen's Chatbox")}</div>
          <button class="jcb-chat-close" type="button" aria-label="${escapeHtml(strings.close)}">×</button>
        </header>
        <div class="jcb-chat-messages"></div>
        <form class="jcb-chat-form">
          <input class="jcb-chat-input" type="text" autocomplete="off" placeholder="${escapeHtml(config.placeholder || 'Ask a question...')}">
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

    if (config.startOpen) {
      windowNode.hidden = false;
    }

    messages.appendChild(createMessage('assistant', config.welcomeMessage || 'Hi. How can I help you?'));

    launcher.addEventListener('click', () => {
      windowNode.hidden = !windowNode.hidden;
      if (!windowNode.hidden) input.focus();
    });
    close.addEventListener('click', () => {
      windowNode.hidden = true;
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const message = input.value.trim();
      if (!message) return;
      input.value = '';
      messages.appendChild(createMessage('user', message));
      const typing = createMessage('assistant', strings.typing || 'Typing...');
      messages.appendChild(typing);
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
        typing.innerHTML = escapeHtml(json.answer || strings.noAnswer || 'No answer returned.');
        const sources = createSources(json.sources || [], strings);
        if (sources) messages.appendChild(sources);
        messages.appendChild(createFeedback(config, strings));
      } catch (error) {
        typing.innerHTML = escapeHtml(error.message);
      } finally {
        send.disabled = false;
        messages.scrollTop = messages.scrollHeight;
      }
    });
  };

  roots.forEach(init);
})();
