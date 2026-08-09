CKEDITOR.dialog.add('chatgptDialog', function (editor) {
  var botUrl = apiGptUrl;
  var conversationState = '';

  return {
    title: titleGpt,
    minWidth: 400,
    minHeight: 300,

    contents: [
      {
        id: 'tab1',
        label: 'Chat Gpt',
        title: 'Chat Gpt',
        elements: [
          {
            type: 'textarea',
            id: 'message',
            label: 'Message',
            rows: 8,
            setup: function (element) {
              this.setValue('');
            },
            commit: function (element) {
              var message = this.getValue();
              var dialog = this.getDialog();

              // Check if 'preloader' element exists
              var preloader = document.getElementById('preloader');
              if (preloader) {
                // Add spinner
                preloader.classList.add('blur'); // Add blur class
                preloader.style.display = 'block';
              }

              // Send the message to the GPT bot
              var xhr = new XMLHttpRequest();
              xhr.open('POST', botUrl, true);
              xhr.setRequestHeader('Accept', 'application/json');
              xhr.setRequestHeader('Content-Type', 'application/json');
              xhr.onreadystatechange = function () {
                if (xhr.readyState != 4) {
                  return;
                }

                var text = '';

                if (xhr.status == 200) {
                  try {
                    var response = JSON.parse(xhr.responseText);
                    text = response.success === true ? (response.text_response || '') : '';
                  } catch (e) {
                    text = '';
                  }
                }

                if (text !== '') {
                  editor.editable().insertHtml('<p>' + text + '</p>');
                } else {
                  console.error('Invalid response from ClicShopping GPT endpoint');
                }

                dialog.getContentElement('tab1', 'message').setValue('');

                // The spinner is cleared on every outcome, not only on success.
                if (preloader) {
                  preloader.style.display = 'none';
                  preloader.classList.remove('blur');
                }
              };

              // The server owns model, provider, prompt and wire format: the dialog only sends
              // what the user typed.
              xhr.send(JSON.stringify({message: conversationState + message}));

              conversationState += message + '\n';
            },
          },
        ],
      },
    ],

    onOk: function () {
      this.commitContent(editor);
    },
  };
});

