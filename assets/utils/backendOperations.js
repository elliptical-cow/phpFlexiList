/**
 * Backend Operations Utilities
 * 
 * Functions for handling backend integration, data synchronization, and state management.
 * Extracted from main app component to improve maintainability and testability.
 */

// Create namespace
window.BackendOperations = window.BackendOperations || {};

/**
 * Load checklist data from backend
 * @param {Object} backendService - Backend service instance
 * @param {string} listId - List identifier
 * @returns {Promise<Object>} Load result with data or error
 */
async function loadFromBackend(backendService, listId) {
    if (!listId) {
        return { success: false, error: 'No list ID provided' };
    }
    
    try {
        const data = await backendService.loadList(listId);
        
        return {
            success: true,
            data,
            title: (data.Metadata && data.Metadata.Title) || data.Title || 'Your Checklist',
            hideChecked: (data.Metadata && data.Metadata.Hide_Checked) || false,
            showNoteEditor: (data.Metadata && data.Metadata.Show_Note_Editor) !== undefined ? (data.Metadata && data.Metadata.Show_Note_Editor) : true,
            checklist: data.Checklist || [],
            error: null
        };
    } catch (error) {
        console.error('Error loading list:', error);
        return {
            success: false,
            data: null,
            title: null,
            hideChecked: null,
            checklist: [],
            error: `Loading error: ${error.message}`
        };
    }
}

/**
 * Save checklist data to backend
 * @param {Object} backendService - Backend service instance
 * @param {string} listId - List identifier
 * @param {Array} checklist - Checklist data to save
 * @param {string} title - List title
 * @param {boolean} hideChecked - Hide checked items status
 * @param {boolean} showNoteEditor - Show note editor status
 * @param {Function} cleanDataForExport - Function to clean data before export
 * @returns {Promise<Object>} Save result
 */
async function saveToBackend(backendService, listId, checklist, title, hideChecked, showNoteEditor, cleanDataForExport) {
    if (!listId) {
        return { success: false, error: 'No list ID provided' };
    }
    
    try {
        const cleanData = cleanDataForExport(checklist);
        const payload = { 
            Metadata: { 
                Title: title,
                Hide_Checked: hideChecked,
                Show_Note_Editor: showNoteEditor
            },
            Checklist: cleanData 
        };
        
        await backendService.saveList(listId, payload);
        
        return {
            success: true,
            payload,
            error: null
        };
    } catch (error) {
        console.error('Error saving:', error);
        return {
            success: false,
            payload: null,
            error: error.message
        };
    }
}

/**
 * Handle item update with patch optimization
 * @param {Object} options - Update options
 * @param {Object} options.backendService - Backend service instance
 * @param {string} options.listId - List identifier
 * @param {Array} options.checklist - Current checklist data
 * @param {string} options.title - Current list title
 * @param {Object} options.lastSavedData - Last saved data for comparison
 * @param {Function} options.cleanDataForExport - Function to clean data
 * @param {boolean} options.patchDebug - Enable debug logging
 * @param {Object} options.patchStats - Patch statistics object
 * @returns {Promise<Object>} Update result
 */
async function handleItemUpdate(options) {
    const {
        backendService,
        listId,
        checklist,
        title,
        lastSavedData,
        cleanDataForExport,
        patchDebug = false,
        patchStats = {}
    } = options;
    
    if (!listId) {
        return { success: false, error: 'No list ID provided' };
    }
    
    try {
        // Generate current data in the same format as backend expects
        const currentData = { 
            Metadata: { 
                Title: title,
                Hide_Checked: options.hideChecked || false,
                Show_Note_Editor: options.showNoteEditor !== undefined ? options.showNoteEditor : true
            },
            Checklist: cleanDataForExport(checklist) 
        };
        
        // Generate patches using fast-json-patch (assuming it's globally available)
        const patches = window.jsonpatch ? 
            window.jsonpatch.compare(lastSavedData || {}, currentData) : 
            [];
        
        if (patches.length === 0) {
            if (patchDebug) console.log('No changes detected, skipping update');
            return { success: true, method: 'no-change', patches: [], currentData };
        }
        
        if (patchDebug) {
            console.log('Generated patches:', patches);
            console.log('Patch size:', JSON.stringify(patches).length, 'bytes');
            console.log('Full data size:', JSON.stringify(currentData).length, 'bytes');
        }
        
        try {
            // Try patch first
            const result = await backendService.patchList(listId, patches);
            
            // Update statistics
            const fullSize = JSON.stringify(currentData).length;
            const patchSize = JSON.stringify(patches).length;
            if (patchStats) {
                patchStats.patchCount = (patchStats.patchCount || 0) + 1;
                patchStats.totalBytesSaved = (patchStats.totalBytesSaved || 0) + (fullSize - patchSize);
            }
            
            if (patchDebug) {
                console.log('Patch applied successfully');
                console.log(`Bandwidth saved: ${fullSize - patchSize} bytes`);
            }
            
            return {
                success: true,
                method: 'patch',
                result,
                patches,
                currentData,
                bandwidthSaved: fullSize - patchSize
            };
            
        } catch (patchError) {
            console.warn('Patch failed, falling back to full update:', patchError);
            
            // Fallback to full update
            try {
                await backendService.saveList(listId, currentData);
                
                if (patchStats) {
                    patchStats.fallbackCount = (patchStats.fallbackCount || 0) + 1;
                }
                
                return {
                    success: true,
                    method: 'full-fallback',
                    currentData,
                    fallbackReason: patchError.message
                };
                
            } catch (fallbackError) {
                console.error('Both patch and full update failed:', fallbackError);
                return {
                    success: false,
                    method: 'failed',
                    error: fallbackError.message,
                    fallbackReason: patchError.message
                };
            }
        }
        
    } catch (error) {
        console.error('Error in handleItemUpdate:', error);
        return {
            success: false,
            method: 'error',
            error: error.message
        };
    }
}

/**
 * Create a debounced update handler
 * @param {Function} updateFunction - Function to call for updates
 * @param {number} delay - Debounce delay in milliseconds
 * @returns {Object} Debounced handler with execute and cancel methods
 */
function createDebouncedUpdateHandler(updateFunction, delay = 50) {
    let timeoutId = null;
    
    return {
        execute: (...args) => {
            // Clear any pending update to prevent race conditions
            if (timeoutId) {
                clearTimeout(timeoutId);
            }
            
            // Debounce updates to prevent concurrent requests
            timeoutId = setTimeout(() => {
                updateFunction(...args);
                timeoutId = null;
            }, delay);
        },
        
        cancel: () => {
            if (timeoutId) {
                clearTimeout(timeoutId);
                timeoutId = null;
            }
        },
        
        isPending: () => timeoutId !== null
    };
}

/**
 * Uncheck all items in a checklist recursively
 * @param {Array} checklist - Checklist items to uncheck
 * @returns {number} Number of items unchecked
 */
function uncheckAllItems(checklist) {
    let uncheckedCount = 0;
    
    function uncheck(items) {
        if (!Array.isArray(items)) return;
        
        items.forEach(item => {
            if (item.Type === 'Item' && item.Checked) {
                item.Checked = false;
                uncheckedCount++;
            } else if (item.Type === 'Category' && item.Content) {
                uncheck(item.Content);
            }
        });
    }
    
    uncheck(checklist);
    return uncheckedCount;
}

/**
 * Initialize expanded state for all categories
 * @param {Array} items - Items to initialize
 * @param {boolean} defaultExpanded - Default expansion state
 */
function initializeExpandedCategories(items, defaultExpanded = true) {
    if (!Array.isArray(items)) return;
    
    items.forEach(item => {
        if (item.Type === 'Category') {
            if (item.Expanded === undefined) {
                item.Expanded = defaultExpanded;
            }
            if (item.Content) {
                initializeExpandedCategories(item.Content, defaultExpanded);
            }
        }
    });
}

/**
 * Generate status message for save operations
 * @param {string} method - Save method used ('patch', 'full', 'full-fallback', etc.)
 * @param {boolean} success - Whether operation succeeded
 * @param {Object} details - Additional details about the operation
 * @returns {string} Status message
 */
function generateSaveStatusMessage(method, success, details = {}) {
    if (!success) {
        return `Error: ${details.error || 'Unknown error'}`;
    }
    
    switch (method) {
        case 'patch':
            return 'Saved ✓ (Patch)';
        case 'full':
            return 'Saved ✓';
        case 'full-fallback':
            return 'Saved ✓ (Full)';
        case 'no-change':
            return ''; // No status for no changes
        default:
            return 'Saved ✓';
    }
}

/**
 * Calculate bandwidth statistics for patch operations
 * @param {Object} patches - JSON patches applied
 * @param {Object} fullData - Full data that would have been sent
 * @returns {Object} Bandwidth statistics
 */
function calculateBandwidthStats(patches, fullData) {
    const patchSize = JSON.stringify(patches).length;
    const fullSize = JSON.stringify(fullData).length;
    
    return {
        patchSize,
        fullSize,
        saved: fullSize - patchSize,
        efficiency: fullSize > 0 ? ((fullSize - patchSize) / fullSize * 100).toFixed(1) : 0
    };
}

// Export functions to global namespace
window.BackendOperations.loadFromBackend = loadFromBackend;
window.BackendOperations.saveToBackend = saveToBackend;
window.BackendOperations.handleItemUpdate = handleItemUpdate;
window.BackendOperations.createDebouncedUpdateHandler = createDebouncedUpdateHandler;
window.BackendOperations.uncheckAllItems = uncheckAllItems;
window.BackendOperations.initializeExpandedCategories = initializeExpandedCategories;
window.BackendOperations.generateSaveStatusMessage = generateSaveStatusMessage;
window.BackendOperations.calculateBandwidthStats = calculateBandwidthStats;