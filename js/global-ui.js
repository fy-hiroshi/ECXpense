document.addEventListener("DOMContentLoaded", function () {
  // 1. Inject the Custom Confirmation Modal into the page (matches Logout Modal)
  const confirmModalHTML = `
        <div id="customConfirmModal" class="modal-overlay" style="display: none;">
            <div class="modal-box">
                <h2 id="confirmModalTitle">Delete Expense?</h2>
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
        confirmTitle.textContent = "Remove Collaborator?";
        confirmText.textContent =
          "Are you sure you want to remove this user from the workspace?";
        confirmBtn.textContent = "Remove";
      } else {
        confirmTitle.textContent = "Delete Expense?";
        confirmText.textContent =
          "Are you sure you want to permanently delete this expense? This action cannot be undone.";
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

  function setTheme(next, save) {
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

  syncThemeButton();
  if (themeBtn) {
    themeBtn.addEventListener("click", function () {
      setTheme(root.getAttribute("data-theme") === "dark" ? "light" : "dark", true);
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
