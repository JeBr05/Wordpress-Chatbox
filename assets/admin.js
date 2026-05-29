(() => {
  const state = {
    settings: window.JCB_ADMIN?.settings || {},
    items: [],
    activeItem: null,
    loadedPanels: new Set(['knowledge']),
  };

  const restUrl = window.JCB_ADMIN.restUrl;
  const nonce = window.JCB_ADMIN.nonce;
  const strings = window.JCB_ADMIN.adminStrings || {};
  const languages = window.JCB_ADMIN.languages || [
    { code: 'en', name: 'English', native: 'English' },
    { code: 'nl', name: 'Dutch', native: 'Nederlands' },
    { code: 'de', name: 'German', native: 'Deutsch' },
    { code: 'fr', name: 'French', native: 'Français' },
  ];

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const t = (key, fallback = '') => strings[key] || fallback || key;
  const sprintf = (key, fallback, ...values) => values.reduce((message, value) => message.replace('%s', String(value)), t(key, fallback));

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
    const box = $('#jcb-notices');
    if (!box) return;
    box.innerHTML = `<div class="jcb-notice ${type}">${escapeHtml(message)}</div>`;
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

  const setBusy = (button, busy, label = t('working', 'Working...')) => {
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
    const selectedNode = $('#jcb-selected-count');
    if (selectedNode) selectedNode.textContent = String(selected);

    const vectorNode = $('#jcb-vector-status');
    if (vectorNode) {
      const hasVector = Boolean(state.settings.vector_store_id);
      const status = state.settings.vector_store_status || 'not_connected';
      vectorNode.textContent = hasVector ? status : t('not_connected', 'Not connected');
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
    const list = $('#jcb-content-list');
    if (!list) return;
    const query = ($('#jcb-content-search')?.value || '').toLowerCase();
    const visible = state.items.filter((item) => item.title.toLowerCase().includes(query) || item.type.toLowerCase().includes(query));
    if (!visible.length) {
      list.innerHTML = `<div class="jcb-empty">${escapeHtml(t('no_content_found', 'No content found.'))}</div>`;
      return;
    }
    list.innerHTML = visible.map((item) => `
      <div class="jcb-content-item ${state.activeItem?.id === item.id ? 'is-active' : ''}" data-id="${item.id}">
        <input type="checkbox" ${item.included ? 'checked' : ''} aria-label="Select ${escapeHtml(item.title)}">
        <div>
          <div class="jcb-content-title">${escapeHtml(item.title)}</div>
          <div class="jcb-content-type">${escapeHtml(item.type)}</div>
        </div>
        <span class="jcb-content-type">PAGE</span>
      </div>
    `).join('');
  };

  const selectItem = (id) => {
    state.activeItem = state.items.find((item) => item.id === Number(id));
    const form = $('#jcb-metadata-form');
    const empty = $('#jcb-editor-empty');
    if (!state.activeItem || !form) return;
    empty.classList.add('jcb-hidden');
    form.classList.remove('jcb-hidden');
    $('#jcb-meta-id').value = state.activeItem.id;
    $('#jcb-meta-title').value = state.activeItem.title;
    $('#jcb-meta-summary').value = state.activeItem.metadata?.summary || '';
    $('#jcb-meta-tags').value = state.activeItem.metadata?.tags || '';
    $('#jcb-meta-priority').value = state.activeItem.metadata?.priority || 0;
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
    const id = $('#jcb-meta-id').value;
    const button = event.target.querySelector('button[type="submit"]');
    setBusy(button, true, t('saving', 'Saving...'));
    try {
      const data = await api(`/metadata/${id}`, {
        method: 'POST',
        body: JSON.stringify({
          summary: $('#jcb-meta-summary').value,
          tags: $('#jcb-meta-tags').value,
          priority: $('#jcb-meta-priority').value,
        }),
      });
      const index = state.items.findIndex((item) => item.id === Number(id));
      if (index >= 0) state.items[index] = data.item;
      state.activeItem = data.item;
      notice(t('metadata_saved', 'Metadata saved.'));
    } catch (error) {
      notice(error.message, 'error');
    } finally {
      setBusy(button, false);
    }
  };

  const field = (label, key, type = 'text', help = '') => `
    <label>${escapeHtml(label)}
      <input type="${escapeHtml(type)}" data-setting="${escapeHtml(key)}" value="${escapeHtml(state.settings[key] ?? '')}">
    </label>
    ${help ? `<p>${escapeHtml(help)}</p>` : ''}
  `;

  const checkbox = (label, key, help = '') => `
    <label class="jcb-check">
      <input type="checkbox" data-setting="${escapeHtml(key)}" ${state.settings[key] ? 'checked' : ''}> ${escapeHtml(label)}
    </label>
    ${help ? `<p>${escapeHtml(help)}</p>` : ''}
  `;

  const textarea = (label, key, rows = 4, help = '') => `
    <label>${escapeHtml(label)}
      <textarea data-setting="${escapeHtml(key)}" rows="${Number(rows)}">${escapeHtml(state.settings[key] ?? '')}</textarea>
    </label>
    ${help ? `<p>${escapeHtml(help)}</p>` : ''}
  `;

  const languageSelect = (help = '') => `
    <label>${escapeHtml(t('plugin_language', 'Plugin and admin language'))}
      <select data-setting="plugin_language">
        ${languages.map((language) => `<option value="${escapeHtml(language.code)}" ${state.settings.plugin_language === language.code ? 'selected' : ''}>${escapeHtml(language.native)} (${escapeHtml(language.name)})</option>`).join('')}
      </select>
    </label>
    ${help ? `<p>${escapeHtml(help)}</p>` : ''}
  `;

  const colorField = (label, key) => field(label, key, 'color');

  const saveButton = () => `<div class="jcb-form-actions"><button class="button button-primary" data-save-settings type="button">${escapeHtml(t('save_settings', 'Save settings'))}</button></div>`;

  const renderSettingsPanel = (panelName) => {
    const panel = $(`[data-panel="${panelName}"]`);
    if (!panel) return;

    if (panelName === 'chatbox') {
      panel.innerHTML = `
        <div class="jcb-grid jcb-grid-two">
          <section class="jcb-card">
            <h2>${escapeHtml(t('chatbox_setup', 'Chatbox setup'))}</h2>
            ${languageSelect(t('plugin_language_help', 'Controls the admin panel, the front end chatbox labels and tells the AI which language to answer in.'))}
            ${field(t('chatbox_name', 'Chatbox name'), 'assistant_name')}
            <label>${escapeHtml(t('model', 'Model'))}
              <select data-setting="model">
                ${['gpt-4.1-mini','gpt-4.1','gpt-4o-mini','gpt-4o','gpt-5-mini','gpt-5','gpt-5.1-mini','gpt-5.1','gpt-5.2-mini','gpt-5.2'].map((model) => `<option ${state.settings.model === model ? 'selected' : ''}>${model}</option>`).join('')}
              </select>
            </label>
            ${textarea(t('instructions', 'Instructions'), 'instructions', 10)}
            ${field(t('max_answer_tokens', 'Maximum answer tokens'), 'max_output_tokens', 'number')}
            ${saveButton()}
          </section>
          <section class="jcb-card">
            <h2>${escapeHtml(t('answer_behaviour', 'Answer behaviour'))}</h2>
            <p>${escapeHtml(t('answer_behaviour_p1', "Jeroen's Chatbox uses your selected pages first."))}</p>
            <p>${escapeHtml(t('answer_behaviour_p2', 'Best use cases are support, opening hours, product details, booking questions and content guidance.'))}</p>
          </section>
        </div>`;
    }

    if (panelName === 'tools') {
      panel.innerHTML = `
        <div class="jcb-grid jcb-grid-two">
          <section class="jcb-card">
            <h2>${escapeHtml(t('retrieval_tools', 'Retrieval tools'))}</h2>
            ${checkbox(t('enable_file_search', 'Enable file search'), 'enable_file_search', t('enable_file_search_help', 'Use the OpenAI vector store as the chatbox knowledge base.'))}
            ${checkbox(t('include_sources', 'Include source search results in API response'), 'include_sources')}
            ${field(t('max_file_results', 'Maximum file search results'), 'max_file_results', 'number')}
            ${checkbox(t('remember_context', 'Remember short session context'), 'session_context_enabled')}
            ${field(t('history_messages', 'History messages per chat'), 'max_history_messages', 'number')}
            ${field(t('session_lifetime', 'Session memory lifetime in minutes'), 'session_ttl_minutes', 'number')}
            ${saveButton()}
          </section>
          <section class="jcb-card">
            <h2>${escapeHtml(t('available_tools', 'Available tools'))}</h2>
            <table class="jcb-table"><tbody>
              <tr><th>${escapeHtml(t('file_search', 'File search'))}</th><td>${escapeHtml(t('file_search_desc', 'Connected to selected WordPress pages.'))}</td></tr>
              <tr><th>${escapeHtml(t('feedback', 'Feedback'))}</th><td>${escapeHtml(t('feedback_desc', 'Stores thumbs up and down events.'))}</td></tr>
              <tr><th>${escapeHtml(t('analytics', 'Analytics'))}</th><td>${escapeHtml(t('analytics_desc', 'Stores usage metrics when logging is enabled.'))}</td></tr>
            </tbody></table>
          </section>
        </div>`;
    }

    if (panelName === 'channels') {
      panel.innerHTML = `
        <div class="jcb-grid jcb-grid-two">
          <section class="jcb-card">
            <h2>${escapeHtml(t('website_visibility', 'Website visibility'))}</h2>
            ${checkbox(t('frontend_enabled', "Enable Jeroen's Chatbox on the front end"), 'frontend_enabled', t('frontend_enabled_help', 'Turn this off to hide the chatbox everywhere.'))}
            ${checkbox(t('auto_embed', 'Auto embed a floating chatbox on public pages'), 'auto_embed', t('auto_embed_help', 'Turn this on if you want the chatbox visible without placing a shortcode.'))}
            ${checkbox(t('start_open', 'Open the chatbox by default'), 'start_open')}
            ${checkbox(t('show_on_mobile', 'Show on mobile'), 'show_on_mobile')}
            ${field(t('launcher_button_text', 'Launcher button text'), 'launcher_label')}
            ${field(t('stacking_order', 'Stacking order'), 'z_index', 'number', t('stacking_order_help', 'Raise this if another plugin or theme element covers the chatbox.'))}
            ${saveButton()}
          </section>
          <section class="jcb-card">
            <h2>${escapeHtml(t('where_to_show', 'Where to show it'))}</h2>
            ${checkbox(t('show_home', 'Show on the home page'), 'show_on_home')}
            ${checkbox(t('show_pages', 'Show on pages'), 'show_on_pages')}
            ${checkbox(t('show_posts', 'Show on posts'), 'show_on_posts')}
            ${checkbox(t('show_archives', 'Show on archive pages'), 'show_on_archives')}
            ${field(t('exclude_page_ids', 'Exclude page IDs'), 'excluded_page_ids', 'text', t('exclude_page_ids_help', 'Use commas. Example: 12, 48, 95.'))}
            ${textarea(t('exclude_url_paths', 'Exclude URL paths'), 'excluded_url_paths', 5, t('exclude_url_paths_help', 'One path per line. Example: /checkout or /privacy-policy.'))}
            ${saveButton()}
          </section>
          <section class="jcb-card">
            <h2>${escapeHtml(t('shortcode', 'Shortcode'))}</h2>
            <label>${escapeHtml(t('use_shortcode', 'Use this shortcode'))}
              <div class="jcb-copybox"><input type="text" readonly value="${escapeHtml(window.JCB_ADMIN.shortcode)}"><button class="button" data-copy-shortcode type="button">${escapeHtml(t('copy', 'Copy'))}</button></div>
            </label>
            <p>${escapeHtml(t('shortcode_help', 'If auto embed is off, place this shortcode on the page where you want the chatbox.'))}</p>
          </section>
          <section class="jcb-card">
            <h2>${escapeHtml(t('why_not_show', 'Why it may not show'))}</h2>
            <p>${escapeHtml(t('why_not_show_p1', 'Check that front end is enabled. Then either enable auto embed or place the shortcode on a page.'))}</p>
            <p>${escapeHtml(t('why_not_show_p2', 'If it is still hidden, check the page type settings, excluded IDs, excluded paths and mobile setting.'))}</p>
          </section>
        </div>`;
    }

    if (panelName === 'design') {
      panel.innerHTML = `
        <div class="jcb-grid jcb-grid-two">
          <section class="jcb-card">
            <h2>${escapeHtml(t('chat_design', 'Chat design'))}</h2>
            ${field(t('welcome_message_label', 'Welcome message'), 'welcome_message')}
            ${field(t('input_placeholder', 'Input placeholder'), 'placeholder')}
            ${colorField(t('accent_color', 'Accent color'), 'accent_color')}
            ${colorField(t('font_color', 'Font colour'), 'font_color')}
            ${colorField(t('background_color', 'Background colour'), 'background_color')}
            ${colorField(t('user_bubble_color', 'User bubble colour'), 'user_bubble_color')}
            ${colorField(t('user_bubble_text_color', 'User bubble text colour'), 'user_bubble_text_color')}
            ${colorField(t('assistant_bubble_color', 'Assistant bubble colour'), 'assistant_bubble_color')}
            ${colorField(t('assistant_bubble_text_color', 'Assistant bubble text colour'), 'assistant_bubble_text_color')}
            <label>${escapeHtml(t('bubble_style', 'Chat bubble style'))}
              <select data-setting="bubble_style">
                <option value="soft" ${state.settings.bubble_style === 'soft' ? 'selected' : ''}>${escapeHtml(t('bubble_style_soft', 'Soft rounded'))}</option>
                <option value="round" ${state.settings.bubble_style === 'round' ? 'selected' : ''}>${escapeHtml(t('bubble_style_round', 'Round'))}</option>
                <option value="square" ${state.settings.bubble_style === 'square' ? 'selected' : ''}>${escapeHtml(t('bubble_style_square', 'Square'))}</option>
              </select>
            </label>
            <label>${escapeHtml(t('launcher_position', 'Launcher position'))}
              <select data-setting="launcher_position">
                <option value="right" ${state.settings.launcher_position === 'right' ? 'selected' : ''}>${escapeHtml(t('right', 'Right'))}</option>
                <option value="left" ${state.settings.launcher_position === 'left' ? 'selected' : ''}>${escapeHtml(t('left', 'Left'))}</option>
              </select>
            </label>
            ${saveButton()}
          </section>
          <section class="jcb-card">
            <h2>${escapeHtml(t('preview', 'Preview'))}</h2>
            <div class="jcb-preview" data-bubble-style="${escapeHtml(state.settings.bubble_style || 'soft')}" style="--jcb-preview-accent:${escapeHtml(state.settings.accent_color || '#6f5bd6')};--jcb-preview-font:${escapeHtml(state.settings.font_color || '#111827')};--jcb-preview-bg:${escapeHtml(state.settings.background_color || '#f8fafc')};--jcb-preview-user-bg:${escapeHtml(state.settings.user_bubble_color || state.settings.accent_color || '#6f5bd6')};--jcb-preview-user-text:${escapeHtml(state.settings.user_bubble_text_color || '#ffffff')};--jcb-preview-assistant-bg:${escapeHtml(state.settings.assistant_bubble_color || '#ffffff')};--jcb-preview-assistant-text:${escapeHtml(state.settings.assistant_bubble_text_color || '#111827')};">
              <div class="jcb-preview-title">${escapeHtml(state.settings.assistant_name || "Jeroen's Chatbox")}</div>
              <div class="jcb-preview-bubble assistant">${escapeHtml(state.settings.welcome_message || '')}</div>
              <div class="jcb-preview-bubble user">${escapeHtml(t('preview_user_message', 'I have a question.'))}</div>
              <button>${escapeHtml(state.settings.launcher_label || 'Chat')}</button>
            </div>
          </section>
        </div>`;
    }

    if (panelName === 'security') {
      panel.innerHTML = `
        <div class="jcb-grid jcb-grid-two">
          <section class="jcb-card">
            <h2>${escapeHtml(t('security_privacy', 'Security and privacy'))}</h2>
            ${field(t('rate_limit_minute', 'Rate limit per minute per IP'), 'rate_limit_per_minute', 'number')}
            ${field(t('rate_limit_hour', 'Rate limit per hour per IP'), 'rate_limit_per_hour', 'number')}
            ${field(t('daily_token_budget', 'Daily token budget'), 'daily_token_budget', 'number', t('daily_token_budget_help', 'Set 0 to disable the daily budget cap.'))}
            ${checkbox(t('log_conversations', 'Log conversations'), 'log_conversations')}
            ${field(t('log_retention_days', 'Log retention days'), 'log_retention_days', 'number')}
            ${checkbox(t('redact_personal_data', 'Redact email addresses and phone numbers before logging'), 'redact_personal_data')}
            ${saveButton()}
          </section>
          <section class="jcb-card">
            <h2>${escapeHtml(t('recommended_defaults', 'Recommended defaults'))}</h2>
            <p>${escapeHtml(t('recommended_defaults_p', 'Keep rate limiting on. Keep redaction on. Only enable debug mode while testing.'))}</p>
          </section>
        </div>`;
    }

    if (panelName === 'api') {
      panel.innerHTML = `
        <div class="jcb-grid jcb-grid-two">
          <section class="jcb-card">
            <h2>${escapeHtml(t('tab_api', 'OpenAI API'))}</h2>
            <p>${escapeHtml(t('api_key_saved', 'API key saved'))}: <strong>${state.settings.api_key_saved ? escapeHtml(t('yes', 'Yes')) : escapeHtml(t('no', 'No'))}</strong></p>
            ${field(t('openai_api_key', 'OpenAI API key'), 'api_key', 'password', t('openai_api_key_help', 'Leave empty to keep the saved key.'))}
            ${saveButton()}
            <div class="jcb-form-actions"><button class="button" data-test-api type="button">${escapeHtml(t('test_connection', 'Test connection'))}</button></div>
          </section>
          <section class="jcb-card">
            <h2>${escapeHtml(t('vector_store', 'Vector Store'))}</h2>
            <table class="jcb-table"><tbody>
              <tr><th>${escapeHtml(t('status', 'Status'))}</th><td>${escapeHtml(state.settings.vector_store_status || t('not_connected', 'not connected'))}</td></tr>
              <tr><th>${escapeHtml(t('vector_store_id', 'Vector store id'))}</th><td>${escapeHtml(state.settings.vector_store_id || t('none', 'None'))}</td></tr>
              <tr><th>${escapeHtml(t('last_sync', 'Last sync'))}</th><td>${escapeHtml(state.settings.last_sync_at || t('never', 'Never'))}</td></tr>
              <tr><th>${escapeHtml(t('last_file_count', 'Last file count'))}</th><td>${escapeHtml(state.settings.last_file_count || 0)}</td></tr>
              <tr><th>${escapeHtml(t('last_file_ids', 'Last file ids'))}</th><td>${escapeHtml(state.settings.last_file_id || t('none', 'None'))}</td></tr>
            </tbody></table>
            <div class="jcb-form-actions"><button class="button" data-check-sync type="button">${escapeHtml(t('check_sync_status', 'Check sync status'))}</button></div>
          </section>
        </div>`;
    }

    if (panelName === 'settings') {
      panel.innerHTML = `
        <div class="jcb-grid jcb-grid-two">
          <section class="jcb-card">
            <h2>${escapeHtml(t('language', 'Language'))}</h2>
            ${languageSelect(t('language_help_simple', 'Available languages are English, Dutch, German and French.'))}
            <p>${escapeHtml(t('language_explanation', 'The selected language changes the admin panel, chatbox interface text and adds an answer language rule to the AI instructions.'))}</p>
            ${saveButton()}
          </section>
          <section class="jcb-card">
            <h2>${escapeHtml(t('advanced_settings', 'Advanced settings'))}</h2>
            ${checkbox(t('debug_mode', 'Debug mode'), 'debug_mode')}
            ${checkbox(t('replace_vector_store', 'Replace old vector store on every sync'), 'replace_vector_store')}
            ${checkbox(t('delete_data_on_uninstall', 'Delete plugin data on uninstall'), 'delete_data_on_uninstall')}
            ${saveButton()}
          </section>
          <section class="jcb-card">
            <h2>${escapeHtml(t('developer_notes', 'Developer notes'))}</h2>
            <p>${escapeHtml(t('rest_namespace', 'REST namespace'))}: ${escapeHtml(restUrl)}</p>
            <p>${escapeHtml(t('plugin_version', 'Plugin version'))}: ${escapeHtml(window.JCB_ADMIN.settings?.version || '0.5.0')}</p>
          </section>
        </div>`;
    }
  };

  const saveSettings = async (button) => {
    const panel = button.closest('.jcb-panel');
    const payload = {};
    const previousLanguage = state.settings.plugin_language;
    $$('[data-setting]', panel).forEach((input) => {
      const key = input.dataset.setting;
      if (input.type === 'checkbox') payload[key] = input.checked;
      else payload[key] = input.value;
    });
    setBusy(button, true, t('saving', 'Saving...'));
    try {
      state.settings = await api('/settings', { method: 'POST', body: JSON.stringify(payload) });
      updateStatus();
      const languageChanged = payload.plugin_language && payload.plugin_language !== previousLanguage;
      notice(languageChanged ? t('settings_saved_reload', 'Settings saved. Reloading the admin panel in the selected language.') : t('settings_saved', 'Settings saved.'));
      if (languageChanged) {
        window.setTimeout(() => window.location.reload(), 700);
        return;
      }
      state.loadedPanels.delete(panel.dataset.panel);
      renderSettingsPanel(panel.dataset.panel);
    } catch (error) {
      notice(error.message, 'error');
    } finally {
      setBusy(button, false);
    }
  };

  const loadAnalytics = async () => {
    const panel = $('[data-panel="analytics"]');
    if (!panel) return;
    panel.innerHTML = `<section class="jcb-card"><h2>${escapeHtml(t('analytics', 'Analytics'))}</h2><p>${escapeHtml(t('loading', 'Loading'))}...</p></section>`;
    try {
      const data = await api('/analytics');
      panel.innerHTML = `
        <section class="jcb-card">
          <h2>${escapeHtml(t('analytics', 'Analytics'))}</h2>
          <div class="jcb-stat-grid">
            <div class="jcb-stat">${escapeHtml(t('conversations', 'Conversations'))}<strong>${data.total_conversations}</strong></div>
            <div class="jcb-stat">${escapeHtml(t('messages', 'Messages'))}<strong>${data.total_messages}</strong></div>
            <div class="jcb-stat">${escapeHtml(t('messages_last_7_days', 'Messages last 7 days'))}<strong>${data.recent_messages}</strong></div>
            <div class="jcb-stat">${escapeHtml(t('tokens_last_7_days', 'Tokens last 7 days'))}<strong>${data.tokens_7_days || 0}</strong></div>
            <div class="jcb-stat">${escapeHtml(t('avg_latency_ms', 'Avg latency ms'))}<strong>${data.avg_latency_ms || 0}</strong></div>
          </div>
          <h3>${escapeHtml(t('recent_messages', 'Recent messages'))}</h3>
          <table class="jcb-table"><thead><tr><th>${escapeHtml(t('time', 'Time'))}</th><th>${escapeHtml(t('role', 'Role'))}</th><th>${escapeHtml(t('message', 'Message'))}</th></tr></thead><tbody>
            ${(data.recent || []).map((row) => `<tr><td>${escapeHtml(row.created_at)}</td><td>${escapeHtml(row.role)}</td><td>${escapeHtml(row.content)}</td></tr>`).join('') || `<tr><td colspan="3">${escapeHtml(t('no_messages_yet', 'No messages yet.'))}</td></tr>`}
          </tbody></table>
        </section>`;
    } catch (error) {
      panel.innerHTML = `<section class="jcb-card"><h2>${escapeHtml(t('analytics', 'Analytics'))}</h2><p>${escapeHtml(error.message)}</p></section>`;
    }
  };

  const sync = async (button) => {
    setBusy(button, true, t('syncing', 'Syncing...'));
    try {
      const data = await api('/sync', { method: 'POST', body: JSON.stringify({}) });
      state.settings = data.options || state.settings;
      updateStatus();
      notice(sprintf('sync_started', 'Knowledge base sync started. %s files sent to the vector store.', data.file_count || 0));
    } catch (error) {
      notice(error.message, 'error');
    } finally {
      setBusy(button, false);
    }
  };

  const checkSync = async (button) => {
    setBusy(button, true, t('checking', 'Checking...'));
    try {
      const data = await api('/sync-status');
      state.settings = data.options || state.settings;
      updateStatus();
      state.loadedPanels.delete('api');
      renderSettingsPanel('api');
      notice(sprintf('sync_status', 'Sync status: %s.', state.settings.vector_store_status || 'unknown'));
    } catch (error) {
      notice(error.message, 'error');
    } finally {
      setBusy(button, false);
    }
  };

  const testApi = async (button) => {
    setBusy(button, true, t('testing', 'Testing...'));
    try {
      const data = await api('/test-api', { method: 'POST', body: JSON.stringify({}) });
      notice(data.message || t('connection_works', 'Connection works.'));
    } catch (error) {
      notice(error.message, 'error');
    } finally {
      setBusy(button, false);
    }
  };

  document.addEventListener('click', (event) => {
    const tab = event.target.closest('.jcb-tab');
    if (tab) {
      $$('.jcb-tab').forEach((node) => node.classList.remove('is-active'));
      $$('.jcb-panel').forEach((node) => node.classList.remove('is-active'));
      tab.classList.add('is-active');
      const name = tab.dataset.tab;
      $(`[data-panel="${name}"]`)?.classList.add('is-active');
      if (name === 'analytics') loadAnalytics();
      else if (!state.loadedPanels.has(name)) {
        renderSettingsPanel(name);
        state.loadedPanels.add(name);
      }
    }

    const contentItem = event.target.closest('.jcb-content-item');
    if (contentItem && !event.target.matches('input[type="checkbox"]')) {
      selectItem(contentItem.dataset.id);
    }

    if (event.target.matches('.jcb-content-item input[type="checkbox"]')) {
      const id = event.target.closest('.jcb-content-item').dataset.id;
      toggleInclude(id, event.target.checked).catch((error) => notice(error.message, 'error'));
    }

    if (event.target.matches('[data-save-settings]')) saveSettings(event.target);
    if (event.target.matches('[data-test-api]')) testApi(event.target);
    if (event.target.matches('[data-check-sync]')) checkSync(event.target);
    if (event.target.matches('.jcb-sync')) sync(event.target);
    if (event.target.matches('[data-copy-shortcode]')) {
      navigator.clipboard?.writeText(window.JCB_ADMIN.shortcode);
      notice(t('shortcode_copied', 'Shortcode copied.'));
    }
  });

  document.addEventListener('input', (event) => {
    if (event.target.matches('#jcb-content-search')) renderContentList();
  });

  document.addEventListener('submit', (event) => {
    if (event.target.matches('#jcb-metadata-form')) saveMetadata(event);
  });

  document.addEventListener('DOMContentLoaded', () => {
    loadContent().catch((error) => notice(error.message, 'error'));
  });
})();
