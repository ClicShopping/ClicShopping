document.getElementById('button-generate').addEventListener('click', function () {
  let rand = '';
  const string = '+%$/?@#!ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz01234567891234567890';
  const inputPassword = document.getElementById('input-password');

  for (let i = 0; i < 10; i++) {
    rand += string[Math.floor(Math.random() * (string.length - 1))];
  }

  inputPassword.value = rand;
});
