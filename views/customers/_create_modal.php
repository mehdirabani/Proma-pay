<div class="modal" id="create-customer" data-customer-create-modal>
  <div class="modal-content proma-modal-lg">
    <div class="modal-header"><h3>افزودن مشتری</h3><button class="icon-btn" type="button" data-close-modal aria-label="بستن">×</button></div>
    <form method="post" action="<?= e(url('customers/store')) ?>" data-customer-create-form>
      <div class="modal-body form-grid three">
        <?= csrf_field() ?>
        <label>نام کامل<input name="full_name" required maxlength="190"></label>
        <label>نام پدر<input name="father_name" maxlength="100"></label>
        <label>محل صدور<input name="issued_from" maxlength="100"></label>
        <label>کد ملی<input name="national_id" required inputmode="numeric" maxlength="10"></label>
        <label>موبایل<input name="mobile" required inputmode="tel" maxlength="20"></label>
        <label>تلفن دوم<input name="secondary_phone" inputmode="tel" maxlength="20"></label>
        <label>وضعیت<select name="status"><option value="active">فعال</option><option value="inactive">غیرفعال</option></select></label>
        <label class="full">آدرس<textarea name="address" rows="3" maxlength="2000"></textarea></label>
        <p class="full" data-customer-create-error role="alert" hidden></p>
      </div>
      <div class="modal-footer"><button class="btn" type="submit">ثبت مشتری</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
    </form>
  </div>
</div>
