document.addEventListener("DOMContentLoaded", function () {
  const scanBtn = document.getElementById("scanBtn");
  const fileInput = document.getElementById("receipt");
  const statusDiv = document.getElementById("status");
  const rawText = document.getElementById("ocrText");
  const parsedForm = document.getElementById("parsedExpenseForm");

  function setStatus(message, state) {
    statusDiv.textContent = message;
    statusDiv.className = "status " + state;
  }

  // Move the modal to root body so parent transforms never trap or misalign it
  if (parsedForm) {
    document.body.appendChild(parsedForm);
  }

  if (scanBtn && fileInput) {
    scanBtn.addEventListener("click", function () {
      const file = fileInput.files[0];
      if (!file) {
        setStatus("Select a receipt image first.", "is-error");
        return;
      }

      setStatus("Reading your receipt...", "is-busy");
      if (rawText) rawText.value = "";
      if (parsedForm) parsedForm.style.display = "none"; // Hide modal while scanning

      // Run OCR
      Tesseract.recognize(file, "eng", {
        logger: (m) => {
          if (m.status === "recognizing text")
            setStatus(`Scanning: ${Math.round(m.progress * 100)}%`, "is-busy");
        },
      })
        .then(({ data: { text } }) => {
          if (rawText) rawText.value = text;

          // Smart Parse the Text & Open Modal
          parseReceipt(text);

          setStatus("Scan complete. Review the details and save the expense.", "is-ok");
        })
        .catch((err) => {
          console.error(err);
          setStatus("We couldn't read that receipt. Try a clearer, well-lit photo.", "is-error");
        });
    });
  }

  function parseReceipt(text) {
    if (!parsedForm) return;
    const lines = text
      .split("\n")
      .map((l) => l.trim())
      .filter((l) => l.length > 0);
    if (lines.length === 0) return;

    // 1. Leave Title blank for manual entry
    let title = "";

    // 2. Extract Amount ("total" or "amount due", ignoring "sub")
    let maxAmount = 0.0;
    let totalCandidates = [];

    lines.forEach((line) => {
      const lowerLine = line.toLowerCase();
      if (
        (lowerLine.includes("total") || lowerLine.includes("amount due")) &&
        !lowerLine.includes("sub")
      ) {
        const nums = line.match(/\b\d+[\.\,]\d{2}\b/g);
        if (nums) {
          nums.forEach((n) =>
            totalCandidates.push(parseFloat(n.replace(",", "."))),
          );
        }
      }
    });

    if (totalCandidates.length > 0) {
      maxAmount = Math.max(...totalCandidates);
    } else {
      const allNumbers = text.match(/\b\d+[\.\,]\d{2}\b/g);
      if (allNumbers) {
        allNumbers.forEach((num) => {
          let val = parseFloat(num.replace(",", "."));
          if (val > maxAmount && val < 1000000) maxAmount = val;
        });
      }
    }

    // 3. Extract Date (or default to today)
    let finalDate = new Date().toISOString().split("T")[0];
    const dateRegex = /\b(\d{1,4}[\/\-\.]\d{1,2}[\/\-\.]\d{1,4})\b/;
    let dateMatch = text.match(dateRegex);

    if (dateMatch) {
      let d = new Date(dateMatch[1].replace(/\./g, "/"));
      if (!isNaN(d.getTime())) finalDate = d.toISOString().split("T")[0];
    }

    // 4. Pre-fill inputs and pop open the modal!
    document.getElementById("parsedTitle").value = title;
    document.getElementById("parsedAmount").value =
      maxAmount > 0 ? maxAmount.toFixed(2) : "";
    document.getElementById("parsedDate").value = finalDate;

    parsedForm.style.display = "flex";
    setTimeout(() => document.getElementById("parsedTitle").focus(), 100);
  }
});
