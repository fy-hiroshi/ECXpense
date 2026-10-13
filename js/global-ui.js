document.addEventListener(
  "click",
  function (e) {
    // 1. Check if the user clicked a button meant to close a modal
    const btn = e.target.closest('[onclick*="display"]');

    if (btn && btn.getAttribute("onclick").includes("none")) {
      e.preventDefault();
      e.stopPropagation(); // Freezes the instant HTML close action

      const modal = btn.closest(".modal-overlay");
      if (modal) {
        modal.classList.add("closing"); // Trigger the smooth CSS exit animation

        // Wait 240ms for the animation to finish, then actually hide it
        setTimeout(() => {
          modal.style.display = "none";
          modal.classList.remove("closing"); // Reset for the next time it opens
        }, 240);
      }
    }
  },
  true,
);
