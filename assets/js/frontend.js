(function () {
  const bar = document.querySelector('[data-cmlc-infobar]');
  const overlay = document.querySelector('[data-cmlc-overlay]');
  const cfg = window.cmlcConfig || {};
  const forms = Array.from(document.querySelectorAll('[data-cmlc-form]'));

  if (!bar && forms.length === 0) return;

  const displayMode = cfg.displayMode || (bar ? bar.dataset.displayMode : 'bottom_bar') || 'bottom_bar';
  const storageKey = 'cmlcState';
  const closeBtn = bar ? bar.querySelector('[data-cmlc-close]') : null;
  const status = bar ? bar.querySelector('[data-cmlc-status]') : null;

  if (!cfg.ajaxUrl || !cfg.nonce) return;

  const loadState = () => {
    try {
      return JSON.parse(localStorage.getItem(storageKey)) || { views: 0, dismissedUntil: 0 };
    } catch (e) {
      return { views: 0, dismissedUntil: 0 };
    }
  };

  const saveState = (state) => localStorage.setItem(storageKey, JSON.stringify(state));

  const isMobile = () => window.matchMedia('(max-width: 768px)').matches;

  const shouldShow = () => {
    const state = loadState();
    if (!cfg.enableMobile && isMobile()) return false;
    if ((state.views || 0) >= (cfg.maxViews || 3)) return false;
    if ((state.dismissedUntil || 0) > Date.now()) return false;
    return true;
  };

  const getReferrerHost = () => {
    try {
      return document.referrer ? new URL(document.referrer).host : '';
    } catch (e) {
      return '';
    }
  };

  const inferCampaignId = () => {
    const fromConfig = parseInt(cfg.campaignId, 10);
    if (!Number.isNaN(fromConfig) && fromConfig > 0) return fromConfig;
    if (bar && bar.dataset && bar.dataset.campaignId) return parseInt(bar.dataset.campaignId, 10) || 0;
    return 0;
  };

  const analyticsPayload = () => ({
    page_id: Number(cfg.pageId || 0),
    campaign_id: inferCampaignId(),
    referrer_host: getReferrerHost()
  });

  const postAjax = (action, payload = {}) => {
    const body = new URLSearchParams({
      action,
      nonce: cfg.nonce,
      contextToken: cfg.contextToken || '',
      pageLocation: cfg.pageLocation || window.location.href,
      ...analyticsPayload(),
      ...payload
    });
    return fetch(cfg.ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body
    }).then((res) => res.json());
  };

  const getTurnstileToken = (form) => {
    const fallbackField = form.querySelector('input[name="turnstile_token"]');
    const responseFieldName = cfg.turnstileResponseField || 'cf-turnstile-response';
    const nativeField = form.querySelector(`input[name="${responseFieldName}"], textarea[name="${responseFieldName}"]`);
    const token = (nativeField && nativeField.value) || (fallbackField && fallbackField.value) || '';

    if (fallbackField) fallbackField.value = token;

    return token;
  };

  const show = () => {
    if (!bar || bar.classList.contains('is-visible') || !shouldShow()) return;

    bar.classList.add('is-visible');
    bar.setAttribute('aria-hidden', 'false');

    if (overlay && displayMode === 'lightbox') {
      overlay.classList.add('is-visible');
      overlay.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    }

    const state = loadState();
    state.views = (state.views || 0) + 1;
    saveState(state);

    postAjax('cmlc_track_impression', analyticsPayload()).catch(() => null);

    // Focus first input for lightbox (accessibility).
    if (displayMode === 'lightbox') {
      const emailInput = bar.querySelector('input[type="email"]');
      if (emailInput) setTimeout(() => emailInput.focus(), 350);
    }
  };

  const dismiss = () => {
    const state = loadState();
    const cooldownMs = (cfg.cooldownHours || 24) * 60 * 60 * 1000;
    state.dismissedUntil = Date.now() + cooldownMs;
    saveState(state);

    if (bar) {
      bar.classList.remove('is-visible');
      bar.setAttribute('aria-hidden', 'true');
    }

    if (overlay && displayMode === 'lightbox') {
      overlay.classList.remove('is-visible');
      overlay.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
    }
  };

  const triggerByScroll = () => {
    const scrollTop = window.scrollY || document.documentElement.scrollTop;
    const docHeight = document.documentElement.scrollHeight - window.innerHeight;
    const pct = docHeight <= 0 ? 100 : (scrollTop / docHeight) * 100;
    if (pct >= (cfg.scrollPercent || 40)) {
      show();
      window.removeEventListener('scroll', triggerByScroll);
    }
  };

  if (cfg.enableExitIntent) {
    document.addEventListener('mouseout', (event) => {
      if (event.clientY <= 0) show();
    });
  }

  if (bar) {
    window.addEventListener('scroll', triggerByScroll, { passive: true });
    setTimeout(show, (cfg.timeDelay || 8) * 1000);
  }

  if (closeBtn) {
    closeBtn.addEventListener('click', dismiss);
  }

  // Close lightbox on overlay click.
  if (overlay) {
    overlay.addEventListener('click', dismiss);
  }

  // Close lightbox on Escape key.
  if (displayMode === 'lightbox') {
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && bar && bar.classList.contains('is-visible')) {
        dismiss();
      }
    });
  }

  forms.forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const emailInput = form.querySelector('input[name="email"]');
      const honeypotInput = form.querySelector('input[name="cmlc_website"]');
      const timestampInput = form.querySelector('input[name="cmlc_form_token"]');
      const captchaInput = form.querySelector('input[name="captcha_token"]');

      if (cfg.enableCaptcha) {
        const captchaEvent = new CustomEvent('cmlc:captcha:request', {
          detail: {
            setToken: (token) => {
              if (captchaInput) captchaInput.value = token || '';
            },
            form
          }
        });
        document.dispatchEvent(captchaEvent);
      }

      const turnstileToken = getTurnstileToken(form);

      const payload = {
        email: emailInput ? emailInput.value : '',
        cmlc_website: honeypotInput ? honeypotInput.value : '',
        cmlc_form_token: timestampInput ? timestampInput.value : '',
        captcha_token: captchaInput ? captchaInput.value : '',
        turnstile_token: turnstileToken,
        ...analyticsPayload()
      };

      postAjax('cmlc_submit_email', payload)
        .then((data) => {
          if (data && data.success) {
            if (status) status.textContent = data.data.message || 'Subscribed';
            dismiss();
          } else if (status) {
            status.textContent = (data && data.data && data.data.message) || 'Unable to submit.';
          }
        })
        .catch(() => {
          if (status) status.textContent = 'Network error. Please try again.';
        });
    });
  });
})();
