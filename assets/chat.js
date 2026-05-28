(() => {
  const roots = Array.from(document.querySelectorAll('.aikb-chat-root'));
  if (!roots.length) return;

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    "'": '&#039;',
    '"': '&quot;',
  }[char]));

  const sessionId = () => {
    const key = 'aikb_session_id';
    let id = window.localStorage.getItem(key);
    if (!id) {
      id = (window.crypto?.randomUUID && window.crypto.randomUUID()) || `aikb_${Date.now()}_${Math.random().toString(16).slice(2)}`;
      window.localStorage.setItem(key, id);
    }
    return id;
  };

  const createMessage = (role, text) => {
    const node = document.createElement('div');
    node.className = `aikb-chat-message ${role}`;
    node.innerHTML = escapeHtml(text);
    return node;
  };


  const createSources = (sources) => {
    if (!Array.isArray(sources) || !sources.length) return null;
    const wrap = document.createElement('div');
    wrap.className = 'aikb-chat-sources';
    const items = sources.map((source) => {
      const title = escapeHtml(source.title || 'Source');
      const url = source.url ? escapeHtml(source.url) : '';
      return url ? `<a href="${url}" target="_blank" rel="noopener noreferrer">${title}</a>` : `<span>${title}</span>`;
    }).join('');
    wrap.innerHTML = `<div>Sources</div>${items}`;
    return wrap;
  };

  const createFeedback = (config) => {
    const wrap = document.createElement('div');
    wrap.className = 'aikb-chat-feedback';
    wrap.innerHTML = '<button type="button" data-rating="up">Helpful</button><button type="button" data-rating="down">Not helpful</button>';
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
        wrap.innerHTML = '<span>Thanks for your feedback.</span>';
      } catch (error) {
        button.disabled = false;
      }
    });
    return wrap;
  };

  const init = (root) => {
    const inlineConfig = root.dataset.config ? JSON.parse(root.dataset.config) : {};
    const config = { ...(window.AIKB_CHAT || {}), ...inlineConfig };
    root.style.setProperty('--aikb-chat-accent', config.accentColor || '#6f5bd6');
    root.dataset.position = config.position || 'right';

    root.innerHTML = `
      <button class="aikb-chat-launcher" type="button">Chat</button>
      <section class="aikb-chat-window" hidden aria-live="polite">
        <header class="aikb-chat-header">
          <div class="aikb-chat-title">${escapeHtml(config.assistantName || 'Assistant')}</div>
          <button class="aikb-chat-close" type="button" aria-label="Close">×</button>
        </header>
        <div class="aikb-chat-messages"></div>
        <form class="aikb-chat-form">
          <input class="aikb-chat-input" type="text" autocomplete="off" placeholder="${escapeHtml(config.placeholder || 'Ask a question...')}">
          <button class="aikb-chat-send" type="submit">Send</button>
        </form>
      </section>
    `;

    const launcher = root.querySelector('.aikb-chat-launcher');
    const windowNode = root.querySelector('.aikb-chat-window');
    const close = root.querySelector('.aikb-chat-close');
    const messages = root.querySelector('.aikb-chat-messages');
    const form = root.querySelector('.aikb-chat-form');
    const input = root.querySelector('.aikb-chat-input');
    const send = root.querySelector('.aikb-chat-send');

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
      const typing = createMessage('assistant', 'Typing...');
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
        if (!response.ok) throw new Error(json.message || 'The chatbot could not answer right now.');
        typing.innerHTML = escapeHtml(json.answer || 'No answer returned.');
        const sources = createSources(json.sources || []);
        if (sources) messages.appendChild(sources);
        messages.appendChild(createFeedback(config));
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
