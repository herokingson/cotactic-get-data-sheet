document.addEventListener("DOMContentLoaded", async () => {
  const container = document.getElementById("cgsd-container");
  if (!container) return;
  container.innerHTML = `<div class="cgsd-loadding"><div class="text-gray-500 py-6 flex flex-col items-center"><div><svg viewBox="25 25 50 50">
    <circle r="20" cy="50" cx="50"></circle></svg></div><div>Loading Google Sheet data...</div></div></div>`;

  try {
    const res = await fetch(`${cgsd_vars.ajax_url}?action=cgsd_get_data`);
    const json = await res.json();
    if (!json.success) throw new Error(json.data);

    const values = json.data;
    const headers = values[0];
    const rows = values.slice(1);
    const idxAgency = headers.indexOf("Agency Name");

    if (idxAgency === -1) {
      container.innerHTML = `<p class="text-red-600">ไม่พบคอลัมน์ Agency Name</p>`;
      return;
    }

    // เรียงตามชื่อ A-Z
    rows.sort((a, b) => (a[idxAgency] || "").localeCompare(b[idxAgency] || ""));

    let html = '<div class="cgsd-tailwind">'; // ✅ ประกาศตรงนี้
    let currentLetter = null;

    rows.forEach((r) => {
      const obj = {};
      headers.forEach((h, i) => (obj[h] = r[i] || ""));

      const agency = (obj["Agency Name"] || "").trim();
      if (!agency) return;

      const desc =
        obj["Meta Description (EN)"] || obj["Meta Description (EN)"] || "";
      const logo = obj["URL Logo"] || obj["Logo URL"] || "";
      const website = obj["Website"] || "";
      const facebook = obj["Facebook Page"] || "";
      const phone = obj["Phone Number"] || "";

      const firstLetter = /^[A-Z]/i.test(agency[0])
        ? agency[0].toUpperCase()
        : "0-9";
      if (firstLetter !== currentLetter) {
        currentLetter = firstLetter;
        html += `<h3 class="!text-2xl font-bold mt-2 !mb-1 text-[#0B284D] border-b border-gray-300 !pb-0 text-left">รายชื่อ Agency ประเภทหมวด ${firstLetter}</h3>`;
      }

      const initial = agency[0].toUpperCase();

      // ✅ การ์ดแต่ละเอเจนซี
      html += `
      <article class="relative flex items-stretch rounded-2xl ring-1 ring-gray-200 bg-white overflow-hidden mb-4 shadow-sm hover:shadow-md transition-all">
        <div class="flex w-1/3 md:w-[15%] min-w-[110px] bg-gradient-to-br from-[#0B284D] to-[#0B284D] items-center justify-center">
          ${
            logo
              ? `<img src="${logo}" loading="lazy" alt="${agency} logo" class="w-full h-full object-contain drop-shadow" />`
              : `<div class="w-full h-full rounded-xl bg-white/10 text-white font-semibold flex items-center justify-center text-xl">${initial}</div>`
          }
        </div>
        <div class="hidden sm:block w-px bg-gray-200"></div>
        <div class="flex-1 px-3 py-[10px] text-left">
          <p class="text-[14px] font-bold font-sarabun mb-[5px] my-0 text-[#0B284D]">${agency}</p>
          ${
            desc
              ? `<p class="text-[14px] font-sarabun leading-4 text-gray-900 h-[35px] max-h-[35px] overflow-hidden">${desc}</p>`
              : ""
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
                : ""
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
                : ""
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
                : ""
            }
          </div>
        </div>
      </article>`;
    });
    html += "</div>"; // ✅ ปิด tag
    container.innerHTML = html;

    setTimeout(() => {
      if (
        typeof elementorFrontend !== "undefined" &&
        elementorFrontend?.hooks
      ) {
        console.log("🔁 Refreshing PowerPack TOC manually...");
        document.dispatchEvent(new CustomEvent("powerpack-toc-refresh"));
      }
    }, 1000);
  } catch (err) {
    container.innerHTML = `<p class="text-red-600">Error: ${err.message}</p>`;
    console.error("CGSD Fetch Error ❌", err);
  }
});

const CONTAINER_SEL = ".cgsd-tailwind";
const TOC_WRAPPER = "#pp-toc-85227a9";

function buildPPTocManually() {
  const toc = document.querySelector(TOC_WRAPPER);
  const host = document.querySelector(CONTAINER_SEL);

  if (!toc || !host) {
    console.warn("TOC fallback: ไม่พบ toc หรือ container", { toc, host });
    return;
  }

  console.log("✅ สร้าง PowerPack TOC fallback...");

  const heads = host.querySelectorAll("h2, h3");
  if (!heads.length) {
    console.warn("TOC fallback: ไม่พบ h2/h3 ใน container");
    return;
  }

  // ถ้ายังเป็น spinner ให้แทนที่ด้วย list ใหม่
  let listWrap = toc.querySelector(".pp-toc__list, ul");
  if (!listWrap) {
    const body = toc.querySelector(".pp-toc__body");
    if (body) {
      // ลบ spinner container ออกก่อน
      const spinner = body.querySelector(".pp-toc__spinner-container");
      if (spinner) spinner.remove();

      // ✅ สร้าง ul ใหม่
      listWrap = document.createElement("ul");
      listWrap.className = "pp-toc__list";
      body.appendChild(listWrap);
    }
  }

  if (!listWrap) {
    console.warn("TOC fallback: ไม่พบตำแหน่งแทรก TOC list");
    return;
  }

  listWrap.innerHTML = "";
  let idx = 0;

  heads.forEach((h) => {
    if (!h.id) h.id = `pp-toc__heading-${idx++}`;
    const level = h.tagName.toLowerCase() === "h2" ? 0 : 1;

    const li = document.createElement("li");
    li.className = `pp-toc__list-item level-${level}`;
    li.innerHTML = `
      <div class="pp-toc__list-item-text-wrapper">
        <a href="#${h.id}" class="pp-toc__list-item-text ${
      level === 0 ? "pp-toc__top-level" : ""
    }">
          ${h.textContent.trim()}
        </a>
      </div>
    `;
    listWrap.appendChild(li);
  });

  console.log(`✅ TOC fallback: เพิ่ม ${heads.length} หัวข้อเรียบร้อย`);

  // เพิ่ม smooth scroll
  listWrap.querySelectorAll("a[href^='#']").forEach((a) => {
    a.addEventListener("click", (e) => {
      e.preventDefault();
      const t = document.querySelector(a.getAttribute("href"));
      if (t) t.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  });
}

// ✅ รอให้ spinner หายก่อน แล้วค่อยทำงาน
const waitForTOCSpinner = setInterval(() => {
  const spinner = document.querySelector(
    `${TOC_WRAPPER} .pp-toc__spinner-container`
  );
  if (spinner) return; // ยังโหลดอยู่
  clearInterval(waitForTOCSpinner);
  setTimeout(buildPPTocManually, 800); // เผื่อดีเลย์นิดนึง
}, 300);
