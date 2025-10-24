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
