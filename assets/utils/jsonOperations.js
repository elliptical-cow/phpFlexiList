/**
 * JSON Operations Utilities
 * 
 * Pure functions for handling JSON import/export operations.
 * Extracted from main app component to improve maintainability and testability.
 */

// Create namespace
window.JsonOperations = window.JsonOperations || {};

/**
 * Parse JSON input and extract checklist data
 * @param {string} jsonString - JSON string to parse
 * @returns {Object} Result object with parsed data or error
 */
function parseJsonInput(jsonString) {
    try {
        const parsed = JSON.parse(jsonString);
        
        if (parsed && parsed.Checklist) {
            return {
                success: true,
                title: (parsed.Metadata && parsed.Metadata.Title) || parsed.Title || 'Your Checklist',
                hideChecked: (parsed.Metadata && parsed.Metadata.Hide_Checked) || false,
                showNoteEditor: (parsed.Metadata && parsed.Metadata.Show_Note_Editor) !== undefined ? (parsed.Metadata && parsed.Metadata.Show_Note_Editor) : true,
                checklist: parsed.Checklist,
                error: null
            };
        } else {
            return {
                success: false,
                title: null,
                hideChecked: null,
                showNoteEditor: null,
                checklist: null,
                error: "Invalid JSON format. Expected an object with 'Checklist' as main key."
            };
        }
    } catch (e) {
        return {
            success: false,
            title: null,
            hideChecked: null,
            showNoteEditor: null,
            checklist: null,
            error: "Error parsing JSON: " + e.message
        };
    }
}

/**
 * Export checklist data to JSON string
 * @param {Array} checklist - Checklist items array
 * @param {string} title - List title
 * @param {boolean} hideChecked - Hide checked items status
 * @param {boolean} showNoteEditor - Show note editor status
 * @returns {string} Formatted JSON string
 */
function exportToJson(checklist, title, hideChecked = false, showNoteEditor = true) {
    const cleanData = cleanDataForExport(checklist);
    return JSON.stringify({ 
        Metadata: { 
            Title: title,
            Hide_Checked: hideChecked,
            Show_Note_Editor: showNoteEditor
        },
        Checklist: cleanData 
    }, null, 2);
}

/**
 * Clean checklist data for export by removing invalid items and internal properties
 * @param {Array} items - Array of checklist items
 * @returns {Array} Cleaned array of items
 */
function cleanDataForExport(items) {
    return items.filter(item => {
        // Skip items with empty names (they're being edited or invalid)
        if (!item.Name || item.Name.trim() === '') {
            return false;
        }
        return true;
    }).map(item => {
        const cleanItem = {
            Type: item.Type,
            Name: item.Name,
            Order: item.Order
        };
        
        if (item.Type === 'Item') {
            cleanItem.Checked = item.Checked;
            if (item.Notes !== undefined) cleanItem.Notes = item.Notes;
        } else if (item.Type === 'Category') {
            // Always add the Content array for categories, even if empty
            cleanItem.Content = item.Content ? cleanDataForExport(item.Content) : [];
            // Include expansion state, default to true if not specified
            cleanItem.Expanded = item.Expanded !== undefined ? item.Expanded : true;
        }
        
        return cleanItem;
    });
}

/**
 * Validate checklist structure
 * @param {Array} checklist - Checklist to validate
 * @returns {Object} Validation result
 */
function validateChecklistStructure(checklist) {
    if (!Array.isArray(checklist)) {
        return { valid: false, error: 'Checklist must be an array' };
    }
    
    for (let i = 0; i < checklist.length; i++) {
        const item = checklist[i];
        
        if (!item.Type || !item.Name) {
            return { 
                valid: false, 
                error: `Item at index ${i} is missing required Type or Name` 
            };
        }
        
        if (item.Type !== 'Item' && item.Type !== 'Category') {
            return { 
                valid: false, 
                error: `Item at index ${i} has invalid Type: ${item.Type}` 
            };
        }
        
        if (item.Type === 'Category' && item.Content) {
            const nestedValidation = validateChecklistStructure(item.Content);
            if (!nestedValidation.valid) {
                return nestedValidation;
            }
        }
    }
    
    return { valid: true, error: null };
}

// Export functions to global namespace
window.JsonOperations.parseJsonInput = parseJsonInput;
window.JsonOperations.exportToJson = exportToJson;
window.JsonOperations.cleanDataForExport = cleanDataForExport;
window.JsonOperations.validateChecklistStructure = validateChecklistStructure;