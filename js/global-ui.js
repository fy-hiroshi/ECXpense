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
