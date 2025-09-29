// markets-media.js — قص/رفع الغلاف والشعار باستخدام Cropper.js (CSP: img-src 'self' data:)
(function () {
  'use strict';

  var modalEl   = document.getElementById('mediaCropModal');
  var fileInput = document.getElementById('media-file');
  var imgEl     = document.getElementById('cropper-target');
  var wrapEl    = document.getElementById('cropper-wrap');
  var toolsEl   = document.getElementById('cropper-tools');
  var btnSave   = document.getElementById('btn-save-media');
  var csrfEl    = document.getElementById('_csrf_media');

  // عناصر مفقودة؟ لا تعمل واطبع تحذير
  if (!modalEl || !fileInput || !imgEl || !wrapEl || !toolsEl || !btnSave || !csrfEl) {
    console.warn('[markets-media] عناصر المودال غير مكتملة، لن يعمل القص.');
    return;
  }

  // Cropper.js محمّل؟
  if (typeof window.Cropper === 'undefined') {
    console.error('[markets-media] لم يتم تحميل cropper.min.js أو أن المسار غير صحيح.');
    // سنسمح بعرض الصورة العادية على الأقل
  }

  var cropper = null;
  var activeType = null; // 'cover' | 'logo'
  var marketId   = null;

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
    // أبقِ ستايل <img> كما هو (لا نزيله بالكامل حتى لا نخسر max-width)
    imgEl.removeAttribute('src');
    destroyCropper();
  }

  // فتح المودال (وتجهيز النوع والمعرّفات)
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('button[data-media]');
    if (!btn) return;

    activeType = btn.getAttribute('data-media'); // cover | logo
    marketId   = btn.getAttribute('data-market-id');

    var titleEl = document.getElementById('mediaCropModalLabel');
    if (titleEl) titleEl.textContent = (activeType === 'logo') ? 'تحديث الشعار' : 'تحديث صورة العرض';

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
        console.error('[markets-media] تعذّر عرض الصورة للقص (تحقق من CSP img-src وامتداد الصورة).');
        alert('تعذّر عرض الصورة للقص. تأكد أن img-src يسمح data: وأن الصورة صالحة.');
      };

      imgEl.onload = function () {
        // إن لم تُحمّل Cropper.js، سنعرض الصورة دون طبقة القص
        if (typeof window.Cropper === 'undefined') {
          btnSave.disabled = true;
          return;
        }

        var aspect = (activeType === 'logo') ? 1 : (16 / 9);
        destroyCropper();
        cropper = new Cropper(imgEl, {
          aspectRatio: aspect,
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
    if (!cropper || !activeType || !marketId) return;

    var canvasOptions = (activeType === 'logo')
      ? { width: 512, height: 512 }
      : { width: 1600, height: 900 };

    var canvas = cropper.getCroppedCanvas(canvasOptions);
    if (!canvas) return;

    canvas.toBlob(function (blob) {
      if (!blob) return;

      var fd = new FormData();
      fd.append('type',  activeType);
      fd.append('_csrf', csrfEl.value);
      fd.append('file',  blob, activeType + '.png');

      fetch('/admincp/markets/' + marketId + '/media', {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' },
        body: fd
      })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        if (!json || !json.ok) {
          alert((json && json.message) ? json.message : 'فشل التحديث.');
          return;
        }

        // تحديث معاينة الصفحة
        if (json.type === 'cover') {
          var coverEl = document.getElementById('market-cover');
          if (coverEl) coverEl.src = json.url + '?t=' + Date.now();
        } else {
          var logoEl = document.getElementById('market-logo');
          if (logoEl) logoEl.src = json.url + '?t=' + Date.now();
        }

        // أغلق المودال ثم أعد تحميل الصفحة
        try {
          var bsModal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
          bsModal.hide();
        } catch (_) {}
        window.location.reload();
      })
      .catch(function () { alert('حدث خطأ غير متوقع أثناء الرفع.'); });
    }, 'image/png', 0.95);
  });
})();
