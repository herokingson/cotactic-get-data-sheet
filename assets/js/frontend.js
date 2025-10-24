document.addEventListener("DOMContentLoaded", async () => {
  const container = document.getElementById("cgsd-container");
  if (!container) return;

  container.innerHTML = `
    <div class="cgsd-loading">
      <div class="text-gray-500 py-6 flex flex-col items-center">
        <div style="width:36px;height:36px;border:3px solid #ddd;border-top-color:#0B284D;border-radius:50%;animation:spin 1s linear infinite"></div>
        <div style="margin-top:8px">Loading data...</div>
      </div>
    </div>
  `;

  try {
    const res = await fetch(`${cgsd_vars.ajax_url}?action=cgsd_get_db_data`, {
      cache: "no-cache",
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.data || "bad response");

    const rows = json.data || [];
    if (!rows.length) {
      container.innerHTML =
        '<p class="text-gray-600">No data in database. (กด Fetch Data ในแอดมินก่อน)</p>';
      return;
    }

    // สร้าง H2 หลัก + H3 ต่อหมวด
    let html = '<div class="cgsd-tailwind">';
    html += `<h2 class="text-2xl font-bold text-[#0B284D] border-b border-gray-300 pb-1 mb-3">
      รายชื่อ DIGITAL MARKETING AGENCY ในไทย
    </h2>`;

    let current = null;
    rows.forEach((r) => {
      const agency = (r.agency_name || "").trim();
      const website = (r.website || "").trim();
      const facebook = (r.facebook || "").trim();
      const phone = (r.phone || "").trim();
      const logo = (r.logo || "").trim();
      const desc = (r.meta_desc || "").trim();
      const letter = (r.first_letter || "0-9").toUpperCase();

      if (letter !== current) {
        current = letter;
        html += `<h3 class="text-xl font-bold mt-6 mb-2 text-[#0B284D] border-b border-gray-200 pb-1">รายชื่อ Agency ประเภทหมวด  ${letter}</h3>`;
      }

      const initial = agency ? agency[0].toUpperCase() : "?";

      html += `
        <article class="relative flex items-stretch rounded-2xl ring-1 ring-gray-200 bg-white overflow-hidden mb-4 shadow-sm hover:shadow-md transition-all">
          <div class="flex w-1/3 md:w-[15%] min-w-[110px] bg-gradient-to-br from-[#0B284D] to-[#0B284D] items-center justify-center">
            ${
              logo
                ? `<img src="${logo}" loading="lazy" alt="${agency} logo" class="w-full h-full object-contain" />`
                : `<div class="w-full h-full text-white font-semibold flex items-center justify-center text-xl">${initial}</div>`
            }
          </div>
          <div class="hidden sm:block w-px bg-gray-200"></div>
          <div class="flex-1 px-3 py-[10px] text-left">
            <p class="text-[14px] font-bold font-sarabun mb-[5px] text-[#0B284D]">${agency}</p>
            ${
              desc
                ? `<p class="text-[14px] leading-4 text-gray-900 h-[35px] overflow-hidden mb-0">${desc}</p>`
                : ``
            }
            <div class="mt-2 flex flex-wrap items-center gap-x-3 text-sm">
              ${
                website
                  ? `<div class="flex items-center gap-2">
                             <i class="fa-solid fa-globe text-[#0B284D] text-[14px]"></i>
                             <a href="${
                               website.startsWith("http")
                                 ? website
                                 : "https://" + website
                             }" target="_blank" class="underline text-[#0B284D] text-[12px]">${website}</a>
                           </div>`
                  : ``
              }
              ${
                facebook
                  ? `<div class="flex items-center gap-2">
                              <i class="fa-brands fa-facebook-f text-[#0B284D] text-[14px]"></i>
                              <a href="${
                                facebook.startsWith("http")
                                  ? facebook
                                  : "https://" + facebook
                              }" target="_blank" class="underline text-[#0B284D] text-[12px]">${agency}</a>
                            </div>`
                  : ``
              }
              ${
                phone
                  ? `<div class="flex items-center gap-2">
                           <i class="fa-solid fa-mobile-screen text-[#173A63] text-[14px]"></i>
                           <a href="tel:${phone.replace(
                             /\D+/g,
                             ""
                           )}" class="text-[#0B284D] text-[12px]">${phone}</a>
                         </div>`
                  : ``
              }
            </div>
          </div>
        </article>`;
    });

    html += "</div>";
    container.innerHTML = html;

    // (ทางเลือก) ถ้าต้อง fallback สร้าง TOC เอง ให้เรียก buildTOC()
    // buildTOC();
  } catch (err) {
    container.innerHTML = `<p class="text-red-600">Error: ${err.message}</p>`;
    console.error("[CGSD] frontend error:", err);
  }
});

/* ------- Fallback: สร้าง Table of Contents เอง (ถ้าจำเป็น) ------- */
function buildTOC() {
  const host = document.querySelector(".cgsd-tailwind");
  const toc = document.querySelector(".pp-toc__body");
  if (!host || !toc) return;

  const spinner = toc.querySelector(".pp-toc__spinner-container");
  if (spinner) spinner.remove();

  const h2h3 = host.querySelectorAll("h2, h3");
  if (!h2h3.length) return;

  const ul = document.createElement("ul");
  ul.className = "pp-toc__list";

  let currentH2 = null;
  let i = 0;
  h2h3.forEach((h) => {
    if (!h.id) h.id = "cgsd-h-" + i++;
    if (h.tagName === "H2") {
      const li = document.createElement("li");
      li.className = "pp-toc__list-item level-0";
      li.innerHTML = `<div class="pp-toc__list-item-text-wrapper">
        <a href="#${
          h.id
        }" class="pp-toc__list-item-text pp-toc__top-level">${h.textContent.trim()}</a>
      </div>`;
      ul.appendChild(li);
      currentH2 = li;
    } else {
      if (!currentH2) return; // h3 ก่อน h2 — ข้าม
      let sub = currentH2.querySelector(".pp-toc__list-wrapper");
      if (!sub) {
        sub = document.createElement("ul");
        sub.className = "pp-toc__list-wrapper";
        currentH2.appendChild(sub);
      }
      const sli = document.createElement("li");
      sli.className = "pp-toc__list-item level-1";
      sli.innerHTML = `<div class="pp-toc__list-item-text-wrapper">
        <a href="#${
          h.id
        }" class="pp-toc__list-item-text">${h.textContent.trim()}</a>
      </div>`;
      sub.appendChild(sli);
    }
  });

  toc.innerHTML = "";
  toc.appendChild(ul);

  ul.querySelectorAll('a[href^="#"]').forEach((a) => {
    a.addEventListener("click", (e) => {
      e.preventDefault();
      const t = document.querySelector(a.getAttribute("href"));
      if (t) t.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  });
}
