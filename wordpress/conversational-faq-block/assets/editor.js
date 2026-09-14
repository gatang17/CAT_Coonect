(function (blocks, blockEditor, components, element, i18n) {
  var el = element.createElement;
  var Fragment = element.Fragment;
  var InspectorControls = blockEditor.InspectorControls;
  var RichText = blockEditor.RichText;
  var useBlockProps = blockEditor.useBlockProps;
  var PanelBody = components.PanelBody;
  var TextControl = components.TextControl;
  var TextareaControl = components.TextareaControl;
  var ToggleControl = components.ToggleControl;
  var Button = components.Button;
  var ColorPalette = components.ColorPalette;
  var __ = i18n.__;

  blocks.registerBlockType('cfaq/conversational-faq', {
    edit: function (props) {
      var a = props.attributes;
      var set = props.setAttributes;
      var blockProps = useBlockProps({ className: 'cfaq-editor' });

      function updateItem(index, key, value) {
        var items = a.items.map(function (item, i) {
          if (i !== index) return item;
          var next = Object.assign({}, item);
          next[key] = value;
          return next;
        });
        set({ items: items });
      }

      function addItem() {
        set({ items: a.items.concat([{ question: __('New question', 'conversational-faq-block'), answer: __('Add your answer here.', 'conversational-faq-block') }]) });
      }

      function removeItem(index) {
        set({ items: a.items.filter(function (_, i) { return i !== index; }) });
      }

      return el(Fragment, {},
        el(InspectorControls, {},
          el(PanelBody, { title: __('FAQ settings', 'conversational-faq-block'), initialOpen: true },
            el(TextControl, { label: __('Suggested questions label', 'conversational-faq-block'), value: a.label, onChange: function (v) { set({ label: v }); } })
          ),
          el(PanelBody, { title: __('Asking a new question', 'conversational-faq-block'), initialOpen: true },
            el(ToggleControl, {
              label: __('Let visitors ask questions', 'conversational-faq-block'),
              help: __('A question arrives as Pending under FAQ Questions. Publishing it is what answers it, and it then joins this conversation.', 'conversational-faq-block'),
              checked: !!a.allowAsking,
              onChange: function (v) { set({ allowAsking: v }); }
            }),
            a.allowAsking && el(TextControl, { label: __('Box placeholder', 'conversational-faq-block'), value: a.placeholder, onChange: function (v) { set({ placeholder: v }); } }),
            a.allowAsking && el(TextControl, { label: __('Button label', 'conversational-faq-block'), value: a.askLabel, onChange: function (v) { set({ askLabel: v }); } }),
            a.allowAsking && el(TextareaControl, {
              label: __('Holding answer', 'conversational-faq-block'),
              help: __('Shown under the visitor\'s own question until a real answer is published.', 'conversational-faq-block'),
              value: a.pendingAnswer,
              onChange: function (v) { set({ pendingAnswer: v }); }
            }),
            a.allowAsking && el(TextareaControl, {
              label: __('Confirmation message', 'conversational-faq-block'),
              value: a.askSuccess,
              onChange: function (v) { set({ askSuccess: v }); }
            })
          ),
          el(PanelBody, { title: __('Colors', 'conversational-faq-block'), initialOpen: false },
            el('p', { style: { fontSize: '12px', fontStyle: 'italic' } }, __('These two paint the question bubble and the Ask button, which the panels below cannot reach. Background, text, border and typography are in those panels — the cards follow whatever you set there.', 'conversational-faq-block')),
            el('p', {}, __('Accent color', 'conversational-faq-block')),
            el(ColorPalette, { value: a.accentColor, onChange: function (v) { set({ accentColor: v || '#8cc63f' }); } }),
            el('p', {}, __('Conversation color', 'conversational-faq-block')),
            el(ColorPalette, { value: a.darkColor, onChange: function (v) { set({ darkColor: v || '#111111' }); } })
          )
        ),
        el('section', blockProps,
          el(RichText, { tagName: 'h2', value: a.title, placeholder: __('Help', 'conversational-faq-block'), onChange: function (v) { set({ title: v }); } }),
          el(RichText, { tagName: 'p', value: a.subtitle, placeholder: __('Subtitle', 'conversational-faq-block'), onChange: function (v) { set({ subtitle: v }); } }),
          el(RichText, { tagName: 'p', value: a.greeting, placeholder: __('Greeting', 'conversational-faq-block'), onChange: function (v) { set({ greeting: v }); } }),
          el('p', {}, a.label),
          a.items.map(function (item, index) {
            return el('div', { className: 'cfaq-editor__item', key: index },
              el(TextControl, { label: __('Question', 'conversational-faq-block'), value: item.question, onChange: function (v) { updateItem(index, 'question', v); } }),
              el(TextareaControl, { label: __('Answer', 'conversational-faq-block'), value: item.answer, onChange: function (v) { updateItem(index, 'answer', v); } }),
              el('div', { className: 'cfaq-editor__item-actions' },
                el(Button, { isDestructive: true, isSmall: true, onClick: function () { removeItem(index); } }, __('Remove', 'conversational-faq-block'))
              )
            );
          }),
          el(Button, { variant: 'primary', onClick: addItem }, __('Add question', 'conversational-faq-block')),
          a.allowAsking && el('p', { className: 'cfaq-editor__ask-note' },
            __('Visitors see an “', 'conversational-faq-block') + a.askLabel + __('” box here. Answered questions from FAQ Questions are appended above automatically.', 'conversational-faq-block'))
        )
      );
    },
    save: function () { return null; }
  });
})(window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n);

