function pappersFillFields(data) {
  // Fill each form field if it exists and data is not empty
  var fields = ['customers_company', 'customers_siret', 'customers_ape',
    'customers_tva_intracom', 'customer_company_information'];

  fields.forEach(function(field) {
    var el = document.querySelector('[name="' + field + '"]');
    if (el && data[field]) {
      el.value = data[field];
    }
  });
}