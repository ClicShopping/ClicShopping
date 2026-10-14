document.getElementById('button-generate').addEventListener('click', function () {
  let rand = '';

  const string = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

  for (let i = 0; i < 256; i++) {
    rand += string.charAt(Math.floor(Math.random() * string.length));
  }

  document.getElementById('input-key').value = rand;
});
