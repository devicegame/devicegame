(() => {
  const showToast = (message, isError = false) => {
    if (!message) return;
    let toast = document.querySelector('[data-social-toast]');
    if (!toast) {
      toast = document.createElement('div');
      toast.className = 'social-toast';
      toast.dataset.socialToast = '';
      toast.setAttribute('role', 'status');
      toast.setAttribute('aria-live', 'polite');
      document.body.appendChild(toast);
    }
    toast.textContent = message;
    toast.classList.toggle('is-error', isError);
    toast.classList.add('is-visible');
    window.clearTimeout(showToast._timer);
    showToast._timer = window.setTimeout(() => toast.classList.remove('is-visible'), 3200);
  };

  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!window.confirm(form.dataset.confirm || 'Emin misin?')) event.preventDefault();
    });
  });

  document.querySelectorAll('form[data-social-ajax]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = form.querySelector('button[type="submit"], button:not([type])');
      const originalHtml = button ? button.innerHTML : '';
      if (button) button.disabled = true;

      const data = new FormData(form);
      data.set('ajax', '1');
      try {
        const response = await fetch(form.action, {
          method: 'POST',
          body: data,
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
        });
        const payload = await response.json();
        if (!response.ok || !payload.ok) {
          throw new Error(payload.message || 'İşlem tamamlanamadı.');
        }

        const mode = form.dataset.socialAjax;
        if (button) {
          button.classList.toggle('is-active', Boolean(payload.active));
          button.setAttribute('aria-pressed', payload.active ? 'true' : 'false');
        }
        if (mode === 'gg' && button) {
          const count = button.querySelector('[data-gg-count]');
          if (count && typeof payload.count !== 'undefined') count.textContent = String(payload.count);
        }
        if (mode === 'save' && button) {
          button.textContent = payload.active ? 'Kaydedildi' : 'Kaydet';
        }
        if (mode === 'follow' && button) {
          button.textContent = payload.active ? 'Takip ediliyor' : 'Takip et';
          button.classList.toggle('btn-primary', !payload.active);
          button.classList.toggle('btn-ghost', Boolean(payload.active));
        }
        showToast(payload.message || 'İşlem tamamlandı.');
      } catch (error) {
        if (button) button.innerHTML = originalHtml;
        showToast(error.message || 'İşlem tamamlanamadı.', true);
      } finally {
        if (button) button.disabled = false;
      }
    });
  });

  document.querySelectorAll('[data-character-count]').forEach((field) => {
    const output = document.querySelector(`[data-count-for="${field.id}"]`);
    if (!output) return;
    const update = () => {
      const max = field.getAttribute('maxlength') || '';
      output.textContent = `${field.value.length}${max ? ` / ${max}` : ''}`;
    };
    field.addEventListener('input', update);
    update();
  });

  const topicSelect = document.querySelector('[data-social-topic-select]');
  const lfgFields = document.querySelector('[data-lfg-fields]');
  if (topicSelect && lfgFields) {
    const syncLfg = () => {
      const visible = topicSelect.value === 'oyun-arkadasi';
      lfgFields.hidden = !visible;
      lfgFields.querySelectorAll('input, select, textarea').forEach((input) => {
        input.disabled = !visible;
      });
    };
    topicSelect.addEventListener('change', syncLfg);
    syncLfg();
  }

  const commentForm = document.getElementById('comment-form');
  if (commentForm) {
    const parentInput = commentForm.querySelector('[data-comment-parent]');
    const banner = commentForm.querySelector('[data-reply-banner]');
    const author = commentForm.querySelector('[data-reply-author]');
    const textarea = commentForm.querySelector('textarea[name="body"]');
    const cancel = commentForm.querySelector('[data-reply-cancel]');

    document.querySelectorAll('[data-reply-button]').forEach((button) => {
      button.addEventListener('click', () => {
        if (parentInput) parentInput.value = button.dataset.parentId || '0';
        if (author) author.textContent = button.dataset.author || '';
        if (banner) banner.hidden = false;
        if (textarea) {
          textarea.focus();
          textarea.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      });
    });

    if (cancel) {
      cancel.addEventListener('click', () => {
        if (parentInput) parentInput.value = '0';
        if (banner) banner.hidden = true;
      });
    }
    if (parentInput && banner && parentInput.value !== '0') banner.hidden = false;
  }

  document.querySelectorAll('[data-copy-link]').forEach((button) => {
    button.addEventListener('click', async () => {
      const url = window.location.href.split('#')[0];
      try {
        await navigator.clipboard.writeText(url);
        showToast('Bağlantı kopyalandı.');
      } catch (_) {
        const area = document.createElement('textarea');
        area.value = url;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        document.execCommand('copy');
        area.remove();
        showToast('Bağlantı kopyalandı.');
      }
    });
  });

  const sortSelect = document.getElementById('social-sort');
  if (sortSelect && sortSelect.form) {
    sortSelect.addEventListener('change', () => sortSelect.form.submit());
  }

  const badges = document.querySelectorAll('[data-notification-count]');
  if (badges.length) {
    const refreshCount = async () => {
      try {
        const response = await fetch('aksiyon.php?islem=bildirim_sayisi', {
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
        });
        if (!response.ok) return;
        const payload = await response.json();
        badges.forEach((badge) => {
          badge.textContent = String(payload.count || 0);
          badge.hidden = !payload.count;
        });
      } catch (_) {
        // Bildirim sayacı kritik değil; sonraki denemede güncellenir.
      }
    };
    window.setInterval(refreshCount, 45000);
  }
})();
