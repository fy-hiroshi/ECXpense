document.addEventListener("DOMContentLoaded", function () {
  // 1. Inject the Custom Confirmation Modal into the page (matches Logout Modal)
  const confirmModalHTML = `
        <div id="customConfirmModal" class="modal-overlay" style="display: none;">
            <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="confirmModalTitle">
                <h2 id="confirmModalTitle">Delete expense?</h2>
                <p id="confirmModalText">Are you sure you want to permanently delete this record? This action cannot be undone.</p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('customConfirmModal').style.display='none'">Cancel</button>
                    <button type="button" id="confirmModalBtn" class="btn btn-danger">Delete</button>
                </div>
            </div>
        </div>
    `;
  document.body.insertAdjacentHTML("beforeend", confirmModalHTML);

  const confirmModal = document.getElementById("customConfirmModal");
  const confirmTitle = document.getElementById("confirmModalTitle");
  const confirmText = document.getElementById("confirmModalText");
  const confirmBtn = document.getElementById("confirmModalBtn");
  let pendingAction = null;

  // 2. Find all buttons/links using the browser's default confirm() and upgrade them
  document.querySelectorAll('[onclick*="confirm("]').forEach((el) => {
    const onclickAttr = el.getAttribute("onclick");
    const match = onclickAttr.match(/confirm\(['"]([^'"]+)['"]\)/);
    const msg = match ? match[1] : "Are you sure?";

    // Strip the default browser confirm so the ugly popup never fires
    el.removeAttribute("onclick");
    el.dataset.confirmMsg = msg;

    el.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();

      // Customize modal text based on whether it's a user removal or expense deletion
      if (
        msg.toLowerCase().includes("user") ||
        msg.toLowerCase().includes("remove")
      ) {
        confirmTitle.textContent = "Remove collaborator?";
        confirmText.textContent =
          "They will lose access to this workspace and its expenses.";
        confirmBtn.textContent = "Remove";
      } else {
        confirmTitle.textContent = "Delete expense?";
        confirmText.textContent =
          "This permanently deletes the expense. You can't undo this.";
        confirmBtn.textContent = "Delete";
      }

      // Store the action to trigger if the user clicks the red confirm button
      pendingAction = () => {
        if (el.tagName === "A" && el.href) {
          window.location.href = el.href;
        } else if (el.form) {
          el.form.submit();
        }
      };

      // Show the custom modal
      confirmModal.style.display = "flex";
    });
  });

  // Execute the delete/remove action when confirmed
  confirmBtn.addEventListener("click", function () {
    if (pendingAction) pendingAction();
  });
});

// 3. Smooth Exit Animation Interceptor for all Modals
document.addEventListener(
  "click",
  function (e) {
    const btn = e.target.closest('[onclick*="display"]');

    if (btn && btn.getAttribute("onclick").includes("none")) {
      e.preventDefault();
      e.stopPropagation();

      const modal = btn.closest(".modal-overlay");
      if (modal) {
        modal.classList.add("closing");

        setTimeout(() => {
          modal.style.display = "none";
          modal.classList.remove("closing");
        }, 240);
      }
    }
  },
  true,
);

// 4. Light / dark theme toggle and the expandable "Shared Records" sidebar group.
//    The saved state is applied before first paint by js/ui-init.js; this wires up the buttons.
document.addEventListener("DOMContentLoaded", function () {
  var root = document.documentElement;

  // --- Theme toggle ---
  var themeBtn = document.getElementById("themeToggle");

  function syncThemeButton() {
    if (!themeBtn) return;
    var label =
      root.getAttribute("data-theme") === "dark"
        ? "Switch to light mode"
        : "Switch to dark mode";
    themeBtn.title = label;
    themeBtn.setAttribute("aria-label", label);
  }

  function applyTheme(next, save) {
    root.setAttribute("data-theme", next);
    if (save) {
      try {
        localStorage.setItem("ecx-theme", next);
      } catch (e) {}
    }
    syncThemeButton();
    // Charts listen for this to re-color themselves.
    window.dispatchEvent(new CustomEvent("themechange", { detail: { theme: next } }));
  }

  // When the switch comes from a click, the new theme spreads out from that point as a circle.
  // It uses the View Transitions API; browsers without it (and people who prefer reduced
  // motion) get the same switch with no animation.
  function setTheme(next, save, origin) {
    var reduceMotion =
      window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    if (!origin || reduceMotion || !document.startViewTransition) {
      applyTheme(next, save);
      return;
    }
    var x = origin.x;
    var y = origin.y;
    var radius = Math.hypot(
      Math.max(x, window.innerWidth - x),
      Math.max(y, window.innerHeight - y),
    );
    root.classList.add("theme-switching");
    var transition = document.startViewTransition(function () {
      applyTheme(next, save);
    });
    var done = function () {
      root.classList.remove("theme-switching");
    };
    transition.finished.then(done, done);
    transition.ready
      .then(function () {
        root.animate(
          {
            clipPath: [
              "circle(0px at " + x + "px " + y + "px)",
              "circle(" + radius + "px at " + x + "px " + y + "px)",
            ],
          },
          {
            duration: 750,
            easing: "cubic-bezier(0.5, 0, 0.2, 1)",
            pseudoElement: "::view-transition-new(root)",
          },
        );
      })
      .catch(function () {});
  }

  syncThemeButton();
  if (themeBtn) {
    themeBtn.addEventListener("click", function () {
      var box = themeBtn.getBoundingClientRect();
      setTheme(root.getAttribute("data-theme") === "dark" ? "light" : "dark", true, {
        x: box.left + box.width / 2,
        y: box.top + box.height / 2,
      });
    });
  }

  // Until the user picks a theme themselves, keep following the OS setting.
  if (window.matchMedia) {
    var mq = window.matchMedia("(prefers-color-scheme: dark)");
    var onOsChange = function (e) {
      var saved = null;
      try {
        saved = localStorage.getItem("ecx-theme");
      } catch (err) {}
      if (saved !== "dark" && saved !== "light") setTheme(e.matches ? "dark" : "light", false);
    };
    if (mq.addEventListener) mq.addEventListener("change", onOsChange);
  }

  // --- Expandable Shared Records group in the sidebar ---
  var navToggle = document.querySelector(".nav-toggle");
  if (navToggle) {
    var syncNav = function () {
      navToggle.setAttribute(
        "aria-expanded",
        root.getAttribute("data-shared-nav") === "open" ? "true" : "false",
      );
    };
    // If the list is long, scroll it so the record you are viewing is visible.
    var revealActive = function () {
      var list = document.getElementById("sharedNavList");
      var active = list && list.querySelector("a.active");
      if (!active || list.offsetParent === null) return; // nothing to show, or list is collapsed
      list.scrollTop = active.offsetTop - (list.clientHeight - active.offsetHeight) / 2;
    };
    syncNav();
    revealActive();
    navToggle.addEventListener("click", function () {
      var open = root.getAttribute("data-shared-nav") === "open";
      if (open) root.removeAttribute("data-shared-nav");
      else root.setAttribute("data-shared-nav", "open");
      try {
        if (open) localStorage.removeItem("ecx-shared-nav");
        else localStorage.setItem("ecx-shared-nav", "open");
      } catch (e) {}
      syncNav();
      revealActive();
    });
  }
});

// 5. Small interface touches: the statement figure counts up once, Escape and a click on the
//    backdrop close modals, and the mobile menu closes when you tap outside it.
document.addEventListener("DOMContentLoaded", function () {
  var reduceMotion =
    window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function money(n) {
    return n.toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  // The figure already holds its final value in the HTML; this only animates it.
  // After logging in it waits for the welcome sequence to lift away first.
  var welcome = document.getElementById("welcome");
  var countupsStarted = false;
  function startCountups() {
    if (countupsStarted) return;
    countupsStarted = true;
    document.querySelectorAll("[data-countup]").forEach(function (el) {
      var target = parseFloat(el.getAttribute("data-countup"));
      if (reduceMotion || !isFinite(target) || target <= 0) return;
      var duration = 900;
      var start = null;
      function frame(now) {
        if (start === null) start = now;
        var t = Math.min((now - start) / duration, 1);
        var eased = 1 - Math.pow(1 - t, 3);
        el.textContent = money(target * eased);
        if (t < 1) requestAnimationFrame(frame);
        else el.textContent = money(target);
      }
      requestAnimationFrame(frame);
    });
  }
  if (!reduceMotion) {
    document.querySelectorAll("[data-countup]").forEach(function (el) {
      var target = parseFloat(el.getAttribute("data-countup"));
      if (isFinite(target) && target > 0) el.textContent = money(0);
    });
  }
  if (welcome && !reduceMotion) {
    // Click or any key skips the welcome; the page content then arrives straight away.
    var skipWelcome = function () {
      if (welcome.classList.contains("skip")) return;
      welcome.classList.add("skip");
      document.documentElement.classList.remove("welcoming");
      startCountups();
    };
    welcome.addEventListener("click", skipWelcome);
    document.addEventListener("keydown", skipWelcome, { once: true });
    // Otherwise the figure starts counting as the curtain is lifting (1.65s + a little).
    setTimeout(startCountups, 1900);
  } else {
    startCountups();
  }

  function closeModal(modal) {
    if (!modal || modal.style.display === "none" || modal.classList.contains("closing")) return;
    modal.classList.add("closing");
    setTimeout(function () {
      modal.style.display = "none";
      modal.classList.remove("closing");
    }, 220);
  }

  document.addEventListener("keydown", function (e) {
    if (e.key !== "Escape") return;
    document.querySelectorAll(".modal-overlay").forEach(function (m) {
      if (m.style.display === "flex") closeModal(m);
    });
  });

  document.addEventListener("mousedown", function (e) {
    // A click on the dimmed backdrop itself (not on the box) closes the modal.
    if (e.target.classList && e.target.classList.contains("modal-overlay")) closeModal(e.target);
  });

  document.addEventListener("click", function (e) {
    var sidebar = document.querySelector(".sidebar.active");
    if (sidebar && !e.target.closest(".sidebar nav") && !e.target.closest(".menu-toggle")) {
      sidebar.classList.remove("active");
    }
  });
});

// 6. Sign in / Register: the button shows a spinner once the form is on its way, so the
//    short wait before the next page feels acknowledged.
document.addEventListener("DOMContentLoaded", function () {
  var form = document.querySelector(".auth-card form");
  if (!form) return;
  var btn = form.querySelector('button[type="submit"]');
  form.addEventListener("submit", function () {
    if (!btn) return;
    btn.classList.add("is-loading");
    btn.setAttribute("aria-busy", "true");
  });
  // Coming back with the browser's back button restores the page as it was left.
  window.addEventListener("pageshow", function () {
    if (!btn) return;
    btn.classList.remove("is-loading");
    btn.removeAttribute("aria-busy");
  });
});
