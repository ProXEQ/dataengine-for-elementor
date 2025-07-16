// src/js/editor.js

import {
    autocompletion,
    closeBrackets,
    completionKeymap,
    startCompletion,
} from "@codemirror/autocomplete";
import { defaultKeymap, indentWithTab } from "@codemirror/commands";
import { html } from "@codemirror/lang-html";
import { EditorState, RangeSetBuilder } from "@codemirror/state";
import { oneDark } from "@codemirror/theme-one-dark";
import { Decoration, EditorView, keymap, ViewPlugin } from "@codemirror/view";
// NEW: Import the linter and lintGutter for error display
import { linter, lintGutter } from "@codemirror/lint";

jQuery(document).ready(function ($) {
    console.log("DataEngine: editor.bundle.js loaded successfully.");

    /**
     * ========================================================================
     * 1. GRANULAR SYNTAX HIGHLIGHTING (UNCHANGED)
     * ========================================================================
     */
    const D = {
        delim: Decoration.mark({ class: "cm-de-delim" }),
        source: Decoration.mark({ class: "cm-de-source" }),
        separator: Decoration.mark({ class: "cm-de-separator" }),
        field: Decoration.mark({ class: "cm-de-field" }),
        property: Decoration.mark({ class: "cm-de-property" }),
        pipe: Decoration.mark({ class: "cm-de-pipe" }),
        filter: Decoration.mark({ class: "cm-de-filter" }),
        conditional: Decoration.mark({ class: "cm-de-conditional" }),
    };

    // MODIFIED: Add styling for the linter (error messages)
    const dataEngineTheme = EditorView.baseTheme({
        "& .cm-de-delim": { color: "#ff6900", fontWeight: "bold" },
        "& .cm-de-source": { color: "#00bcff", fontWeight: "bold" },
        "& .cm-de-separator": { color: "#fdc700" },
        "& .cm-de-field": { color: "#b8e6fe" },
        "& .cm-de-property": { color: "#8ec5ff" },
        "& .cm-de-pipe": { color: "#fdc700", fontWeight: "bold" },
        "& .cm-de-filter": { color: "#fff085" },
        "& .cm-de-conditional": { color: "#bbf451", fontStyle: "italic" },
        // NEW: Styles for linting gutter and diagnostics
        ".cm-lintRange-error": {
            backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3E%3Cpath d='M0 1.5 L8 1.5 M0 3.5 L8 3.5 M0 5.5 L8 5.5' stroke='%23e06c75' stroke-width='1.2'/%3E%3C/svg%3E")`,
            backgroundRepeat: "repeat-x",
            backgroundPosition: "bottom",
        },
        ".cm-tooltip-lint": {
            backgroundColor: "#3b2323",
            border: "1px solid #e06c75",
            color: "#e06c75",
        },
    });

    const dataEngineSyntaxHighlighting = ViewPlugin.fromClass(
        class {
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
                const decorations = [];

                // Process each visible range
                for (const { from, to } of view.visibleRanges) {
                    const text = view.state.doc.sliceString(from, to);

                    // Find conditional tags first
                    const conditionalRegex = /\[\/?if[^\]]*\]|\[else(?: if)?[^\]]*\]/g;
                    let match;
                    while ((match = conditionalRegex.exec(text)) !== null) {
                        const start = from + match.index;
                        const end = start + match[0].length;
                        decorations.push({
                            from: start,
                            to: end,
                            decoration: D.conditional,
                        });
                    }

                    // NEW: Updated regex to correctly handle filters with spaces.
                    const tagRegex = /(%)([\w]+)(:)([\w.-]+)(?:\s*(\|)\s*([^%]*?))?(%)/g;

                    while ((match = tagRegex.exec(text))) {
                        const matchStart = from + match.index;
                        let pos = matchStart;

                        // match[1] is the opening '%'
                        decorations.push({
                            from: pos,
                            to: pos + match[1].length,
                            decoration: D.delim,
                        });
                        pos += match[1].length;

                        // match[2] is the source
                        decorations.push({
                            from: pos,
                            to: pos + match[2].length,
                            decoration: D.source,
                        });
                        pos += match[2].length;

                        // match[3] is the ':'
                        decorations.push({
                            from: pos,
                            to: pos + match[3].length,
                            decoration: D.separator,
                        });
                        pos += match[3].length;

                        // match[4] is the field with optional properties
                        const fieldPart = match[4];
                        if (fieldPart.includes(".")) {
                            const dotIndex = fieldPart.indexOf(".");
                            decorations.push({
                                from: pos,
                                to: pos + dotIndex,
                                decoration: D.field,
                            });
                            decorations.push({
                                from: pos + dotIndex,
                                to: pos + dotIndex + 1,
                                decoration: D.separator,
                            });
                            decorations.push({
                                from: pos + dotIndex + 1,
                                to: pos + fieldPart.length,
                                decoration: D.property,
                            });
                        } else {
                            decorations.push({
                                from: pos,
                                to: pos + fieldPart.length,
                                decoration: D.field,
                            });
                        }
                        pos += fieldPart.length;

                        // Check for filter part (now correctly handles spaces)
                        // match[5] is the '|' and match[6] is the filter content
                        if (match[5] && match[6] !== undefined) {
                            const spaceBeforePipe = match[0]
                                .substring(pos - matchStart)
                                .match(/^\s*/)[0].length;
                            pos += spaceBeforePipe;

                            // Pipe |
                            decorations.push({ from: pos, to: pos + 1, decoration: D.pipe });
                            pos += 1;

                            const spaceAfterPipe = match[0]
                                .substring(pos - matchStart)
                                .match(/^\s*/)[0].length;
                            pos += spaceAfterPipe;

                            // Filter content
                            const filterContent = match[6].trimEnd(); // Trim trailing spaces if any
                            decorations.push({
                                from: pos,
                                to: pos + filterContent.length,
                                decoration: D.filter,
                            });
                            pos += filterContent.length;
                            pos += match[6].length - filterContent.length; // Add back trailing spaces to position
                        }

                        // Find the closing '%'
                        const closingPercentIndex = match[0].lastIndexOf("%");
                        if (closingPercentIndex > 0) {
                            const closingPos = matchStart + closingPercentIndex;
                            decorations.push({
                                from: closingPos,
                                to: closingPos + 1,
                                decoration: D.delim,
                            });
                        }
                    }
                }

                // CRITICAL: Sort decorations by position before adding to builder
                decorations.sort((a, b) => a.from - b.from);

                // Add sorted decorations to builder
                for (const { from, to, decoration } of decorations) {
                    // A safety check to prevent overlapping decorations which can cause issues.
                    if (
                        !decorations.some(
                            (d) =>
                                d !== decoration && d.from < to && d.to > from && d.from < from
                        )
                    ) {
                        builder.add(from, to, decoration);
                    }
                }

                return builder.finish();
            }
        },
        {
            decorations: (v) => v.decorations,
        }
    );

    /**
     * ========================================================================
     * NEW: 2. REAL-TIME SYNTAX VALIDATION (LINTING)
     * ========================================================================
     * This function provides real-time error checking against our data dictionary.
     */
    const dataEngineLinter = (dictionary) =>
        linter((view) => {
            let diagnostics = [];
            const text = view.state.doc.toString();
            const tagRegex = /%([a-zA-Z0-9_.-]+:[a-zA-Z0-9_.-]+(?:\|[^%]+)?)%/g;
            let match;

            while ((match = tagRegex.exec(text))) {
                const fullTag = match[1];
                const tagContent = fullTag.split("|")[0];
                const [source, fieldPath] = tagContent.split(":");

                // Rule 1: Validate data source
                if (!["acf", "post", "sub"].includes(source)) {
                    diagnostics.push({
                        from: match.index + 1,
                        to: match.index + 1 + source.length,
                        severity: "error",
                        message: `Unknown data source: '${source}'. Available: acf, post, sub.`,
                    });
                    continue;
                }

                // Rule 2: Validate field name existence
                if (fieldPath) {
                    const fieldName = fieldPath.split(".")[0];
                    if (
                        dictionary[source] &&
                        !dictionary[source].some((field) => field.name === fieldName)
                    ) {
                        diagnostics.push({
                            from: match.index + 1 + source.length + 1,
                            to: match.index + 1 + source.length + 1 + fieldName.length,
                            severity: "error",
                            message: `Field '${fieldName}' not found in '${source}' source.`,
                        });
                    }
                }

                // Rule 3: Validate filters (FIXED)
                if (fullTag.includes('|') && dictionary.filters) {
                    const filtersPart = fullTag.split('|').slice(1);

                    filtersPart.forEach((filterStr, index) => {
                        const cleanFilterStr = filterStr.trim();
                        if (!cleanFilterStr) return;

                        const filterName = cleanFilterStr.split('(')[0].trim();

                        if (!dictionary.filters.some(f => f.name === filterName)) {
                            // Calculate position more accurately
                            const filterStartIndex = fullTag.indexOf(filterStr);
                            diagnostics.push({
                                from: match.index + 1 + filterStartIndex,
                                to: match.index + 1 + filterStartIndex + filterName.length,
                                severity: 'error',
                                message: `Unknown filter: '${filterName}'. Available: ${dictionary.filters.map(f => f.name).join(', ')}`
                            });
                        }
                    });
                }
            }

            return diagnostics;
        });

    /**
     * ========================================================================
     * 3. AUTOCOMPLETION LOGIC (UNCHANGED)
     * ========================================================================
     */
    // ... (ta sekcja pozostaje bez zmian)
    function createDataEngineCompletionSource(dictionary) {
        return (context) => {
            const line = context.state.doc.lineAt(context.pos);
            const lineText = line.text;
            const posInLine = context.pos - line.from;

            // Find the tag we're currently in
            const tagRegex = /%([^%]*)$/;
            const beforeCursor = lineText.substring(0, posInLine);
            const tagMatch = beforeCursor.match(tagRegex);

            if (!tagMatch) return null;

            const tagContent = tagMatch[1];
            const tagStart = context.pos - tagContent.length;

            // --- Context: Filter Completion (MOVED TO PROPER POSITION) ---
            const pipeIndex = tagContent.lastIndexOf("|");
            if (pipeIndex > 0) {
                const afterPipe = tagContent.substring(pipeIndex + 1).trim();
                const beforePipe = tagContent.substring(0, pipeIndex);

                // Check if we're after a pipe and should show filters
                if (beforePipe.includes(":") && dictionary.filters) {
                    return {
                        from: tagStart + pipeIndex + 1,
                        options: dictionary.filters.map((filter) => ({
                            label: filter.name,
                            type: "function",
                            info: filter.description || `Filter: ${filter.name}`,
                            detail: filter.label || filter.name,
                            apply: (view, completion, from, to) => {
                                let textToApply = filter.name;

                                // Add parentheses if filter has arguments
                                if (filter.args && filter.args.length > 0) {
                                    const defaultArgs = filter.args
                                        .map((arg) => {
                                            if (arg.type === "string") {
                                                return `'${arg.default || ""}'`;
                                            }
                                            return arg.default || "";
                                        })
                                        .join(", ");
                                    textToApply = `${filter.name}(${defaultArgs})`;
                                }

                                // Auto-close the tag only if we're at the end
                                const nextChar = view.state.doc.sliceString(to, to + 1);
                                if (nextChar !== "%" && nextChar !== "|") {
                                    textToApply += "%";
                                }

                                view.dispatch({
                                    changes: { from, to, insert: textToApply },
                                    selection: { anchor: from + textToApply.length },
                                });
                            },
                        })),
                        validFor: /^[\w-]*$/,
                    };
                }
            }

            // --- Context: Property Completion ---
            const dotIndex = tagContent.lastIndexOf(".");
            if (dotIndex > 0 && dotIndex > pipeIndex) { // FIXED: Make sure we're not in filter context
                const beforeDot = tagContent.substring(0, dotIndex);

                if (beforeDot.includes(":") && !beforeDot.includes("|")) { // FIXED: Exclude filter context
                    const [source, fieldName] = beforeDot.split(":");
                    if (source === "acf" && dictionary.acf) {
                        const acfField = dictionary.acf.find((f) => f.name === fieldName);
                        if (acfField && acfField.properties) {
                            return {
                                from: tagStart + dotIndex + 1,
                                options: acfField.properties.map((prop) => ({
                                    label: prop.name,
                                    type: "property",
                                    info: prop.label,
                                    apply: (view, completion, from, to) => {
                                        const needsClosing = view.state.doc.sliceString(to, to + 1) !== "%";
                                        const textToApply = needsClosing ? `${prop.name}%` : prop.name;

                                        view.dispatch({
                                            changes: { from, to, insert: textToApply },
                                            selection: { anchor: from + textToApply.length },
                                        });
                                    },
                                })),
                                validFor: /^[\w-]*$/,
                            };
                        }
                    }
                }
                return null;
            }

            // --- Context: Field Name Completion ---
            const colonIndex = tagContent.indexOf(":");
            if (colonIndex > 0 && !tagContent.includes("|")) { // FIXED: Exclude filter context
                const source = tagContent.substring(0, colonIndex);

                if (dictionary[source]) {
                    return {
                        from: tagStart + colonIndex + 1,
                        options: dictionary[source].map((field) => ({
                            label: field.name,
                            type: "variable",
                            info: field.label,
                            apply: (view, completion, from, to) => {
                                const hasProperties = field.properties && field.properties.length > 0;
                                let textToApply;

                                if (hasProperties) {
                                    textToApply = `${field.name}.`;
                                } else {
                                    const needsClosing = view.state.doc.sliceString(to, to + 1) !== "%";
                                    textToApply = needsClosing ? `${field.name}%` : field.name;
                                }

                                view.dispatch({
                                    changes: { from, to, insert: textToApply },
                                    selection: { anchor: from + textToApply.length },
                                });

                                if (hasProperties) {
                                    setTimeout(() => {
                                        startCompletion(view);
                                    }, 10);
                                }
                            },
                        })),
                        validFor: /^[\w-]*$/,
                    };
                }
                return null;
            }

            // --- Context: Source Completion ---
            return {
                from: tagStart,
                options: ["acf", "post", "sub"].map((label) => ({
                    label,
                    type: "namespace",
                    info: `Data from "${label}" source`,
                    apply: (view, completion, from, to) => {
                        const textToApply = `${label}:`;

                        view.dispatch({
                            changes: { from, to, insert: textToApply },
                            selection: { anchor: from + textToApply.length },
                        });

                        setTimeout(() => {
                            startCompletion(view);
                        }, 10);
                    },
                })),
                validFor: /^\w*$/,
            };
        };
    }

    /**
     * ========================================================================
     * 4. MODAL AND EDITOR INITIALIZATION (WITH LINTING)
     * ========================================================================
     * Added the linter and lintGutter extensions.
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
        // NEW: Create the linter instance with our data dictionary
        const liveLinter = dataEngineLinter(dataDictionary);

        const editorState = EditorState.create({
            doc: initialContent,
            extensions: [
                oneDark,
                EditorView.lineWrapping,
                html(),
                dataEngineTheme,
                dataEngineSyntaxHighlighting,
                closeBrackets(),
                autocompletion({
                    override: [dataEngineCompletions],
                    activateOnTyping: true,
                    maxRenderedOptions: 20,
                }),
                // NEW: Enable the linter and the gutter for displaying icons
                liveLinter,
                lintGutter(),
                keymap.of([
                    ...completionKeymap,
                    ...defaultKeymap,
                    indentWithTab,
                    {
                        key: "Escape",
                        run: (view) => {
                            $("#de-cancel-button").click();
                            return true;
                        },
                    },
                ]),
            ],
        });

        const editorView = new EditorView({
            state: editorState,
            parent: contentArea,
        });

        $("#de-cancel-button").on("click", function () {
            onCancel();
            $(".data-engine-modal").remove();
        });

        $("#de-save-button").on("click", function () {
            onSave(editorView.state.doc.toString());
            $(".data-engine-modal").remove();
        });
    }

    /**
     * ========================================================================
     * 5. OVERLAY AND MAIN BUTTON LOGIC (UNCHANGED)
     * ========================================================================
     */
    // ... (ta sekcja pozostaje bez zmian)
    function showOverlay(content) {
        $(".data-engine-overlay").remove();
        $("body").append(`<div class="data-engine-overlay">${content}</div>`);
        setTimeout(() => $(".data-engine-overlay").addClass("is-visible"), 10);
    }

    function hideOverlay() {
        $(".data-engine-overlay")
            .removeClass("is-visible")
            .delay(300)
            .queue(function () {
                $(this).remove();
            });
    }

    function getRepeaterContextFromDOM($clickedButton) {
        // Look for the repeater field name input in multiple ways
        const selectors = [
            'input[data-setting="repeater_field_name"]',
            'input[name*="repeater_field_name"]',
            ".data-engine-repeater-name-input input",
        ];

        const containers = [
            $clickedButton.closest(".elementor-panel-controls"),
            $clickedButton.closest(".elementor-widget-controls"),
            $clickedButton.closest(".elementor-panel"),
            $("#elementor-panel"),
        ];

        for (let container of containers) {
            if (container.length > 0) {
                for (let selector of selectors) {
                    const $input = container.find(selector);
                    if ($input.length > 0) {
                        const value = $input.val();
                        if (value) {
                            console.log(
                                `DataEngine: Found repeater field name "${value}" using selector "${selector}" in container:`,
                                container[0]
                            );
                            return value;
                        }
                    }
                }
            }
        }

        return null;
    }

    const $panel = $("#elementor-panel");
    $panel.on(
        "click",
        'button.elementor-button[data-event="data-engine:launch-editor"]',
        function () {
            const $clickedButton = $(this);
            const $textarea = $(this)
                .closest(".elementor-control")
                .prevAll(".elementor-control-type-textarea")
                .first()
                .find("textarea");

            if (!$textarea.length) {
                console.error("DataEngine Error: Could not find the target textarea.");
                return;
            }

            showOverlay(
                `<div class="data-engine-loader"><div class="spinner"></div><div class="status-text">Fetching data dictionary...</div></div>`
            );

            const editorConfig = elementor.config.document;
            const ajaxData = {
                action: "data_engine_get_data_dictionary",
                nonce: DataEngineEditorConfig.nonce,
            };

            // --- NEW: Get the exact same context that %post:ID% uses ---
            let contextPostId = null;

            // Method 1: Check for preview_id in document settings (this is what %post:ID% uses)
            if (
                editorConfig.settings &&
                editorConfig.settings.settings &&
                editorConfig.settings.settings.preview_id
            ) {
                contextPostId = editorConfig.settings.settings.preview_id;
                console.log(
                    "DataEngine: Using preview_id from document.settings.settings:",
                    contextPostId
                );
            }

            // Method 2: Fallback to document.id (template ID)
            else if (editorConfig.id) {
                contextPostId = editorConfig.id;
                console.log("DataEngine: Using document.id (template):", contextPostId);
            }

            // Send the context post ID that matches %post:ID% logic
            if (contextPostId) {
                ajaxData.context_post_id = contextPostId;
                console.log("DataEngine: Sending context_post_id:", contextPostId);
            }

            // Also send template info for debugging
            ajaxData.template_id = editorConfig.id;
            ajaxData.is_preview = !!(
                editorConfig.settings &&
                editorConfig.settings.settings &&
                editorConfig.settings.settings.preview_id
            );

            // Check for repeater context
            const $repeaterNameInput = $clickedButton
                .closest("#elementor-controls")
                .find(".data-engine-repeater-name-input input");
            if ($repeaterNameInput.length > 0 && $repeaterNameInput.val()) {
                ajaxData.repeater_context_field = $repeaterNameInput.val();
                console.log(
                    "DataEngine: Added repeater context:",
                    ajaxData.repeater_context_field
                );
            }

            console.log("DataEngine: Final AJAX data:", ajaxData);

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
                            $(".data-engine-overlay").empty();
                            showEditorModal(
                                $textarea.val(),
                                response.data,
                                (newContent) => {
                                    $textarea.val(newContent).trigger("input");
                                    showOverlay(
                                        `<div class="data-engine-success"><div class="icon-checkmark">✓</div><div>Saved successfully!</div></div>`
                                    );
                                    setTimeout(hideOverlay, 1500);
                                },
                                () => {
                                    console.log("DataEngine: Editor closed without saving.");
                                    hideOverlay();
                                }
                            );
                        }, 300);
                    } else {
                        hideOverlay();
                        alert(
                            "Error: Could not fetch data dictionary from the server. " +
                            (response.data?.message || "")
                        );
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    hideOverlay();
                    alert("Error: The AJAX request to the server failed. " + textStatus);
                    console.error(
                        "DataEngine AJAX Error:",
                        textStatus,
                        errorThrown,
                        jqXHR.responseText
                    );
                },
            });
        }
    );
});
