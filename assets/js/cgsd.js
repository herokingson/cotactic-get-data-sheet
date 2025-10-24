document.addEventListener("DOMContentLoaded", async () => {
  const container = document.getElementById("cgsd-container");
  if (!container) return;

  // 🔄 แสดงสถานะโหลด
  container.innerHTML = `
    <div class="cgsd-loadding">
      <div class="text-gray-500 py-6 flex flex-col items-center">
        <div>
          <svg viewBox="25 25 50 50">
            <circle r="20" cy="50" cx="50"></circle>
          </svg>
        </div>
        <div>Loading Google Sheet data...</div>
      </div>
    </div>`;

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

    // ✅ เรียงตามชื่อ A-Z
    rows.sort((a, b) => (a[idxAgency] || "").localeCompare(b[idxAgency] || ""));

    let html = '<div class="cgsd-tailwind">';
    let currentLetter = null;

    rows.forEach((r) => {
      const obj = {};
      headers.forEach((h, i) => (obj[h] = r[i] || ""));

      const agency = (obj["Agency Name"] || "").trim();
      if (!agency) return;

      const desc =
        obj["Meta Description (EN)"] || obj["Meta Description (TH)"] || "";
      const logo = obj["URL Logo"] || obj["Logo URL"] || "";
      const website = obj["Website"] || "";
      const facebook = obj["Facebook Page"] || "";
      const phone = obj["Phone Number"] || "";

      const firstLetter = /^[A-Z]/i.test(agency[0])
        ? agency[0].toUpperCase()
        : "0-9";
      if (firstLetter !== currentLetter) {
        currentLetter = firstLetter;
        html += `<h3 class="!text-2xl font-bold mt-2 !mb-1 text-[#0B284D] border-b border-gray-300 !pb-0 text-left">รายชื่อ Agency หมวด ${firstLetter}</h3>`;
      }

      const initial = agency[0].toUpperCase();

      html += `
        <article class="relative flex items-stretch rounded-2xl ring-1 ring-gray-200 bg-white overflow-hidden mb-4 shadow-sm hover:shadow-md transition-all">
          <div class="flex w-1/3 md:w-[15%] min-w-[110px] bg-gradient-to-br from-[#0B284D] to-[#0B284D] items-center justify-center">
            ${
              logo
                ? `<img src="${logo}" loading="lazy" alt="${agency} logo" class="w-full h-full object-contain drop-shadow" />`
                : `<div class="w-full h-full bg-white/10 text-white font-semibold flex items-center justify-center text-xl">${initial}</div>`
            }
          </div>
          <div class="hidden sm:block w-px bg-gray-200"></div>
          <div class="flex-1 px-3 py-[10px] text-left">
            <p class="text-[14px] font-bold font-sarabun mb-[5px] text-[#0B284D]">${agency}</p>
            ${
              desc
                ? `<p class="text-[14px] font-sarabun leading-4 text-gray-900 h-[35px] overflow-hidden mb-0">${desc}</p>`
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

    html += "</div>";
    container.innerHTML = html;

    // ✅ โหลด Table of Contents ใหม่หลังโหลดข้อมูลครบ
    setTimeout(() => {
      refreshPowerPackTOC();
    }, 500);
  } catch (err) {
    container.innerHTML = `<p class="text-red-600">Error: ${err.message}</p>`;
    console.error("CGSD Fetch Error ❌", err);
  }
});

// --------------------- ส่วนจัดการ Table of Contents ---------------------
const CONTAINER_SEL = ".cgsd-tailwind";
const TOC_WRAPPER = ".pp-toc, #pp-toc-ad5b393";

function refreshPowerPackTOC() {
  console.log("🔁 เริ่มรีเฟรช PowerPack TOC...");

  // 1️⃣ พยายามรีเฟรชผ่าน elementor handler ก่อน
  if (
    typeof window.ppTocHandler !== "undefined" &&
    typeof window.ppTocHandler.populateTOC === "function"
  ) {
    try {
      window.ppTocHandler.populateTOC();
      console.log("✅ PowerPack TOC รีเฟรชผ่าน handler สำเร็จ");
      return;
    } catch (err) {
      console.warn("⚠️ Handler error:", err);
    }
  }

  // 2️⃣ ถ้าไม่มี handler → ใช้ fallback
  buildPPTocManually();
}

function buildPPTocManually() {
  const host = document.querySelector(CONTAINER_SEL);
  if (!host) {
    console.warn("⚠️ ไม่พบ container สำหรับสร้าง TOC");
    return;
  }

  const tocs = document.querySelectorAll(TOC_WRAPPER);
  if (!tocs.length) {
    console.warn("⚠️ ไม่พบ TOC element ตาม selector:", TOC_WRAPPER);
    return;
  }

  const heads = host.querySelectorAll("h2, h3");
  if (!heads.length) {
    console.warn("⚠️ ไม่พบหัวข้อ h2/h3 ใน container");
    return;
  }

  console.log(`🧩 กำลังสร้าง TOC ทั้งหมด ${tocs.length} จุด...`);

  tocs.forEach((toc) => {
    const body = toc.querySelector(".pp-toc__body");
    if (!body) return;

    const spinner = body.querySelector(".pp-toc__spinner-container");
    if (spinner) spinner.remove();

    // ✅ ประกาศ listWrap ก่อนใช้งาน
    let listWrap = toc.querySelector(".pp-toc__list");
    if (!listWrap) {
      listWrap = document.createElement("ul");
      listWrap.className = "pp-toc__list";
      body.appendChild(listWrap);
    } else {
      listWrap.innerHTML = "";
    }

    let idx = 0;
    let currentParent = null; // h2 ล่าสุด

    heads.forEach((h) => {
      if (!h.id) h.id = `pp-toc__heading-${idx++}`;
      const isH2 = h.tagName.toLowerCase() === "h2";

      const li = document.createElement("li");
      li.className = `pp-toc__list-item ${isH2 ? "level-0" : "level-1"}`;
      li.innerHTML = `
        <div class="pp-toc__list-item-text-wrapper">
          <a href="#${h.id}" class="pp-toc__list-item-text ${
        isH2 ? "pp-toc__top-level" : ""
      }">${h.textContent.trim()}</a>
        </div>`;

      if (isH2) {
        // h2 → ต่อใน root list
        listWrap.appendChild(li);
        currentParent = li;
      } else if (currentParent) {
        // h3 → ซ้อนใน h2 ล่าสุด
        let subList = currentParent.querySelector("ul.pp-toc__list-wrapper");
        if (!subList) {
          subList = document.createElement("ul");
          subList.className = "pp-toc__list-wrapper";
          currentParent.appendChild(subList);
        }
        subList.appendChild(li);
      } else {
        // กรณีไม่มี h2 ก่อนหน้า → แปะไว้ root
        listWrap.appendChild(li);
      }
    });

    console.log(`✅ สร้าง TOC (${toc.id || "no-id"}) สำเร็จ`);
  });

  // ✅ เพิ่ม smooth scroll ทุก TOC
  document.querySelectorAll(".pp-toc__list a[href^='#']").forEach((a) => {
    a.addEventListener("click", (e) => {
      e.preventDefault();
      const target = document.querySelector(a.getAttribute("href"));
      if (target) target.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  });

  console.log(`🎯 เพิ่มหัวข้อทั้งหมด ${heads.length} หัวข้อเสร็จเรียบร้อย`);
}
