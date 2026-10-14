let table = document.getElementById('table');
let button = document.getElementById('button');

function confirmFormSubmission() {
  if (!window.confirm('Are you sure?')) {
    return false;
  }
}

function initializeTable() {
  let exportTypes = ['json', 'xml', 'csv', 'excel', 'pdf'];
  let exportDataType = this.value;

  let options = {
    exportDataType: exportDataType,
    exportTypes: exportTypes
  };

  new BootstrapTable(table, options);
}

if (button !== null && button !== undefined) {
  button.addEventListener('click', function () {
    document.querySelector('form').addEventListener('submit', confirmFormSubmission);
  });
}

initializeTable();
