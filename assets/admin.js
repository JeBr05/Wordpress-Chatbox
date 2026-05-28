(() => {
  const state = {
    settings: window.AIKB_ADMIN?.settings || {},
    items: [],
    activeItem: null,
    loadedPanels: new Set(['knowledge']),
  };

  const restUrl = window.AIKB_ADMIN.restUrl;
  const nonce = window.AIKB_ADMIN.nonce;

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

  const api = async (path, options = {}) => {
    const response = await fetch(`${restUrl}${path}`, {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': nonce,
        ...(options.headers || {}),
      },
    });
    const json = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(json.message || 'Request failed');
    }
    return json;
  };

  const notice = (message, type = 'success') => {
    const box = $('#aikb-notices');
    if (!box) return;
    box.innerHTML = `<div class="aikb-notice ${type}">${escapeHtml(message)}</div>`;
    window.setTimeout(() => {
      box.innerHTML = '';
    }, 5500);
  };

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    "'": '&#039;',
    '"': '&quot;',
  }[char]));

  const setBusy = (button, busy, label = 'Working...') => {
    if (!button) return;
    if (busy) {
      button.dataset.originalText = button.textContent;
      button.textContent = label;
      button.disabled = true;
    } else {
      button.textContent = button.dataset.originalText || button.textContent;
      button.disabled = false;
    }
  };

  const updateStatus = () => {
    const selected = state.items.filter((item) => item.included).length;
    const selectedNode = $('#aikb-selected-count');
    if (selectedNode) selectedNode.textContent = String(selected);

    const vectorNode = $('#aikb-vector-status');
    if (vectorNode) {
      const hasVector = Boolean(state.settings.vector_store_id);
      const status = state.settings.vector_store_status || 'not_connected';
      vectorNode.textContent = hasVector ? status : 'Not connected';
      vectorNode.className = hasVector ? 'connected' : 'not-connected';
    }
  };

  const loadContent = async () => {
    const data = await api('/content');
    state.items = data.items || [];
    state.settings = data.options || state.settings;
    renderContentList();
    updateStatus();
  };

  const renderContentList = () => {
    const list = $('#aikb-content-list');
    if (!list) return;
    const query = ($('#aikb-content-search')?.value || '').toLowerCase();
    const visible = state.items.filter((item) => item.title.toLowerCase().includes(query) || item.type.toLowerCase().includes(query));
    if (!visible.length) {
      list.innerHTML = '<div class="aikb-empty">No content found.</div>';
      return;
    }
    list.innerHTML = visible.map((item) => `
      <div class="aikb-content-item ${state.activeItem?.id === item.id ? 'is-active' : ''}" data-id="${item.id}">
        <input type="checkbox" ${item.included ? 'checked' : ''} aria-label="Select ${escapeHtml(item.title)}">
        <div>
          <div class="aikb-content-title">${escapeHtml(item.title)}</div>
          <div class="aikb-content-type">${escapeHtml(item.type)}</div>
        </div>
        <span class="aikb-content-type">PAGE</span>
      </div>
    `).join('');
  };

  const selectItem = (id) => {
    state.activeItem = state.items.find((item) => item.id === Number(id));
    const form = $('#aikb-metadata-form');
    const empty = $('#aikb-editor-empty');
    if (!state.activeItem || !form) return;
    empty.classList.add('aikb-hidden');
    form.classList.remove('aikb-hidden');
    $('#aikb-meta-id').value = state.activeItem.id;
    $('#aikb-meta-title').value = state.activeItem.title;
    $('#aikb-meta-summary').value = state.activeItem.metadata?.summary || '';
    $('#aikb-meta-tags').value = state.activeItem.metadata?.tags || '';
    $('#aikb-meta-priority').value = state.activeItem.metadata?.priority || 0;
    renderContentList();
  };

  const toggleInclude = async (id, included) => {
    const data = await api(`/content/${id}/include`, {
      method: 'POST',
      body: JSON.stringify({ included }),
    });
    const index = state.items.findIndex((item) => item.id === Number(id));
    if (index >= 0) state.items[index] = data.item;
    if (state.activeItem?.id === Number(id)) state.activeItem = data.item;
    renderContentList();
    updateStatus();
  };

  const saveMetadata = async (event) => {
    event.preventDefault();
    const id = $('#aikb-meta-id').value;
    const button = event.target.querySelector('button[type="submit"]');
    setBusy(button, true, 'Saving...');
    try {
      const data = await api(`/metadata/${id}`, {
        method: 'POST',
        body: JSON.stringify({
          summary: $('#aikb-meta-summary').value,
          tags: $('#aikb-meta-tags').value,
          priority: $('#aikb-meta-priority').value,
        }),
      });
      const index = state.items.findIndex((item) => item.id === Number(id));
      if (index >= 0) state.items[index] = data.item;
      state.activeItem = data.item;
      notice('Metadata saved.');
    } catch (error) {
      notice(error.message, 'error');
    } finally {
      setBusy(button, false);
    }
  };

  const field = (label, key, type = 'text', help = '') => `
    <label>${label}
      <input type="${type}" data-setting="${key}" value="${escapeHtml(state.settings[key] ?? '')}">
    </label>
    ${help ? `<p>${escapeHtml(help)}</p>` : ''}
  `;

  const checkbox = (label, key, help = '') => `
    <label class="aikb-check">
      <input type="checkbox" data-setting="${key}" ${state.settings[key] ? 'checked' : ''}> ${label}
    </label>
    ${help ? `<p>${escapeHtml(help)}</p>` : ''}
  `;

  const saveButton = '<div class="aikb-form-actions"><button class="button button-primary" data-save-settings type="button">Save settings</button></div>';

  const renderSettingsPanel = (panelName) => {
    const panel = $(`[data-panel="${panelName}"]`);
    if (!panel) return;

    if (panelName === 'assistants') {
      panel.innerHTML = `
        <div class="aikb-grid aikb-grid-two">
          <section class="aikb-card">
            <h2>Assistant setup</h2>
            ${field('Assistant name', 'assistant_name')}
            <label>Model
              <select data-setting="model">
                ${['gpt-4.1-mini','gpt-4.1','gpt-4o-mini','gpt-4o','gpt-5-mini','gpt-5','gpt-5.1-mini','gpt-5.1','gpt-5.2-mini','gpt-5.2'].map((model) => `<option ${state.settings.model === model ? 'selected' : ''}>${model}</option>`).join('')}
              </select>
            </label>
            <label>Instructions
              <textarea data-setting="instructions" rows="10">${escapeHtml(state.settings.instructions || '')}</textarea>
            </label>
            ${field('Maximum answer tokens', 'max_output_tokens', 'number')}
            ${saveButton}
          </section>
          <section class="aikb-card">
            <h2>Answer behaviour</h2>
            <p>The assistant uses your selected pages first. The fallback instruction tells it to be honest when the site content does not contain the answer.</p>
            <p>Best use cases are support, opening hours, product details, booking questions and content guidance.</p>
          </section>
        </div>`;
    }

    if (panelName === 'tools') {
      panel.innerHTML = `
        <div class="aikb-grid aikb-grid-two">
          <section class="aikb-card">
            <h2>Retrieval tools</h2>
            ${checkbox('Enable file search', 'enable_file_search', 'Use the OpenAI vector store as the chatbot knowledge base.')}
            ${checkbox('Include source search results in API response', 'include_sources')}
            ${field('Maximum file search results', 'max_file_results', 'number')}
            ${checkbox('Remember short session context', 'session_context_enabled')}
            ${field('History messages per chat', 'max_history_messages', 'number')}
            ${field('Session memory lifetime in minutes', 'session_ttl_minutes', 'number')}
            ${saveButton}
          </section>
          <section class="aikb-card">
            <h2>Available tools</h2>
            <table class="aikb-table"><tbody>
              <tr><th>File search</th><td>Connected to selected WordPress pages.</td></tr>
              <tr><th>Feedback</th><td>Stores thumbs up and down events.</td></tr>
              <tr><th>Analytics</th><td>Stores usage metrics when logging is enabled.</td></tr>
            </tbody></table>
          </section>
        </div>`;
    }

    if (panelName === 'channels') {
      panel.innerHTML = `
        <div class="aikb-grid aikb-grid-two">
          <section class="aikb-card">
            <h2>Publish</h2>
            <label>Shortcode
              <div class="aikb-copybox"><input type="text" readonly value="${escapeHtml(window.AIKB_ADMIN.shortcode)}"><button class="button" data-copy-shortcode type="button">Copy</button></div>
            </label>
            ${checkbox('Auto embed on every public page', 'auto_embed')}
            ${saveButton}
          </section>
          <section class="aikb-card">
            <h2>Use cases</h2>
            <p>Place the shortcode in a page, post, template part or widget area.</p>
            <p>Use auto embed when you want one global floating chatbot across the site.</p>
          </section>
        </div>`;
    }

    if (panelName === 'design') {
      panel.innerHTML = `
        <div class="aikb-grid aikb-grid-two">
          <section class="aikb-card">
            <h2>Chat design</h2>
            ${field('Welcome message', 'welcome_message')}
            ${field('Input placeholder', 'placeholder')}
            ${field('Accent color', 'accent_color', 'color')}
            <label>Launcher position
              <select data-setting="launcher_position">
                <option value="right" ${state.settings.launcher_position === 'right' ? 'selected' : ''}>Right</option>
                <option value="left" ${state.settings.launcher_position === 'left' ? 'selected' : ''}>Left</option>
              </select>
            </label>
            ${saveButton}
          </section>
          <section class="aikb-card">
            <h2>Preview</h2>
            <div class="aikb-preview" style="border:1px solid #e5e7ef;border-radius:16px;max-width:360px;padding:16px;">
              <div style="font-weight:800;margin-bottom:12px;">${escapeHtml(state.settings.assistant_name)}</div>
              <div style="background:#f8fafc;border-radius:14px;padding:12px;margin-bottom:12px;">${escapeHtml(state.settings.welcome_message)}</div>
              <button style="background:${escapeHtml(state.settings.accent_color)};color:#fff;border:0;border-radius:999px;padding:12px 16px;">Chat</button>
            </div>
          </section>
        </div>`;
    }

    if (panelName === 'security') {
      panel.innerHTML = `
        <div class="aikb-grid aikb-grid-two">
          <section class="aikb-card">
            <h2>Security and privacy</h2>
            ${field('Rate limit per minute per IP', 'rate_limit_per_minute', 'number')}
            ${field('Rate limit per hour per IP', 'rate_limit_per_hour', 'number')}
            ${field('Daily token budget', 'daily_token_budget', 'number', 'Set 0 to disable the daily budget cap.')}
            ${checkbox('Log conversations', 'log_conversations')}
            ${field('Log retention days', 'log_retention_days', 'number')}
            ${checkbox('Redact email addresses and phone numbers before logging', 'redact_personal_data')}
            ${saveButton}
          </section>
          <section class="aikb-card">
            <h2>Recommended defaults</h2>
            <p>Keep rate limiting on. Keep redaction on. Only enable debug mode while testing.</p>
          </section>
        </div>`;
    }

    if (panelName === 'api') {
      panel.innerHTML = `
        <div class="aikb-grid aikb-grid-two">
          <section class="aikb-card">
            <h2>OpenAI API</h2>
            <p>API key saved: <strong>${state.settings.api_key_saved ? 'Yes' : 'No'}</strong></p>
            ${field('OpenAI API key', 'api_key', 'password', 'Leave empty to keep the saved key.')}
            ${saveButton}
            <div class="aikb-form-actions"><button class="button" data-test-api type="button">Test connection</button></div>
          </section>
          <section class="aikb-card">
            <h2>Vector store</h2>
            <table class="aikb-table"><tbody>
              <tr><th>Status</th><td>${escapeHtml(state.settings.vector_store_status || 'not connected')}</td></tr>
              <tr><th>Vector store id</th><td>${escapeHtml(state.settings.vector_store_id || 'None')}</td></tr>
              <tr><th>Last sync</th><td>${escapeHtml(state.settings.last_sync_at || 'Never')}</td></tr>
              <tr><th>Last file count</th><td>${escapeHtml(state.settings.last_file_count || 0)}</td></tr>
              <tr><th>Last file ids</th><td>${escapeHtml(state.settings.last_file_id || 'None')}</td></tr>
            </tbody></table>
            <div class="aikb-form-actions"><button class="button" data-check-sync type="button">Check sync status</button></div>
          </section>
        </div>`;
    }

    if (panelName === 'settings') {
      panel.innerHTML = `
        <div class="aikb-grid aikb-grid-two">
          <section class="aikb-card">
            <h2>Advanced settings</h2>
            ${checkbox('Debug mode', 'debug_mode')}
            ${checkbox('Replace old vector store on every sync', 'replace_vector_store')}
            ${checkbox('Delete plugin data on uninstall', 'delete_data_on_uninstall')}
            ${saveButton}
          </section>
          <section class="aikb-card">
            <h2>Developer notes</h2>
            <p>REST namespace: ${escapeHtml(restUrl)}</p>
            <p>Plugin version: ${escapeHtml(window.AIKB_ADMIN.settings?.version || '0.2.0')}</p>
          </section>
        </div>`;
    }
  };

  const saveSettings = async (button) => {
    const panel = button.closest('.aikb-panel');
    const payload = {};
    $$('[data-setting]', panel).forEach((input) => {
      const key = input.dataset.setting;
      if (input.type === 'checkbox') payload[key] = input.checked;
      else payload[key] = input.value;
    });
    setBusy(button, true, 'Saving...');
    try {
      state.settings = await api('/settings', { method: 'POST', body: JSON.stringify(payload) });
      updateStatus();
      state.loadedPanels.delete(panel.dataset.panel);
      renderSettingsPanel(panel.dataset.panel);
      notice('Settings saved.');
    } catch (error) {
      notice(error.message, 'error');
    } finally {
      setBusy(button, false);
    }
  };

  const loadAnalytics = async () => {
    const panel = $('[data-panel="analytics"]');
    if (!panel) return;
    panel.innerHTML = '<section class="aikb-card"><h2>Analytics</h2><p>Loading...</p></section>';
    try {
      const data = await api('/analytics');
      panel.innerHTML = `
        <section class="aikb-card">
          <h2>Analytics</h2>
          <div class="aikb-stat-grid">
            <div class="aikb-stat">Conversations<strong>${data.total_conversations}</strong></div>
            <div class="aikb-stat">Messages<strong>${data.total_messages}</strong></div>
            <div class="aikb-stat">Messages last 7 days<strong>${data.recent_messages}</strong></div>
            <div class="aikb-stat">Tokens last 7 days<strong>${data.tokens_7_days || 0}</strong></div>
            <div class="aikb-stat">Avg latency ms<strong>${data.avg_latency_ms || 0}</strong></div>
          </div>
          <h3>Recent messages</h3>
          <table class="aikb-table"><thead><tr><th>Time</th><th>Role</th><th>Message</th></tr></thead><tbody>
            ${(data.recent || []).map((row) => `<tr><td>${escapeHtml(row.created_at)}</td><td>${escapeHtml(row.role)}</td><td>${escapeHtml(row.content)}</td></tr>`).join('') || '<tr><td colspan="3">No messages yet.</td></tr>'}
          </tbody></table>
        </section>`;
    } catch (error) {
      panel.innerHTML = `<section class="aikb-card"><h2>Analytics</h2><p>${escapeHtml(error.message)}</p></section>`;
    }
  };

  const sync = async (button) => {
    setBusy(button, true, 'Syncing...');
    try {
      const data = await api('/sync', { method: 'POST', body: JSON.stringify({}) });
      state.settings = data.options || state.settings;
      updateStatus();
      notice(`Knowledge base sync started. ${data.file_count || 0} files sent to the vector store.`);
    } catch (error) {
      notice(error.message, 'error');
    } finally {
      setBusy(button, false);
    }
  };


  const checkSync = async (button) => {
    setBusy(button, true, 'Checking...');
    try {
      const data = await api('/sync-status');
      state.settings = data.options || state.settings;
      updateStatus();
      state.loadedPanels.delete('api');
      renderSettingsPanel('api');
      notice(`Sync status: ${state.settings.vector_store_status || 'unknown'}.`);
    } catch (error) {
      notice(error.message, 'error');
    } finally {
      setBusy(button, false);
    }
  };

  const testApi = async (button) => {
    setBusy(button, true, 'Testing...');
    try {
      const data = await api('/test-api', { method: 'POST', body: JSON.stringify({}) });
      notice(data.message || 'Connection works.');
    } catch (error) {
      notice(error.message, 'error');
    } finally {
      setBusy(button, false);
    }
  };

  document.addEventListener('click', (event) => {
    const tab = event.target.closest('.aikb-tab');
    if (tab) {
      $$('.aikb-tab').forEach((node) => node.classList.remove('is-active'));
      $$('.aikb-panel').forEach((node) => node.classList.remove('is-active'));
      tab.classList.add('is-active');
      const name = tab.dataset.tab;
      $(`[data-panel="${name}"]`)?.classList.add('is-active');
      if (name === 'analytics') loadAnalytics();
      else if (!state.loadedPanels.has(name)) {
        renderSettingsPanel(name);
        state.loadedPanels.add(name);
      }
    }

    const contentItem = event.target.closest('.aikb-content-item');
    if (contentItem && !event.target.matches('input[type="checkbox"]')) {
      selectItem(contentItem.dataset.id);
    }

    if (event.target.matches('.aikb-content-item input[type="checkbox"]')) {
      const id = event.target.closest('.aikb-content-item').dataset.id;
      toggleInclude(id, event.target.checked).catch((error) => notice(error.message, 'error'));
    }

    if (event.target.matches('[data-save-settings]')) saveSettings(event.target);
    if (event.target.matches('[data-test-api]')) testApi(event.target);
    if (event.target.matches('[data-check-sync]')) checkSync(event.target);
    if (event.target.matches('.aikb-sync')) sync(event.target);
    if (event.target.matches('[data-copy-shortcode]')) {
      navigator.clipboard?.writeText(window.AIKB_ADMIN.shortcode);
      notice('Shortcode copied.');
    }
  });

  document.addEventListener('input', (event) => {
    if (event.target.matches('#aikb-content-search')) renderContentList();
  });

  document.addEventListener('submit', (event) => {
    if (event.target.matches('#aikb-metadata-form')) saveMetadata(event);
  });

  document.addEventListener('DOMContentLoaded', () => {
    loadContent().catch((error) => notice(error.message, 'error'));
  });
})();
