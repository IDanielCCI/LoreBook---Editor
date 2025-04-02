<?php
// index.php
// Main script for the Lorebook Editor web application.

// =============================================================================
// Initialization
// =============================================================================

// Initialize variables used throughout the script.
$loreEntries = []; // Array to hold lorebook entries after a file is loaded.
$errorMessage = ''; // String to hold any error messages encountered during file processing or validation.
$loadedFileName = 'lorebook.json'; // Default filename displayed; updated when a file is successfully loaded.

// =============================================================================
// File Upload Handling (POST Request Processing)
// =============================================================================

// Check if the request method is POST, indicating a potential file upload attempt.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if the 'lorebookFile' field was included in the upload and if there were no upload errors.
    if (isset($_FILES['lorebookFile']) && $_FILES['lorebookFile']['error'] === UPLOAD_ERR_OK) {

        // Get the temporary path where the uploaded file is stored on the server.
        $tempFilePath = $_FILES['lorebookFile']['tmp_name'];
        // Get the original filename from the client. Use basename() for security to prevent path traversal attacks.
        $originalFileName = basename($_FILES['lorebookFile']['name']);

        // --- Basic File Validation ---
        // Check if the file extension is '.json'. This is a basic check and can be spoofed.
        // More robust validation might involve checking the MIME type.
        if (pathinfo($originalFileName, PATHINFO_EXTENSION) === 'json') {

            // --- Read File Content ---
            // Attempt to read the entire content of the uploaded JSON file into a string.
            $jsonString = file_get_contents($tempFilePath);

            // Check if reading the file was successful.
            if ($jsonString !== false) {
                // --- Decode JSON Data ---
                // Attempt to decode the JSON string into a PHP associative array (indicated by the `true` argument).
                $decodedJsonData = json_decode($jsonString, true);

                // Check if any errors occurred during JSON decoding.
                if (json_last_error() === JSON_ERROR_NONE) {
                    // --- Validate JSON Structure ---
                    // Check if the decoded data has the expected top-level 'entries' key and if its value is an array.
                    // This structure is specific to the expected lorebook format.
                    if (isset($decodedJsonData['entries']) && is_array($decodedJsonData['entries'])) {
                        // Success! The file is valid JSON with the expected structure.
                        // Assign the 'entries' array to the $loreEntries variable.
                        $loreEntries = $decodedJsonData['entries'];
                        // Update the $loadedFileName to reflect the name of the file that was actually processed.
                        $loadedFileName = $originalFileName;
                        // No error message is set, indicating success.
                    } else {
                        // Set an error message if the JSON structure is invalid (missing 'entries' or not an array).
                        $errorMessage = 'JSON structure is invalid: Missing or invalid "entries" key.';
                    }
                } else {
                    // Set an error message if JSON decoding failed. Include the filename and the specific JSON error message.
                    // Use htmlspecialchars on the filename for safe display in HTML.
                    $errorMessage = 'Error decoding JSON file (' . htmlspecialchars($originalFileName) . '): ' . json_last_error_msg();
                }
            } else {
                // Set an error message if the uploaded file could not be read from its temporary location.
                $errorMessage = 'Error reading uploaded file: ' . htmlspecialchars($originalFileName);
            }
        } else {
            // Set an error message if the uploaded file does not have a '.json' extension.
            $errorMessage = 'Invalid file type. Please upload a .json file.';
        }
    } else {
        // --- Handle Specific Upload Errors ---
        // Determine the specific upload error code if one occurred. Default to UPLOAD_ERR_NO_FILE if not set.
        $uploadError = isset($_FILES['lorebookFile']['error']) ? $_FILES['lorebookFile']['error'] : UPLOAD_ERR_NO_FILE;
        // Provide user-friendly messages based on common PHP upload error constants.
        switch ($uploadError) {
            case UPLOAD_ERR_INI_SIZE: // File exceeds upload_max_filesize in php.ini
            case UPLOAD_ERR_FORM_SIZE: // File exceeds MAX_FILE_SIZE specified in the HTML form (if used)
                $errorMessage = "Error: The uploaded file exceeds the maximum allowed size.";
                break;
            case UPLOAD_ERR_PARTIAL: // File was only partially uploaded
                $errorMessage = "Error: The uploaded file was only partially uploaded.";
                break;
            case UPLOAD_ERR_NO_FILE: // No file was uploaded
                // Only treat 'no file selected' as an error if it was a POST request (i.e., the user clicked 'Load' without choosing a file).
                // Don't show an error on the initial page load (which is not a POST).
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $errorMessage = "Error: No file was selected for upload.";
                }
                break;
            default: // Handle any other unexpected upload errors.
                $errorMessage = "An unknown error occurred during file upload.";
                break;
        }
    }
} // End of POST request handling

// =============================================================================
// Default Entry Data Definition
// =============================================================================

// Define the default structure and values for a *new* lorebook entry in PHP.
// This structure is based on the documentation found at: https://docs.chub.ai/docs/advanced-setups/lorebooks
// This PHP array will be JSON-encoded and passed to the JavaScript for creating new entries.
$defaultEntryData = [
    "uid" => 0, // Unique Identifier - This will be dynamically replaced by JavaScript when a new entry is created.
    "key" => [], // Primary keywords for activation (array of strings).
    "keysecondary" => [], // Secondary keywords for activation (array of strings).
    "comment" => "", // User-provided comment or name for the entry.
    "content" => "", // The main text content of the lorebook entry.
    "constant" => false, // If true, the entry is always active (inserted into context). Typically set true if vectorized is true.
    "vectorized" => true, // Default insertion strategy: Use vector similarity. Setting this true implies constant=true in some systems.
    "selective" => true, // If true, use keyword matching logic (Primary AND/NOT Secondary).
    "selectiveLogic" => 0, // Logic for selective keyword matching: 0 = AND (Primary AND Secondary must match), 1 = NOT (Primary AND NOT Secondary must match).
    "addMemo" => true, // Whether to add the entry's content to the AI's memory or context.
    "order" => 100, // Insertion order priority (lower numbers insert earlier/higher).
    "position" => 0, // Insertion position relative to context: 0 = Center, 1 = Top, 2 = Bottom.
    "disable" => false, // If true, the entry is completely ignored. Default is enabled.
    "excludeRecursion" => false, // Prevent this entry from triggering itself.
    "preventRecursion" => false, // Alias or related to excludeRecursion.
    "delayUntilRecursion" => false, // Delay activation until a certain recursion depth.
    "probability" => 100, // Chance (out of 100) of the entry being activated if conditions are met.
    "useProbability" => true, // Whether to use the probability check.
    "depth" => 4, // Search depth for keyword matching within the context.
    "group" => "", // Group identifier for grouping related entries.
    "groupOverride" => false, // Whether this entry's settings override group settings.
    "groupWeight" => 100, // Weight assigned to this entry within its group scoring.
    "scanDepth" => null, // Specific scan depth override (often null to use default).
    "caseSensitive" => null, // Override case sensitivity for keyword matching (null = use global setting).
    "matchWholeWords" => null, // Override whole word matching for keywords (null = use global setting).
    "useGroupScoring" => null, // Override group scoring behavior (null = use global setting).
    "automationId" => "", // ID for linking with automation systems.
    "role" => null, // Role association for the entry: 0 = User, 1 = Character, 2 = Assistant (null = none).
    "sticky" => 0, // Keep entry active for subsequent turns: 0 = None, 1 = InputStart, 2 = OutputStart.
    "cooldown" => 0, // Number of turns before the entry can be activated again.
    "delay" => 0, // Number of turns to wait before the entry can be activated initially.
    "displayIndex" => 0, // Visual order in the editor - This will be dynamically replaced by JavaScript.
];

// Encode the default entry data array into a JSON string.
// This makes it easy to pass the entire structure to the JavaScript code.
$defaultEntryJson = json_encode($defaultEntryData);

// =============================================================================
// Determine Editor Visibility
// =============================================================================

// Flag to determine whether the main lorebook editing area should be displayed in the HTML.
// Show the editor if:
// 1. `$loreEntries` is not empty (meaning a file was successfully loaded).
// OR
// 2. It was a POST request (upload attempt), there was no error message, AND a file was potentially uploaded (even if empty/invalid later).
// This prevents showing the editor area on initial page load before any file is chosen.
$showEditorArea = !empty($loreEntries) || ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errorMessage) && isset($_FILES['lorebookFile']));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lorebook Editor</title>
    <link rel="stylesheet" href="style.css"> <?php /* Link to external CSS file for styling */ ?>
</head>
<body>

    <h1>Lorebook Editor</h1>

    <?php // --- File Upload Form --- ?>
    <form method="post" enctype="multipart/form-data" style="margin-bottom: 20px; padding: 15px; background-color: #f0f0f0; border: 1px solid #ccc; border-radius: 5px;">
        <label for="lorebookFile" style="display: block; margin-bottom: 8px; font-weight: bold;">Load Lorebook (.json):</label>
        <?php /* File input: accepts only .json files, required for form submission */ ?>
        <input type="file" id="lorebookFile" name="lorebookFile" accept=".json" required style="margin-right: 10px;">
        <?php /* Submit button to trigger the file upload and page reload */ ?>
        <input type="submit" value="Load Lorebook">
    </form>

    <?php // --- Status Messages (Error or Success) --- ?>
    <?php // Display error message if one occurred during file processing. Use htmlspecialchars for security. ?>
    <?php if (!empty($errorMessage)): ?>
        <div class="error"><?php echo htmlspecialchars($errorMessage); ?></div>
    <?php // Display success message if a file was loaded and the editor should be shown. ?>
    <?php elseif (!empty($loadedFileName) && $showEditorArea): ?>
        <p style="background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px; border-radius: 4px;">
            Successfully loaded: <strong><?php echo htmlspecialchars($loadedFileName); ?></strong>. Displaying entries below.
            <br><strong>Note:</strong> Changes/Additions/Deletions/Reordering are <strong>not</strong> saved automatically. Use 'Export to JSON' to save changes.
        </p>
    <?php endif; ?>


    <?php // --- Main Editor Area --- ?>
    <?php // Conditionally display the editor section based on the $showEditorArea flag. ?>
    <?php if ($showEditorArea): ?>

        <?php // --- Global Action Buttons --- ?>
        <div class="action-buttons">
            <?php // Button to add a new lore entry at the very end of the list. ?>
            <button type="button" id="addEntryBtn" class="add-entry-button">Add New Entry (Bottom)</button>
            <?php // Button to trigger the export of the current editor state to a JSON file. ?>
            <button type="button" id="exportJsonBtn" class="export-json-button">Export to JSON</button>
        </div>

        <?php // --- Lorebook Entries Container --- ?>
        <?php // This div will hold all the individual lore entry editor elements. It's the target for drag & drop. ?>
        <div class="lorebook-container" id="lorebookContainer">
            <?php // Initialize a counter for the original visual index of entries as they are loaded from the file. ?>
            <?php $original_index_counter = 0; ?>
            <?php // Loop through each entry loaded from the JSON file. $index is the key from the JSON, $entry is the value (entry data object). ?>
            <?php foreach ($loreEntries as $index => $entry): ?>
                <?php
                    // --- Prepare Data for Displaying Existing Entries ---
                    // Determine if the entry is disabled (using nullish coalescing for safety). Default to false.
                    $isDisabled = isset($entry['disable']) ? (bool)$entry['disable'] : false;
                    // Get the Unique ID (UID) from the entry data, or fall back to the original JSON key/index if UID is missing.
                    $entryUid = isset($entry['uid']) ? $entry['uid'] : $index;
                    // Get the comment/name, escape HTML characters for safe display. Default to empty string.
                    $entryComment = isset($entry['comment']) ? htmlspecialchars($entry['comment']) : '';
                    // Get primary keys, implode the array into a comma-separated string, escape HTML. Default to empty string.
                    $entryKey = isset($entry['key']) && is_array($entry['key']) ? htmlspecialchars(implode(', ', $entry['key'])) : '';
                    // Get secondary keys, implode the array, escape HTML. Default to empty string.
                    $entryKeySecondary = isset($entry['keysecondary']) && is_array($entry['keysecondary']) ? htmlspecialchars(implode(', ', $entry['keysecondary'])) : '';
                    // Determine selective state (default true).
                    $isSelective = isset($entry['selective']) ? (bool)$entry['selective'] : true;
                    // Determine selective logic (default 0 - AND).
                    $selectiveLogic = isset($entry['selectiveLogic']) ? (int)$entry['selectiveLogic'] : 0;
                    // Determine constant state (default false).
                    $isConstant = isset($entry['constant']) ? (bool)$entry['constant'] : false;
                    // Determine vectorized state (default false). Important for strategy calculation.
                    $isVectorized = isset($entry['vectorized']) ? (bool)$entry['vectorized'] : false;
                    // Determine the 'strategy' based on vectorized/constant flags for the radio buttons.
                    $strategy = 'normal'; // Default strategy
                    if ($isVectorized) { $strategy = 'vectorized'; } // Vectorized takes precedence
                    elseif ($isConstant) { $strategy = 'constant'; } // Constant is next
                    // Get the main content, escape HTML characters. Default to empty string.
                    $entryContent = isset($entry['content']) ? htmlspecialchars($entry['content']) : '';

                    // Create a prefix for HTML element IDs within this entry to ensure uniqueness.
                    // Using the original $index from the JSON helps maintain some stability if needed.
                    $idPrefix = "entry-" . htmlspecialchars($index) . "-";

                    // Determine the CSS class for the summary background based on the disabled state.
                    $summaryBgClass = $isDisabled ? 'entry-disabled' : 'entry-enabled';
                    // Determine the CSS class for the enable/disable toggle button icon.
                    $enableToggleClass = $isDisabled ? 'disabled' : 'enabled';
                    // Determine the icon character for the enable/disable toggle button.
                    $enableToggleIcon = $isDisabled ? '✗' : '✓'; // Cross mark for disabled, Check mark for enabled

                    // Assign the current value of the loop counter as the original visual index for this entry.
                    $original_numeric_index = $original_index_counter++;
                ?>
                <?php // --- Individual Lore Entry Element --- ?>
                <?php // Main div for a single entry. `draggable="true"` enables HTML5 drag and drop. `data-original-index` stores its initial load order. ?>
                <div class="lore-entry" draggable="true" data-original-index="<?php echo $original_numeric_index; ?>">

                    <?php // --- Entry Summary (Header) --- ?>
                    <?php // Always visible part of the entry, contains title, index, and controls. Background color indicates enabled/disabled state. ?>
                    <div class="entry-summary <?php echo $summaryBgClass; ?>">
                         <?php // --- Index Display --- ?>
                         <?php // Shows the current visual index (updates on reorder). `data-current-index` stores the current index for JS. ?>
                        <span class="entry-index-display" data-current-index="<?php echo $original_numeric_index; ?>">
                            Index: <?php echo $original_numeric_index; ?> <?php // Initially shows the original index ?>
                        </span>
                        <?php // --- Entry Title (Comment) --- ?>
                        <span class="entry-title"> -- <?php echo $entryComment ?: '<i>(No Comment)</i>'; ?> </span> <?php // Display comment or placeholder ?>

                        <?php // --- Entry Controls (Buttons) --- ?>
                        <div class="entry-controls">
                            <?php // Toggle Enable/Disable Button ?>
                            <button type="button" class="toggle-enable-btn <?php echo $enableToggleClass; ?>" title="Toggle Enable/Disable"><?php echo $enableToggleIcon; ?></button>
                            <?php // Add New Entry Above Button ?>
                            <button type="button" class="add-above-btn" title="Add New Entry Above">↑+</button>
                            <?php // Add New Entry Below Button ?>
                            <button type="button" class="add-below-btn" title="Add New Entry Below">↓+</button>
                            <?php // Duplicate Entry Button ?>
                            <button type="button" class="duplicate-entry-btn" title="Duplicate Entry">📄</button>
                            <?php // Move Up Button ?>
                            <button type="button" class="move-up-btn" title="Move Up">▲</button>
                            <?php // Move Down Button ?>
                            <button type="button" class="move-down-btn" title="Move Down">▼</button>
                            <?php // Delete Entry Button ?>
                            <button type="button" class="delete-entry-btn" title="Delete Entry">×</button>
                            <?php // Toggle Details Button/Icon (+) ?>
                            <span class="toggle-icon">+</span>
                        </div>
                    </div> <?php // <!-- /.entry-summary --> ?>

                    <?php // --- Entry Details (Collapsible Form Fields) --- ?>
                    <?php // Contains the actual form inputs for editing the entry's data. Hidden by default. ?>
                    <div class="entry-details">
                        <?php // Hidden input to store the entry's Unique ID (UID). Crucial for tracking entries if they are reordered. ?>
                        <input type="hidden" name="uid" value="<?php echo htmlspecialchars($entryUid); ?>">

                        <?php // --- Hidden Enable Checkbox (Internal State) --- ?>
                        <?php // This checkbox mirrors the visual enabled/disabled state controlled by the button in the summary. ?>
                        <?php // It's hidden from the user but used by JS to easily read the state during export. ?>
                         <div class="form-group" style="display:none;">
                             <input type="checkbox" class="enable-checkbox" id="<?php echo $idPrefix; ?>enabled" value="true" <?php echo !$isDisabled ? 'checked' : ''; ?> data-entry-index="<?php echo $original_numeric_index; ?>">
                             <label class="checkbox-label" for="<?php echo $idPrefix; ?>enabled">Enabled (Internal)</label>
                         </div>

                        <?php // --- Comment (Name) Field --- ?>
                        <div class="form-group">
                            <label for="<?php echo $idPrefix; ?>comment">Comment (Name):</label>
                            <?php // Input field for the entry's comment/name. Value is pre-filled from loaded data. ?>
                            <input type="text" id="<?php echo $idPrefix; ?>comment" name="comment" value="<?php echo $entryComment; ?>">
                        </div>

                        <?php // --- Keywords Row --- ?>
                        <div class="form-row">
                             <?php // --- Primary Keywords Field --- ?>
                             <div class="form-group">
                                 <label for="<?php echo $idPrefix; ?>key">Primary Keywords:</label>
                                 <?php // Input for comma-separated primary keywords. ?>
                                 <input type="text" id="<?php echo $idPrefix; ?>key" name="key" value="<?php echo $entryKey; ?>" placeholder="keyword1, keyword2">
                                 <small>Comma-separated.</small>
                             </div>
                             <?php // --- Secondary Keywords Field --- ?>
                             <div class="form-group">
                                 <label for="<?php echo $idPrefix; ?>keysecondary">Secondary Keywords:</label>
                                 <?php // Input for comma-separated secondary keywords. ?>
                                 <input type="text" id="<?php echo $idPrefix; ?>keysecondary" name="keysecondary" value="<?php echo $entryKeySecondary; ?>" placeholder="secondary1, secondary2">
                                 <small>Comma-separated.</small>
                             </div>
                        </div>
                        <hr> <?php // Visual separator ?>

                        <?php // --- Selective & Logic Row --- ?>
                        <div class="form-row">
                             <?php // --- Selective Checkbox --- ?>
                             <div class="form-group">
                                 <?php // Checkbox to enable/disable selective keyword matching logic. ?>
                                 <input type="checkbox" id="<?php echo $idPrefix; ?>selective" name="selective" value="true" <?php echo $isSelective ? 'checked' : ''; ?>>
                                 <label class="checkbox-label" for="<?php echo $idPrefix; ?>selective">Selective</label>
                                 <small>(Uses logic below)</small>
                             </div>
                             <div class="form-group">
                                 <?php // Placeholder for alignment if needed ?>
                             </div>
                        </div>


                        <div class="form-row">
                            <?php // --- Selective Logic Radio Buttons --- ?>
                            <div class="form-group">
                                <label>Logic:</label>
                                <?php // Radio buttons for AND (0) or NOT (1) logic for selective matching. ?>
                                <input type="radio" id="<?php echo $idPrefix; ?>logic_and" name="selectiveLogic" value="0" <?php echo ($selectiveLogic === 0) ? 'checked' : ''; ?>>
                                <label class="radio-label" for="<?php echo $idPrefix; ?>logic_and">AND</label>
                                <input type="radio" id="<?php echo $idPrefix; ?>logic_not" name="selectiveLogic" value="1" <?php echo ($selectiveLogic === 1) ? 'checked' : ''; ?>>
                                <label class="radio-label" for="<?php echo $idPrefix; ?>logic_not">NOT</label>
                            </div>
                             <?php // --- Strategy Radio Buttons --- ?>
                            <div class="form-group strategy-group">
                                <label>Strategy:</label>
                                <?php // Radio buttons to select insertion strategy: Normal, Constant, or Vectorized. ?>
                                <input type="radio" class="strategy-normal" id="<?php echo $idPrefix; ?>strategy_normal" name="strategy" value="normal" <?php echo ($strategy === 'normal') ? 'checked' : ''; ?>>
                                <label class="radio-label" for="<?php echo $idPrefix; ?>strategy_normal">Normal</label>
                                <input type="radio" class="strategy-constant" id="<?php echo $idPrefix; ?>strategy_constant" name="strategy" value="constant" <?php echo ($strategy === 'constant') ? 'checked' : ''; ?>>
                                <label class="radio-label" for="<?php echo $idPrefix; ?>strategy_constant">Constant</label>
                                <input type="radio" class="strategy-vectorized" id="<?php echo $idPrefix; ?>strategy_vectorized" name="strategy" value="vectorized" <?php echo ($strategy === 'vectorized') ? 'checked' : ''; ?>>
                                <label class="radio-label" for="<?php echo $idPrefix; ?>strategy_vectorized">Vectorized</label>
                            </div>
                        </div>
                        <hr> <?php // Visual separator ?>

                        <?php // --- Content Field --- ?>
                        <div class="form-group">
                              <label for="<?php echo $idPrefix; ?>content">Content:</label>
                              <?php // Textarea for the main lorebook entry content. Rows attribute provides initial height. ?>
                              <textarea id="<?php echo $idPrefix; ?>content" name="content" rows="3"><?php echo $entryContent; ?></textarea>
                        </div>

                         <?php // --- Hidden Display Index Field --- ?>
                         <?php // Stores the current visual index of this entry. Updated by JS on reorder/add/delete. Used during export. ?>
                        <input type="hidden" name="displayIndex" value="<?php echo $original_numeric_index; ?>">

                    </div> <?php // <!-- /.entry-details --> ?>
                </div> <?php // <!-- /.lore-entry --> ?>
            <?php endforeach; ?> <?php // End of the loop iterating through loaded entries. ?>
        </div> <?php // <!-- /#lorebookContainer --> ?>

    <?php // --- Initial Message (If no file loaded) --- ?>
    <?php // Display a message prompting the user to upload a file if the editor isn't shown, ?>
    <?php // it wasn't a POST request, and there are no error messages. ?>
    <?php elseif ($_SERVER['REQUEST_METHOD'] !== 'POST' && empty($errorMessage)): ?>
        <p class="no-data">Please upload a lorebook JSON file using the form above to begin editing.</p>
    <?php endif;  ?>


    <script>
        // =============================================================================
        // JavaScript Logic for Lorebook Editor Interactivity
        // =============================================================================

        // --- Pass PHP Data to JavaScript ---
        // Parse the JSON string containing the default entry structure passed from PHP.
        const defaultEntryData = JSON.parse(<?php echo json_encode($defaultEntryJson); ?>);
        // Get the filename that was loaded (or the default 'lorebook.json') passed from PHP. Used for suggesting export filename.
        const loadedFileName = <?php echo json_encode($loadedFileName); ?>;

        // --- DOM Element References ---
        // Get references to the main container and global action buttons.
        const lorebookContainer = document.getElementById('lorebookContainer');
        const addEntryBtn = document.getElementById('addEntryBtn'); // Button to add entry to the bottom
        const exportJsonBtn = document.getElementById('exportJsonBtn'); // Button to export data

        // --- Drag & Drop State Variables ---
        let draggedItem = null; // Stores the reference to the `.lore-entry` element currently being dragged.
        let placeholder = null; // Stores the reference to the visual placeholder element shown during drag.

        // --- Global Event Listener Setup ---
        // Add click listener to the main "Add Entry (Bottom)" button if it exists.
        if (addEntryBtn) {
            addEntryBtn.addEventListener('click', () => addEntry(null, 'bottom')); // Calls addEntry to append to the end.
        }
        // Add click listener to the "Export to JSON" button if it exists.
        if (exportJsonBtn) {
            exportJsonBtn.addEventListener('click', exportToJson); // Calls the export function.
        }

        // Setup listeners on the main container if it exists. Using event delegation for efficiency.
        if (lorebookContainer) {

            // --- Delegated Click Listener for Entry Actions ---
            // Listen for clicks within the container, then determine the actual target.
            lorebookContainer.addEventListener('click', function(event) {
                const target = event.target; // The specific element clicked.
                const entryDiv = target.closest('.lore-entry'); // Find the nearest ancestor `.lore-entry` div.

                // If the click wasn't inside a lore entry, ignore it.
                if (!entryDiv) return;

                // Check which button or element within the entry was clicked using classList.contains.
                if (target.classList.contains('delete-entry-btn')) {
                    event.stopPropagation(); // Prevent the click from also toggling the entry details.
                    handleDeleteClick(target); // Call the delete handler.
                } else if (target.classList.contains('toggle-enable-btn')) {
                     event.stopPropagation(); // Prevent toggling details.
                     handleEnableToggle(target); // Call the enable/disable handler.
                } else if (target.classList.contains('move-up-btn')) {
                     event.stopPropagation(); // Prevent toggling details.
                     moveEntryUp(entryDiv); // Call move up handler.
                } else if (target.classList.contains('move-down-btn')) {
                     event.stopPropagation(); // Prevent toggling details.
                     moveEntryDown(entryDiv); // Call move down handler.
                } else if (target.classList.contains('add-above-btn')) {
                    event.stopPropagation(); // Prevent toggling details.
                    addEntry(entryDiv, 'above'); // Call add handler to insert above the current entry.
                } else if (target.classList.contains('add-below-btn')) {
                    event.stopPropagation(); // Prevent toggling details.
                    addEntry(entryDiv, 'below'); // Call add handler to insert below the current entry.
                } else if (target.classList.contains('duplicate-entry-btn')) {
                     event.stopPropagation(); // Prevent toggling details.
                     duplicateEntry(entryDiv); // Call duplicate handler.
                } else if (target.classList.contains('toggle-icon')) {
                     // Toggle details only if the specific '+' icon is clicked.
                     event.stopPropagation();
                     toggleEntry(entryDiv.querySelector('.entry-summary')); // Call toggle handler using the summary element.
                } else if (target.closest('.entry-summary')) {
                    // Allow clicking anywhere on the summary bar (that isn't another button) to toggle details.
                    toggleEntry(target.closest('.entry-summary')); // Call toggle handler.
                }
            });

            // --- Drag and Drop Event Listeners ---
            // Attach drag and drop listeners directly to the container.
            lorebookContainer.addEventListener('dragstart', handleDragStart); // When dragging starts on a child element.
            lorebookContainer.addEventListener('dragend', handleDragEnd);     // When dragging ends.
            lorebookContainer.addEventListener('dragover', handleDragOver);   // When dragging over the container.
            lorebookContainer.addEventListener('dragleave', handleDragLeaveGeneral); // When dragging leaves the container bounds.
            lorebookContainer.addEventListener('drop', handleDrop);         // When the dragged item is dropped onto the container.

            // --- Delegated Listener for Auto-Growing Textareas ---
            // Listen for 'input' events (typing, pasting) within the container.
             lorebookContainer.addEventListener('input', function(event) {
                 // Check if the event target is a textarea named 'content'.
                 if (event.target.tagName.toLowerCase() === 'textarea' && event.target.name === 'content') {
                     autoGrowTextarea(event.target); // Call the auto-grow function.
                 }
             });

        } // End of lorebookContainer listener setup

         // --- Auto-Grow Textarea Function ---
         // Dynamically adjusts the height of a textarea based on its content.
         function autoGrowTextarea(textarea) {
             if (!textarea) return; // Exit if no textarea provided
             textarea.style.height = 'auto'; // Temporarily reset height to accurately measure scroll height.
             // Set the height to the scroll height plus a small buffer (e.g., 2px) to prevent scrollbars from flickering.
             textarea.style.height = (textarea.scrollHeight + 2) + 'px';
             textarea.style.overflowY = 'hidden'; // Hide vertical scrollbar if content fits.
         }


        // --- Refactored Add/Create Entry Logic ---

        /**
         * Creates the HTML structure for a single lore entry element.
         * @param {number} entryIndex - The intended visual index for the new entry.
         * @param {number} entryUid - The unique identifier (UID) for the new entry.
         * @param {object} data - The data object (e.g., defaultEntryData or duplicated data) to populate the fields.
         * @returns {HTMLElement} The newly created lore entry div element.
         */
        function createEntryElement(entryIndex, entryUid, data) {
            const idPrefix = `entry-uid-${entryUid}-`; // Create a prefix for element IDs using the stable UID.
            const entryDiv = document.createElement('div');
            entryDiv.className = 'lore-entry';
            entryDiv.draggable = true; // Make it draggable
            entryDiv.dataset.originalIndex = entryIndex; // Store its initial visual index (will be updated if needed)

            // --- Extract or default values from the provided data object ---
            const isDisabled = data.disable ?? false; // Default to false if 'disable' is not present
            const bgClass = isDisabled ? 'entry-disabled' : 'entry-enabled'; // CSS class for summary background
            const enableClass = isDisabled ? 'disabled' : 'enabled'; // CSS class for enable toggle button
            const enableIcon = isDisabled ? '✗' : '✓'; // Icon for enable toggle button
            const comment = data.comment ?? ''; // Default to empty string
            const keys = data.key ?? []; // Default to empty array
            const keysSecondary = data.keysecondary ?? []; // Default to empty array
            const selective = data.selective ?? true; // Default to true
            const logic = data.selectiveLogic ?? 0; // Default to 0 (AND)
            const constant = data.constant ?? false; // Default to false
            const vectorized = data.vectorized ?? false; // Default to false (Note: Default *new* entry sets this true via defaultEntryData)
            const content = data.content ?? ''; // Default to empty string

            // Determine the 'strategy' radio button state based on vectorized/constant flags
            let currentStrategy = 'normal';
            if (vectorized) { currentStrategy = 'vectorized'; }
            else if (constant) { currentStrategy = 'constant'; }

            // --- Build Summary Section HTML ---
            const summaryDiv = document.createElement('div');
            summaryDiv.classList.add('entry-summary', bgClass); // Apply state class
            const indexSpan = document.createElement('span');
            indexSpan.className = 'entry-index-display';
            indexSpan.dataset.currentIndex = entryIndex; // Store current index
            indexSpan.innerHTML = `Index: ${entryIndex}`; // Display index
            const titleSpan = document.createElement('span');
            titleSpan.className = 'entry-title';
            // Display comment or a placeholder for new/duplicated entries. Use htmlspecialcharsDecode for safety if comment came from existing data.
            titleSpan.innerHTML = ` -- ${comment ? htmlspecialcharsDecode(comment) : '<i>(New/Duplicated Entry)</i>'}`;
            const controlsDiv = document.createElement('div');
            controlsDiv.className = 'entry-controls';
            // Use template literal for cleaner HTML structure of controls
            controlsDiv.innerHTML = `
                <button type="button" class="toggle-enable-btn ${enableClass}" title="Toggle Enable/Disable">${enableIcon}</button>
                <button type="button" class="add-above-btn" title="Add New Entry Above">↑+</button>
                <button type="button" class="add-below-btn" title="Add New Entry Below">↓+</button>
                <button type="button" class="duplicate-entry-btn" title="Duplicate Entry">📄</button>
                <button type="button" class="move-up-btn" title="Move Up">▲</button>
                <button type="button" class="move-down-btn" title="Move Down">▼</button>
                <button type="button" class="delete-entry-btn" title="Delete Entry">×</button>
                <span class="toggle-icon">+</span>
            `;
            summaryDiv.appendChild(indexSpan);
            summaryDiv.appendChild(titleSpan);
            summaryDiv.appendChild(controlsDiv);

            // --- Build Details Section HTML ---
            const detailsDiv = document.createElement('div');
            detailsDiv.className = 'entry-details'; // Initially hidden via CSS
            // Build HTML string for the form elements inside the details section
            let detailsHTML = `<input type="hidden" name="uid" value="${entryUid}">`; // Hidden UID input
            // Hidden 'enabled' checkbox for internal state tracking
            detailsHTML += `<div class="form-group" style="display:none;"><input type="checkbox" class="enable-checkbox" id="${idPrefix}enabled" value="true" ${!isDisabled ? 'checked' : ''} data-entry-index="${entryIndex}"><label class="checkbox-label" for="${idPrefix}enabled">Enabled (Internal)</label></div>`;
            // Comment input
            detailsHTML += `<div class="form-group"><label for="${idPrefix}comment">Comment (Name):</label><input type="text" id="${idPrefix}comment" name="comment" value="${htmlspecialcharsDecode(comment)}"></div>`;
            // Keywords row
            detailsHTML += `<div class="form-row">
                                <div class="form-group"><label for="${idPrefix}key">Primary Keywords:</label><input type="text" id="${idPrefix}key" name="key" value="${keys.join(', ')}" placeholder="keyword1, keyword2"><small>Comma-separated.</small></div>
                                <div class="form-group"><label for="${idPrefix}keysecondary">Secondary Keywords:</label><input type="text" id="${idPrefix}keysecondary" name="keysecondary" value="${keysSecondary.join(', ')}" placeholder="secondary1, secondary2"><small>Comma-separated.</small></div>
                            </div><hr>`;
             // Selective checkbox row
            detailsHTML += `<div class="form-row">
                                <div class="form-group"><input type="checkbox" id="${idPrefix}selective" name="selective" value="true" ${selective ? 'checked' : ''}><label class="checkbox-label" for="${idPrefix}selective">Selective</label><small>(Uses logic below)</small></div>
                                <div class="form-group"></div> <?php /* Alignment placeholder */ ?>
                           </div>`;
             // Logic and Strategy row
            detailsHTML += `<div class="form-row">
                                <div class="form-group"> <?php /* Logic Radio Buttons */ ?>
                                    <label>Logic:</label>
                                    <input type="radio" id="${idPrefix}logic_and" name="selectiveLogic" value="0" ${logic === 0 ? 'checked' : ''}><label class="radio-label" for="${idPrefix}logic_and">AND</label>
                                    <input type="radio" id="${idPrefix}logic_not" name="selectiveLogic" value="1" ${logic === 1 ? 'checked' : ''}><label class="radio-label" for="${idPrefix}logic_not">NOT</label>
                                </div>
                                <div class="form-group strategy-group"> <?php /* Strategy Radio Buttons */ ?>
                                    <label>Strategy:</label>
                                    <input type="radio" class="strategy-normal" id="${idPrefix}strategy_normal" name="strategy" value="normal" ${currentStrategy === 'normal' ? 'checked' : ''}><label class="radio-label" for="${idPrefix}strategy_normal">Normal</label>
                                    <input type="radio" class="strategy-constant" id="${idPrefix}strategy_constant" name="strategy" value="constant" ${currentStrategy === 'constant' ? 'checked' : ''}><label class="radio-label" for="${idPrefix}strategy_constant">Constant</label>
                                    <input type="radio" class="strategy-vectorized" id="${idPrefix}strategy_vectorized" name="strategy" value="vectorized" ${currentStrategy === 'vectorized' ? 'checked' : ''}><label class="radio-label" for="${idPrefix}strategy_vectorized">Vectorized</label>
                                </div>
                            </div><hr>`;
             // Content textarea
            detailsHTML += `<div class="form-group">
                                <label for="${idPrefix}content">Content:</label>
                                <textarea id="${idPrefix}content" name="content" rows="3">${htmlspecialcharsDecode(content)}</textarea>
                            </div>`;
             // Hidden display index input (value updated later by updateIndicesAndButtons)
            detailsHTML += `<input type="hidden" name="displayIndex" value="${entryIndex}">`;
            // Set the innerHTML of the details div
            detailsDiv.innerHTML = detailsHTML;

            // --- Assemble Entry ---
            entryDiv.appendChild(summaryDiv);
            entryDiv.appendChild(detailsDiv);

            // --- Post-Creation Setup ---
            // Initialize Auto-Grow for the new textarea
            const textarea = detailsDiv.querySelector('textarea[name="content"]');
            autoGrowTextarea(textarea);

            // Setup ARIA attributes for accessibility
            setupAriaForEntry(entryDiv, entryUid); // Use UID for stable ARIA references

            return entryDiv; // Return the fully constructed entry element
        }


        /**
         * Adds a new lore entry to the editor.
         * @param {HTMLElement|null} referenceEntry - The entry relative to which the new entry should be added (null for adding to bottom).
         * @param {string} [position='bottom'] - Where to add the entry ('above', 'below', or 'bottom').
         */
        function addEntry(referenceEntry, position = 'bottom') {
            const newUid = findNextAvailableUid(); // Get a unique ID for the new entry.

            // Calculate the initial visual index where the entry will be inserted.
            // This will be updated more accurately by updateIndicesAndButtons shortly after insertion.
            let insertIndex = lorebookContainer.querySelectorAll('.lore-entry').length; // Default to end index
             if (referenceEntry) { // If adding relative to an existing entry
                 const allEntries = Array.from(lorebookContainer.querySelectorAll('.lore-entry'));
                 const refIndex = allEntries.indexOf(referenceEntry); // Find the index of the reference entry
                 if (refIndex !== -1) { // If found
                    // Set index based on 'above' or 'below' position
                    insertIndex = (position === 'above') ? refIndex : refIndex + 1;
                 }
             }

            // Create the new entry's HTML element using the default data structure.
            const newEntryDiv = createEntryElement(insertIndex, newUid, defaultEntryData);

            // Insert the new element into the DOM at the correct position.
            if (position === 'above' && referenceEntry) {
                lorebookContainer.insertBefore(newEntryDiv, referenceEntry); // Insert before reference
            } else if (position === 'below' && referenceEntry) {
                lorebookContainer.insertBefore(newEntryDiv, referenceEntry.nextElementSibling); // Insert after reference
            } else { // Default case: 'bottom' or if referenceEntry is null
                lorebookContainer.appendChild(newEntryDiv); // Append to the end of the container
            }

            // Update all entry indices and move button states after insertion.
            updateIndicesAndButtons();
             // Optional: Smoothly scroll the view to the newly added entry.
             newEntryDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }


        /**
         * Duplicates an existing lore entry.
         * @param {HTMLElement} sourceEntryDiv - The lore entry element to duplicate.
         */
        function duplicateEntry(sourceEntryDiv) {
            if (!sourceEntryDiv) return; // Exit if no source entry provided

            // 1. Extract *current* data from the source entry's form fields.
            //    This ensures that any unsaved edits in the source entry are copied.
            const currentData = {};
            // Use optional chaining (?.) and nullish coalescing (??) for robustness
            currentData.comment = sourceEntryDiv.querySelector('input[name="comment"]')?.value ?? '';
            // Split comma-separated keys, trim whitespace, and filter out empty strings
            currentData.key = sourceEntryDiv.querySelector('input[name="key"]')?.value.split(',').map(k => k.trim()).filter(Boolean) ?? [];
            currentData.keysecondary = sourceEntryDiv.querySelector('input[name="keysecondary"]')?.value.split(',').map(k => k.trim()).filter(Boolean) ?? [];
            currentData.selective = sourceEntryDiv.querySelector('input[name="selective"]')?.checked ?? true;
            // Parse the value of the checked logic radio button, default to 0 (AND)
            currentData.selectiveLogic = parseInt(sourceEntryDiv.querySelector('input[name="selectiveLogic"]:checked')?.value ?? '0', 10);
            // Get the value of the checked strategy radio button
            const currentStrategyValue = sourceEntryDiv.querySelector('input[name="strategy"]:checked')?.value ?? 'normal';
            // Set 'constant' and 'vectorized' based on the selected strategy
            currentData.constant = (currentStrategyValue === 'constant' || currentStrategyValue === 'vectorized');
            currentData.vectorized = (currentStrategyValue === 'vectorized');
            currentData.content = sourceEntryDiv.querySelector('textarea[name="content"]')?.value ?? '';
            // Check the disabled state from the summary div's class list
            currentData.disable = sourceEntryDiv.querySelector('.entry-summary')?.classList.contains('entry-disabled') ?? false;

             // Create the final data object for the new entry by merging the extracted
             // current data over the default entry data. This ensures all fields are present.
             const finalData = { ...defaultEntryData, ...currentData };

            // 2. Get a new UID and calculate the insertion index (insert right after the source entry).
            const newUid = findNextAvailableUid();
            const allEntries = Array.from(lorebookContainer.querySelectorAll('.lore-entry'));
            const sourceVisualIndex = allEntries.indexOf(sourceEntryDiv);
            // Insert after the source, or at the end if source index not found (shouldn't happen)
            let insertIndex = (sourceVisualIndex !== -1) ? sourceVisualIndex + 1 : allEntries.length;

            // 3. Create the new DOM element using the merged `finalData`.
            const newEntryDiv = createEntryElement(insertIndex, newUid, finalData);

            // 4. Insert the new element into the DOM immediately after the source element.
            lorebookContainer.insertBefore(newEntryDiv, sourceEntryDiv.nextElementSibling);

            // 5. Update indices/buttons and scroll the new entry into view.
            updateIndicesAndButtons();
            newEntryDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }


        // --- Toggle Entry Details Visibility ---
        /**
         * Toggles the visibility of an entry's details section.
         * @param {HTMLElement} summaryElement - The summary element (`.entry-summary`) that was clicked.
         */
        function toggleEntry(summaryElement) {
             const entryContainer = summaryElement.closest('.lore-entry'); // Find parent entry container
             if (!entryContainer) return; // Exit if not found

             const detailsElement = entryContainer.querySelector('.entry-details'); // Find the details section
             const iconElement = summaryElement.querySelector('.toggle-icon'); // Find the toggle icon (+)

             if (detailsElement && iconElement) {
                 // Toggle the 'active' class on the details section to show/hide it (CSS handles visibility).
                 const isActive = detailsElement.classList.toggle('active');
                 // Toggle the 'active' class on the icon for visual feedback (e.g., rotation via CSS).
                 iconElement.classList.toggle('active', isActive);
                 // Update ARIA attributes for accessibility.
                 summaryElement.setAttribute('aria-expanded', isActive); // Indicates if the controlled section is expanded.
                 detailsElement.setAttribute('aria-hidden', !isActive); // Indicates if the section is hidden from assistive technologies.

                 // If the details section is being expanded, trigger auto-grow for the textarea inside.
                 // This ensures its height is correct if content was changed while it was hidden.
                 if (isActive) {
                    const textarea = detailsElement.querySelector('textarea[name="content"]');
                    if (textarea) {
                        // Use a small timeout (0ms) to ensure the element is rendered and visible
                        // before calculating its scrollHeight, preventing potential calculation errors.
                        setTimeout(() => autoGrowTextarea(textarea), 0);
                    }
                 }
             }
        }

        // --- Delete Entry ---
        /**
         * Handles the click event for a delete button. Confirms deletion and removes the entry.
         * @param {HTMLElement} deleteButton - The delete button element that was clicked.
         */
        function handleDeleteClick(deleteButton) {
            const entryContainer = deleteButton.closest('.lore-entry'); // Find the parent entry container.
            // Try to get the entry's name (comment) for the confirmation dialog.
            const summary = entryContainer?.querySelector('.entry-summary .entry-title');
            const entryNameMatch = summary ? summary.innerHTML.match(/--\s*(.*)/) : null; // Extract text after "-- "
            // Clean up the extracted name (remove potential <i> tags) or use a generic placeholder.
            const entryName = entryNameMatch ? entryNameMatch[1].trim().replace(/<\/?i>/g,'') : 'this entry';

            // Confirm with the user before deleting.
            if (entryContainer && confirm(`Are you sure you want to delete "${entryName}"? This cannot be undone.`)) {
                entryContainer.remove(); // Remove the entry element from the DOM.
                updateIndicesAndButtons(); // Update indices and button states of remaining entries.
            }
        }

        // --- Toggle Enable/Disable State ---
        /**
         * Handles the click event for the enable/disable toggle button. Updates visual state and internal checkbox.
         * @param {HTMLElement} toggleButton - The enable/disable button element that was clicked.
         */
        function handleEnableToggle(toggleButton) {
             const entryContainer = toggleButton.closest('.lore-entry'); // Find parent entry.
             const summaryElement = entryContainer?.querySelector('.entry-summary'); // Find summary bar.
             const checkbox = entryContainer?.querySelector('.enable-checkbox'); // Find the hidden internal checkbox.

             if (summaryElement && checkbox) {
                 // Toggle the 'entry-disabled' class on the summary element. Returns true if the class was added (now disabled).
                 const isDisabled = summaryElement.classList.toggle('entry-disabled');
                 // Ensure the 'entry-enabled' class is the inverse.
                 summaryElement.classList.toggle('entry-enabled', !isDisabled);
                 // Toggle the 'enabled'/'disabled' classes on the button itself for styling.
                 toggleButton.classList.toggle('enabled', !isDisabled);
                 toggleButton.classList.toggle('disabled', isDisabled);
                 // Update the button's icon/text.
                 toggleButton.innerHTML = isDisabled ? '✗' : '✓';
                 // Sync the state of the hidden checkbox. Checked = enabled, unchecked = disabled.
                 checkbox.checked = !isDisabled;
             }
        }

        // --- Move Entry Up/Down ---
        /**
         * Moves the specified lore entry one position up in the list.
         * @param {HTMLElement} entryDiv - The lore entry element to move up.
         */
        function moveEntryUp(entryDiv) {
            const prevEntry = entryDiv.previousElementSibling; // Get the immediately preceding sibling element.
            // Check if a previous sibling exists and if it's also a lore entry (not the placeholder).
            if (prevEntry && prevEntry.classList.contains('lore-entry')) {
                // Use insertBefore to move the current entryDiv *before* the previous entry.
                lorebookContainer.insertBefore(entryDiv, prevEntry);
                updateIndicesAndButtons(); // Update indices and button states after moving.
            }
        }
        /**
         * Moves the specified lore entry one position down in the list.
         * @param {HTMLElement} entryDiv - The lore entry element to move down.
         */
        function moveEntryDown(entryDiv) {
            const nextEntry = entryDiv.nextElementSibling; // Get the immediately following sibling element.
            let targetElement = nextEntry; // The element to insert *before*.

             // If the next sibling is the drag placeholder, we need to find the element *after* the placeholder.
             if (nextEntry && nextEntry.classList.contains('drag-over-placeholder')) {
                 targetElement = nextEntry.nextElementSibling;
             }

            // Check if a target element exists (either the next entry or the one after the placeholder).
            if (targetElement && targetElement.classList.contains('lore-entry')) {
                 // Insert the current entryDiv *before* the element that comes *after* the target element.
                 // This effectively moves entryDiv down one position.
                 lorebookContainer.insertBefore(entryDiv, targetElement.nextElementSibling);
                 updateIndicesAndButtons(); // Update indices and buttons.
            } else if (!targetElement) { // If targetElement is null, it means we are moving the item to the very end.
                 lorebookContainer.appendChild(entryDiv); // Append the entry to the end of the container.
                 updateIndicesAndButtons(); // Update indices and buttons.
            }
        }

        // =============================================================================
        // Drag and Drop Handlers
        // =============================================================================

        /**
         * Handles the 'dragstart' event. Sets up the drag operation.
         * @param {DragEvent} event - The drag event object.
         */
        function handleDragStart(event) {
            // Prevent dragging if the user starts dragging on an interactive element
            // like an input, button, textarea, etc., or the controls div itself.
            const nonDraggableTarget = event.target.closest('input, textarea, button, select, label, .entry-controls, .toggle-icon');

            // Find the actual '.lore-entry' element being interacted with.
            const intendedDragTarget = event.target.closest('.lore-entry');

            // Only initiate drag if we are inside a lore entry AND not starting on a non-draggable element.
            if (intendedDragTarget && !nonDraggableTarget) {
                // Further check: Only allow dragging if the direct target is the entry div itself
                // or the summary bar (which acts as the drag handle).
                 if (event.target === intendedDragTarget || event.target.classList.contains('entry-summary') ) {
                    draggedItem = intendedDragTarget; // Store the element being dragged.

                    // Use requestAnimationFrame to apply the 'dragging' class slightly after
                    // the drag starts, preventing the original element from disappearing immediately.
                    requestAnimationFrame(() => {
                        if(draggedItem) draggedItem.classList.add('dragging'); // Add class for visual feedback (e.g., opacity)
                    });

                    event.dataTransfer.effectAllowed = 'move'; // Indicate that the item will be moved.
                 } else {
                     // If drag started within details but not on summary/entry, prevent it.
                     event.preventDefault();
                 }
            } else {
                // Prevent drag initiation if starting on an input, button, etc.
                event.preventDefault();
            }
        }

        /**
         * Handles the 'dragend' event. Cleans up after the drag operation.
         * @param {DragEvent} event - The drag event object.
         */
        function handleDragEnd(event) {
             if (draggedItem) {
                 // Use requestAnimationFrame for cleanup consistency.
                 requestAnimationFrame(() => {
                     if(draggedItem) draggedItem.classList.remove('dragging'); // Remove visual feedback class.
                 });
                draggedItem = null; // Clear the reference to the dragged item.
            }
            removePlaceholder(); // Ensure the visual placeholder is removed.
        }

        /**
         * Handles the 'dragover' event. Allows dropping and positions the placeholder.
         * @param {DragEvent} event - The drag event object.
         */
        function handleDragOver(event) {
            event.preventDefault(); // Must prevent default to allow dropping.
            if (!draggedItem) return; // Only handle if an item is being dragged.

            event.dataTransfer.dropEffect = 'move'; // Visual cue for the user.

            // Determine where the placeholder should be inserted based on mouse position.
            const targetElement = getDragAfterElement(event.clientY);

            // Create the placeholder if it doesn't exist.
            createPlaceholder();

            // Insert the placeholder before the targetElement, or at the end if targetElement is null.
            if (targetElement == null) { // Dragging to the end
                // Only append if placeholder isn't already the last child
                if (lorebookContainer.lastChild !== placeholder) {
                     lorebookContainer.appendChild(placeholder);
                }
             } else { // Dragging between existing elements
                 // Only insert if placeholder isn't already before the target
                 if (targetElement !== placeholder.nextSibling) {
                     lorebookContainer.insertBefore(placeholder, targetElement);
                 }
             }
        }

        /**
         * Handles the 'dragleave' event for the container. Removes placeholder if leaving container bounds.
         * @param {DragEvent} event - The drag event object.
         */
        function handleDragLeaveGeneral(event) {
             // Check if the mouse pointer is moving to an element *outside* the lorebook container.
             // `event.relatedTarget` is the element the mouse is entering.
             if (!lorebookContainer.contains(event.relatedTarget)) {
                  removePlaceholder(); // Remove placeholder if truly leaving the container.
             }
        }

        /**
         * Handles the 'drop' event. Finalizes the move operation.
         * @param {DragEvent} event - The drag event object.
         */
        function handleDrop(event) {
            event.preventDefault(); // Prevent default browser behavior (e.g., opening file).
            if (draggedItem) {
                // Determine the final drop position again.
                const targetElement = getDragAfterElement(event.clientY);
                // Insert the dragged item at the calculated position.
                if (targetElement == null) { // Dropping at the end
                    lorebookContainer.appendChild(draggedItem);
                } else { // Dropping between elements
                    lorebookContainer.insertBefore(draggedItem, targetElement);
                }
                 updateIndicesAndButtons(); // Update indices and button states after the drop.
            }
            removePlaceholder(); // Clean up the placeholder.
        }

        // --- Drag & Drop Helper Functions ---

        /** Creates the placeholder element if it doesn't exist. */
        function createPlaceholder() {
            if (!placeholder) {
                placeholder = document.createElement('div');
                placeholder.className = 'drag-over-placeholder'; // Apply CSS class for styling
            }
        }

        /** Removes the placeholder element from the DOM if it exists. */
        function removePlaceholder() {
            if (placeholder && placeholder.parentNode) {
                placeholder.parentNode.removeChild(placeholder);
                // Optional: nullify placeholder reference after removal
                // placeholder = null;
            }
        }

        /**
         * Determines which element the dragged item should be placed *before*,
         * based on the current mouse Y-coordinate.
         * @param {number} y - The vertical client coordinate of the mouse pointer.
         * @returns {HTMLElement|null} The element to insert before, or null if inserting at the end.
         */
        function getDragAfterElement(y) {
            // Get all draggable elements *except* the one currently being dragged and the placeholder.
            const draggableElements = [...lorebookContainer.querySelectorAll('.lore-entry:not(.dragging)')];

            // Use reduce to find the element closest to the mouse pointer position.
            return draggableElements.reduce((closest, child) => {
                const box = child.getBoundingClientRect(); // Get element dimensions and position.
                // Calculate the vertical distance from the mouse pointer to the center of the element.
                const offset = y - box.top - box.height / 2;
                // If the offset is negative (mouse is above the center) and closer than the previous closest,
                // update 'closest' to this element.
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                } else {
                    // Otherwise, keep the current 'closest'.
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element; // Initial 'closest' has infinitely negative offset.
            // The final result is the 'element' property of the 'closest' object found.
        }


        // --- Update Indices, Button States, and Hidden Fields ---
        /**
         * Iterates through all lore entries, updates their visual index display,
         * ensures the hidden 'displayIndex' field is correct, and enables/disables
         * move up/down buttons based on position.
         */
        function updateIndicesAndButtons() {
            // Get all current lore entry elements in the container. The order reflects the current visual order.
            const entries = lorebookContainer.querySelectorAll('.lore-entry');
            entries.forEach((entry, currentIndex) => { // Loop through each entry with its current index
                const originalIndex = entry.dataset.originalIndex; // Get the initial index stored when loaded/created.
                const indexDisplaySpan = entry.querySelector('.entry-index-display'); // Get the span showing the index.

                // --- Update Visual Index Display ---
                // Update the data attribute storing the current index.
                indexDisplaySpan.dataset.currentIndex = currentIndex;
                // Update the displayed text. Show original index if it differs from current.
                if (String(currentIndex) !== String(originalIndex)) { // Compare as strings for safety
                    indexDisplaySpan.innerHTML = `Index: ${currentIndex} / <small>(Orig: ${originalIndex})</small>`;
                } else {
                    indexDisplaySpan.innerHTML = `Index: ${currentIndex}`;
                }

                // --- Update Hidden Form Fields (UID remains, DisplayIndex updates) ---
                const uidInput = entry.querySelector('input[name="uid"]'); // Find UID input
                const displayIndexInput = entry.querySelector('input[name="displayIndex"]'); // Find DisplayIndex input

                // Note: UID should generally remain constant once assigned. No update needed here unless specifically intended.
                // if (uidInput) { /* uidInput.value = ... */ }

                 // Update the hidden 'displayIndex' input to reflect the current visual order. This is crucial for export.
                 if (displayIndexInput) {
                     displayIndexInput.value = currentIndex;
                 } else {
                    // If the hidden input is missing (e.g., older code version or error), create and add it.
                    console.warn("Missing displayIndex input for entry, creating one:", entry);
                    const hiddenDisplayIndex = document.createElement('input');
                    hiddenDisplayIndex.type = 'hidden';
                    hiddenDisplayIndex.name = 'displayIndex';
                    hiddenDisplayIndex.value = currentIndex;
                    const detailsDiv = entry.querySelector('.entry-details');
                    if(detailsDiv) detailsDiv.appendChild(hiddenDisplayIndex);
                 }

                // --- Update Move Button States ---
                const upBtn = entry.querySelector('.move-up-btn');
                const downBtn = entry.querySelector('.move-down-btn');
                // Disable 'Up' button if it's the first entry (index 0).
                if (upBtn) upBtn.disabled = (currentIndex === 0);
                // Disable 'Down' button if it's the last entry.
                if (downBtn) downBtn.disabled = (currentIndex === entries.length - 1);
            });
            console.log("Indices and buttons updated."); // Debugging message
        }


        // --- Helper function for ARIA setup ---
        /**
         * Sets appropriate ARIA attributes on an entry's summary and details elements
         * to improve accessibility for screen readers, linking the summary (acting as a button)
         * to the details section it controls.
         * @param {HTMLElement} entryDiv - The main lore entry container element.
         * @param {number|string} idSuffix - A unique suffix (like UID or index) for generating IDs.
         */
         function setupAriaForEntry(entryDiv, idSuffix) {
            const summary = entryDiv.querySelector('.entry-summary');
            const details = entryDiv.querySelector('.entry-details');
            if (summary && details) {
                const detailsId = `lore-entry-details-${idSuffix}`; // Unique ID for the details section
                const summaryId = `lore-entry-summary-${idSuffix}`; // Unique ID for the summary section

                summary.setAttribute('id', summaryId); // Set ID for summary
                // Check if details are currently expanded (have 'active' class)
                const isExpanded = details.classList.contains('active');
                // Indicate whether the controlled region (details) is expanded
                summary.setAttribute('aria-expanded', isExpanded);
                // Link the summary (button) to the details section it controls using aria-controls
                summary.setAttribute('aria-controls', detailsId);
                // Define the summary's role as a button for assistive technologies
                summary.setAttribute('role', 'button');
                // Make the summary focusable if needed (often handled by button role)
                // summary.setAttribute('tabindex', '0');

                details.setAttribute('id', detailsId); // Set ID for details
                // Indicate whether the details section is hidden from assistive technologies
                details.setAttribute('aria-hidden', !isExpanded);
                // Link the details section back to the summary element that labels it
                details.setAttribute('aria-labelledby', summaryId);
            }
         }

        // --- Find Next Available UID ---
        /**
         * Finds the smallest non-negative integer UID that is not currently used
         * by any entry in the editor. Scans hidden UID inputs.
         * @returns {number} The next available UID.
         */
        function findNextAvailableUid() {
            const existingUids = new Set(); // Use a Set for efficient checking of existing UIDs.
            // Select all hidden input fields with name="uid" within the container (or document as fallback).
            const container = lorebookContainer || document;
            container.querySelectorAll('.lore-entry input[name="uid"]').forEach(input => {
                const uid = parseInt(input.value, 10); // Parse the value as an integer.
                if (!isNaN(uid)) { // If parsing was successful
                    existingUids.add(uid); // Add the UID to the set.
                }
            });
            let nextUid = 0; // Start checking from UID 0.
            // Increment nextUid until a value not present in the set is found.
            while (existingUids.has(nextUid)) {
                nextUid++;
            }
            return nextUid; // Return the first available UID.
        }

        // --- Export to JSON Functionality ---
        /**
         * Gathers data from all current lore entries in the editor, formats it
         * into the expected JSON structure ({ "entries": { "0": {...}, "1": {...} ... } }),
         * and triggers a file download for the user.
         */
        function exportToJson() {
            // Get all lore entry elements currently in the container.
            const entries = lorebookContainer.querySelectorAll('.lore-entry');
            if (entries.length === 0) {
                alert("Nothing to export!"); // Show message if there are no entries.
                return;
            }

            // Initialize the structure for the export JSON. It needs an 'entries' key
            // which holds an *object* (not an array), where keys are the display indices (as strings).
            const exportData = { entries: {} };

            // Iterate through each entry element in its *current visual order*.
            entries.forEach((entryDiv, currentIndex) => {
                // Create a fresh data object for this entry. Start by cloning the default structure
                // to ensure all expected fields are present. Use Object.assign for a shallow clone.
                const entryData = Object.assign({}, defaultEntryData);

                // --- Extract data from the form fields within this specific entryDiv ---
                // Use optional chaining (?.) and nullish coalescing (??) for safety.
                const uidInput = entryDiv.querySelector('input[name="uid"]');
                const commentInput = entryDiv.querySelector('input[name="comment"]');
                const keyInput = entryDiv.querySelector('input[name="key"]');
                const keySecondaryInput = entryDiv.querySelector('input[name="keysecondary"]');
                const selectiveCheckbox = entryDiv.querySelector('input[name="selective"]');
                const logicRadio = entryDiv.querySelector('input[name="selectiveLogic"]:checked'); // Get the *checked* radio button
                const strategyRadio = entryDiv.querySelector('input[name="strategy"]:checked'); // Get the *checked* strategy radio
                const contentTextArea = entryDiv.querySelector('textarea[name="content"]');
                const summaryDiv = entryDiv.querySelector('.entry-summary'); // Needed to check disabled class
                // Get disabled state directly from the summary div's class list. Fallback to default if summary not found.
                const isDisabled = summaryDiv ? summaryDiv.classList.contains('entry-disabled') : defaultEntryData.disable;

                // --- Populate the entryData object with extracted values ---
                // Use the existing UID if available, otherwise find a new one (shouldn't be needed if UIDs are handled correctly).
                entryData.uid = uidInput ? parseInt(uidInput.value, 10) : findNextAvailableUid();
                entryData.comment = commentInput ? commentInput.value : ''; // Get comment value
                // Process keyword inputs: split by comma, trim whitespace, filter empty strings.
                entryData.key = keyInput ? keyInput.value.split(',').map(k => k.trim()).filter(Boolean) : [];
                entryData.keysecondary = keySecondaryInput ? keySecondaryInput.value.split(',').map(k => k.trim()).filter(Boolean) : [];
                entryData.selective = selectiveCheckbox ? selectiveCheckbox.checked : defaultEntryData.selective; // Get selective state
                // Get logic value (parsed as int), fallback to default.
                entryData.selectiveLogic = logicRadio ? parseInt(logicRadio.value, 10) : defaultEntryData.selectiveLogic;
                entryData.disable = isDisabled; // Set the disable state.

                // Determine constant/vectorized flags based on the selected strategy radio button.
                const strategyValue = strategyRadio ? strategyRadio.value : 'normal'; // Default to 'normal' if none selected
                entryData.constant = (strategyValue === 'constant' || strategyValue === 'vectorized'); // Constant is true if strategy is constant OR vectorized
                entryData.vectorized = (strategyValue === 'vectorized'); // Vectorized is true only if strategy is vectorized

                entryData.content = contentTextArea ? contentTextArea.value : ''; // Get content value
                // *** Crucial: Set the displayIndex to the entry's current visual order in the editor. ***
                entryData.displayIndex = currentIndex;

                // --- Ensure all default fields exist (Optional but good practice) ---
                // This loop adds any fields from the default structure that might be missing
                // from the current `entryData` object (e.g., if a field wasn't represented by an input).
                for (const key in defaultEntryData) {
                    if (!(key in entryData)) { // If the key from defaults is not already in entryData
                        // Avoid overwriting uid and displayIndex which were explicitly set above.
                        if (key !== 'uid' && key !== 'displayIndex') {
                             entryData[key] = defaultEntryData[key];
                        }
                    }
                }
                 // Double-check UID and displayIndex are set correctly, especially if inputs were somehow missing.
                 if (!uidInput) entryData.uid = findNextAvailableUid(); // Assign new UID if input was missing
                 // Ensure displayIndex uses currentIndex even if hidden input was missing
                 if (!entryDiv.querySelector('input[name="displayIndex"]')) entryData.displayIndex = currentIndex;


                // --- Add the processed entryData to the export object ---
                // Use the current visual index (`currentIndex`) as the key in the 'entries' object.
                // The lorebook format expects an object here, not an array.
                exportData.entries[currentIndex.toString()] = entryData; // Ensure key is string if needed
            });

            // --- Trigger JSON File Download ---
            try {
                // Convert the JavaScript exportData object into a JSON string.
                // `null, 2` formats the JSON with indentation for readability.
                const jsonString = JSON.stringify(exportData, null, 2);
                // Create a Blob (Binary Large Object) containing the JSON data. Specify UTF-8 charset.
                const blob = new Blob([jsonString], { type: 'application/json;charset=utf-8' });
                // Create a temporary URL representing the Blob object.
                const url = URL.createObjectURL(blob);

                // Create a temporary anchor (link) element to trigger the download.
                const a = document.createElement('a');
                a.href = url; // Set the link's href to the Blob URL.

                // Suggest a filename for the download. Prefix with "edited_" and use the loaded filename (stripping .json).
                const exportFilenameBase = loadedFileName && loadedFileName !== 'lorebook.json' ? loadedFileName.replace(/\.json$/i, '') : 'lorebook';
                const exportFilename = `edited_${exportFilenameBase}.json`;
                a.download = exportFilename; // Set the download attribute to the desired filename.

                // Temporarily add the link to the document, simulate a click, then remove it.
                document.body.appendChild(a);
                a.click(); // Trigger the download.
                document.body.removeChild(a);

                // Release the object URL to free up memory.
                URL.revokeObjectURL(url);

                console.log("Export successful.");

            } catch (error) {
                // Log any errors during the export process to the console.
                console.error("Error during JSON export:", error);
                // Inform the user that an error occurred.
                alert("An error occurred while exporting the JSON file.");
            }
        }


        // --- Initial Setup on Page Load ---
        // Wait for the DOM to be fully loaded before running setup scripts.
        document.addEventListener('DOMContentLoaded', () => {
             // Only run setup if the main container exists.
             if (lorebookContainer) {
                 // --- Initial ARIA Setup and Auto-Grow for Existing Entries ---
                 // Iterate through all entry divs that were rendered by PHP.
                 lorebookContainer.querySelectorAll('.lore-entry').forEach((entryDiv) => {
                     // Get the original index stored in the data attribute.
                     const originalIndex = entryDiv.dataset.originalIndex;
                     if (originalIndex !== undefined) {
                         // Set up ARIA attributes using the original index (or UID if preferred) for ID stability.
                         setupAriaForEntry(entryDiv, originalIndex); // Consider using UID here if available and stable
                     } else {
                         // Log a warning if the data attribute is missing (shouldn't happen).
                         console.warn("Missing original index data attribute for ARIA setup:", entryDiv);
                     }
                     // Initialize auto-grow for the textarea within this existing entry.
                     const textarea = entryDiv.querySelector('textarea[name="content"]');
                     autoGrowTextarea(textarea);
                 });

                 // --- Set Initial Button States ---
                 // Call updateIndicesAndButtons once at the start to set the correct
                 // initial state for move up/down buttons (e.g., disable 'up' on first item).
                 updateIndicesAndButtons();
             }
        });

        // --- Helper function to decode HTML entities ---
        /**
         * Decodes HTML special characters (like &, <, >, ") back into
         * their original characters. Useful when setting form field values that were
         * escaped by PHP's htmlspecialchars().
         * @param {string} str - The string containing HTML entities.
         * @returns {string} The decoded string.
         */
        function htmlspecialcharsDecode(str) {
            if (typeof str !== 'string') return str; // Return non-strings as-is
            // Create a temporary textarea element in memory (not added to DOM).
            const temp = document.createElement("textarea");
            // Set its innerHTML to the encoded string. The browser automatically decodes entities here.
            temp.innerHTML = str;
            // Return the textarea's 'value', which now contains the decoded text.
            return temp.value;
        }

    </script>

</body>
</html>
