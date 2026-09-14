/**
 * Maryam Roshan — core front-end module (Vanilla ES6, no bundler).
 * Provides: fetch wrapper honouring the API envelope, toasts, modals,
 * confirm dialogs, tabs, loading/empty/error state helpers, Persian numbers.
 */

export const csrfToken = () =>
  document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

export const baseUrl = () =>
  document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';

/* ------------------------------------------------------------------ HTTP */

export class ApiError extends Error {
  constructor(code, message, status, details = {}) {
    super(message);
    this.code = code;
    this.status = status;
    this.details = details;
  }
}

/**
 * Fetch helper that unwraps {success,data} / {success,error} envelopes.
 * @returns {Promise<any>} the `data` payload
 */
export async function api(url, { method = 'GET', body = null, headers = {}, signal } = {}) {
  const opts = {
    method,
    credentials: 'same-origin',
    signal,
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      Accept: 'application/json',
      ...headers,
    },
  };

  if (body instanceof FormData) {
    body.append('csrf_token', csrfToken());
    opts.body = body;
  } else if (body !== null) {
    opts.headers['Content-Type'] = 'application/json';
    opts.headers['X-CSRF-Token'] = csrfToken();
    opts.body = JSON.stringify(body);
  } else if (method !== 'GET') {
    opts.headers['X-CSRF-Token'] = csrfToken();
  }

  let res;
  try {
    res = await fetch(url, opts);
  } catch (e) {
    throw new ApiError('NETWORK_ERROR', 'ارتباط با سرور برقرار نشد. اتصال اینترنت را بررسی کنید.', 0);
  }

  if (res.status === 204) return null;

  let payload;
  try {
    payload = await res.json();
  } catch {
    throw new ApiError('BAD_RESPONSE', 'پاسخ سرور قابل پردازش نبود.', res.status);
  }

  if (!res.ok || payload.success === false) {
    const err = payload.error || {};
    throw new ApiError(err.code || 'ERROR', err.message || 'خطایی رخ داد.', res.status, err.details || {});
  }
  return payload.data;
}

export const get = (url, params = {}) => {
  const qs = new URLSearchParams(Object.entries(params).filter(([, v]) => v !== null && v !== '' && v !== undefined));
  return api(qs.toString() ? `${url}?${qs}` : url);
};
export const post = (url, body) => api(url, { method: 'POST', body });

/* ---------------------------------------------------------------- toasts */

function toastHost() {
  let host = document.querySelector('.mr-toasts');
  if (!host) {
    host = document.createElement('div');
    host.className = 'mr-toasts';
    host.setAttribute('role', 'status');
    host.setAttribute('aria-live', 'polite');
    document.body.appendChild(host);
  }
  return host;
}

export function toast(message, type = 'info', timeout = 4200) {
  const el = document.createElement('div');
  el.className = `mr-toast mr-toast--${type}`;
  el.textContent = message;
  toastHost().appendChild(el);
  setTimeout(() => {
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 250);
  }, timeout);
  return el;
}

export const toastError = (e) => toast(e instanceof Error ? e.message : String(e), 'error', 6000);

/* ----------------------------------------------------------------- state */

/** Render a loading skeleton into a container. */
export function showLoading(el, rows = 3) {
  if (!el) return;
  el.innerHTML = `<div class="p-4 flex flex-col gap-3">${
    Array.from({ length: rows }, () => '<div class="mr-skeleton" style="height:16px"></div>').join('')
  }</div>`;
}

export function showEmpty(el, title = 'موردی یافت نشد', text = 'هنوز داده‌ای برای نمایش وجود ندارد.', icon = '🔍') {
  if (!el) return;
  el.innerHTML = `<div class="mr-state"><div class="mr-state__icon">${icon}</div>
    <div class="mr-state__title">${escapeHtml(title)}</div>
    <div class="mr-state__text">${escapeHtml(text)}</div></div>`;
}

export function showError(el, message = 'خطا در دریافت اطلاعات', onRetry = null) {
  if (!el) return;
  el.innerHTML = `<div class="mr-state"><div class="mr-state__icon">⚠️</div>
    <div class="mr-state__title">${escapeHtml(message)}</div>
    ${onRetry ? '<button type="button" class="mr-btn mr-btn--soft mt-3" data-retry>تلاش دوباره</button>' : ''}</div>`;
  if (onRetry) el.querySelector('[data-retry]')?.addEventListener('click', onRetry);
}

/** Button busy state so no click ever looks dead. */
export function busy(btn, on = true, labelWhenBusy = 'در حال انجام…') {
  if (!btn) return;
  if (on) {
    btn.dataset.label = btn.innerHTML;
    btn.disabled = true;
    btn.classList.add('is-loading');
    btn.innerHTML = `<span class="mr-spinner"></span> ${escapeHtml(labelWhenBusy)}`;
  } else {
    btn.disabled = false;
    btn.classList.remove('is-loading');
    if (btn.dataset.label) btn.innerHTML = btn.dataset.label;
  }
}

/* ---------------------------------------------------------------- modals */

export function openModal(id) {
  document.getElementById(id)?.classList.add('is-open');
  document.body.style.overflow = 'hidden';
}
export function closeModal(id) {
  document.getElementById(id)?.classList.remove('is-open');
  document.body.style.overflow = '';
}

/** Promise-based confirmation dialog (replaces window.confirm). */
export function confirmDialog(message, { title = 'تأیید عملیات', okLabel = 'تأیید', danger = true } = {}) {
  return new Promise((resolve) => {
    const wrap = document.createElement('div');
    wrap.className = 'mr-modal is-open';
    wrap.innerHTML = `<div class="mr-modal__panel" role="dialog" aria-modal="true">
      <div class="mr-card__head"><h3 class="mr-card__title">${escapeHtml(title)}</h3></div>
      <div class="mr-card__body">${escapeHtml(message)}</div>
      <div class="mr-card__foot flex gap-2 justify-end">
        <button type="button" class="mr-btn mr-btn--ghost" data-no>انصراف</button>
        <button type="button" class="mr-btn ${danger ? 'mr-btn--danger' : 'mr-btn--primary'}" data-yes>${escapeHtml(okLabel)}</button>
      </div></div>`;
    document.body.appendChild(wrap);
    const done = (v) => { wrap.remove(); resolve(v); };
    wrap.querySelector('[data-yes]').addEventListener('click', () => done(true));
    wrap.querySelector('[data-no]').addEventListener('click', () => done(false));
    wrap.addEventListener('click', (e) => { if (e.target === wrap) done(false); });
    wrap.querySelector('[data-yes]').focus();
  });
}

/* ------------------------------------------------------------- utilities */

export const escapeHtml = (s) =>
  String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const FA_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
export const toFa = (v) => String(v ?? '').replace(/\d/g, (d) => FA_DIGITS[+d]);
export const toEn = (v) => String(v ?? '').replace(/[۰-۹]/g, (d) => String(FA_DIGITS.indexOf(d)))
  .replace(/[٠-٩]/g, (d) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)));

export const money = (n) => toFa(Number(n || 0).toLocaleString('en-US')) + ' تومان';
export const num = (n) => toFa(Number(n || 0).toLocaleString('en-US'));

export function debounce(fn, wait = 300) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), wait); };
}

/** Serialise a form into a plain object, supporting name="a[b]" and a[]. */
export function formData(form) {
  const out = {};
  new FormData(form).forEach((value, key) => {
    if (key.endsWith('[]')) {
      const k = key.slice(0, -2);
      (out[k] ||= []).push(value);
    } else if (out[key] !== undefined) {
      out[key] = [].concat(out[key], value);
    } else {
      out[key] = value;
    }
  });
  return out;
}

/** Show server-side validation errors next to the matching inputs. */
export function applyFieldErrors(form, details = {}) {
  form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
  form.querySelectorAll('[data-error-for]').forEach((el) => { el.textContent = ''; });
  Object.entries(details).forEach(([field, messages]) => {
    const input = form.querySelector(`[name="${field}"], [name="${field}[]"]`);
    if (input) input.classList.add('is-invalid');
    const slot = form.querySelector(`[data-error-for="${field}"]`);
    if (slot) slot.textContent = Array.isArray(messages) ? messages[0] : messages;
  });
}

/* ---------------------------------------------------------------- global */

document.addEventListener('DOMContentLoaded', () => {
  // Sidebar drawer
  document.querySelector('[data-sidebar-toggle]')?.addEventListener('click', () => {
    document.querySelector('.mr-sidebar')?.classList.toggle('is-open');
  });

  // Tabs
  document.querySelectorAll('[data-tabs]').forEach((group) => {
    group.addEventListener('click', (e) => {
      const tab = e.target.closest('.mr-tab');
      if (!tab) return;
      group.querySelectorAll('.mr-tab').forEach((t) => t.classList.remove('is-active'));
      tab.classList.add('is-active');
      const panelId = tab.dataset.tab;
      document.querySelectorAll(`[data-tab-panel]`).forEach((p) => {
        p.classList.toggle('hidden', p.dataset.tabPanel !== panelId);
      });
    });
  });

  // Modal open/close by data attribute
  document.addEventListener('click', (e) => {
    const open = e.target.closest('[data-modal-open]');
    if (open) openModal(open.dataset.modalOpen);
    const close = e.target.closest('[data-modal-close]');
    if (close) closeModal(close.closest('.mr-modal')?.id);
    if (e.target.classList?.contains('mr-modal')) e.target.classList.remove('is-open');
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') document.querySelectorAll('.mr-modal.is-open').forEach((m) => m.classList.remove('is-open'));
  });

  // Confirm-before-submit forms (delete actions etc.)
  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', async (e) => {
      if (form.dataset.confirmed === '1') return;
      e.preventDefault();
      if (await confirmDialog(form.dataset.confirm)) {
        form.dataset.confirmed = '1';
        form.submit();
      }
    });
  });

  // Flash messages rendered by the server become toasts on mobile widths.
  document.querySelectorAll('[data-flash]').forEach((el) => {
    if (window.innerWidth < 768) {
      toast(el.textContent.trim(), el.dataset.flash === 'error' ? 'error' : 'success');
      el.remove();
    }
  });
});
