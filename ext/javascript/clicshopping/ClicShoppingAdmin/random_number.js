// Run when button clicked
document.getElementById("generate_button").addEventListener('click', function () {
  // Link to input
  const input = document.getElementById("RandomNumber");
  // Get data-generated-value-length
  const keyLength = this.getAttribute('data-generated-value-length')
  // Generator
  let text = "";
  const possible = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
  for (var i = 0; i < keyLength; i++) text += possible.charAt(Math.floor(Math.random() * possible.length));
  // Set generated value in to the input
  input.value = text;
});

