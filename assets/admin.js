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
  const users = window.JCB_ADMIN.users || [];
  const presets = window.JCB_ADMIN.presets || [];
  const categorySuggestions = window.JCB_ADMIN.categories || [];
  const securityStats = window.JCB_ADMIN.securityStats || { total_flagged: 0, last_24_hours: 0, last_7_days: 0 };
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


  const designThemes = () => [
    {
      id: 'purple',
      label: t('theme_purple', 'Classic purple'),
      description: t('theme_purple_desc', 'Bright, simple and close to the default look.'),
      colors: {
        accent_color: '#6f5bd6',
        font_color: '#111827',
        background_color: '#f8fafc',
        user_bubble_color: '#6f5bd6',
        user_bubble_text_color: '#ffffff',
        assistant_bubble_color: '#ffffff',
        assistant_bubble_text_color: '#111827',
      },
      bubble_style: 'soft',
    },
    {
      id: 'ocean',
      label: t('theme_ocean', 'Ocean blue'),
      description: t('theme_ocean_desc', 'Clean blue colours for service and support sites.'),
      colors: {
        accent_color: '#2563eb',
        font_color: '#0f172a',
        background_color: '#eff6ff',
        user_bubble_color: '#2563eb',
        user_bubble_text_color: '#ffffff',
        assistant_bubble_color: '#ffffff',
        assistant_bubble_text_color: '#0f172a',
      },
      bubble_style: 'round',
    },
    {
      id: 'forest',
      label: t('theme_forest', 'Forest green'),
      description: t('theme_forest_desc', 'Calm green colours for natural or sustainable brands.'),
      colors: {
        accent_color: '#047857',
        font_color: '#0f172a',
        background_color: '#ecfdf5',
        user_bubble_color: '#047857',
        user_bubble_text_color: '#ffffff',
        assistant_bubble_color: '#ffffff',
        assistant_bubble_text_color: '#0f172a',
      },
      bubble_style: 'soft',
    },
    {
      id: 'midnight',
      label: t('theme_midnight', 'Midnight dark'),
      description: t('theme_midnight_desc', 'Dark interface with clear contrast.'),
      colors: {
        accent_color: '#8b5cf6',
        font_color: '#f8fafc',
        background_color: '#0f172a',
        user_bubble_color: '#8b5cf6',
        user_bubble_text_color: '#ffffff',
        assistant_bubble_color: '#1e293b',
        assistant_bubble_text_color: '#f8fafc',
      },
      bubble_style: 'soft',
    },
    {
      id: 'sand',
      label: t('theme_sand', 'Warm sand'),
      description: t('theme_sand_desc', 'Warm colours for a softer website style.'),
      colors: {
        accent_color: '#b45309',
        font_color: '#1f2937',
        background_color: '#fff7ed',
        user_bubble_color: '#b45309',
        user_bubble_text_color: '#ffffff',
        assistant_bubble_color: '#ffffff',
        assistant_bubble_text_color: '#1f2937',
      },
      bubble_style: 'soft',
    },
  ];

  const designColorKeys = [
    'accent_color',
    'font_color',
    'background_color',
    'user_bubble_color',
    'user_bubble_text_color',
    'assistant_bubble_color',
    'assistant_bubble_text_color',
  ];

  const isHexColor = (value) => /^#[0-9a-fA-F]{6}$/.test(String(value || '').trim());

  const normalizeHexColor = (value, fallback = '#000000') => {
    const color = String(value || '').trim();
    return isHexColor(color) ? color.toLowerCase() : fallback;
  };

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

  const populateCategorySuggestions = () => {
    const list = $('#jcb-category-suggestions');
    if (!list || list.dataset.filled) return;
    list.innerHTML = categorySuggestions.map((name) => `<option value="${escapeHtml(name)}"></option>`).join('');
    list.dataset.filled = '1';
  };

  const applySuggestionToEditor = (item) => {
    if (!state.activeItem || state.activeItem.id !== item.id) return;
    const derived = item.derived || {};
    const summaryNode = $('#jcb-meta-summary');
    const badge = $('#jcb-meta-autofilled');
    const suggestion = derived.suggested_summary || '';
    const storedSummary = item.metadata?.summary || '';
    if (summaryNode && !storedSummary && !summaryNode.value && suggestion) {
      summaryNode.value = suggestion;
      badge?.classList.remove('jcb-hidden');
    }
    const wordCount = $('#jcb-meta-wordcount');
    if (wordCount) {
      const count = Number(derived.word_count || 0);
      wordCount.textContent = count ? sprintf('word_count', '%s words on this page', count) : '';
    }
  };

  const loadSuggestion = async (item) => {
    if (item.derived) {
      applySuggestionToEditor(item);
      return;
    }
    try {
      const data = await api(`/content/${item.id}/suggestion`);
      item.derived = data || {};
      applySuggestionToEditor(item);
    } catch (error) {
      // A failed suggestion is non-critical; leave the field as-is.
    }
  };

  const selectItem = (id) => {
    state.activeItem = state.items.find((item) => item.id === Number(id));
    const form = $('#jcb-metadata-form');
    const empty = $('#jcb-editor-empty');
    if (!state.activeItem || !form) return;
    const item = state.activeItem;
    const meta = item.metadata || {};
    empty.classList.add('jcb-hidden');
    form.classList.remove('jcb-hidden');
    populateCategorySuggestions();

    $('#jcb-meta-id').value = item.id;
    $('#jcb-meta-title').value = item.title;

    const urlNode = $('#jcb-meta-url');
    if (urlNode) {
      urlNode.textContent = item.url || '';
      urlNode.href = item.url || '#';
    }

    const summaryNode = $('#jcb-meta-summary');
    const badge = $('#jcb-meta-autofilled');
    if (summaryNode) summaryNode.value = meta.summary || '';
    badge?.classList.add('jcb-hidden');

    const wordCount = $('#jcb-meta-wordcount');
    if (wordCount) wordCount.textContent = '';

    const autoSummary = $('#jcb-meta-auto-summary');
    if (autoSummary) autoSummary.checked = meta.auto_summary !== false;

    const category = $('#jcb-meta-category');
    if (category) category.value = meta.category || '';

    $('#jcb-meta-tags').value = meta.tags || '';
    $('#jcb-meta-priority').value = meta.priority || 0;
    renderContentList();

    // Lazily load the summary suggestion + word count for this page only.
    loadSuggestion(item);
  };

  const autofillSummary = async () => {
    if (!state.activeItem) return;
    const item = state.activeItem;
    const summaryNode = $('#jcb-meta-summary');
    if (!summaryNode) return;
    let suggestion = item.derived?.suggested_summary || '';
    if (!item.derived) {
      try {
        const data = await api(`/content/${item.id}/suggestion`);
        item.derived = data || {};
        suggestion = item.derived.suggested_summary || '';
      } catch (error) {
        suggestion = '';
      }
    }
    if (!suggestion) {
      notice(t('autofill_none', 'No meta description or content was found to build a summary.'), 'error');
      return;
    }
    summaryNode.value = suggestion;
    $('#jcb-meta-autofilled')?.classList.remove('jcb-hidden');
    notice(t('autofill_done', 'Summary filled from the page. Review it and save.'));
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
          category: $('#jcb-meta-category')?.value || '',
          auto_summary: $('#jcb-meta-auto-summary')?.checked ? 1 : 0,
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

  const selectedVisibilityUsers = () => String(state.settings.visibility_user_ids || '')
    .split(/[\s,]+/)
    .map((id) => Number(id))
    .filter((id) => id > 0);

  const visibilityUserPicker = () => {
    const selected = selectedVisibilityUsers();
    return `
      <div class="jcb-user-picker">
        ${users.length ? users.map((user) => {
          const label = user.label || user.login || `User ${user.id}`;
          const meta = [user.login, user.email].filter(Boolean).join(' · ');
          return `
            <label class="jcb-user-option">
              <input type="checkbox" data-visibility-user value="${escapeHtml(user.id)}" ${selected.includes(Number(user.id)) ? 'checked' : ''}>
              <span>
                <strong>${escapeHtml(label)}</strong>
                <small>${escapeHtml(meta)}</small>
              </span>
            </label>
          `;
        }).join('') : `<p>${escapeHtml(t('no_users_found', 'No WordPress users found.'))}</p>`}
      </div>
      <input type="hidden" data-setting="visibility_user_ids" value="${escapeHtml(state.settings.visibility_user_ids || '')}">
    `;
  };

  const syncVisibilityUsers = (panel) => {
    const input = $('[data-setting="visibility_user_ids"]', panel);
    if (!input) return;
    const ids = $$('[data-visibility-user]:checked', panel).map((box) => box.value);
    input.value = ids.join(',');
  };

  const colorField = (label, key) => {
    const value = normalizeHexColor(state.settings[key], '#000000');
    return `
      <label class="jcb-color-label">${escapeHtml(label)}
        <div class="jcb-color-control" data-color-control="${escapeHtml(key)}">
          <span class="jcb-color-swatch" data-color-swatch="${escapeHtml(key)}" style="background:${escapeHtml(value)}"></span>
          <input class="jcb-color-picker" type="color" data-setting="${escapeHtml(key)}" data-design-live="1" value="${escapeHtml(value)}" aria-label="${escapeHtml(label)}">
          <input class="jcb-color-hex" type="text" data-color-hex="${escapeHtml(key)}" data-design-live="1" value="${escapeHtml(value)}" aria-label="${escapeHtml(t('hex_value', 'Hex value'))}">
        </div>
      </label>
    `;
  };

  const designThemeCards = () => `
    <div class="jcb-theme-grid">
      ${designThemes().map((theme) => {
        const active = (state.settings.design_theme || 'custom') === theme.id;
        return `
          <button class="jcb-theme-card ${active ? 'is-active' : ''}" type="button" data-design-theme="${escapeHtml(theme.id)}">
            <span class="jcb-theme-title">${escapeHtml(theme.label)}</span>
            <span class="jcb-theme-swatches">
              ${Object.values(theme.colors).slice(0, 5).map((color) => `<span style="background:${escapeHtml(color)}"></span>`).join('')}
            </span>
            <span class="jcb-theme-description">${escapeHtml(theme.description)}</span>
          </button>
        `;
      }).join('')}
    </div>
  `;

  const saveButton = () => `<div class="jcb-form-actions"><button class="button button-primary" data-save-settings type="button">${escapeHtml(t('save_settings', 'Save settings'))}</button></div>`;

  const switchField = (label, key, help = '') => `
    <div class="jcb-setting-row">
      <div>
        <strong>${escapeHtml(label)}</strong>
        ${help ? `<p>${escapeHtml(help)}</p>` : ''}
      </div>
      <label class="jcb-switch" aria-label="${escapeHtml(label)}">
        <input type="checkbox" data-setting="${escapeHtml(key)}" ${state.settings[key] ? 'checked' : ''}>
        <span></span>
      </label>
    </div>
  `;

  const securitySelect = (label, key, options) => `
    <label>${escapeHtml(label)}
      <select data-setting="${escapeHtml(key)}">
        ${options.map((option) => `<option value="${escapeHtml(option.value)}" ${String(state.settings[key]) === String(option.value) ? 'selected' : ''}>${escapeHtml(option.label)}</option>`).join('')}
      </select>
    </label>
  `;

  const severitySelect = (key) => securitySelect(t('severity', 'Severity'), key, [
    { value: 1, label: t('severity_low', 'Low 1 point') },
    { value: 3, label: t('severity_medium', 'Medium 3 points') },
    { value: 5, label: t('severity_high', 'High 5 points') },
    { value: 10, label: t('severity_critical', 'Critical 10 points') },
  ]);

  const securityInfo = (text) => `<div class="jcb-security-info">${escapeHtml(text)}</div>`;

  const securityStatCards = () => `
    <div class="jcb-security-stats">
      <div class="jcb-security-stat is-active"><span>${escapeHtml(t('security_status', 'Security status'))}</span><strong>${state.settings.security_enabled ? escapeHtml(t('active', 'Active')) : escapeHtml(t('inactive', 'Inactive'))}</strong></div>
      <div class="jcb-security-stat"><span>${escapeHtml(t('total_flagged', 'Total flagged'))}</span><strong>${escapeHtml(securityStats.total_flagged || 0)}</strong></div>
      <div class="jcb-security-stat"><span>${escapeHtml(t('last_24_hours', 'Last 24 hours'))}</span><strong>${escapeHtml(securityStats.last_24_hours || 0)}</strong></div>
      <div class="jcb-security-stat"><span>${escapeHtml(t('last_7_days', 'Last 7 days'))}</span><strong>${escapeHtml(securityStats.last_7_days || 0)}</strong></div>
    </div>
  `;

  const securityScoreCards = () => `
    <div class="jcb-score-grid">
      <div><strong>1 ${escapeHtml(t('point_short', 'pt'))}</strong><span>${escapeHtml(t('low', 'Low'))}</span><small>${escapeHtml(t('minor_anomaly', 'Minor anomaly'))}</small></div>
      <div><strong>3 ${escapeHtml(t('points_short', 'pts'))}</strong><span>${escapeHtml(t('medium', 'Medium'))}</span><small>${escapeHtml(t('suspicious_pattern', 'Suspicious pattern'))}</small></div>
      <div><strong>5 ${escapeHtml(t('points_short', 'pts'))}</strong><span>${escapeHtml(t('high', 'High'))}</span><small>${escapeHtml(t('likely_malicious', 'Likely malicious'))}</small></div>
      <div><strong>10 ${escapeHtml(t('points_short', 'pts'))}</strong><span>${escapeHtml(t('critical', 'Critical'))}</span><small>${escapeHtml(t('clear_attack', 'Clear attack'))}</small></div>
    </div>
  `;

  const securityRuleCard = (title, description, enabledKey, severityKey, body) => `
    <section class="jcb-security-rule">
      <div class="jcb-security-rule-head">
        <label class="jcb-rule-enable">
          <input type="checkbox" data-setting="${escapeHtml(enabledKey)}" ${state.settings[enabledKey] ? 'checked' : ''}>
          <strong>${escapeHtml(title)}</strong>
        </label>
        <div class="jcb-rule-severity">${severitySelect(severityKey)}</div>
      </div>
      <p>${escapeHtml(description)}</p>
      ${body}
    </section>
  `;

  const securityTestResult = (data) => {
    const flags = data.flags || [];
    return `
      <div class="jcb-test-result ${escapeHtml(data.action || 'allowed')}">
        <strong>${escapeHtml(t('test_result', 'Test result'))}: ${escapeHtml(data.action || 'allowed')}</strong>
        <p>${escapeHtml(data.message || '')}</p>
        <p>${escapeHtml(t('score', 'Score'))}: ${escapeHtml(data.score || 0)}</p>
        ${flags.length ? `<ul>${flags.map((flag) => `<li>${escapeHtml(flag.label || flag.name)}: ${escapeHtml(flag.severity || 0)} ${escapeHtml(t('points_short', 'pts'))}</li>`).join('')}</ul>` : `<p>${escapeHtml(t('no_flags', 'No flags.'))}</p>`}
      </div>
    `;
  };


  const presetDescription = (id) => {
    if (!id || id === 'custom') return t('preset_custom_help', 'Keeps your current instructions. Choose a preset to replace them.');
    const preset = presets.find((item) => item.id === id);
    return preset ? preset.description : '';
  };

  const applyPreset = (panel) => {
    const select = $('[data-preset-select]', panel);
    const textareaNode = $('[data-setting="instructions"]', panel);
    const id = select?.value || 'custom';
    if (id === 'custom') {
      notice(t('preset_custom_help', 'Keeps your current instructions. Choose a preset to replace them.'));
      return;
    }
    const preset = presets.find((item) => item.id === id);
    if (!preset || !textareaNode) return;
    textareaNode.value = preset.instructions;
    notice(t('preset_applied', 'Preset applied. Review the instructions and save.'));
  };

  const avatarShapeSelect = () => securitySelect(t('avatar_shape', 'Avatar shape'), 'avatar_shape', [
    { value: 'circle', label: t('shape_circle', 'Circle') },
    { value: 'rounded', label: t('shape_rounded', 'Rounded square') },
    { value: 'squircle', label: t('shape_squircle', 'Squircle') },
    { value: 'speech', label: t('shape_speech', 'Speech bubble') },
  ]);

  const avatarSizeSelect = () => securitySelect(t('avatar_size', 'Avatar size in chat'), 'avatar_size', [
    { value: 'small', label: t('size_small', 'Small') },
    { value: 'medium', label: t('size_medium', 'Medium') },
    { value: 'large', label: t('size_large', 'Large') },
  ]);

  const launcherStyleSelect = () => securitySelect(t('launcher_style', 'Launcher style'), 'launcher_style', [
    { value: 'label', label: t('launcher_style_label', 'Text button') },
    { value: 'icon', label: t('launcher_style_icon', 'Round icon button') },
    { value: 'avatar', label: t('launcher_style_avatar', 'Logo / avatar button') },
  ]);

  const launcherIconSelect = () => securitySelect(t('launcher_icon', 'Launcher icon'), 'launcher_icon', [
    { value: 'chat', label: t('icon_chat', 'Chat bubble') },
    { value: 'question', label: t('icon_question', 'Question mark') },
    { value: 'sparkle', label: t('icon_sparkle', 'Sparkle') },
    { value: 'bot', label: t('icon_bot', 'Robot') },
  ]);

  const launcherSizeSelect = () => securitySelect(t('launcher_size', 'Launcher button size'), 'launcher_size', [
    { value: 'small', label: t('size_small', 'Small') },
    { value: 'medium', label: t('size_medium', 'Medium') },
    { value: 'large', label: t('size_large', 'Large') },
  ]);

  const avatarPicker = () => {
    const url = state.settings.avatar_url || '';
    return `
      <label>${escapeHtml(t('avatar_image', 'Profile picture'))}</label>
      <div class="jcb-avatar-control">
        <span class="jcb-avatar-thumb ${url ? '' : 'is-empty'}" data-avatar-thumb data-shape="${escapeHtml(state.settings.avatar_shape || 'circle')}">${url ? `<img src="${escapeHtml(url)}" alt="">` : escapeHtml(t('no_image', 'No image'))}</span>
        <span class="jcb-avatar-buttons">
          <button class="button" type="button" data-avatar-select>${escapeHtml(t('select_image', 'Select image'))}</button>
          <button class="button" type="button" data-avatar-remove ${url ? '' : 'disabled'}>${escapeHtml(t('remove', 'Remove'))}</button>
        </span>
        <input type="hidden" data-setting="avatar_url" data-design-live="1" value="${escapeHtml(url)}">
      </div>
      <p class="jcb-meta-hint">${escapeHtml(t('avatar_image_help', 'Shown in the chat header, on answers and optionally on the launcher button.'))}</p>
    `;
  };

  const openMediaPicker = (panel) => {
    if (!window.wp || !window.wp.media) {
      notice(t('media_unavailable', 'The WordPress media library is not available on this screen.'), 'error');
      return;
    }
    const frame = window.wp.media({
      title: t('select_avatar', 'Select a profile picture'),
      button: { text: t('use_image', 'Use this image') },
      library: { type: 'image' },
      multiple: false,
    });
    frame.on('select', () => {
      const attachment = frame.state().get('selection').first().toJSON();
      const url = attachment.sizes?.thumbnail?.url || attachment.url || '';
      const input = $('[data-setting="avatar_url"]', panel);
      const thumb = $('[data-avatar-thumb]', panel);
      const remove = $('[data-avatar-remove]', panel);
      if (input) input.value = url;
      if (thumb) {
        thumb.classList.remove('is-empty');
        thumb.innerHTML = `<img src="${escapeHtml(url)}" alt="">`;
      }
      if (remove) remove.disabled = false;
      renderDesignPreview(panel);
    });
    frame.open();
  };

  const removeAvatar = (panel) => {
    const input = $('[data-setting="avatar_url"]', panel);
    const thumb = $('[data-avatar-thumb]', panel);
    const remove = $('[data-avatar-remove]', panel);
    if (input) input.value = '';
    if (thumb) {
      thumb.classList.add('is-empty');
      thumb.textContent = t('no_image', 'No image');
    }
    if (remove) remove.disabled = true;
    renderDesignPreview(panel);
  };

  const renderSettingsPanel = (panelName) => {
    const panel = $(`[data-panel="${panelName}"]`);
    if (!panel) return;

    if (panelName === 'chatbox') {
      const presetOptions = [{ id: 'custom', label: t('preset_custom', 'Custom (keep my text)') }]
        .concat(presets.map((preset) => ({ id: preset.id, label: preset.label })));
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
            <div class="jcb-preset-row">
              <label>${escapeHtml(t('instruction_preset', 'Instruction preset'))}
                <select data-preset-select>
                  ${presetOptions.map((preset) => `<option value="${escapeHtml(preset.id)}" ${(state.settings.instruction_preset || 'custom') === preset.id ? 'selected' : ''}>${escapeHtml(preset.label)}</option>`).join('')}
                </select>
              </label>
              <button class="button" type="button" data-apply-preset>${escapeHtml(t('apply_preset', 'Apply preset'))}</button>
            </div>
            <p class="jcb-meta-hint" data-preset-description>${escapeHtml(presetDescription(state.settings.instruction_preset || 'custom'))}</p>
            ${textarea(t('instructions', 'Instructions'), 'instructions', 12)}
            ${field(t('max_answer_tokens', 'Maximum answer tokens'), 'max_output_tokens', 'number')}
            ${saveButton()}
          </section>
          <section class="jcb-card">
            <h2>${escapeHtml(t('answer_behaviour', 'Answer behaviour'))}</h2>
            <p>${escapeHtml(t('answer_behaviour_p1', "Jeroen's Chatbox uses your selected pages first."))}</p>
            <p>${escapeHtml(t('answer_behaviour_p2', 'Best use cases are support, opening hours, product details, booking questions and content guidance.'))}</p>
            <h3>${escapeHtml(t('contact_details', 'Contact details'))}</h3>
            <p class="jcb-meta-hint">${escapeHtml(t('contact_details_help', 'Presets use these so the chatbox can share how to reach you. Leave empty to skip.'))}</p>
            ${field(t('contact_email', 'Contact email'), 'contact_email', 'email')}
            ${field(t('contact_phone', 'Contact phone'), 'contact_phone')}
            ${field(t('contact_address', 'Contact address'), 'contact_address')}
            ${saveButton()}
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
            <label>${escapeHtml(t('who_can_see_chatbox', 'Who can see the chatbox'))}
              <select data-setting="visibility_mode">
                <option value="everyone" ${state.settings.visibility_mode === 'everyone' ? 'selected' : ''}>${escapeHtml(t('visibility_everyone', 'Everyone who visits the website'))}</option>
                <option value="logged_in" ${state.settings.visibility_mode === 'logged_in' ? 'selected' : ''}>${escapeHtml(t('visibility_logged_in', 'All logged in WordPress users'))}</option>
                <option value="admins" ${state.settings.visibility_mode === 'admins' ? 'selected' : ''}>${escapeHtml(t('visibility_admins', 'Only administrators'))}</option>
                <option value="selected_users" ${state.settings.visibility_mode === 'selected_users' ? 'selected' : ''}>${escapeHtml(t('visibility_selected_users', 'Only selected WordPress users'))}</option>
              </select>
            </label>
            <p>${escapeHtml(t('who_can_see_help', 'Use selected users or administrators only while testing. Switch to everyone when you want to publish it.'))}</p>
            <div class="jcb-selected-users-wrap ${state.settings.visibility_mode === 'selected_users' ? '' : 'jcb-hidden'}" data-selected-users-wrap>
              <h3>${escapeHtml(t('selected_test_users', 'Selected test users'))}</h3>
              <p>${escapeHtml(t('selected_test_users_help', 'These users must be logged in before they can see the chatbox.'))}</p>
              ${visibilityUserPicker()}
            </div>
            ${checkbox(t('start_open_desktop', 'Open the chatbox by default on desktop'), 'start_open_desktop')}
            ${checkbox(t('start_open_mobile', 'Open the chatbox by default on mobile'), 'start_open_mobile')}
            <p class="jcb-meta-hint">${escapeHtml(t('start_open_help', 'Devices narrower than 768px count as mobile. Auto-opening on mobile can feel intrusive on small screens.'))}</p>
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
        <div class="jcb-design-layout">
          <section class="jcb-card jcb-design-settings-card">
            <h2>${escapeHtml(t('chat_design', 'Chat design'))}</h2>
            ${field(t('welcome_message_label', 'Welcome message'), 'welcome_message')}
            ${field(t('input_placeholder', 'Input placeholder'), 'placeholder')}
            <input type="hidden" data-setting="design_theme" data-design-theme-value value="${escapeHtml(state.settings.design_theme || 'custom')}">
            ${colorField(t('accent_color', 'Accent color'), 'accent_color')}
            ${colorField(t('font_color', 'Font colour'), 'font_color')}
            ${colorField(t('background_color', 'Background colour'), 'background_color')}
            ${colorField(t('user_bubble_color', 'User bubble colour'), 'user_bubble_color')}
            ${colorField(t('user_bubble_text_color', 'User bubble text colour'), 'user_bubble_text_color')}
            ${colorField(t('assistant_bubble_color', 'Assistant bubble colour'), 'assistant_bubble_color')}
            ${colorField(t('assistant_bubble_text_color', 'Assistant bubble text colour'), 'assistant_bubble_text_color')}
            <label>${escapeHtml(t('bubble_style', 'Chat bubble style'))}
              <select data-setting="bubble_style" data-design-live="1">
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
            <h3>${escapeHtml(t('avatar_section', 'Profile picture and launcher'))}</h3>
            ${avatarPicker()}
            ${avatarShapeSelect()}
            ${avatarSizeSelect()}
            ${checkbox(t('show_avatar_in_header', 'Show avatar in the chat header'), 'show_avatar_in_header')}
            ${checkbox(t('show_avatar_on_messages', 'Show avatar next to answers'), 'show_avatar_on_messages')}
            ${launcherStyleSelect()}
            <p class="jcb-meta-hint">${escapeHtml(t('launcher_style_help', 'Choose "Logo / avatar button" to use the profile picture above as the round chat button.'))}</p>
            ${launcherIconSelect()}
            ${launcherSizeSelect()}
            <p class="jcb-meta-hint">${escapeHtml(t('launcher_size_help', 'Sets how big the round chat button in the corner is. Applies to the icon and logo styles.'))}</p>
            <h3>${escapeHtml(t('conversation_extras', 'Conversation extras'))}</h3>
            ${checkbox(t('enable_markdown', 'Render Markdown links and formatting in answers'), 'enable_markdown', t('enable_markdown_help', 'Turns [text](link), bold and lists into clickable, formatted output.'))}
            ${textarea(t('quick_replies', 'Quick reply suggestions'), 'quick_replies', 5, t('quick_replies_help', 'One per line. Shown as tappable chips under the welcome message. Up to 8.'))}
            ${saveButton()}
          </section>
          <aside class="jcb-design-side">
            <section class="jcb-card jcb-preview-card">
              <h2>${escapeHtml(t('preview', 'Preview'))}</h2>
              <p>${escapeHtml(t('preview_live_note', 'This preview updates while you edit. Your website changes only after saving.'))}</p>
              <div class="jcb-preview-shell">
                <div class="jcb-preview" data-design-preview data-bubble-style="${escapeHtml(state.settings.bubble_style || 'soft')}" style="--jcb-preview-accent:${escapeHtml(state.settings.accent_color || '#6f5bd6')};--jcb-preview-font:${escapeHtml(state.settings.font_color || '#111827')};--jcb-preview-bg:${escapeHtml(state.settings.background_color || '#f8fafc')};--jcb-preview-user-bg:${escapeHtml(state.settings.user_bubble_color || state.settings.accent_color || '#6f5bd6')};--jcb-preview-user-text:${escapeHtml(state.settings.user_bubble_text_color || '#ffffff')};--jcb-preview-assistant-bg:${escapeHtml(state.settings.assistant_bubble_color || '#ffffff')};--jcb-preview-assistant-text:${escapeHtml(state.settings.assistant_bubble_text_color || '#111827')};">
                  <div class="jcb-preview-window">
                    <div class="jcb-preview-header">
                      <span class="jcb-preview-avatar ${state.settings.avatar_url ? '' : 'jcb-hidden'}" data-preview-avatar data-shape="${escapeHtml(state.settings.avatar_shape || 'circle')}">${state.settings.avatar_url ? `<img src="${escapeHtml(state.settings.avatar_url)}" alt="">` : ''}</span>
                      <span>${escapeHtml(state.settings.assistant_name || "Jeroen's Chatbox")}</span>
                      <span class="jcb-preview-close">×</span>
                    </div>
                    <div class="jcb-preview-messages">
                      <div class="jcb-preview-bubble assistant" data-preview-welcome>${escapeHtml(state.settings.welcome_message || '')}</div>
                      <div class="jcb-preview-bubble user">${escapeHtml(t('preview_user_message', 'I have a question.'))}</div>
                    </div>
                    <div class="jcb-preview-form">
                      <span data-preview-placeholder>${escapeHtml(state.settings.placeholder || '')}</span>
                      <button type="button">${escapeHtml(t('send', 'Send'))}</button>
                    </div>
                  </div>
                  <button class="jcb-preview-launcher" type="button">${escapeHtml(state.settings.launcher_label || 'Chat')}</button>
                </div>
              </div>
            </section>
            <section class="jcb-card">
              <h2>${escapeHtml(t('design_presets', 'Preset design themes'))}</h2>
              <p>${escapeHtml(t('design_preset_help', 'Choose a theme to fill the colour settings. The website only changes after you save.'))}</p>
              ${designThemeCards()}
            </section>
          </aside>
        </div>`;
      renderDesignPreview(panel);
    }

    if (panelName === 'security') {
      panel.innerHTML = `
        <div class="jcb-security-layout">
          ${securityStatCards()}

          <section class="jcb-card jcb-security-card">
            <div class="jcb-card-heading"><span class="jcb-card-icon">◎</span><div><h2>${escapeHtml(t('security_system', 'Security system'))}</h2><p>${escapeHtml(t('security_system_desc', 'Master switch for all security protections.'))}</p></div></div>
            ${switchField(t('enable_security_system', 'Enable security system'), 'security_enabled', t('enable_security_system_help', 'When disabled, security checks are bypassed and messages go directly to the AI.'))}
          </section>

          <section class="jcb-card jcb-security-card">
            <div class="jcb-card-heading"><span class="jcb-card-icon cyan">◷</span><div><h2>${escapeHtml(t('rate_limiting', 'Rate limiting'))}</h2><p>${escapeHtml(t('rate_limiting_desc', 'Prevent spam by limiting how fast visitors can send messages.'))}</p></div></div>
            ${switchField(t('enable_rate_limiting', 'Enable rate limiting'), 'rate_limit_enabled', t('enable_rate_limiting_help', 'Limits messages by session token and IP address.'))}
            <div class="jcb-security-subgrid">
              <div>
                <h3>${escapeHtml(t('per_user_token', 'Per session token'))}</h3>
                ${field(t('max_messages', 'Max messages'), 'rate_limit_user_max', 'number', t('messages_per_window', 'Messages per window.'))}
                ${field(t('time_window', 'Time window'), 'rate_limit_user_window', 'number', t('seconds', 'Seconds.'))}
              </div>
              <div>
                <h3>${escapeHtml(t('per_ip_address', 'Per IP address'))}</h3>
                ${field(t('max_messages', 'Max messages'), 'rate_limit_ip_max', 'number', t('messages_per_window', 'Messages per window.'))}
                ${field(t('time_window', 'Time window'), 'rate_limit_ip_window', 'number', t('seconds', 'Seconds.'))}
              </div>
            </div>
            ${securityInfo(t('rate_limit_note', 'IP based limits are useful when visitors share a network. Session token limits are useful for normal visitors.'))}
            <div class="jcb-security-subgrid">
              ${field(t('cooldown_period', 'Cooldown period'), 'rate_limit_cooldown_seconds', 'number', t('cooldown_help', 'How long a visitor must wait after hitting the limit. Use 0 until the window expires.'))}
              ${textarea(t('rate_limit_message', 'Rate limit message'), 'rate_limit_message', 3, t('message_shown_rate_limited', 'Message shown to visitors who are rate limited.'))}
            </div>
          </section>

          <section class="jcb-card jcb-security-card">
            <div class="jcb-card-heading"><span class="jcb-card-icon purple">≡</span><div><h2>${escapeHtml(t('message_length', 'Message length'))}</h2><p>${escapeHtml(t('message_length_desc', 'Control the maximum length of user messages.'))}</p></div></div>
            ${switchField(t('enable_message_length_limit', 'Enable message length limit'), 'message_length_enabled', t('message_length_help', 'Reject very long prompts before they can use API tokens.'))}
            ${field(t('maximum_characters', 'Maximum characters'), 'message_max_chars', 'number', t('maximum_characters_help', 'Default is 2000 characters.'))}
            ${securityInfo(t('message_length_note', 'A normal visitor question is usually short. Lowering below 200 can block valid questions.'))}
            ${textarea(t('length_exceeded_message', 'Length exceeded message'), 'message_length_message', 3, t('length_exceeded_help', 'Use {limit} to insert the character limit.'))}
          </section>

          <section class="jcb-card jcb-security-card">
            <div class="jcb-card-heading"><span class="jcb-card-icon red">⊘</span><div><h2>${escapeHtml(t('blocked_words_phrases', 'Blocked words and phrases'))}</h2><p>${escapeHtml(t('blocked_words_desc', 'Filter messages containing specific words or phrases.'))}</p></div></div>
            ${switchField(t('enable_blocked_words_filter', 'Enable blocked words filter'), 'blocked_words_enabled', t('blocked_words_help', 'Add one word or phrase per line. You can use * as a wildcard.'))}
            ${switchField(t('use_default_word_list', 'Block common offensive words automatically'), 'blocked_words_use_default', t('use_default_word_list_help', 'Adds a built-in multilingual profanity list on top of your own words.'))}
            ${textarea(t('blocked_words_list', 'Blocked words list'), 'blocked_words_list', 8, t('blocked_words_list_help', 'One word or phrase per line. Matching is case insensitive.'))}
            ${securitySelect(t('action_when_blocked_word_found', 'Action when blocked word is found'), 'blocked_words_action', [
              { value: 'warn', label: t('warn_allow', 'Warn and allow') },
              { value: 'block', label: t('block_message', 'Block message') },
            ])}
            ${textarea(t('blocked_word_message', 'Blocked word message'), 'blocked_words_message', 3, t('blocked_word_message_help', 'Shown when a message is blocked or warned.'))}
          </section>

          <section class="jcb-card jcb-security-card">
            <div class="jcb-card-heading"><span class="jcb-card-icon orange">▤</span><div><h2>${escapeHtml(t('ip_blocklist', 'IP blocklist'))}</h2><p>${escapeHtml(t('ip_blocklist_desc', 'Block specific IP addresses from using the chat.'))}</p></div></div>
            ${switchField(t('enable_ip_blocklist', 'Enable IP blocklist'), 'ip_blocklist_enabled', t('ip_blocklist_help', 'Supports exact IPv4 and IPv4 CIDR ranges.'))}
            ${textarea(t('blocked_ip_addresses', 'Blocked IP addresses'), 'ip_blocklist', 7, t('blocked_ip_addresses_help', 'One IP address per line. Example: 192.168.1.100 or 10.0.0.0/24.'))}
            ${textarea(t('blocked_ip_message', 'Blocked IP message'), 'ip_block_message', 3, t('blocked_ip_message_help', 'Shown to visitors whose IP address is blocked.'))}
          </section>

          <section class="jcb-card jcb-security-card">
            <div class="jcb-card-heading"><span class="jcb-card-icon amber">⚑</span><div><h2>${escapeHtml(t('auto_flag_detection', 'Auto flag detection'))}</h2><p>${escapeHtml(t('auto_flag_desc', 'Detect suspicious conversations using pattern matching and scoring.'))}</p></div></div>
            ${switchField(t('enable_auto_flag_system', 'Enable auto flag system'), 'auto_flag_enabled', t('auto_flag_help', 'A message can trigger multiple checks. Each check adds points.'))}
            <h3>${escapeHtml(t('how_scoring_works', 'How scoring works'))}</h3>
            ${securityScoreCards()}
            ${securityInfo(t('scoring_note', 'When the total score reaches the threshold, the configured action is taken.'))}
            <div class="jcb-security-subgrid">
              ${field(t('flag_threshold', 'Flag threshold'), 'auto_flag_threshold', 'number', t('flag_threshold_help', 'Flag when total score reaches this value.'))}
              ${securitySelect(t('action_when_threshold_exceeded', 'Action when threshold is exceeded'), 'auto_flag_action', [
                { value: 'flag', label: t('flag_allow_review', 'Flag and allow') },
                { value: 'block', label: t('block_message', 'Block message') },
              ])}
            </div>
            ${textarea(t('auto_flag_block_message', 'Auto flag block message'), 'auto_flag_block_message', 3, t('auto_flag_block_help', 'Shown when the action is block.'))}
          </section>

          ${securityRuleCard(t('jailbreak_detection', 'Jailbreak detection'), t('jailbreak_detection_desc', 'Detect attempts to override instructions, extract system prompts or bypass rules.'), 'detect_jailbreak_enabled', 'jailbreak_severity', `${switchField(t('jailbreak_multilingual', 'Detect jailbreaks in any language'), 'jailbreak_multilingual_enabled', t('jailbreak_multilingual_help', 'Adds a built-in pattern bank for Dutch, German, French, Spanish, Italian and Portuguese on top of the English patterns below.'))}${textarea(t('jailbreak_patterns', 'Jailbreak patterns'), 'jailbreak_patterns', 9, t('pattern_help', 'One phrase per line. Wrap in / / for regex. Use * as wildcard.'))}`)}

          ${securityRuleCard(t('abuse_detection', 'Abuse detection'), t('abuse_detection_desc', 'Detect excessive special characters and code injection attempts.'), 'detect_abuse_enabled', 'abuse_severity', switchField(t('code_injection_check', 'Code injection check'), 'code_injection_enabled', t('code_injection_check_help', 'Checks for SQL injection, script tags and common eval or exec calls.')))}

          ${securityRuleCard(t('content_flags', 'Content flags'), t('content_flags_desc', 'Custom content patterns to flag. Useful for sensitive information or admin access requests.'), 'detect_content_enabled', 'content_severity', textarea(t('content_patterns', 'Content patterns'), 'content_patterns', 8, t('content_patterns_help', 'One phrase per line. Same syntax as jailbreak patterns.')))}

          ${securityRuleCard(t('behavioral_analysis', 'Behavioral analysis'), t('behavioral_analysis_desc', 'Detect rapid messages and repeated messages from the same session.'), 'detect_behavior_enabled', 'behavior_severity', `<div class="jcb-security-subgrid">${field(t('rapid_message_threshold', 'Rapid message threshold'), 'behavior_rapid_messages', 'number', t('messages', 'Messages'))}${field(t('time_window', 'Time window'), 'behavior_time_window', 'number', t('seconds', 'Seconds'))}${field(t('repeated_message_max', 'Repeated message max'), 'behavior_repeated_message_max', 'number', t('repeated_message_help', 'Same message sent this many times.'))}</div>`)}

          <section class="jcb-card jcb-security-card">
            <div class="jcb-card-heading"><span class="jcb-card-icon green">✓</span><div><h2>${escapeHtml(t('whitelist', 'Whitelist'))}</h2><p>${escapeHtml(t('whitelist_desc', 'Session tokens and IPs that bypass all security checks.'))}</p></div></div>
            ${securityInfo(t('whitelist_note', 'Use this for trusted testers or internal staff. Whitelisted visitors bypass rate limiting, blocked words and auto flag detection.'))}
            <div class="jcb-security-subgrid">
              ${textarea(t('whitelisted_user_tokens', 'Whitelisted session tokens'), 'whitelist_user_tokens', 5, t('whitelisted_user_tokens_help', 'One session token per line.'))}
              ${textarea(t('whitelisted_ip_addresses', 'Whitelisted IP addresses'), 'whitelist_ips', 5, t('whitelisted_ip_addresses_help', 'One IP address per line. CIDR ranges are supported for IPv4.'))}
            </div>
          </section>

          <section class="jcb-card jcb-security-card">
            <div class="jcb-card-heading"><span class="jcb-card-icon">‹›</span><div><h2>${escapeHtml(t('test_security_rules', 'Test security rules'))}</h2><p>${escapeHtml(t('test_security_rules_desc', 'Test a message against your current security configuration. Save settings first.'))}</p></div></div>
            <label>${escapeHtml(t('test_message', 'Test message'))}
              <textarea data-security-test-message rows="5" placeholder="${escapeHtml(t('test_message_placeholder', 'Type a test message here.'))}"></textarea>
            </label>
            <div class="jcb-form-actions"><button class="button" data-test-security type="button">${escapeHtml(t('run_test', 'Run test'))}</button></div>
            <div data-security-test-result></div>
          </section>

          ${saveButton()}
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
            <h2>${escapeHtml(t('cost_controls', 'Cost controls'))}</h2>
            ${field(t('daily_token_budget', 'Daily token budget'), 'daily_token_budget', 'number', t('daily_token_budget_help', 'Set 0 to disable the daily budget cap.'))}
            <p class="jcb-meta-hint">${escapeHtml(t('daily_token_budget_note', 'This is the combined input + output tokens the chatbox may use per day across all visitors. When reached, visitors see a friendly "try again later" message until midnight (UTC).'))}</p>
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
            <p>${escapeHtml(t('plugin_version', 'Plugin version'))}: ${escapeHtml(window.JCB_ADMIN.settings?.version || '0.8.0')}</p>
          </section>
        </div>`;
    }
  };

  const getDesignPanel = (root = document) => $('[data-panel="design"]', root);

  const markCustomTheme = (panel) => {
    const input = $('[data-design-theme-value]', panel);
    if (input) input.value = 'custom';
    $$('.jcb-theme-card', panel).forEach((card) => card.classList.remove('is-active'));
  };

  const syncColorControl = (input) => {
    const panel = getDesignPanel();
    if (!panel) return;
    const key = input.dataset.setting || input.dataset.colorHex;
    if (!key) return;
    const picker = $(`[data-setting="${key}"]`, panel);
    const hex = $(`[data-color-hex="${key}"]`, panel);
    const swatch = $(`[data-color-swatch="${key}"]`, panel);

    if (input.dataset.colorHex) {
      const value = String(input.value || '').trim();
      if (isHexColor(value) && picker) picker.value = value;
    }

    const current = normalizeHexColor(picker?.value || hex?.value, '#000000');
    if (hex && hex !== input) hex.value = current;
    if (swatch) swatch.style.background = current;
  };

  const designValuesFromPanel = (panel) => {
    const values = { ...state.settings };
    designColorKeys.forEach((key) => {
      values[key] = normalizeHexColor($(`[data-setting="${key}"]`, panel)?.value, state.settings[key] || '#000000');
    });
    values.bubble_style = $('[data-setting="bubble_style"]', panel)?.value || state.settings.bubble_style || 'soft';
    values.welcome_message = $('[data-setting="welcome_message"]', panel)?.value || state.settings.welcome_message || '';
    values.placeholder = $('[data-setting="placeholder"]', panel)?.value || state.settings.placeholder || '';
    values.avatar_url = $('[data-setting="avatar_url"]', panel)?.value || '';
    values.avatar_shape = $('[data-setting="avatar_shape"]', panel)?.value || state.settings.avatar_shape || 'circle';
    return values;
  };

  const renderDesignPreview = (panel = getDesignPanel()) => {
    if (!panel) return;
    const preview = $('[data-design-preview]', panel);
    if (!preview) return;
    const values = designValuesFromPanel(panel);
    preview.style.setProperty('--jcb-preview-accent', values.accent_color || '#6f5bd6');
    preview.style.setProperty('--jcb-preview-font', values.font_color || '#111827');
    preview.style.setProperty('--jcb-preview-bg', values.background_color || '#f8fafc');
    preview.style.setProperty('--jcb-preview-user-bg', values.user_bubble_color || values.accent_color || '#6f5bd6');
    preview.style.setProperty('--jcb-preview-user-text', values.user_bubble_text_color || '#ffffff');
    preview.style.setProperty('--jcb-preview-assistant-bg', values.assistant_bubble_color || '#ffffff');
    preview.style.setProperty('--jcb-preview-assistant-text', values.assistant_bubble_text_color || '#111827');
    preview.dataset.bubbleStyle = values.bubble_style || 'soft';
    const welcome = $('[data-preview-welcome]', panel);
    const placeholder = $('[data-preview-placeholder]', panel);
    if (welcome) welcome.textContent = values.welcome_message || '';
    if (placeholder) placeholder.textContent = values.placeholder || '';
    const avatar = $('[data-preview-avatar]', panel);
    if (avatar) {
      avatar.dataset.shape = values.avatar_shape || 'circle';
      if (values.avatar_url) {
        avatar.classList.remove('jcb-hidden');
        avatar.innerHTML = `<img src="${escapeHtml(values.avatar_url)}" alt="">`;
      } else {
        avatar.classList.add('jcb-hidden');
        avatar.innerHTML = '';
      }
    }
    const thumb = $('[data-avatar-thumb]', panel);
    if (thumb) thumb.dataset.shape = values.avatar_shape || 'circle';
  };

  const applyDesignTheme = (themeId) => {
    const panel = getDesignPanel();
    if (!panel) return;
    const theme = designThemes().find((item) => item.id === themeId);
    if (!theme) return;
    Object.entries(theme.colors).forEach(([key, value]) => {
      const picker = $(`[data-setting="${key}"]`, panel);
      const hex = $(`[data-color-hex="${key}"]`, panel);
      const swatch = $(`[data-color-swatch="${key}"]`, panel);
      if (picker) picker.value = value;
      if (hex) hex.value = value;
      if (swatch) swatch.style.background = value;
    });
    const style = $('[data-setting="bubble_style"]', panel);
    if (style) style.value = theme.bubble_style;
    const themeInput = $('[data-design-theme-value]', panel);
    if (themeInput) themeInput.value = theme.id;
    $$('.jcb-theme-card', panel).forEach((card) => card.classList.toggle('is-active', card.dataset.designTheme === theme.id));
    renderDesignPreview(panel);
  };

  const saveSettings = async (button) => {
    const panel = button.closest('.jcb-panel');
    if (panel?.dataset.panel === 'channels') {
      syncVisibilityUsers(panel);
    }
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


  const runSecurityTest = async (button) => {
    const panel = button.closest('[data-panel="security"]');
    const message = $('[data-security-test-message]', panel)?.value || '';
    const result = $('[data-security-test-result]', panel);
    setBusy(button, true, t('testing', 'Testing...'));
    try {
      const data = await api('/security-test', { method: 'POST', body: JSON.stringify({ message }) });
      if (result) result.innerHTML = securityTestResult(data);
    } catch (error) {
      if (result) result.innerHTML = `<div class="jcb-test-result blocked"><strong>${escapeHtml(error.message)}</strong></div>`;
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

    const themeButton = event.target.closest('[data-design-theme]');
    if (themeButton) applyDesignTheme(themeButton.dataset.designTheme);

    if (event.target.matches('#jcb-meta-autofill')) autofillSummary();
    if (event.target.matches('[data-apply-preset]')) applyPreset(event.target.closest('.jcb-panel'));
    if (event.target.matches('[data-avatar-select]')) openMediaPicker(event.target.closest('.jcb-panel'));
    if (event.target.matches('[data-avatar-remove]')) removeAvatar(event.target.closest('.jcb-panel'));

    if (event.target.matches('[data-save-settings]')) saveSettings(event.target);
    if (event.target.matches('[data-test-api]')) testApi(event.target);
    if (event.target.matches('[data-check-sync]')) checkSync(event.target);
    if (event.target.matches('[data-test-security]')) runSecurityTest(event.target);
    if (event.target.matches('.jcb-sync')) sync(event.target);
    if (event.target.matches('[data-copy-shortcode]')) {
      navigator.clipboard?.writeText(window.JCB_ADMIN.shortcode);
      notice(t('shortcode_copied', 'Shortcode copied.'));
    }
  });

  const isThemeField = (el) => {
    const key = el.dataset?.setting || el.dataset?.colorHex;
    if (!key) return false;
    return designColorKeys.includes(key) || key === 'bubble_style' || el.matches('[data-color-hex]');
  };

  document.addEventListener('input', (event) => {
    if (event.target.matches('#jcb-content-search')) renderContentList();
    const designPanel = event.target.closest('[data-panel="design"]');
    if (designPanel) {
      if (isThemeField(event.target)) {
        syncColorControl(event.target);
        markCustomTheme(designPanel);
      }
      renderDesignPreview(designPanel);
    }
  });

  document.addEventListener('change', (event) => {
    if (event.target.matches('[data-preset-select]')) {
      const desc = event.target.closest('.jcb-panel')?.querySelector('[data-preset-description]');
      if (desc) desc.textContent = presetDescription(event.target.value);
    }

    const channelsPanel = event.target.closest('[data-panel="channels"]');
    if (channelsPanel) {
      if (event.target.matches('[data-visibility-user]')) {
        syncVisibilityUsers(channelsPanel);
      }
      if (event.target.matches('[data-setting="visibility_mode"]')) {
        const wrap = $('[data-selected-users-wrap]', channelsPanel);
        if (wrap) wrap.classList.toggle('jcb-hidden', event.target.value !== 'selected_users');
      }
    }

    const designPanel = event.target.closest('[data-panel="design"]');
    if (designPanel) {
      if (isThemeField(event.target)) {
        syncColorControl(event.target);
        markCustomTheme(designPanel);
      }
      renderDesignPreview(designPanel);
    }
  });

  document.addEventListener('submit', (event) => {
    if (event.target.matches('#jcb-metadata-form')) saveMetadata(event);
  });

  document.addEventListener('DOMContentLoaded', () => {
    loadContent().catch((error) => notice(error.message, 'error'));
  });
})();
