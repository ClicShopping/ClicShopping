CKEDITOR.dialog.add('chatgptDialog', function (editor) {
  var botUrl = apiGptUrl;
  var apiKey = apiKeyGpt; // Replace with your own API key
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
              xhr.setRequestHeader('Authorization', 'Bearer ' + apiKey);
              xhr.onreadystatechange = function () {
                if (xhr.readyState == 4 && xhr.status == 200) {
                  var response = JSON.parse(xhr.responseText);
                  // Check if choices array exists and has at least one item
                  if (
                    response.choices &&
                    Array.isArray(response.choices) &&
                    response.choices.length > 0 &&
                    response.choices[0].message &&
                    response.choices[0].message.content
                  ) {
                    var text = response.choices[0].message.content;

                    // Append the response to the editor
                    editor.editable().insertHtml(`<p>${text}</p>`);
                  } else {
                    // Handle the case when response is empty or invalid
                    console.error('Invalid response from the API');
                  }

                  // Append the response to the editor
                  editor.editable().insertHtml(`<p>${text}</p>`);

                  // Clear the message input
                  dialog.getContentElement('tab1', 'message').setValue('');

                  if (preloader) {
                    // Remove spinner
                    preloader.style.display = 'none';
                    preloader.classList.remove('blur'); // Remove blur class
                  }
                }
              };

              let payload;

              if (modelGpt.startsWith("gpt-5-")) {
                payload = {
                  model: modelGpt,
                  max_output_tokens: max_tokens_gpt,
                  reasoning_effort: reasoning_effort_gpt,
                  verbosity: verbosity_gpt,
                  messages: [
                    {
                      role: 'system',
                      content: "\n\nYou are an expert in E-Commerce Marketing."
                    },
                    {
                      role: 'user',
                      content: conversationState + message,
                    }
                  ]
                };
              } else {
                payload = {
                  model: modelGpt,
                  max_tokens: max_tokens_gpt,
                  temperature: temperatureGpt,
                  top_p: top_p_gpt,
                  n: nGpt,
                  messages: [
                    {
                      role: 'system',
                      content: "\n\nYou are an expert in E-Commerce Marketing."
                    },
                    {
                      role: 'user',
                      content: conversationState + message,
                    }
                  ]
                };
              }

              xhr.send(JSON.stringify(payload));

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

