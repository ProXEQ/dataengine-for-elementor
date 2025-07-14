import { EditorState, RangeSetBuilder } from '@codemirror/state';
import { EditorView, keymap, Decoration, ViewPlugin } from "@codemirror/view";
import { defaultKeymap, indentWithTab } from "@codemirror/commands";
import { oneDark } from "@codemirror/theme-one-dark";
import { autocompletion, completionKeymap, startCompletion } from '@codemirror/autocomplete';
import { html } from "@codemirror/lang-html";

jQuery(document).ready(function ($) {
  console.log("DataEngine: editor.bundle.js loaded successfully.");

  const dataEngineTagStyling = EditorView.baseTheme({
    "& .cm-data-engine-tag": { color: "#C678DD" },
    "& .cm-data-engine-conditional": { color: "#E5C07B" },
  });

  const dataEngineSyntaxHighlighting = ViewPlugin.fromClass(class {
        constructor(view) {
            this.decorations = this.buildDecorations(view);
        }
        update(update) {
            if (update.docChanged || update.viewportChanged) {
                this.decorations = this.buildDecorations(update.view);
            }
        }
        buildDecorations(view) {
            const builder = new RangeSetBuilder();
            const tagRegex = /%[^%]+%/g;
            const conditionalRegex = /\[\/?if[^\]]*\]|\[else(?: if)?[^\]]*\]/g;
            for (let { from, to } of view.visibleRanges) {
                let text = view.state.doc.sliceString(from, to);
                let match;
                while ((match = tagRegex.exec(text))) {
                    builder.add(from + match.index, from + match.index + match[0].length, Decoration.mark({ class: 'cm-data-engine-tag' }));
                }
                while ((match = conditionalRegex.exec(text))) {
                    builder.add(from + match.index, from + match.index + match[0].length, Decoration.mark({ class: 'cm-data-engine-conditional' }));
                }
            }
            return builder.finish();
        }
    }, {
        decorations: v => v.decorations
    });

  function createDataEngineCompletionSource(dictionary) {
    return (context) => {
      let match = context.matchBefore(/%[\w:.-]*$/);
      if (!match) return null;
      if (match.from > 0) {
        const prevChar = context.state.sliceDoc(match.from - 1, match.from);
        if (/[\w_]/.test(prevChar)) {
          return null;
        }
      }
      const innerText = match.text.substring(1);
      if (innerText.includes(".")) {
        const parts = innerText.split(":");
        if (parts.length < 2 || parts[0] !== "acf") return null;
        const fieldName = parts[1].split(".")[0];
        const acfField = dictionary.acf.find((f) => f.name === fieldName);
        if (acfField && acfField.properties) {
          return {
            from: match.from + innerText.lastIndexOf(".") + 2,
            options: acfField.properties.map((prop) => ({
              label: prop.name,
              type: "property",
              info: prop.label,
            })),
          };
        }
      }
      if (innerText.includes(":")) {
        const [source] = innerText.split(":");
        if (dictionary[source]) {
          return {
            from: match.from + source.length + 2,
            options: dictionary[source].map((field) => ({
              label: field.name,
              type: "variable",
              info: field.label,
              apply:
                field.properties && field.properties.length > 1
                  ? `${field.name}.`
                  : field.name,
            })),
          };
        }
      }
      return {
        from: match.from + 1,
        options: ["acf", "post", "sub"].map((label) => ({
          label,
          type: "namespace",
          apply: `${label}:`,
        })),
      };
    };
  }

  /**
   * Tworzy i pokazuje modal z edytorem CodeMirror, teraz z obsługą anulowania.
   */
  function showEditorModal(initialContent, dataDictionary, onSave, onCancel) {
    const modalHTML = `
            <div class="data-engine-modal">
                <div class="modal-header">DataEngine Live Editor</div>
                <div class="modal-content"></div>
                <div class="modal-footer">
                    <button class="elementor-button" id="de-cancel-button">Cancel</button>
                    <button class="elementor-button elementor-button-success" id="de-save-button">Save & Close</button>
                </div>
            </div>`;
    $("body").append(modalHTML);
    const contentArea = document.querySelector(
      ".data-engine-modal .modal-content"
    );

    const dataEngineCompletions =
      createDataEngineCompletionSource(dataDictionary);

    const editorState = EditorState.create({
      doc: initialContent,
      extensions: [
        oneDark,
        EditorView.lineWrapping,
        html(),
        dataEngineSyntaxHighlighting,
        dataEngineTagStyling,
        autocompletion({ override: [dataEngineCompletions] }),
        // --- ZMIANA 2: Poprawna obsługa klawiszy, w tym ESC ---
        keymap.of([
          ...completionKeymap,
          ...defaultKeymap,
          indentWithTab, // Umożliwia wcięcia tabulatorem
          {
            key: "Escape",
            run: (view) => {
              // Programowe kliknięcie naszego nowego przycisku "Cancel"
              $("#de-cancel-button").click();
              return true; // Zwracamy true, aby zatrzymać dalszą propagację zdarzenia
            },
          },
        ]),
        EditorView.updateListener.of(update => {
                    if (update.docChanged) {
                        const lastChange = update.changes.map.mapPos(update.changes.map.mapPos(update.startState.selection.main.head, -1), 1);
                        const lastChar = update.state.sliceDoc(lastChange - 1, lastChange);
                        if (lastChar === ':') {
                           startCompletion(update.view);
                        }
                    }
                })
      ],
    });
    const editorView = new EditorView({
      state: editorState,
      parent: contentArea,
    });

    // --- ZMIANA 3: Dodanie obsługi dla przycisku "Cancel" ---
    $("#de-cancel-button").on("click", function () {
      onCancel(); // Wywołujemy callback anulowania
      $(".data-engine-modal").remove();
    });

    $("#de-save-button").on("click", function () {
      onSave(editorView.state.doc.toString());
      $(".data-engine-modal").remove();
    });
  }

  /**
   * Pokazuje nakładkę z dynamiczną zawartością.
   */
  function showOverlay(content) {
    $(".data-engine-overlay").remove();
    $("body").append(`<div class="data-engine-overlay">${content}</div>`);
    setTimeout(() => $(".data-engine-overlay").addClass("is-visible"), 10);
  }

  /**
   * Ukrywa i usuwa nakładkę z opóźnieniem.
   */
  function hideOverlay() {
    $(".data-engine-overlay")
      .removeClass("is-visible")
      .delay(300)
      .queue(function () {
        $(this).remove();
      });
  }

  // --- GŁÓWNA LOGIKA NASŁUCHU NA PRZYCISK ---
  const $panel = $("#elementor-panel");
  $panel.on(
    "click",
    'button.elementor-button[data-event="data-engine:launch-editor"]',
    function () {
      const $textarea = $(this)
        .closest(".elementor-control")
        .prev(".elementor-control-type-textarea")
        .find("textarea");

      showOverlay(`
            <div class="data-engine-loader">
                <div class="spinner"></div>
                <div class="status-text">Fetching data dictionary...</div>
            </div>`);

      const editorConfig = elementor.config.document;
      const ajaxData = {
        action: "data_engine_get_data_dictionary",
        nonce: DataEngineEditorConfig.nonce,
      };
      if (editorConfig.preview && editorConfig.preview.id) {
        ajaxData.preview_id = editorConfig.preview.id;
      } else {
        ajaxData.post_id = editorConfig.id;
      }

      $.ajax({
        url: DataEngineEditorConfig.ajax_url,
        type: "POST",
        data: ajaxData,
        success: function (response) {
          if (response.success) {
            $(".data-engine-overlay .status-text").text(
              "Initializing editor..."
            );

            setTimeout(function () {
              // --- ZMIANA 4: Nakładka NIE jest już ukrywana ---
              // Usuwamy tylko zawartość (spinner i tekst), pozostawiając ciemne tło.
              $(".data-engine-overlay").empty();

              showEditorModal(
                $textarea.val(),
                response.data,
                // Funkcja `onSave` (po kliknięciu "Save & Close"):
                function (newContent) {
                  $textarea.val(newContent).trigger("input");
                  showOverlay(`
                                    <div class="data-engine-success">
                                        <div class="icon-checkmark">✓</div>
                                        <div>Saved successfully!</div>
                                    </div>`);
                  setTimeout(hideOverlay, 1500);
                },
                // Funkcja `onCancel` (po kliknięciu "Cancel" lub ESC):
                function () {
                  console.log("DataEngine: Editor closed without saving.");
                  hideOverlay(); // Ukrywamy nakładkę po anulowaniu
                }
              );
            }, 300);
          } else {
            hideOverlay();
            alert("Error: Could not fetch data dictionary from the server.");
          }
        },
        error: function () {
          hideOverlay();
          alert("Error: The AJAX request to the server failed.");
        },
      });
    }
  );
});
