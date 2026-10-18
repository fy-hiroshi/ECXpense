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
    "#2f5be8", "#17a398", "#c79a3b", "#7c5ce0", "#e0577a", "#3ba3e0",
    "#5f6f94", "#4fb06b", "#e08a3c", "#a15fd1", "#94a0bd", "#d96a5b",
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
      text: cs.getPropertyValue("--text").trim() || "#1b2338",
      surface: cs.getPropertyValue("--surface").trim() || "#ffffff",
    };
  };

  // 12345.6 -> "₱12,345.60"
  window.ecxMoney = function (n) {
    return (
      "\u20B1" +
      Number(n).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    );
  };

  // Category list shown beside the doughnut: color, name, amount and share of the total.
  window.ecxRenderLegend = function (el, labels, values, colors) {
    if (!el) return;
    var total = values.reduce(function (a, b) {
      return a + b;
    }, 0);
    el.innerHTML = "";
    labels.forEach(function (label, i) {
      var li = document.createElement("li");
      var pct = total > 0 ? Math.round((values[i] / total) * 100) : 0;
      var dot = document.createElement("span");
      dot.className = "dot";
      dot.style.background = colors[i];
      var name = document.createElement("span");
      name.className = "name";
      name.textContent = label;
      name.title = label;
      var amt = document.createElement("span");
      amt.className = "amt";
      amt.textContent = window.ecxMoney(values[i]);
      var share = document.createElement("span");
      share.className = "pct";
      share.textContent = (pct < 1 && values[i] > 0 ? "<1" : pct) + "%";
      li.appendChild(dot);
      li.appendChild(name);
      li.appendChild(amt);
      li.appendChild(share);
      el.appendChild(li);
    });
  };

  // The one doughnut used on the dashboard and in shared records.
  window.ecxDoughnut = function (canvas, labels, values, legendEl) {
    var colors = window.ecxChartColors(values.length);
    if (window.Chart) {
      Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    }
    var chart = new Chart(canvas, {
      type: "doughnut",
      data: {
        labels: labels,
        datasets: [
          {
            data: values,
            backgroundColor: colors,
            borderColor: window.ecxThemeColors().surface,
            borderWidth: 3,
            hoverOffset: 6,
          },
        ],
      },
      options: {
        responsive: true,
        cutout: "74%",
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (ctx) {
                return " " + window.ecxMoney(ctx.parsed);
              },
            },
          },
        },
      },
    });
    // Re-color the chart when the light/dark toggle is used.
    window.addEventListener("themechange", function () {
      chart.data.datasets[0].borderColor = window.ecxThemeColors().surface;
      chart.update();
    });
    window.ecxRenderLegend(legendEl, labels, values, colors);
    return chart;
  };
})();
