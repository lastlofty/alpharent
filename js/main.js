/* Alpha Rent — interactivity */
(function () {
  "use strict";

  // ---- Mobile menu ----
  var burger = document.querySelector(".burger");
  var nav = document.querySelector(".nav");
  if (burger && nav) {
    burger.addEventListener("click", function () {
      burger.classList.toggle("open");
      nav.classList.toggle("open");
    });
    nav.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", function () {
        burger.classList.remove("open");
        nav.classList.remove("open");
      });
    });
  }

  // ---- FAQ accordion ----
  document.querySelectorAll(".faq-item").forEach(function (item) {
    var q = item.querySelector(".faq-q");
    var a = item.querySelector(".faq-a");
    if (!q || !a) return;
    q.addEventListener("click", function () {
      var isOpen = item.classList.contains("open");
      document.querySelectorAll(".faq-item.open").forEach(function (other) {
        other.classList.remove("open");
        var oa = other.querySelector(".faq-a");
        if (oa) oa.style.maxHeight = null;
      });
      if (!isOpen) {
        item.classList.add("open");
        a.style.maxHeight = a.scrollHeight + "px";
      }
    });
  });

  // ---- Scroll reveal ----
  var reveals = document.querySelectorAll(".reveal");
  if ("IntersectionObserver" in window && reveals.length) {
    var io = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (e) {
          if (e.isIntersecting) {
            e.target.classList.add("visible");
            io.unobserve(e.target);
          }
        });
      },
      { threshold: 0.12 }
    );
    reveals.forEach(function (el) { io.observe(el); });
  } else {
    reveals.forEach(function (el) { el.classList.add("visible"); });
  }

  // ---- Forms (отправка заявок в Telegram через send_request.php) ----
  document.querySelectorAll("form[data-form]").forEach(function (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      if (!checkFormPhones(form)) { return; }
      var ok = form.querySelector(".form__ok");
      var btn = form.querySelector('button[type="submit"]');
      var data = new FormData(form);
      data.append("source", document.title);
      if (btn) { btn.disabled = true; }
      fetch("send_request.php", { method: "POST", body: data })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res && res.ok) {
            if (res.pay_url) {
              if (ok) {
                ok.textContent = "Заявка принята! Открываем страницу оплаты…";
                ok.classList.add("show");
                ok.scrollIntoView({ behavior: "smooth", block: "center" });
              }
              form.reset();
              setTimeout(function () { window.location.href = res.pay_url; }, 1600);
            } else {
              if (ok) {
                ok.classList.add("show");
                ok.scrollIntoView({ behavior: "smooth", block: "center" });
              }
              form.reset();
            }
          } else {
            alert("Не удалось отправить заявку. Позвоните нам: +7 (995) 687-03-04");
          }
        })
        .catch(function () {
          alert("Не удалось отправить заявку. Проверьте интернет-соединение или позвоните: +7 (995) 687-03-04");
        })
        .finally(function () {
          if (btn) { btn.disabled = false; }
        });
    });
  });

  // ---- Prefill model from catalog "Арендовать" buttons ----
  document.querySelectorAll("[data-pick-model]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var model = btn.getAttribute("data-pick-model");
      var select = document.querySelector("#booking-model");
      if (select) {
        for (var i = 0; i < select.options.length; i++) {
          if (select.options[i].value === model) { select.selectedIndex = i; break; }
        }
      }
    });
  });

  // ---- Map source toggle (Яндекс / 2ГИС) ----
  var mapTabs = document.querySelectorAll(".map-tab");
  if (mapTabs.length) {
    mapTabs.forEach(function (tab) {
      tab.addEventListener("click", function () {
        var key = tab.getAttribute("data-map");
        mapTabs.forEach(function (t) {
          t.classList.toggle("active", t === tab);
        });
        document.querySelectorAll("[data-map-panel]").forEach(function (panel) {
          panel.style.display =
            panel.getAttribute("data-map-panel") === key ? "block" : "none";
        });
      });
    });
  }

  // ---- Reviews carousel ----
  var rTrack = document.querySelector("[data-reviews-track]");
  if (rTrack) {
    var rStep = function () {
      var card = rTrack.querySelector(".review-card");
      return card ? card.offsetWidth + 22 : 320;
    };
    var rPrev = document.querySelector("[data-reviews-prev]");
    var rNext = document.querySelector("[data-reviews-next]");
    if (rPrev) {
      rPrev.addEventListener("click", function () {
        rTrack.scrollBy({ left: -rStep(), behavior: "smooth" });
      });
    }
    if (rNext) {
      rNext.addEventListener("click", function () {
        rTrack.scrollBy({ left: rStep(), behavior: "smooth" });
      });
    }
  }

  // ---- Specs panel toggle (страница «Аренда») ----
  document.querySelectorAll("[data-specs-toggle]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var actions = btn.closest(".bike-card__actions");
      var panel = actions ? actions.nextElementSibling : null;
      if (!panel || !panel.hasAttribute("data-specs-panel")) return;
      var isHidden = panel.hasAttribute("hidden");
      if (isHidden) {
        panel.removeAttribute("hidden");
      } else {
        panel.setAttribute("hidden", "");
      }
      btn.classList.toggle("active", isHidden);
    });
  });

  // ---- Маска и проверка телефона (+7 (XXX) XXX-XX-XX) ----
  function formatRuPhone(raw) {
    var d = String(raw).replace(/\D/g, "");
    if (!d) return "";
    if (d[0] === "8") d = "7" + d.slice(1);
    else if (d[0] !== "7") d = "7" + d;
    d = d.slice(0, 11);
    var p = d.slice(1);
    var s = "+7";
    if (p.length > 0) s += " (" + p.slice(0, 3);
    if (p.length >= 3) s += ")";
    if (p.length > 3) s += " " + p.slice(3, 6);
    if (p.length > 6) s += "-" + p.slice(6, 8);
    if (p.length > 8) s += "-" + p.slice(8, 10);
    return s;
  }
  function phoneIsComplete(value) {
    var d = String(value).replace(/\D/g, "");
    if (d.length === 11 && d[0] === "8") { d = "7" + d.slice(1); }
    return d.length === 11 && d[0] === "7";
  }
  function checkFormPhones(form) {
    var allOk = true;
    form.querySelectorAll('input[type="tel"]').forEach(function (inp) {
      inp.value = formatRuPhone(inp.value);
      var val = inp.value.trim();
      if (val === "" && !inp.required) { inp.setCustomValidity(""); return; }
      if (!phoneIsComplete(val)) {
        inp.setCustomValidity("Введите номер телефона полностью — +7 и 10 цифр");
        allOk = false;
      } else {
        inp.setCustomValidity("");
      }
    });
    if (!allOk && form.reportValidity) { form.reportValidity(); }
    return allOk;
  }
  document.querySelectorAll('input[type="tel"]').forEach(function (inp) {
    var apply = function () {
      var formatted = formatRuPhone(inp.value);
      if (inp.value !== formatted) { inp.value = formatted; }
      inp.setCustomValidity("");
    };
    // реагируем на ввод, вставку, автозаполнение и потерю фокуса
    inp.addEventListener("input", apply);
    inp.addEventListener("change", apply);
    inp.addEventListener("blur", apply);
    inp.addEventListener("focus", apply);
    // форматируем уже заполненное значение (например, подставленное браузером)
    if (inp.value) { apply(); }
  });
  // автозаполнение иногда срабатывает чуть позже загрузки — подстрахуемся
  setTimeout(function () {
    document.querySelectorAll('input[type="tel"]').forEach(function (inp) {
      if (inp.value) { inp.value = formatRuPhone(inp.value); }
    });
  }, 600);
  // Проверка телефона в формах с обычной отправкой (регистрация, профиль)
  document.querySelectorAll("form:not([data-form])").forEach(function (form) {
    if (!form.querySelector('input[type="tel"]')) { return; }
    form.addEventListener("submit", function (e) {
      if (!checkFormPhones(form)) { e.preventDefault(); }
    });
  });

  // ---- Year in footer ----
  var y = document.querySelector("[data-year]");
  if (y) y.textContent = new Date().getFullYear();
})();
