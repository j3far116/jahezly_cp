// assets/toast.js — Bootstrap 5 Toast integration (RTL-friendly)

(function () {
  // تأكد من وجود bootstrap.bundle (لأن Toast يعتمد على JS الخاص ببوسترب)
  if (typeof bootstrap === 'undefined' || !bootstrap.Toast) {
    console.warn('[toast.js] Bootstrap Toast not found. Make sure bootstrap.bundle.min.js is included.');
  }

  const ROOT_ID = 'toast-root';
  const DEFAULT_DELAY = 3500; // ms

  // تحويل نوع الرسالة إلى كلاس لون بوسترب
  function typeClass(type) {
    switch ((type || 'info').toLowerCase()) {
      case 'success': return 'text-bg-success';
      case 'error':   return 'text-bg-danger';
      case 'warning': return 'text-bg-warning';
      case 'info':
      default:        return 'text-bg-info';
    }
  }

  // إنشاء عنصر Toast وفق مواصفات Bootstrap
  function buildToastEl(message, type, delay) {
    const el = document.createElement('div');
    el.className = `toast align-items-center ${typeClass(type)} border-0`;
    el.setAttribute('role', 'alert');
    el.setAttribute('aria-live', 'assertive');
    el.setAttribute('aria-atomic', 'true');

    // body + close
    const inner = document.createElement('div');
    inner.className = 'd-flex';

    const body = document.createElement('div');
    body.className = 'toast-body';
    body.textContent = message;

    const btn = document.createElement('button');
    btn.className = 'btn-close btn-close-white me-2 m-auto';
    btn.type = 'button';
    btn.setAttribute('data-bs-dismiss', 'toast');
    btn.setAttribute('aria-label', 'إغلاق');

    inner.appendChild(body);
    inner.appendChild(btn);
    el.appendChild(inner);

    // init
    const opts = {
      autohide: true,
      delay: typeof delay === 'number' ? delay : DEFAULT_DELAY
    };
    const inst = bootstrap && bootstrap.Toast ? new bootstrap.Toast(el, opts) : null;

    // نظافة: إزالة العنصر من DOM بعد الإخفاء
    if (inst) {
      el.addEventListener('hidden.bs.toast', () => {
        try { el.remove(); } catch {}
      });
    }

    return { el, inst };
  }

  // عرض توست
  function showToast(message, type, delay) {
    const root = document.getElementById(ROOT_ID);
    if (!root) return;
    const { el, inst } = buildToastEl(message, type, delay);
    root.appendChild(el);
    // Bootstrap يتكفّل بالأنيميشن
    if (inst) inst.show();
  }

  // قراءة عناصر data-toast التي حقنها القالب (flash)
  function bootstrapFromData() {
    const nodes = document.querySelectorAll('[data-toast]');
    nodes.forEach(n => {
      const msg = n.getAttribute('data-msg') || '';
      const type = n.getAttribute('data-type') || 'info';
      if (msg.trim()) showToast(msg, type);
      // إزالة العنصر المخفي بعد القراءة
      if (n.parentNode) n.parentNode.removeChild(n);
    });
  }

  // واجهة عامة للاستخدام من أي سكربت
  window.Toast = { show: showToast };

  document.addEventListener('DOMContentLoaded', bootstrapFromData);
})();


/* (function () {
  const ROOT_ID = 'toast-root';
  const DEFAULT_DURATION = 3500; // ms

  function makeToastEl(message, type) {
    const el = document.createElement('div');
    el.className = `toast ${type || 'info'}`;

    const span = document.createElement('div');
    span.className = 'msg';
    span.textContent = message;

    const btn = document.createElement('button');
    btn.className = 'close';
    btn.type = 'button';
    btn.setAttribute('aria-label', 'Close');
    btn.textContent = '×';

    btn.addEventListener('click', () => removeToast(el));

    el.appendChild(span);
    el.appendChild(btn);
    return el;
  }

  function removeToast(el) {
    if (!el) return;
    el.classList.remove('show');
    setTimeout(() => el.remove(), 180);
  }

  function showToast(message, type, duration) {
    const root = document.getElementById(ROOT_ID);
    if (!root) return;
    const el = makeToastEl(message, type);
    root.appendChild(el);
    requestAnimationFrame(() => el.classList.add('show'));
    setTimeout(() => removeToast(el), typeof duration === 'number' ? duration : DEFAULT_DURATION);
  }

  function bootstrapFromData() {
    const nodes = document.querySelectorAll('[data-toast]');
    nodes.forEach(n => {
      const msg = n.getAttribute('data-msg') || '';
      const type = n.getAttribute('data-type') || 'info';
      if (msg.trim()) showToast(msg, type);
      n.parentNode && n.parentNode.removeChild(n);
    });
  }

  // API عامة للاستخدام الاختياري لاحقاً
  window.Toast = { show: showToast };

  document.addEventListener('DOMContentLoaded', bootstrapFromData);
})();
 */