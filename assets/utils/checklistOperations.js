/**
 * Checklist Operations Utilities
 * 
 * Pure functions for handling checklist manipulation operations.
 * Extracted from main app component to improve maintainability and testability.
 */

// Create namespace
window.ChecklistOperations = window.ChecklistOperations || {};

/**
 * Delete an item from the checklist, preserving children if it's a category
 * @param {Array} list - The list containing the item to delete
 * @param {number} index - Index of the item to delete
 * @returns {Object} Result with the modified list and any preserved children
 */
function deleteItem(list, index) {
    const itemToDelete = list[index];
    let preservedChildren = [];
    
    // If it's a category with children, preserve the children
    if (itemToDelete.Type === 'Category' && itemToDelete.Content && itemToDelete.Content.length > 0) {
        preservedChildren = [...itemToDelete.Content]; // Create a copy
        
        // Remove the category first
        list.splice(index, 1);
        
        // Append children to the end of the parent list
        list.push(...preservedChildren);
        
        // Reorder the entire list
        reorderItems(list);
    } else {
        // Simple deletion for items or empty categories
        list.splice(index, 1);
        reorderItems(list);
    }
    
    return {
        list,
        preservedChildren,
        success: true
    };
}

/**
 * Add a new item to a parent category
 * @param {Object} parentItem - The parent category to add to
 * @param {string} type - Type of item to add ('Item' or 'Category')
 * @param {string} name - Optional name for the item (defaults to "New {type}")
 * @returns {Object} The newly created item
 */
function addItem(parentItem, type, name = null) {
    const newItem = {
        "Type": type,
        "Name": name || (window.t ? window.t(`New ${type}`) : `New ${type}`),  // Default name that will be selected
        "Order": parentItem.Content ? parentItem.Content.length + 1 : 1,
        "Checked": type === "Item" ? false : undefined,
        "Notes": type === "Item" ? "" : undefined,
        "Content": type === "Category" ? [] : undefined,
        "Expanded": type === "Category" ? true : undefined,  // New categories start expanded
        "_isNewItem": true  // Marker for new items to trigger edit mode
    };

    // Ensure the category has a Content array
    if (parentItem.Type === 'Category') {
        if (!parentItem.Content) {
            parentItem.Content = [];
        }
        // Auto-expand parent category when adding items to it
        parentItem.Expanded = true;
        parentItem.Content.push(newItem);
        reorderItems(parentItem.Content);
    }
    
    return newItem;
}

/**
 * Add a new item to the root checklist
 * @param {Array} checklist - The root checklist array
 * @param {string} type - Type of item to add ('Item' or 'Category')
 * @param {string} name - Optional name for the item (defaults to "New {type}")
 * @returns {Object} The newly created item
 */
function addItemToRoot(checklist, type, name = null) {
    const newItem = {
        "Type": type,
        "Name": name || (window.t ? window.t(`New ${type}`) : `New ${type}`),
        "Order": checklist.length + 1,
        "Checked": type === "Item" ? false : undefined,
        "Notes": type === "Item" ? "" : undefined,
        "Content": type === "Category" ? [] : undefined,
        "Expanded": type === "Category" ? true : undefined,
        "_isNewItem": true
    };

    checklist.push(newItem);
    reorderItems(checklist);
    
    return newItem;
}

/**
 * Move an item up or down within a list
 * @param {Array} list - The list containing the item
 * @param {number} index - Current index of the item
 * @param {string} direction - 'up' or 'down'
 * @returns {boolean} Success status
 */
function moveItem(list, index, direction) {
    const item = list[index];
    
    if (direction === 'up' && index > 0) {
        list.splice(index, 1);
        list.splice(index - 1, 0, item);
        reorderItems(list);
        return true;
    } else if (direction === 'down' && index < list.length - 1) {
        list.splice(index, 1);
        list.splice(index + 1, 0, item);
        reorderItems(list);
        return true;
    }
    
    return false; // Move not possible
}

/**
 * Move an item to a different category
 * @param {Array} sourceList - List containing the item to move
 * @param {number} sourceIndex - Index of item in source list
 * @param {Object} targetCategory - Target category to move item to
 * @returns {Object} Result of the move operation
 */
function moveItemToCategory(sourceList, sourceIndex, targetCategory) {
    if (sourceIndex < 0 || sourceIndex >= sourceList.length) {
        return { success: false, error: 'Invalid source index' };
    }
    
    if (!targetCategory || targetCategory.Type !== 'Category') {
        return { success: false, error: 'Invalid target category' };
    }
    
    // Remove item from source
    const [movedItem] = sourceList.splice(sourceIndex, 1);
    
    // Ensure target category has Content array
    if (!targetCategory.Content) {
        targetCategory.Content = [];
    }
    
    // Add to target category
    targetCategory.Content.push(movedItem);
    
    // Reorder both lists
    reorderItems(sourceList);
    reorderItems(targetCategory.Content);
    
    // Auto-expand target category
    targetCategory.Expanded = true;
    
    return {
        success: true,
        movedItem,
        sourceList,
        targetCategory
    };
}

/**
 * Reorder items in a list by updating their Order property
 * @param {Array} list - List of items to reorder
 */
function reorderItems(list) {
    if (!Array.isArray(list)) return;
    
    list.forEach((item, idx) => {
        item.Order = idx + 1;
    });
    
    // Re-sort after reordering to ensure consistency
    list.sort((a, b) => a.Order - b.Order);
}

/**
 * Sort items by their Order property
 * @param {Array} items - Items to sort
 * @returns {Array} Sorted items array
 */
function sortItems(items) {
    if (!Array.isArray(items)) return [];
    return [...items].sort((a, b) => a.Order - b.Order);
}

/**
 * Find an item by reference in a nested checklist structure
 * @param {Array} checklist - Root checklist to search
 * @param {Object} targetItem - Item to find
 * @returns {Object|null} Result with item location or null if not found
 */
function findItemLocation(checklist, targetItem) {
    // Check root level
    const rootIndex = checklist.indexOf(targetItem);
    if (rootIndex !== -1) {
        return {
            list: checklist,
            index: rootIndex,
            parentItem: null,
            found: true
        };
    }
    
    // Search nested categories
    function searchNested(items, parentItem = null) {
        for (let i = 0; i < items.length; i++) {
            const item = items[i];
            
            if (item === targetItem) {
                return {
                    list: items,
                    index: i,
                    parentItem,
                    found: true
                };
            }
            
            if (item.Type === 'Category' && item.Content) {
                const nested = searchNested(item.Content, item);
                if (nested.found) {
                    return nested;
                }
            }
        }
        return { found: false };
    }
    
    return searchNested(checklist);
}

/**
 * Filter out checked items from a checklist (non-recursive, preserves object references)
 * @param {Array} items - Items to filter
 * @returns {Array} Filtered items with unchecked items only (preserves category object references for Vue reactivity)
 */
function filterCheckedItems(items) {
    if (!Array.isArray(items)) return [];
    
    return items.filter(item => {
        if (item.Type === 'Category') {
            return true; // Keep all categories (preserve object references)
        }
        return !item.Checked; // Only keep unchecked items
    });
    // Note: Nested filtering is handled by individual ListItem components via sortedContent
}

/**
 * Count total items in checklist (recursive)
 * @param {Array} items - Items to count
 * @returns {Object} Count statistics
 */
function countItems(items) {
    if (!Array.isArray(items)) return { total: 0, checked: 0, categories: 0 };
    
    let total = 0;
    let checked = 0;
    let categories = 0;
    
    items.forEach(item => {
        if (item.Type === 'Category') {
            categories++;
            if (item.Content) {
                const subCounts = countItems(item.Content);
                total += subCounts.total;
                checked += subCounts.checked;
                categories += subCounts.categories;
            }
        } else {
            total++;
            if (item.Checked) {
                checked++;
            }
        }
    });
    
    return { total, checked, categories };
}

/**
 * Validate checklist item structure
 * @param {Object} item - Item to validate
 * @returns {Object} Validation result
 */
function validateItem(item) {
    if (!item || typeof item !== 'object') {
        return { valid: false, error: 'Item must be an object' };
    }
    
    if (!item.Type || (item.Type !== 'Item' && item.Type !== 'Category')) {
        return { valid: false, error: 'Item must have Type of "Item" or "Category"' };
    }
    
    if (!item.Name || typeof item.Name !== 'string') {
        return { valid: false, error: 'Item must have a valid Name' };
    }
    
    if (item.Order === undefined || typeof item.Order !== 'number') {
        return { valid: false, error: 'Item must have a valid Order number' };
    }
    
    if (item.Type === 'Item') {
        if (typeof item.Checked !== 'boolean') {
            return { valid: false, error: 'Item type "Item" must have boolean Checked property' };
        }
    }
    
    if (item.Type === 'Category') {
        if (item.Content && !Array.isArray(item.Content)) {
            return { valid: false, error: 'Category Content must be an array' };
        }
    }
    
    return { valid: true, error: null };
}

// Export functions to global namespace
window.ChecklistOperations.deleteItem = deleteItem;
window.ChecklistOperations.addItem = addItem;
window.ChecklistOperations.addItemToRoot = addItemToRoot;
window.ChecklistOperations.moveItem = moveItem;
window.ChecklistOperations.moveItemToCategory = moveItemToCategory;
window.ChecklistOperations.reorderItems = reorderItems;
window.ChecklistOperations.sortItems = sortItems;
window.ChecklistOperations.findItemLocation = findItemLocation;
window.ChecklistOperations.filterCheckedItems = filterCheckedItems;
window.ChecklistOperations.countItems = countItems;
window.ChecklistOperations.validateItem = validateItem;