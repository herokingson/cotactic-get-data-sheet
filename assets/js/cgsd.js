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

  $("#cgsd_fetch").on("click", async function () {
    $msg.text("Fetching from Google Sheets and saving to DB...");
    const res = await $.post(CGSD_ADMIN.ajax, {
      action: "cgsd_fetch_to_db",
      nonce: CGSD_ADMIN.nonce,
      sheet_id: $("#cgsd_sheet_id").val(),
      range: $("#cgsd_range").val(),
      api_key: $("#cgsd_api_key").val(),
    });
    $msg.text(res.success ? res.data : "Error: " + res.data);
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
  // 🧭 ย้าย anchor span เข้าไปในหัวข้อจริง
  document.querySelectorAll(".pp-toc-menu-anchor").forEach((anchor) => {
    const next = anchor.nextElementSibling;
    if (next && /^H[1-6]$/.test(next.tagName)) {
      next.prepend(anchor);
    }
  });

  // 🧩 ปรับ offset ตอน scroll ให้หัวข้อไม่โดน header (ถ้ามี fixed header)
  const tocLinks = document.querySelectorAll(
    'a[href^="#pp-toc__heading-anchor"]'
  );
  tocLinks.forEach((link) => {
    link.addEventListener("click", (e) => {
      const id = link.getAttribute("href").substring(1);
      const target = document.getElementById(id);
      if (target) {
        e.preventDefault();
        const offset = 100; // ปรับได้ตามความสูงของ header
        const y = target.getBoundingClientRect().top + window.scrollY - offset;
        window.scrollTo({ top: y, behavior: "smooth" });
      }
    });
  });
});
