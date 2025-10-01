document.addEventListener("DOMContentLoaded", function () {
  const container = document.getElementById("cgsd-sheet-container");
  if (!container) return;

  const attrs = JSON.parse(container.dataset.attrs);
  const sheetId = attrs.sheet_id;
  const apiKey = attrs.api_key;
  const range = attrs.range;

  if (!sheetId || !apiKey) {
    container.innerHTML =
      '<p class="text-red-600">Missing Sheet ID or API Key</p>';
    return;
  }

  const url = `https://sheets.googleapis.com/v4/spreadsheets/${sheetId}/values/${range}?key=${apiKey}`;
  console.log(url);
  fetch(url)
    .then((res) => res.json())
    .then((data) => {
      if (!data.values || data.values.length < 2) {
        container.innerHTML = '<p class="text-yellow-700">No data found</p>';
        return;
      }

      const headers = data.values[0];
      const rows = data.values.slice(1);

      let html =
        '<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-2 gap-6">';
      rows.forEach((r) => {
        const obj = {};
        headers.forEach((h, i) => (obj[h] = r[i] || ""));
        html += `
                <article class=" group relative rounded-2xl ring-1 ring-gray-200 bg-white hover:shadow-xl transition-shadow">
                    <div class="font-bold text-lg text-center bg-[#0B284D] text-[#FED312] py-1 rounded-t-xl">${
                      obj["Agency Name"] || "—"
                    }</div>
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
                </article>`;
      });
      html += "</div>";

      container.innerHTML = html;
    })
    .catch((err) => {
      container.innerHTML = `<p class="text-red-600">Error fetching data: ${err}</p>`;
      console.error(err);
    });
});
