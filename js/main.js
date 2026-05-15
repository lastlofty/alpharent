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

  // ---- Forms ----
  document.querySelectorAll("form[data-form]").forEach(function (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var ok = form.querySelector(".form__ok");
      if (ok) {
        ok.classList.add("show");
        ok.scrollIntoView({ behavior: "smooth", block: "center" });
      }
      form.reset();
      /*
        Подключение онлайн-оплаты:
        здесь после отправки заявки выполняется редирект на платёжную
        страницу (ЮKassa / Точка / Тинькофф). Платёжный модуль создаёт
        счёт по выбранной модели и периоду аренды и возвращает
        confirmation_url, на который перенаправляется клиент.
      */
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

  // ---- Year in footer ----
  var y = document.querySelector("[data-year]");
  if (y) y.textContent = new Date().getFullYear();
})();
