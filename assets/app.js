// التقاط التوكن من الميتا
function getCsrf() {
  const m = document.querySelector('meta[name="csrf-token"]');
  return m ? m.getAttribute('content') : '';
}

// تأكيد وحذف عبر Fetch
// حذف عبر Fetch مع Toast بدلاً من alert
document.addEventListener('click', async function (e) {
  const btn = e.target.closest('.btn-delete');
  if (!btn) return;

  const url = btn.getAttribute('data-url');
  const msg = btn.getAttribute('data-confirm') || 'Are you sure?';

  // تأكيد قبل الحذف (يبقى كما هو)
  if (!window.confirm(msg)) return;

  // جلب التوكن من <meta>
  function getCsrf() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-Token': getCsrf(),
        'X-Requested-With': 'fetch',
        // اختياري: إن كنت تريد محاكاة DELETE على السيرفر
        'X-HTTP-Method-Override': 'DELETE'
      }
    });

    const data = await res.json().catch(() => ({}));

    if (!res.ok) {
      // بدلاً من alert —> توست خطأ
      if (window.Toast && typeof window.Toast.show === 'function') {
        window.Toast.show(data.message || 'فشل الحذف', 'error', 5000);
      }
      return;
    }

    // نجاح: إزالة الصف من الجدول + توست نجاح
    const tr = btn.closest('tr');
    if (tr) tr.remove();

    if (window.Toast && typeof window.Toast.show === 'function') {
      window.Toast.show(data.message || 'تم الحذف بنجاح', 'success', 3500);
    }
  } catch (err) {
    console.error(err);
    // بدلاً من alert —> توست خطأ
    if (window.Toast && typeof window.Toast.show === 'function') {
      window.Toast.show('تعذر الاتصال بالسيرفر', 'error', 5000);
    }
  }
}, false);




/// .. خاص بعرض نافذة تأكيد الحذف
 
/* (function () {
  // التقط submit في طور الالتقاط قبل أي سكربت آخر
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f) return;
    if (f.classList.contains('js-confirm-delete') || f.hasAttribute('data-confirm')) {
      var msg = f.getAttribute('data-confirm') || 'تأكيد الحذف؟';
      if (!window.confirm(msg)) {
        e.preventDefault();
        e.stopPropagation();
      }
    }
  }, true); // <-- capture

  // اعترض الاستدعاء البرمجي form.submit()
  var _nativeSubmit = HTMLFormElement.prototype.submit;
  HTMLFormElement.prototype.submit = function () {
    var f = this;
    if (f && (f.classList.contains('js-confirm-delete') || f.hasAttribute('data-confirm'))) {
      var msg = f.getAttribute('data-confirm') || 'تأكيد الحذف؟';
      if (!window.confirm(msg)) return; // ألغِ بدون إرسال
    }
    return _nativeSubmit.call(f);
  };
})(); */

