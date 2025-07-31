jQuery(document).ready(function ($) {
    console.log("DataEngine Import/Export Field loaded");

    if (typeof deImportExport === 'undefined') {
        console.warn('deImportExport not defined, using fallback values');
        window.deImportExport = {
            ajaxurl: ajaxurl || '/wp-admin/admin-ajax.php',
            nonce: '',
            post_id: 0
        };
    }

    /**
     * Import/Export Field Handler
     */
    class ImportExportHandler {
        constructor() {
            this.currentField = null;
            this.currentTargetField = null;
            this.uploadedFile = null;
            this.previewData = null;

            this.init();
        }

        init() {
            this.bindEvents();
            this.initializeFields();
        }

        /**
         * Bind all event handlers
         */
        bindEvents() {
            // Import events
            $(document).on(
                "change",
                ".import-file-input",
                this.handleFileChange.bind(this)
            );
            $(document).on(
                "click",
                ".import-preview-btn",
                this.handlePreviewClick.bind(this)
            );
            $(document).on("click", ".import-btn", this.handleImportClick.bind(this));

            // Export events
            $(document).on(
                "click",
                ".export-csv-btn",
                this.handleExportClick.bind(this)
            );
            $(document).on(
                "click",
                ".export-json-btn",
                this.handleExportClick.bind(this)
            );

            // UI events
            $(document).on("click", ".de-preview-close", this.hidePreview.bind(this));
            $(document).on(
                "change",
                ".de-overwrite-checkbox",
                this.handleOverwriteChange.bind(this)
            );
        }

        /**
         * Initialize existing fields on page load
         */
        initializeFields() {
            $(".acf-import-export-controls").each((index, element) => {
                const $control = $(element);
                const fieldKey = $control.data("field");
                const targetField = $control.data("target");

                if (fieldKey && targetField) {
                    this.setupFieldControl($control, fieldKey, targetField);
                }
            });
        }

        /**
         * Setup individual field control
         */
        setupFieldControl($control, fieldKey, targetField) {
            // Add loading state capability
            $control.data("field-key", fieldKey);
            $control.data("target-field", targetField);

            // Check if target field has existing data
            this.checkExistingData($control, targetField);

            // Initialize file input styling
            this.initializeFileInput($control);
        }

        initializeFileInput($control) {
            const $fileInput = $control.find(".import-file-input");
            const $importButtons = $control.find(".import-preview-btn, .import-btn");

            // Initially disable import buttons
            $importButtons.prop("disabled", true);

            // Replace default file input with custom design
            this.createCustomFileInput($control);

            // Add drag & drop functionality
            this.addDragDropHandlers($control);
        }

        createCustomFileInput($control) {
            const $fileInput = $control.find(".import-file-input");

            // Wrap the file input
            $fileInput.wrap('<div class="import-file-wrapper"></div>');

            // Create custom UI
            const customHtml = `
                <div class="de-file-input-label">
                    <div class="de-file-input-icon dashicons dashicons-upload"></div>
                    <div class="de-file-input-text">Choose file or drag & drop</div>
                    <div class="de-file-input-hint">Supports CSV and JSON files (max 5MB)</div>
                </div>
            `;

            $fileInput.after(customHtml);

            // Handle click on custom area
            $control.find(".de-file-input-label").on("click", () => {
                $fileInput.click();
            });
        }

        addDragDropHandlers($control) {
            const $dropArea = $control.find(".import-file-wrapper");
            const $fileInput = $control.find(".import-file-input");

            // Prevent default drag behaviors
            ["dragenter", "dragover", "dragleave", "drop"].forEach((eventName) => {
                $dropArea.on(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                });
            });

            // Highlight drop area when item is dragged over it
            ["dragenter", "dragover"].forEach((eventName) => {
                $dropArea.on(eventName, () => {
                    $fileInput.addClass("dragover");
                });
            });

            ["dragleave", "drop"].forEach((eventName) => {
                $dropArea.on(eventName, () => {
                    $fileInput.removeClass("dragover");
                });
            });

            // Handle dropped files
            $dropArea.on("drop", (e) => {
                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    // Simulate file input change
                    const $realInput = $fileInput.find('input[type="file"]');
                    $realInput[0].files = files;
                    $realInput.trigger("change");
                }
            });
        }
        /**
         * Handle file input change
         */

        handleFileChange(e) {
            const $input = $(e.target);
            const $control = $input.closest(".acf-import-export-controls");
            const file = e.target.files[0];

            if (!file) {
                this.resetFileInput($control);
                return;
            }

            // Validate file
            const validation = this.validateFile(file);
            if (!validation.valid) {
                this.showError($control, validation.message);
                this.resetFileInput($control);
                return;
            }
            // 🔥 Smart UI updates based on file type
            this.updateUIForFileType($control, file);

            // Update UI
            this.updateFileInputState($control, file);

            // Store file reference
            this.uploadedFile = file;
            this.currentField = $control.data("field-key");
            this.currentTargetField = $control.data("target-field");

            // Enable preview/import buttons
            $control.find(".import-preview-btn, .import-btn").prop("disabled", false);

            console.log("File selected:", file.name, file.type, file.size);
        }

        /**
         * Handle preview button click
         */
        handlePreviewClick(e) {
            e.preventDefault();

            const $btn = $(e.target);
            const $control = $btn.closest(".acf-import-export-controls");

            if (!this.uploadedFile) {
                this.showError($control, "Please select a file first");
                return;
            }

            this.showPreview($control);
        }

        /**
         * Handle import button click
         */
        handleImportClick(e) {
            e.preventDefault();

            const $btn = $(e.target);
            const $control = $btn.closest(".acf-import-export-controls");

            if (!this.uploadedFile) {
                this.showError($control, "Please select a file first");
                return;
            }

            // Check for existing data
            this.checkExistingDataBeforeImport($control);
        }

        /**
         * Handle export button click
         */
        handleExportClick(e) {
            e.preventDefault();

            const $btn = $(e.target);
            const $control = $btn.closest(".acf-import-export-controls");
            const format = $btn.hasClass("export-csv-btn") ? "csv" : "json";

            const fieldKey = $control.data("field-key");
            const targetField = $control.data("target-field");

            if (!targetField) {
                this.showError($control, "No target field configured");
                return;
            }

            this.performExport($control, targetField, format);
        }

        /**
         * Validate selected file
         */
        validateFile(file) {
            const maxSize = 5 * 1024 * 1024; // 5MB
            const allowedTypes = [
                "text/csv",
                "application/csv",
                "text/plain",
                "application/json",
                "text/json",
            ];
            const allowedExtensions = ["csv", "json"];

            // Check file size
            if (file.size > maxSize) {
                return {
                    valid: false,
                    message: "File too large. Maximum size is 5MB",
                };
            }

            // Check file type
            const fileExtension = file.name.split(".").pop().toLowerCase();
            if (!allowedExtensions.includes(fileExtension)) {
                return {
                    valid: false,
                    message: "Invalid file type. Please select a CSV or JSON file",
                };
            }

            // Check MIME type (as backup)
            if (!allowedTypes.includes(file.type) && file.type !== "") {
                console.warn(
                    "MIME type check failed, but extension is valid:",
                    file.type
                );
            }

            return { valid: true };
        }

        /**
         * Show preview modal
         */
        /**
         * Show import preview
         */
        showPreview($control) {
            if (!this.uploadedFile) {
                this.showError($control, "No file selected");
                return;
            }

            console.log("Starting preview for:", this.uploadedFile.name);

            // Show loading state
            this.showPreviewLoading($control);

            // Prepare form data
            const formData = new FormData();
            formData.append("action", "de_preview_import_data");
            formData.append("field_key", this.currentTargetField);
            formData.append("preview_rows", 5);
            formData.append("import_file", this.uploadedFile);
            formData.append("nonce", deImportExport.nonce);

            // AJAX request
            $.ajax({
                url: deImportExport.ajaxurl,
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                timeout: 30000, // 30 second timeout
                success: (response) => {
                    console.log("Preview response:", response);

                    if (response.success) {
                        this.renderPreview($control, response.data);
                    } else {
                        this.showError(
                            $control,
                            response.data?.message || "Preview failed"
                        );
                    }
                },
                error: (xhr, status, error) => {
                    console.error("Preview AJAX error:", {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        responseText: xhr.responseText,
                        error: error,
                    });

                    let errorMessage = "Preview failed";

                    if (xhr.status === 500) {
                        errorMessage =
                            "Server error occurred. Please check the file format and try again.";
                    } else if (xhr.status === 413) {
                        errorMessage = "File too large. Please use a smaller file.";
                    } else if (status === "timeout") {
                        errorMessage = "Request timeout. Please try again.";
                    } else if (xhr.responseJSON?.data?.message) {
                        errorMessage = xhr.responseJSON.data.message;
                    }

                    this.showError($control, errorMessage);
                },
                complete: () => {
                    this.hidePreviewLoading($control);
                },
            });
        }
        showPreviewLoading($control) {
            const $previewContainer = this.getPreviewContainer($control);

            const loadingHtml = `
                <div class="de-preview-loading">
                    <div class="de-spinner"></div>
                    <p>Loading preview...</p>
                </div>
            `;

            $previewContainer.html(loadingHtml).show();
        }

        hidePreviewLoading($control) {

        }

        getPreviewContainer($control) {
            let $container = $control.find('.import-preview');

            if (!$container.length) {
                $container = $('<div class="import-preview" style="display: none;"></div>');
                $control.append($container);
            }

            return $container;
        }

        updateUIForFileType($control, file) {
            const fileExtension = file.name.split(".").pop().toLowerCase();
            const $previewBtn = $control.find(".import-preview-btn");

            // Show/hide preview button based on file type
            if (fileExtension === "csv" || fileExtension === "json") {
                $previewBtn.show().text(`Preview ${fileExtension.toUpperCase()}`);
            } else {
                $previewBtn.hide();
            }

            // Add file type indicator
            $control
                .removeClass("csv-file json-file xlsx-file")
                .addClass(`${fileExtension}-file`);

            console.log(`UI updated for ${fileExtension} file`);
        }

        /**
         * Render preview data
         */
        renderPreview($container, data) {
            // 🔥 ZMIANA: Użyj getPreviewContainer zamiast bezpośrednio $container
            const $previewContainer = this.getPreviewContainer($container);
            const format = data.format || "csv";

            let html = `
        <div class="de-preview-header">
            <h4>Import Preview (${format.toUpperCase()})</h4>
            <button type="button" class="de-preview-close">&times;</button>
        </div>
        <div class="de-preview-info">
            <p><strong>Format:</strong> ${format.toUpperCase()}</p>
            <p><strong>Total rows:</strong> ${data.total_rows}</p>
            <p><strong>Showing:</strong> First ${data.preview_data.length} rows</p>
        </div>
    `;

            if (data.headers && data.preview_data) {
                html += `
            <div class="de-preview-table">
                <table>
                    <thead>
                        <tr>
        `;

                data.headers.forEach((header) => {
                    html += `<th>${this.escapeHtml(header)}</th>`;
                });

                html += `
                        </tr>
                    </thead>
                    <tbody>
        `;

                data.preview_data.forEach((row) => {
                    html += "<tr>";
                    row.forEach((cell) => {
                        html += `<td>${this.escapeHtml(cell || "")}</td>`;
                    });
                    html += "</tr>";
                });

                html += `
                    </tbody>
                </table>
            </div>
        `;
            }

            // Add field mapping info if available
            if (data.field_mapping) {
                html += `
            <div class="de-preview-mapping">
                <h5>Field Mapping</h5>
                <div class="de-mapping-list">
        `;

                Object.entries(data.field_mapping).forEach(([header, fieldName]) => {
                    const status = fieldName ? "mapped" : "unmapped";
                    const displayName = fieldName || "No matching field";
                    html += `
                <div class="de-mapping-item ${status}">
                    <span class="de-mapping-header">${this.escapeHtml(header)}</span>
                    <span class="de-mapping-arrow">→</span>
                    <span class="de-mapping-field">${this.escapeHtml(displayName)}</span>
                </div>
            `;
                });

                html += `
                </div>
            </div>
        `;
            }

            html += `
        <div class="de-preview-actions">
            <button type="button" class="button de-preview-close">Close</button>
            <button type="button" class="button button-primary de-confirm-import">Import Data</button>
        </div>
    `;

            // 🔥 ZMIANA: Użyj $previewContainer zamiast $container
            $previewContainer.html(html).show();

            // Bind close and import actions
            $previewContainer.find(".de-preview-close").on("click", () => {
                this.hidePreview($container);
            });

            $previewContainer.find(".de-confirm-import").on("click", () => {
                this.hidePreview($container);
                this.performImport($container);
            });
        }

        /**
         * Check for existing data before import
         */
        checkExistingDataBeforeImport($control) {
            const targetField = $control.data("target-field");

            // Get existing data via AJAX or check DOM
            $.ajax({
                url: deImportExport.ajax_url,
                type: "POST",
                data: {
                    action: "de_get_field_structure",
                    field_key: targetField,
                    post_id: deImportExport.post_id,
                    nonce: deImportExport.nonce,
                },
                success: (response) => {
                    if (response && response.success) {
                        // Check if field has existing data
                        const existingRows = this.getExistingRowCount($control);

                        if (existingRows > 0) {
                            this.showOverwriteConfirmation($control, existingRows);
                        } else {
                            this.performImport($control);
                        }
                    } else {
                        this.performImport($control); // Proceed anyway
                    }
                },
                error: () => {
                    this.performImport($control); // Proceed anyway
                },
            });
        }

        /**
         * Show overwrite confirmation
         */
        showOverwriteConfirmation($control, existingRows) {
            const html = `
        <div class="de-overwrite-warning">
            <div class="de-warning-icon">⚠️</div>
            <div class="de-warning-content">
                <h4>Existing Data Found</h4>
                <p>This field already contains <strong>${existingRows} rows</strong> of data.</p>
                <p>Importing will replace all existing data. This action cannot be undone.</p>
                <div class="de-warning-actions">
                    <label>
                        <input type="checkbox" class="de-overwrite-checkbox">
                        I understand that existing data will be replaced
                    </label>
                    <div class="de-warning-buttons">
                        <button type="button" class="button de-cancel-import">Cancel</button>
                        <button type="button" class="button button-primary de-confirm-overwrite" disabled>Import & Replace</button>
                    </div>
                </div>
            </div>
        </div>
    `;

            const $warning = $(html);
            // 🔥 ZMIANA: Użyj getPreviewContainer
            const $previewContainer = this.getPreviewContainer($control);
            $previewContainer.html($warning).show();

            // Bind events
            $warning.find(".de-cancel-import").on("click", () => {
                this.hidePreview($control);
            });

            $warning.find(".de-confirm-overwrite").on("click", () => {
                this.hidePreview($control);
                this.performImport($control, true);
            });
        }

        /**
         * Handle overwrite checkbox change
         */
        handleOverwriteChange(e) {
            const $checkbox = $(e.target);
            const $confirmBtn = $checkbox
                .closest(".de-overwrite-warning")
                .find(".de-confirm-overwrite");

            $confirmBtn.prop("disabled", !$checkbox.is(":checked"));
        }

        /**
         * Perform the actual import
         */
        performImport($control, overwrite = false) {
            const $importBtn = $control.find(".import-btn");
            const originalText = $importBtn.text();

            // Show loading state
            $importBtn.prop("disabled", true).text("Importing...");
            this.showProgress($control, "Importing data...");

            // Determine file format
            const fileExtension = this.uploadedFile.name
                .split(".")
                .pop()
                .toLowerCase();

            // DEBUG: Log values being sent
            console.log("Import data being sent:");
            console.log("- currentField (import field):", this.currentField);
            console.log("- currentTargetField (repeater field):", this.currentTargetField);
            console.log("- post_id:", deImportExport.post_id);
            console.log("- file_format:", fileExtension);

            // Validate required data
            if (!this.currentTargetField) {
                console.error("ERROR: No target field specified!");
                this.hideProgress($control);
                this.showError($control, "No target field configured");
                $importBtn.prop("disabled", false).text(originalText);
                return;
            }

            // Create FormData
            const formData = new FormData();
            formData.append("action", "de_import_repeater_data");
            formData.append("field_key", this.currentTargetField);
            formData.append("post_id", deImportExport.post_id);
            formData.append("file_format", fileExtension);
            formData.append("overwrite_existing", overwrite ? "1" : "0");
            formData.append("import_file", this.uploadedFile);
            formData.append("nonce", deImportExport.nonce);

            // Send AJAX request
            $.ajax({
                url: deImportExport.ajaxurl,
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    this.hideProgress($control);

                    console.log("Import response:", response); // 🔥 DODANO: debug response

                    if (response.success) {
                        const importedRows = (response.data && response.data.imported_rows) || 0;
                        this.showSuccess(
                            $control,
                            `Successfully imported ${importedRows} rows`
                        );
                        this.resetFileInput($control);

                        // Refresh the page after successful import
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        // 🔥 POPRAWIONO: Better error handling
                        if (response.data && response.data.code === "DATA_EXISTS") {
                            this.showOverwriteConfirmation(
                                $control,
                                response.data.existing_rows
                            );
                        } else {
                            // Sprawdź różne możliwe miejsca dla message
                            let errorMessage = "Import failed";
                            if (response.data && response.data.message) {
                                errorMessage = response.data.message;
                            } else if (response.message) {
                                errorMessage = response.message;
                            } else if (typeof response === 'string') {
                                errorMessage = response;
                            }

                            this.showError($control, errorMessage);
                        }
                    }
                },
                error: (xhr, status, error) => {
                    this.hideProgress($control);
                    console.error("Import AJAX error:", {
                        xhr: xhr,
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });

                    let errorMessage = "Import request failed";

                    // Try to parse error response if available
                    try {
                        if (xhr.responseText) {
                            const errorResponse = JSON.parse(xhr.responseText);
                            if (errorResponse.data && errorResponse.data.message) {
                                errorMessage = errorResponse.data.message;
                            }
                        }
                    } catch (e) {
                        // If JSON parsing fails, use default message
                        console.warn("Could not parse error response:", e);
                    }

                    this.showError($control, errorMessage);
                },
                complete: () => {
                    $importBtn.prop("disabled", false).text(originalText);
                },
            });
        }

        /**
         * Perform export
         */
        performExport($control, fieldKey, format) {
            const $exportBtn = $control.find(`.export-${format}-btn`);
            const originalText = $exportBtn.text();

            // Show loading state
            $exportBtn.prop("disabled", true).text("Exporting...");
            this.showProgress($control, "Preparing export...");

            // Send AJAX request
            $.ajax({
                url: deImportExport.ajax_url,
                type: "POST",
                data: {
                    action: "de_export_repeater_data",
                    field_key: fieldKey,
                    post_id: deImportExport.post_id,
                    format: format,
                    nonce: deImportExport.nonce,
                },
                success: (response) => {
                    this.hideProgress($control);

                    if (response && response.success && response.data) {
                        this.downloadFile(response.data.content, response.data.filename);
                        const rowsExported = response.data.rows_exported || 0;
                        this.showSuccess(
                            $control,
                            `Successfully exported ${rowsExported} rows`
                        );
                    } else {
                        const errorMessage = (response && response.data && response.data.message) || 
                                        (response && response.message) || 
                                        "Export failed";
                        this.showError($control, errorMessage);
                    }
                },
                error: (xhr, status, error) => {
                    this.hideProgress($control);
                    console.error("Export AJAX error:", error);
                    this.showError($control, "Export request failed");
                },
                complete: () => {
                    $exportBtn.prop("disabled", false).text(originalText);
                },
            });
        }

        /**
         * Download file
         */
        downloadFile(content, filename) {
            const blob = new Blob([content], { type: "text/plain" });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }

        /**
         * UI Helper Methods
         */

        updateFileInputState($control, file) {
            const $fileInput = $control.find(".import-file-input");
            const $importButtons = $control.find(".import-preview-btn, .import-btn");

            // Add file selected state
            $fileInput.addClass("has-file");

            // Update custom label
            const fileExtension = file.name.split(".").pop().toLowerCase();
            $control.find(".de-file-input-label").html(`
                <div class="de-file-input-icon dashicons dashicons-yes-alt"></div>
                <div class="de-file-input-text">File selected: ${file.name}</div>
                <div class="de-file-input-hint">Click to choose different file</div>
            `);

            // Add enhanced file info
            let $fileInfo = $control.find(".de-file-info");
            if (!$fileInfo.length) {
                $fileInfo = $('<div class="de-file-info"></div>');
                $control.find(".import-file-wrapper").after($fileInfo);
            }

            $fileInfo.html(`
                <div class="de-file-details">
                    <div class="de-file-icon ${fileExtension}">${fileExtension.toUpperCase()}</div>
                    <div class="de-file-meta">
                        <div class="de-file-name">${file.name}</div>
                        <div class="de-file-size">${this.formatFileSize(
                file.size
            )}</div>
                    </div>
                    <button type="button" class="de-file-remove">Remove</button>
                </div>
            `);

            // Bind remove file event
            $fileInfo.find(".de-file-remove").on("click", () => {
                this.resetFileInput($control);
            });

            // Enable import buttons
            $importButtons.prop("disabled", false);
        }

        checkExistingData($control, targetField) {
            // This would check if the target field has existing data
            // For now, we'll implement a simple DOM check
            const existingRows = this.getExistingRowCount($control);

            if (existingRows > 0) {
                $control.addClass("has-existing-data");
                $control.find(".import-section").prepend(`
                    <div class="de-existing-data-notice">
                        <i class="dashicons dashicons-info"></i>
                        Field contains ${existingRows} rows of data
                    </div>
                `);
            }
        }

        getExistingRowCount($control) {
            // Try to find existing repeater rows in the DOM
            const targetField = $control.data("target-field");
            if (!targetField) return 0;

            // This is a simplified check - in reality, you might need to query the field directly
            const $repeaterTable = $(
                `[data-key="${targetField}"] .acf-repeater tbody tr`
            );
            return $repeaterTable.length;
        }

        showProgress($control, message) {
            let $progress = $control.find(".de-progress");
            if (!$progress.length) {
                $progress = $(
                    '<div class="de-progress"><div class="de-progress-bar"></div><div class="de-progress-message"></div></div>'
                );
                $control.append($progress);
            }

            $progress.find(".de-progress-message").text(message);
            $progress.show();
        }

        hideProgress($control) {
            $control.find(".de-progress").hide();
        }

        showSuccess($control, message) {
            this.showNotification($control, message, "success");
        }

        showError($control, message) {
            this.showNotification($control, message, "error");
        }

        showNotification($control, message, type) {
            const $notification = $(`
                <div class="de-notification de-notification-${type}">
                    <div class="de-notification-icon">
                        ${type === "success" ? "✓" : "✗"}
                    </div>
                    <div class="de-notification-message">${message}</div>
                    <button type="button" class="de-notification-close">&times;</button>
                </div>
            `);

            $control.find(".de-notification").remove();
            $control.prepend($notification);

            // Auto-hide after 5 seconds
            setTimeout(() => {
                $notification.fadeOut();
            }, 5000);

            // Bind close event
            $notification.find(".de-notification-close").on("click", () => {
                $notification.fadeOut();
            });
        }

        hidePreview($control) {
            if ($control) {
                $control.find(".import-preview").hide();
            } else {
                $(".import-preview").hide();
            }
        }

        formatFileSize(bytes) {
            if (bytes === 0) return "0 Bytes";
            const k = 1024;
            const sizes = ["Bytes", "KB", "MB", "GB"];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
        }

        escapeHtml(text) {
            const div = document.createElement("div");
            div.textContent = text;
            return div.innerHTML;
        }
        resetFileInput($control) {
            const $fileInput = $control.find(".import-file-input");
            const $importButtons = $control.find(".import-preview-btn, .import-btn");
            const $fileInfo = $control.find(".de-file-info");

            $fileInput.val("").removeClass("has-file");
            $fileInfo.remove();
            $importButtons.prop("disabled", true);

            // Reset custom label
            $control.find(".de-file-input-label").html(`
                <div class="de-file-input-icon dashicons dashicons-upload"></div>
                <div class="de-file-input-text">Choose file or drag & drop</div>
                <div class="de-file-input-hint">Supports CSV and JSON files (max 5MB)</div>
            `);

            // Reset UI state
            $control.removeClass("csv-file json-file xlsx-file");
            $control.find(".import-preview-btn").show().text("Preview");

            this.uploadedFile = null;
            this.hidePreview($control);
        }
    }

    // Initialize the handler
    window.DataEngineImportExport = new ImportExportHandler();
});
