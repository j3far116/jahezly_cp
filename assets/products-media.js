// products-media.js — قص/رفع غلاف المنتج باستخدام Cropper.js (CSP: img-src 'self' data:)
(function () {
  'use strict';

  var modalEl   = document.getElementById('mediaCropModal');   // مودال المنتجات
  var fileInput = document.getElementById('media-file');       // <input type="file">
  var imgEl     = document.getElementById('cropper-target');   // <img> داخل المعاينة
  var wrapEl    = document.getElementById('cropper-wrap');     // حاوية المعاينة
  var toolsEl   = document.getElementById('cropper-tools');    // أزرار التحكم (تكبير/تصغير..)
  var btnSave   = document.getElementById('btn-save-media');   // زر الحفظ
  var csrfEl    = document.getElementById('_csrf_media');      // CSRF hidden

  // عناصر مفقودة؟ لا تعمل واطبع تحذير
  if (!modalEl || !fileInput || !imgEl || !wrapEl || !toolsEl || !btnSave || !csrfEl) {
    console.warn('[products-media] عناصر المودال غير مكتملة، لن يعمل القص.');
    return;
  }

  if (typeof window.Cropper === 'undefined') {
    console.error('[products-media] لم يتم تحميل cropper.min.js أو أن المسار غير صحيح.');
  }

  var cropper = null;
  var productId = null;   // ID المنتج
  var marketId  = null;   // ID المتجر

  function destroyCropper() {
    if (cropper) {
      try { cropper.destroy(); } catch (_) {}
      cropper = null;
    }
  }

  function resetUI() {
    toolsEl.style.display = 'none';
    wrapEl.style.display  = 'none';
    btnSave.disabled      = true;
    imgEl.removeAttribute('src');
    destroyCropper();
  }

  // عند الضغط على زر فتح المودال: خزّن IDs من الـ data-attrs
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('button[data-media="cover"][data-product-id][data-market-id]');
    if (!btn) return;

    productId = btn.getAttribute('data-product-id');
    marketId  = btn.getAttribute('data-market-id');

    var titleEl = document.getElementById('mediaCropModalLabel');
    if (titleEl) titleEl.textContent = 'تحديث غلاف المنتج';

    resetUI();
    fileInput.value = '';
  });

  modalEl.addEventListener('hidden.bs.modal', resetUI);
  modalEl.addEventListener('shown.bs.modal', function () {
    if (cropper) { try { cropper.reset(); cropper.setDragMode('move'); } catch (_) {} }
  });

  // اختيار صورة → DataURL → معاينة + Cropper
  fileInput.addEventListener('change', function () {
    var file = this.files && this.files[0];
    if (!file) return;

    destroyCropper();

    var reader = new FileReader();
    reader.onerror = function () {
      alert('تعذّر قراءة الملف.');
    };
    reader.onload = function (ev) {
      var dataUrl = ev.target.result; // data:image/...;base64,...

      // أظهر الحاوية والأدوات قبل ضبط src
      wrapEl.style.display  = '';
      toolsEl.style.display = '';

      imgEl.onerror = function () {
        btnSave.disabled = true;
        console.error('[products-media] تعذّر عرض الصورة للقص (تحقق من CSP img-src وامتداد الصورة).');
        alert('تعذّر عرض الصورة للقص. تأكد أن img-src يسمح data: وأن الصورة صالحة.');
      };

      imgEl.onload = function () {
        if (typeof window.Cropper === 'undefined') {
          btnSave.disabled = true;
          return;
        }

        destroyCropper();
        cropper = new Cropper(imgEl, {
          aspectRatio: 16 / 9,       // غلاف المنتج 16:9
          viewMode: 1,
          dragMode: 'move',
          autoCropArea: 1,
          responsive: true,
          background: false,
          checkOrientation: true
        });

        btnSave.disabled = false;
      };

      imgEl.src = dataUrl;
    };

    reader.readAsDataURL(file);
  });

  // أدوات القص
  toolsEl.addEventListener('click', function (e) {
    var actEl = e.target.closest('[data-act]');
    if (!actEl || !cropper) return;
    var act = actEl.getAttribute('data-act');
    switch (act) {
      case 'zoom-in':      cropper.zoom(0.1);  break;
      case 'zoom-out':     cropper.zoom(-0.1); break;
      case 'rotate-left':  cropper.rotate(-90);break;
      case 'rotate-right': cropper.rotate(90); break;
      case 'reset':        cropper.reset();    break;
    }
  });

  // قص وحفظ
  btnSave.addEventListener('click', function () {
    if (!cropper || !productId || !marketId) return;

    var canvas = cropper.getCroppedCanvas({ width: 1600, height: 900 }); // جودة مناسبة لـ 16:9
    if (!canvas) return;

    canvas.toBlob(function (blob) {
      if (!blob) return;

      var fd = new FormData();
      fd.append('_csrf', csrfEl.value);
      fd.append('file',  blob, 'cover.png');

      // نفس نمط الراوتر لديك: /admincp/markets/{market_id}/products/{id}/media
      fetch('/admincp/markets/' + marketId + '/products/' + productId + '/media', {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' },
        body: fd
      })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        if (!json || !json.ok) {
          alert((json && json.msg) ? json.msg : 'فشل التحديث.');
          return;
        }

        // تحديث معاينة الغلاف في الصفحة إن وجدت
        var coverEl = document.getElementById('product-cover-img');
        if (coverEl) coverEl.src = json.url + '?t=' + Date.now();

        // أغلق المودال
        try {
          var bsModal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
          bsModal.hide();
        } catch (_) {}

        // (اختياري) إعادة تحميل الصفحة لضمان تحديث كل شيء
        // window.location.reload();
      })
      .catch(function () { alert('حدث خطأ غير متوقع أثناء الرفع.'); });
    }, 'image/png', 0.95);
  });
})();
