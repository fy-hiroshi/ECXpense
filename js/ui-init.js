/*
 * Runs synchronously in <head> (before first paint) so the saved theme and the
 * sidebar's Shared Records open/closed state are applied without a flash.
 *
 *   localStorage "ecx-theme"       -> "light" | "dark"  (falls back to the OS setting)
 *   localStorage "ecx-shared-nav"  -> "open"            (absent = collapsed)
 */
(function () {
  var root = document.documentElement;

  var theme = null;
  try {
    theme = localStorage.getItem("ecx-theme");
  } catch (e) {}
  if (theme !== "dark" && theme !== "light") {
    theme =
      window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches
        ? "dark"
        : "light";
  }
  root.setAttribute("data-theme", theme);

  try {
    if (localStorage.getItem("ecx-shared-nav") === "open") {
      root.setAttribute("data-shared-nav", "open");
    }
  } catch (e) {}

  // Slice colors for the doughnut charts. Chart.js only auto-colors slices when the
  // dataset has no color settings at all, so we pass these explicitly. They are chosen
  // to stay readable on both the light and the dark surface.
  var palette = [
    "#397ff5", "#10b981", "#f59e0b", "#ef4444", "#8b5cf6", "#06b6d4",
    "#ec4899", "#84cc16", "#f97316", "#14b8a6", "#6366f1", "#94a3b8",
  ];
  window.ecxChartColors = function (count) {
    var out = [];
    for (var i = 0; i < count; i++) out.push(palette[i % palette.length]);
    return out;
  };

  // Text/border colors for Chart.js, read from the active theme's CSS variables.
  window.ecxThemeColors = function () {
    var cs = getComputedStyle(root);
    return {
      text: cs.getPropertyValue("--text").trim() || "#1e293b",
      surface: cs.getPropertyValue("--surface").trim() || "#ffffff",
    };
  };
})();
