/*
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.

 */

//Display a message inside input fields
//address_book_details
//create_account_registration
//create_account_pro_registration
//guest account
const tooltipTriggerList = Array.from(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
const tooltipList = tooltipTriggerList.map(tooltipTriggerEl => {
  return new Tooltip(tooltipTriggerEl);
});
