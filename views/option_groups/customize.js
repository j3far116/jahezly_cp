(() => {
  'use strict';

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  // ✅ Toast helper
  function toast(msg, type = 'success') {
    const container = document.querySelector('.position-fixed') || document.body;
    const el = document.createElement('div');
    el.className = `toast align-items-center text-bg-${type} border-0 show mb-2 position-fixed bottom-0 end-0 m-3`;
    el.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">${msg}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>`;
    container.appendChild(el);
    setTimeout(() => el.remove(), 3000);
  }

  // ✅ بناء روابط الـ Ajax
  function buildUrl(base, groupId, action) {
    return `${base}/option-groups/${groupId}/${action}`;
  }

  document.addEventListener('DOMContentLoaded', () => {
    const root = $('#og-customize-root');
    if (!root) return;

    const csrf = root.dataset.csrf || '';
    const marketId = root.dataset.marketId || '';
    const productId = root.dataset.productId || '';
    const groupId = root.dataset.groupId || '';
    const baseUrl = root.dataset.base || '';

    // ===================== فتح نافذة إدارة الخيارات =====================
    const btnManage = $('#btn-manage');
    const modalManage = $('#manageOptionsModal');
    if (typeof bootstrap !== 'undefined' && btnManage && modalManage) {
      btnManage.addEventListener('click', () => {
        new bootstrap.Modal(modalManage).show();
        refreshTable();
      });
    }

    // ===================== تفعيل السحب =====================
    const selectedList = $('#selected-list');
    if (selectedList && window.Sortable) {
      new Sortable(selectedList, {
        animation: 180,
        handle: '.grip',
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        dragClass: 'sortable-drag',
      });
    }

    // ===================== حفظ ترتيب الخيارات =====================
    const saveOrderBtn = $('#save-order');
    if (saveOrderBtn && selectedList) {
      saveOrderBtn.addEventListener('click', async () => {
        const items = $$('#selected-list li[data-id]');
        const order = items.map((li, i) => ({ id: li.dataset.id, sort_order: i + 1 }));
        const res = await fetch(buildUrl(baseUrl, groupId, 'ajax-order'), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
          body: JSON.stringify({ order })
        });
        const data = await res.json().catch(() => ({}));
        data.ok
          ? toast('✅ تم حفظ الترتيب بنجاح')
          : toast('❌ حدث خطأ أثناء الحفظ', 'danger');
      });
    }

    // ===================== إضافة / إزالة خيار من المجموعة =====================
    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('.add-option, .remove-option');
      if (!btn) return;

      const optionId = btn.dataset.id;
      const action = btn.classList.contains('add-option') ? 'ajax-add' : 'ajax-remove';
      const url = buildUrl(baseUrl, groupId, action);

      btn.disabled = true;
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrf },
        body: new URLSearchParams({ option_id: optionId })
      });
      const data = await res.json().catch(() => ({}));
      btn.disabled = false;

      if (data.ok) {
        toast(`✅ تم ${action === 'ajax-add' ? 'الإضافة' : 'الإزالة'} بنجاح`);
        await refreshProductOptionsList();
        await refreshSelectedOptionsList();
      } else {
        toast('❌ ' + (data.msg || 'حدث خطأ أثناء العملية'), 'danger');
      }
    });

    // ===================== إدارة الخيارات داخل نافذة المودال =====================
    const tableBody = $('#product-options-table tbody');
    const nameInput = $('#new-opt-name');
    const priceInput = $('#new-opt-price');
    const btnAddOpt = $('#btn-add-opt');

    // ---- جلب جميع الخيارات في نافذة الإدارة ----
    async function refreshTable() {
      if (!tableBody) return;
      const res = await fetch(`${baseUrl}/product-options/list_in_managment?group_id=${groupId}`, {
        headers: { 'X-CSRF-Token': csrf }
      });
      const data = await res.json().catch(() => ({ ok: false, items: [] }));

      tableBody.innerHTML = '';
      if (!data.ok || !data.items.length) {
        tableBody.innerHTML = `<tr><td colspan="4" class="text-center text-muted">لا توجد خيارات بعد</td></tr>`;
        return;
      }

      data.items.forEach(opt => {
        const tr = document.createElement('tr');
        tr.dataset.id = opt.id;
        const delBtn = opt.is_used
          ? `<button class="btn btn-sm btn-secondary" disabled title="الخيار مستخدم في المجموعة">🔒</button>`
          : `<button class="btn btn-sm btn-danger del-opt">🗑️</button>`;

        tr.innerHTML = `
          <td>${opt.id}</td>
          <td><input type="text" class="form-control form-control-sm name" value="${opt.name}"></td>
          <td><input type="number" class="form-control form-control-sm price" value="${opt.price}"></td>
          <td>
            <button class="btn btn-sm btn-success save-opt">💾</button>
            ${delBtn}
          </td>`;
        tableBody.appendChild(tr);
      });
    }

    // ---- إضافة خيار جديد ----
    if (btnAddOpt) {
      btnAddOpt.addEventListener('click', async () => {
        const name = nameInput.value.trim();
        const price = (priceInput.value || '0').trim();
        if (!name) return toast('⚠️ الرجاء إدخال اسم الخيار', 'warning');

        const res = await fetch(`${baseUrl}/product-options/add`, {
          method: 'POST',
          headers: { 'X-CSRF-Token': csrf },
          body: new URLSearchParams({ name, price })
        });
        const data = await res.json().catch(() => ({}));

        if (data.ok) {
          toast('✅ تمت الإضافة بنجاح');
          nameInput.value = '';
          priceInput.value = '';

          await refreshTable();
          await refreshProductOptionsList();
          await refreshSelectedOptionsList();
        } else {
          toast(data.msg || 'خطأ أثناء الإضافة', 'danger');
        }
      });
    }

    // ---- حفظ / حذف خيار ----
    document.addEventListener('click', async (e) => {
      // حفظ
      if (e.target.classList.contains('save-opt')) {
        const row = e.target.closest('tr');
        const id = row.dataset.id;
        const name = $('.name', row).value.trim();
        const price = ($('.price', row).value || '0').trim();

        const res = await fetch(`${baseUrl}/product-options/${id}/update`, {
          method: 'POST',
          headers: { 'X-CSRF-Token': csrf },
          body: new URLSearchParams({ name, price })
        });
        const data = await res.json().catch(() => ({}));

        if (data.ok) {
          toast('💾 تم الحفظ بنجاح');
          await refreshTable();
          await refreshProductOptionsList();
          await refreshSelectedOptionsList();
        } else {
          toast('❌ خطأ أثناء الحفظ', 'danger');
        }
      }

      // حذف
      if (e.target.classList.contains('del-opt')) {
        if (!confirm('هل أنت متأكد من الحذف؟')) return;
        const id = e.target.closest('tr').dataset.id;
        e.target.disabled = true;

        const res = await fetch(`${baseUrl}/product-options/${id}/delete`, {
          method: 'POST',
          headers: { 'X-CSRF-Token': csrf }
        });
        const data = await res.json().catch(() => ({}));
        e.target.disabled = false;

        if (data.ok) {
          toast('🗑️ تم الحذف بنجاح');
          await refreshTable();
          await refreshProductOptionsList();
          await refreshSelectedOptionsList();
        } else {
          toast('❌ خطأ أثناء الحذف', 'danger');
        }
      }
    });

    // ===================== تحديث القائمتين =====================
    async function refreshProductOptionsList() {
      const listEl = $('#product-options');
      if (!listEl) return;
      const res = await fetch(`${baseUrl}/product-options/list?group_id=${groupId}`, {
        headers: { 'X-CSRF-Token': csrf }
      });
      const data = await res.json().catch(() => ({ ok: false, items: [] }));
      if (!data.ok) return;

      listEl.innerHTML = '';
      if (!data.items.length) {
        listEl.innerHTML = `<li class="list-group-item text-center text-muted">لا توجد خيارات بعد</li>`;
        return;
      }

      data.items.forEach(o => {
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between align-items-center';
        li.dataset.id = o.id;
        li.innerHTML = `
          ${o.name}
          <button class="btn btn-sm btn-outline-success add-option" data-id="${o.id}">➕</button>`;
        listEl.appendChild(li);
      });
    }

    async function refreshSelectedOptionsList() {
      const listEl = $('#selected-list');
      if (!listEl) return;

      const res = await fetch(`${baseUrl}/option-groups/${groupId}/refresh`, {
        headers: { 'X-CSRF-Token': csrf }
      });
      const data = await res.json().catch(() => ({ ok: false }));
      if (!data.ok) return;

      listEl.innerHTML = '';
      if (!data.selected.length) {
        listEl.innerHTML = `<li class="list-group-item text-center text-muted">لم تتم إضافة أي خيار بعد</li>`;
        return;
      }

      data.selected.forEach(o => {
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between align-items-center';
        li.dataset.id = o.id;
        li.innerHTML = `
          <div class="d-flex align-items-center">
            <span class="grip me-2">⋮⋮</span>
            <span>${o.name} <small class="text-muted ms-1">(${o.price} ر.س)</small></span>
          </div>
          <button class="btn btn-sm btn-outline-danger remove-option" data-id="${o.id}">➖</button>`;
        listEl.appendChild(li);
      });
    }

    // ===================== ربط الأحداث العامة =====================
    document.addEventListener('refresh-left-options', refreshProductOptionsList);
    document.addEventListener('refresh-right-options', refreshSelectedOptionsList);

    if (modalManage) {
      modalManage.addEventListener('hidden.bs.modal', async () => {
        await refreshProductOptionsList();
        await refreshSelectedOptionsList();
      });
    }
  });
})();
