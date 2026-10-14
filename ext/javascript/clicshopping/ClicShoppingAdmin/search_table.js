document.getElementById("search").addEventListener("keyup", function () {
  let value = this.value.toLowerCase().trim();
  let rows = document.querySelectorAll("table tr");

  for (var i = 0; i < rows.length; i++) {
    if (!i) continue;
    let cells = rows[i].querySelectorAll("td");
    let not_found = true;

    for (var j = 0; j < cells.length; j++) {
      let id = cells[j].textContent.toLowerCase().trim();
      if (id.indexOf(value) != -1) {
        not_found = false;
        break;
      }
    }

    rows[i].style.display = not_found ? "none" : "";
  }
});