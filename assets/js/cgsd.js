document.addEventListener("DOMContentLoaded", async function () {
  const container = document.getElementById("cgsd-sheet-container");
  if (!container) return;

  const attrs = JSON.parse(container.dataset.attrs || "{}");
  const { sheet_id: sheetId, api_key: apiKey, range } = attrs;

  if (!sheetId || !apiKey) {
    container.innerHTML =
      '<p class="text-red-600">Missing Sheet ID or API Key</p>';
    return;
  }

  const url = `https://sheets.googleapis.com/v4/spreadsheets/${sheetId}/values/${range}?key=${apiKey}`;
  const cacheKey = "cgsd_sheet_cache";
  const cacheTime = 1000 * 60 * 10; // 10 นาที
  const now = Date.now();

  // ---------- Loading state ----------
  container.innerHTML = `
    <div class="animate-pulse text-center text-gray-500 py-6">
      Loading data from Google Sheets...
    </div>
  `;

  try {
    // ---------- Check cache ----------
    const cached = localStorage.getItem(cacheKey);
    const cacheTimestamp = localStorage.getItem(cacheKey + "_time");

    let data;
    if (cached && cacheTimestamp && now - cacheTimestamp < cacheTime) {
      data = JSON.parse(cached);
      console.log("Using cached Google Sheet data ✅");
    } else {
      const res = await fetch(url, { cache: "no-cache" });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      data = await res.json();
      localStorage.setItem(cacheKey, JSON.stringify(data));
      localStorage.setItem(cacheKey + "_time", now);
      console.log("Fetched fresh data from Google Sheet 🌐");
    }

    if (!data.values || data.values.length < 2) {
      container.innerHTML = '<p class="text-yellow-700">No data found</p>';
      return;
    }

    const headers = data.values[0];
    const rows = data.values.slice(1);
    const urlPattern =
      /^(https?:\/\/)?([\w.-]+)\.([a-zA-Z]{2,})([\w\/\.\-\?\=\&\#\%]*)?$/;

    // ---------- Render with DocumentFragment (เร็วกว่า innerHTML ตรงๆ) ----------
    const frag = document.createDocumentFragment();
    const grid = document.createElement("div");
    grid.className = "grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-2 gap-6";

    rows.forEach((r) => {
      const obj = {};
      headers.forEach((h, i) => (obj[h] = r[i] || ""));

      const card = document.createElement("article");
      card.className =
        "group relative rounded-2xl ring-1 ring-gray-200 bg-white hover:shadow-xl transition-shadow";

      card.innerHTML = `
        <div class="font-bold text-lg text-center bg-[#0B284D] text-[#FED312] py-1 rounded-t-xl">
          ${obj["Agency Name"] || "—"}
        </div>
        <div class="p-4">
          <div class="mt-2 text-sm text-gray-700"><strong>Website:</strong> ${
            obj["Website"] || ""
          }</div>
          <div class="text-sm text-gray-700"><strong>Facebook:</strong> ${
            obj["Facebook Page"] || ""
          }</div>
          <div class="text-sm text-gray-700"><strong>Phone:</strong> ${
            obj["Phone Number"] || ""
          }</div>
        </div>
      `;

      const btns = document.createElement("div");
      btns.className = "flex items-center justify-center pb-2 gap-3";

      if (obj["Website"] && urlPattern.test(obj["Website"])) {
        const link = document.createElement("a");
        link.href = obj["Website"];
        link.target = "_blank";
        link.rel = "noopener";
        link.className =
          "inline-flex font-bold items-center rounded-xl border px-6 py-1.5 text-sm hover:bg-[#0B284D]/90 hover:text-[#FED312] bg-[#0B284D] text-[#FED312]";
        link.textContent = "View";
        btns.appendChild(link);
      }

      if (obj["Facebook Page"] && urlPattern.test(obj["Facebook Page"])) {
        const fb = document.createElement("a");
        fb.href = obj["Facebook Page"];
        fb.target = "_blank";
        fb.rel = "noopener";
        fb.className =
          "inline-flex font-bold items-center rounded-xl border px-6 py-1.5 text-sm hover:bg-gray-50";
        fb.textContent = "Facebook";
        btns.appendChild(fb);
      }

      card.appendChild(btns);
      grid.appendChild(card);
    });

    frag.appendChild(grid);
    container.innerHTML = "";
    container.appendChild(frag);
  } catch (err) {
    container.innerHTML = `<p class="text-red-600">Error fetching data: ${err.message}</p>`;
    console.error("Google Sheet Fetch Error ❌", err);
  }
});
