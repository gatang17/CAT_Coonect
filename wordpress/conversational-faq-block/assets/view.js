(function () {
  var CFG = window.cfaqConfig || {};
  var STR = CFG.strings || {};

  function setup(root) {
    if (root.dataset.cfaqReady) return;
    root.dataset.cfaqReady = 'true';

    var list = root.querySelector('.cfaq__items');
    var form = root.querySelector('.cfaq__ask');
    var empty = root.querySelector('.cfaq__empty');
    var status = root.querySelector('.cfaq__status');
    var input = form && form.querySelector('input[type="text"]');
    var button = form && form.querySelector('button');

    function items() {
      return Array.prototype.slice.call(root.querySelectorAll('.cfaq__item'));
    }

    // Typing still narrows the list. It is a helper, not the action: it shows
    // you whether the thing you are about to ask has already been answered.
    function filter() {
      var query = input ? input.value.trim().toLowerCase() : '';
      var visible = [];
      items().forEach(function (item) {
        var matches = !query || item.textContent.toLowerCase().indexOf(query) !== -1;
        item.hidden = !matches;
        if (matches) visible.push(item);
      });
      if (empty) empty.hidden = visible.length > 0 || !query;
      return visible;
    }

    function say(message, isError) {
      if (!status) return;
      status.textContent = message;
      status.hidden = !message;
      status.classList.toggle('cfaq__status--error', !!isError);
    }

    function open(item) {
      item.hidden = false;
      item.open = true;
      item.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // Show the visitor their own question straight away, marked as unanswered.
    // It is not persisted here — on the next load it is back only once someone
    // has actually answered and published it.
    function appendPending(question) {
      if (!list) return null;
      var item = document.createElement('details');
      item.className = 'cfaq__item cfaq__item--pending';
      var summary = document.createElement('summary');
      summary.textContent = question;
      var answer = document.createElement('div');
      answer.className = 'cfaq__answer';
      answer.textContent = root.dataset.cfaqPendingAnswer || '';
      item.appendChild(summary);
      item.appendChild(answer);
      list.appendChild(item);
      return item;
    }

    function findByText(question) {
      var needle = question.trim().toLowerCase();
      return items().filter(function (item) {
        var s = item.querySelector('summary');
        return s && s.textContent.trim().toLowerCase() === needle;
      })[0];
    }

    if (input) input.addEventListener('input', filter);
    if (!form) return;

    form.addEventListener('submit', function (event) {
      event.preventDefault();

      var question = input.value.trim();
      var min = CFG.minLength || 10;
      var max = CFG.maxLength || 300;

      if (question.length < min) { say(STR.tooShort, true); return; }
      if (question.length > max) { say(STR.tooLong, true); return; }

      var already = findByText(question);
      if (already) { say(STR.duplicate); open(already); return; }

      button.disabled = true;
      say(STR.sending);

      var hp = form.querySelector('input[name="cfaq_hp"]');
      fetch(CFG.endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ question: question, cfaq_hp: hp ? hp.value : '' })
      })
        .then(function (response) {
          return response.json().then(function (data) {
            return { ok: response.ok, data: data };
          });
        })
        .then(function (result) {
          button.disabled = false;

          if (!result.ok) {
            say((result.data && result.data.message) || STR.failed, true);
            return;
          }

          if (result.data && result.data.status === 'duplicate') {
            var match = findByText(result.data.question || question);
            say(STR.duplicate);
            if (match) open(match); else appendPending(question);
            input.value = '';
            filter();
            return;
          }

          var added = appendPending(question);
          input.value = '';
          filter();
          if (added) open(added);
          say(root.dataset.cfaqSuccess || '');
        })
        .catch(function () {
          button.disabled = false;
          say(STR.failed, true);
        });
    });
  }

  function init() {
    Array.prototype.forEach.call(document.querySelectorAll('.cfaq'), setup);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
