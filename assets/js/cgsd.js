(function ($) {
  const $msg = $("#cgsd_msg");

  $("#cgsd_toggle_api").on("change", function () {
    const $inp = $("#cgsd_api_key");
    $inp.attr("type", this.checked ? "text" : "password");
  });

  $("#cgsd_save_settings").on("click", async function () {
    $msg.text("Saving...");
    const res = await $.post(CGSD_ADMIN.ajax, {
      action: "cgsd_save_settings",
      nonce: CGSD_ADMIN.nonce,
      sheet_id: $("#cgsd_sheet_id").val(),
      range: $("#cgsd_range").val(),
      api_key: $("#cgsd_api_key").val(),
    });
    $msg.text(res.success ? "Saved." : "Error: " + res.data);
  });

  $("#cgsd_fetch").on("click", function () {
    $msg.text("Fetching from Google Sheets and saving to DB...");
    $.ajax({
      url: CGSD_ADMIN.ajax,
      method: "POST",
      dataType: "json",
      data: {
        action: "cgsd_fetch_to_db",
        _ajax_nonce: CGSD_ADMIN.nonce, // 👈 เปลี่ยนชื่อฟิลด์
        sheet_id: $("#cgsd_sheet_id").val(),
        range: $("#cgsd_range").val(),
        api_key: $("#cgsd_api_key").val(),
      },
    })
      .done((res) => $msg.text(res.success ? res.data : "Error: " + res.data))
      .fail((xhr) =>
        $msg.text(
          "HTTP " + xhr.status + ": " + (xhr.responseText || "Bad Request")
        )
      );
  });

  $("#cgsd_clear").on("click", async function () {
    if (!confirm("ล้างข้อมูลทั้งหมดใน DB ?")) return;
    $msg.text("Clearing database...");
    const res = await $.post(CGSD_ADMIN.ajax, {
      action: "cgsd_clear_db",
      nonce: CGSD_ADMIN.nonce,
    });
    $msg.text(res.success ? res.data : "Error: " + res.data);
  });
})(jQuery);

document.addEventListener("DOMContentLoaded", () => {
  function moveAnchors() {
    document.querySelectorAll(".pp-toc-menu-anchor").forEach((anchor) => {
      const next = anchor.nextElementSibling;
      if (next && /^H[1-6]$/.test(next.tagName)) {
        next.prepend(anchor);
      }
    });
  }

  // 🔁 เรียกตอนโหลดครั้งแรก
  moveAnchors();

  // 👁 เฝ้าดู DOM ถ้ามี span ใหม่เพิ่มมา (เช่นปลั๊กอิน TOC inject)
  const observer = new MutationObserver(() => moveAnchors());
  observer.observe(document.body, { childList: true, subtree: true });

  // 🧭 ปรับ scroll offset ตอนคลิก TOC
  document.addEventListener("click", (e) => {
    const link = e.target.closest('a[href^="#pp-toc__heading-anchor"]');
    if (!link) return;
    const id = link.getAttribute("href").substring(1);
    const target = document.getElementById(id);
    if (!target) return;

    e.preventDefault();
    const offset = 90; // ปรับตามความสูงของ header ของคุณ
    const y = target.getBoundingClientRect().top + window.scrollY - offset;
    window.scrollTo({ top: y, behavior: "smooth" });
  });
});
