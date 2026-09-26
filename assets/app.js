// Utility functions are loaded via script tags and available in global namespaces:
// - window.JsonOperations.*
// - window.ChecklistOperations.*
// - window.BackendOperations.*

const app = Vue.createApp({
    data() {
        return {
            draggedItem: null, // Currently dragged item
            draggedItemSource: null, // Source information for dragged item
            showMoveModal: false, // Show move modal
            itemToMove: null, // Item selected for moving via modal
            isDragOverRoot: false, // Track if dragging over root area
            showMainActions: false, // Show main actions dropdown
            
            // Backend integration
            backendMode: false, // Whether we're using backend or local mode
            listId: null, // Current list ID from URL
            backendService: null, // Backend service instance
            isLoading: false, // Loading state
            saveStatus: '', // Save status message
            
            // Auto-categorization
            isAutoCategorizing: false, // Auto-categorization loading state
            showKIConsentDialog: false, // KI consent dialog visibility
            kiConsentGiven: false, // KI consent checkbox state
            
            // Save as new list
            isSavingNewList: false, // Save as new list loading state
            
            // Start new list
            isCreatingNewList: false, // Start new list loading state
            
            // Patch functionality
            lastSavedData: null, // Track last saved state for patch generation
            patchDebug: false,   // Enable patch debugging
            patchStats: {        // Track patch statistics
                patchCount: 0,
                fallbackCount: 0,
                totalBytesSaved: 0
            },
            debouncedUpdateHandler: null, // Debounced update handler
            
            // List title
            listTitle: window.t ? window.t("Your Checklist") : "Your Checklist", // Default title
            isEditingTitle: false, // Title editing state
            
            // Hide checked items
            hideCheckedItems: false, // Whether to hide checked items
            
            // Note editor visibility
            showNoteEditor: true, // Whether to show note editor functionality
            
            jsonInput: JSON.stringify({
                "Metadata": {
                    "Title": window.t ? window.t("Your Checklist") : "Your Checklist",
                    "Hide_Checked": false,
                    "Show_Note_Editor": true
                },
                "Checklist": [
                    {
                        "Type": "Category",
                        "Name": "Dairy",
                        "Order": 1,
                        "Content": [
                            {
                                "Type": "Category",
                                "Name": "Milk Products",
                                "Order": 1,
                                "Content": [
                                    {
                                        "Type": "Item",
                                        "Name": "Milk",
                                        "Notes": "1.5% Fat",
                                        "Checked": false,
                                        "Order": 1
                                    },
                                    {
                                        "Type": "Item",
                                        "Name": "Yogurt",
                                        "Notes": "Natural",
                                        "Checked": false,
                                        "Order": 2
                                    }
                                ]
                            },
                            {
                                "Type": "Item",
                                "Name": "Butter",
                                "Notes": "",
                                "Checked": false,
                                "Order": 2
                            }
                        ]
                    },
                    {
                        "Type": "Category",
                        "Name": "Fruits and Vegetables",
                        "Order": 2,
                        "Content": [
                            {
                                "Type": "Item",
                                "Name": "Tomatoes",
                                "Notes": "Cherry tomatoes",
                                "Checked": false,
                                "Order": 1
                            },
                            {
                                "Type": "Item",
                                "Name": "Apples",
                                "Notes": "Red apples",
                                "Checked": false,
                                "Order": 2
                            }
                        ]
                    },
                    {
                        "Type": "Item",
                        "Name": "Frozen Pizza",
                        "Notes": "For emergencies",
                        "Checked": false,
                        "Order": 3
                    }
                ]
            }, null, 2),
            checklist: [],
            jsonError: null,
            
            // Auto-complete functionality
            autoCompleteConfig: {
                enabled: window.FLEXI_CONFIG?.AUTO_COMPLETE?.ENABLED ?? true,
                minChars: window.FLEXI_CONFIG?.AUTO_COMPLETE?.MIN_CHARS ?? 3,
                maxSuggestions: window.FLEXI_CONFIG?.AUTO_COMPLETE?.MAX_SUGGESTIONS ?? 10,
                showCheckedFirst: window.FLEXI_CONFIG?.AUTO_COMPLETE?.SHOW_CHECKED_FIRST ?? true,
                debounceMs: window.FLEXI_CONFIG?.AUTO_COMPLETE?.DEBOUNCE_MS ?? 150,
                caseSensitive: window.FLEXI_CONFIG?.AUTO_COMPLETE?.CASE_SENSITIVE ?? false
            },
            searchIndex: [], // Flattened list of all items for searching
            
            // Category progress indicators
            categoryCountMap: {} // Store counts for each category path
        };
    },
    computed: {
        // Configuration-based feature flags
        showPatchDebug() {
            return window.FLEXI_CONFIG?.FEATURES?.SHOW_PATCH_DEBUG ?? false;
        },
        showJsonView() {
            return window.FLEXI_CONFIG?.FEATURES?.SHOW_JSON_VIEW ?? false;
        },
        debugMode() {
            return window.FLEXI_CONFIG?.FEATURES?.DEBUG_MODE ?? false;
        },
        // Filtered checklist that hides checked items if requested
        filteredChecklist() {
            if (!this.hideCheckedItems) {
                return this.checklist;
            }
            return this.filterCheckedItems(this.checklist);
        }
    },
    mounted() {
        try {
            this.initializeApp();
            // Set initial document title and meta tags
            this.updateTitleAndMeta(this.listTitle);
            // Build initial search index
            this.buildSearchIndex();
            // Initialize category counts
            this.updateCategoryCountsAfterChange();
            
            // Signal that Vue app is mounted and ready
            document.dispatchEvent(new CustomEvent('appMounted'));
        } catch (error) {
            console.error('Error during Vue app mounting:', error);
            // Still signal mounted to hide loading, but show error state
            document.dispatchEvent(new CustomEvent('appMounted'));
            this.saveStatus = 'Application initialization failed';
        }
    },
    provide() {
        return {
            // AutoComplete Services
            autoCompleteService: {
                config: this.autoCompleteConfig,
                getSuggestions: (query, currentItem = null) => this.getSuggestions(query, currentItem)
            }
        };
    },
    watch: {
        // Update document title and meta tags when listTitle changes
        listTitle(newTitle) {
            this.updateTitleAndMeta(newTitle);
        },
        // Rebuild search index and category counts when checklist changes
        checklist: {
            handler() {
                this.buildSearchIndex();
                this.updateCategoryCountsAfterChange();
            },
            deep: true
        },
        
        // Save when hideCheckedItems changes
        hideCheckedItems() {
            this.handleItemUpdate();
        },
        
        // Save when showNoteEditor changes
        showNoteEditor() {
            this.handleItemUpdate();
        }
    },
    methods: {
        // Translation helper method with debugging
        t(key) {
            console.log(`[APP] Translating "${key}", window.t exists:`, !!window.t);
            if (window.t) {
                const result = window.t(key);
                console.log(`[APP] Translation result: "${result}"`);
                return result;
            }
            console.log(`[APP] No window.t, returning key: "${key}"`);
            return key;
        },
        
        // Update document title and meta tags for iPhone compatibility
        updateTitleAndMeta(title) {
            document.title = `${title} (FlexiList)`;
            
            // Update apple-mobile-web-app-title for iPhone home screen
            let appleTitle = document.querySelector('meta[name="apple-mobile-web-app-title"]');
            if (appleTitle) {
                appleTitle.setAttribute('content', title);
            }
            
            // Update application-name for other platforms
            let appName = document.querySelector('meta[name="application-name"]');
            if (appName) {
                appName.setAttribute('content', title);
            }
        },
        
        // Initialization
        async initializeApp() {
            // Initialize backend service
            this.backendService = new BackendService();
            
            // Debug checklist items
            console.log('[APP] Checklist items:', this.checklist);
            
            // Initialize debounced update handler with 500ms delay
            this.debouncedUpdateHandler = window.BackendOperations.createDebouncedUpdateHandler(
                this.performItemUpdate.bind(this), 
                500
            );
            
            // Check backend availability
            const backendInfo = this.backendService.checkBackendAvailability();
            
            if (backendInfo.available) {
                this.backendMode = true;
                this.listId = backendInfo.listId;
                this.backendService.backendUrl = backendInfo.backendUrl;
                await this.loadFromBackend();
            } else {
                // Local mode - use textarea
                this.backendMode = false;
                this.parseJson();
            }
        },

        // Main Actions Dropdown Methods
        toggleMainActions() {
            this.showMainActions = !this.showMainActions;
            // Close dropdown when clicking outside
            if (this.showMainActions) {
                this.$nextTick(() => {
                    document.addEventListener('click', this.closeMainActions);
                });
            }
        },
        closeMainActions() {
            this.showMainActions = false;
            document.removeEventListener('click', this.closeMainActions);
        },

        // Title editing methods
        startEditingTitle() {
            this.isEditingTitle = true;
            this.$nextTick(() => {
                if (this.$refs.titleInput) {
                    this.$refs.titleInput.focus();
                    this.$refs.titleInput.select();
                }
            });
        },
        
        finishEditingTitle() {
            this.isEditingTitle = false;
            // Provide default title if empty
            if (!this.listTitle || this.listTitle.trim() === '') {
                this.listTitle = 'Your Checklist';
            }
            // Save the updated title
            this.handleItemUpdate();
        },
        
        cancelEditingTitle() {
            this.isEditingTitle = false;
            // Title is already bound via v-model, no need to reset
        },

        // Copy list link to clipboard
        async copyListLink() {
            if (!this.backendMode || !this.listId) {
                alert('No shareable link available in local mode.');
                this.closeMainActions();
                return;
            }

            const baseUrl = window.FLEXI_CONFIG.BACKEND_URL.startsWith('http') ? window.FLEXI_CONFIG.BACKEND_URL : `http://${window.FLEXI_CONFIG.BACKEND_URL}`;
            const listUrl = `${baseUrl}/app?key=${this.listId}`;
            
            try {
                await navigator.clipboard.writeText(listUrl);
                this.saveStatus = 'Link copied to clipboard ✓';
                
                // Clear status after 2 seconds
                setTimeout(() => {
                    if (this.saveStatus.includes('Link copied')) {
                        this.saveStatus = '';
                    }
                }, 2000);
            } catch (error) {
                console.error('Failed to copy link:', error);
                // Fallback for older browsers
                try {
                    const textArea = document.createElement('textarea');
                    textArea.value = listUrl;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    this.saveStatus = 'Link copied to clipboard ✓';
                    
                    setTimeout(() => {
                        if (this.saveStatus.includes('Link copied')) {
                            this.saveStatus = '';
                        }
                    }, 2000);
                } catch (fallbackError) {
                    this.saveStatus = 'Failed to copy link';
                    setTimeout(() => {
                        if (this.saveStatus.includes('Failed to copy')) {
                            this.saveStatus = '';
                        }
                    }, 2000);
                }
            } finally {
                this.closeMainActions();
            }
        },

        // Generate random ID for new list
        generateRandomId(length = 12) {
            const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
            let result = '';
            for (let i = 0; i < length; i++) {
                result += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            return result;
        },

        // Save current list as new list with random ID
        async saveAsNewList() {
            if (!this.backendMode) {
                alert('Save as new list is only available in backend mode.');
                this.closeMainActions();
                return;
            }

            if (this.checklist.length === 0) {
                alert('Cannot save an empty list.');
                this.closeMainActions();
                return;
            }

            this.isSavingNewList = true;
            this.saveStatus = window.t ? window.t('Creating...') : 'Creating...';

            try {
                // Generate random ID for the new list
                const newListId = this.generateRandomId(12);
                
                // Prepare the data with " (copy)" added to title
                const copyTitle = this.listTitle + ' (copy)';
                const listData = {
                    Metadata: { 
                        Title: copyTitle,
                        Hide_Checked: this.hideCheckedItems,
                        Show_Note_Editor: this.showNoteEditor
                    },
                    Checklist: window.JsonOperations.cleanDataForExport(this.checklist)
                };

                // Save to backend with new ID
                await this.backendService.saveList(newListId, listData);
                
                // Create the new list URL
                const baseUrl = window.FLEXI_CONFIG.BACKEND_URL.startsWith('http') ? window.FLEXI_CONFIG.BACKEND_URL : `http://${window.FLEXI_CONFIG.BACKEND_URL}`;
                const newListUrl = `${baseUrl}/app?key=${newListId}`;
                const localUrl = `${window.location.origin}${window.location.pathname}?key=${newListId}`;
                
                this.saveStatus = 'New list created successfully ✓';
                
                // Show success message with the new list link and copy to clipboard
                if (confirm(`New list created successfully!\n\nTitle: ${copyTitle}\nList ID: ${newListId}\nURL: ${newListUrl}\n\nThe link has been copied to clipboard and you will be redirected to the new list.`)) {
                    // User clicked OK - copy to clipboard and redirect
                    try {
                        await navigator.clipboard.writeText(newListUrl);
                        this.saveStatus = 'Link copied to clipboard, redirecting...';
                    } catch (error) {
                        // Fallback for older browsers
                        try {
                            const textArea = document.createElement('textarea');
                            textArea.value = newListUrl;
                            document.body.appendChild(textArea);
                            textArea.select();
                            document.execCommand('copy');
                            document.body.removeChild(textArea);
                            this.saveStatus = 'Link copied to clipboard, redirecting...';
                        } catch (fallbackError) {
                            console.error('Failed to copy new list link:', fallbackError);
                            this.saveStatus = 'Redirecting to new list...';
                        }
                    }
                } else {
                    // User clicked Cancel - still redirect but don't copy
                    this.saveStatus = 'Redirecting to new list...';
                }
                
                // Redirect to the new list after a short delay
                setTimeout(() => {
                    window.location.href = localUrl;
                }, 500);

            } catch (error) {
                console.error('Error creating new list:', error);
                this.saveStatus = `Error creating new list: ${error.message}`;
            } finally {
                this.isSavingNewList = false;
                this.closeMainActions();
                
                // Clear status after 3 seconds
                setTimeout(() => {
                    if (this.saveStatus.includes('New list') || this.saveStatus.includes('Error creating')) {
                        this.saveStatus = '';
                    }
                }, 3000);
            }
        },

        // Start new list with unique random ID
        async startNewList() {
            if (!this.backendMode) {
                alert('Start new list is only available in backend mode.');
                this.closeMainActions();
                return;
            }

            this.isCreatingNewList = true;
            this.saveStatus = 'Creating new list...';

            try {
                let newListId;
                let attempts = 0;
                const maxAttempts = 10;

                // Keep generating IDs until we find one that doesn't exist
                do {
                    newListId = this.generateRandomId(12);
                    attempts++;
                    
                    if (attempts >= maxAttempts) {
                        throw new Error('Failed to generate unique list ID after multiple attempts');
                    }

                    // Check if the list already exists
                    try {
                        const response = await fetch(`${this.backendService.backendUrl}/api/list/${newListId}/exists`);
                        const result = await response.json();
                        
                        if (!result.exists) {
                            break; // Found a unique ID
                        }
                    } catch (error) {
                        // If check fails, assume ID is available and try to create
                        console.warn('Could not check list existence, proceeding with ID:', newListId);
                        break;
                    }
                } while (true);

                // Create the new empty list
                const emptyListData = {
                    Metadata: { 
                        Title: 'Your Checklist',
                        Hide_Checked: false,
                        Show_Note_Editor: true
                    },
                    Checklist: []
                };

                await this.backendService.saveList(newListId, emptyListData);
                
                this.saveStatus = 'New list created, redirecting...';

                // Redirect to the new list
                const newListUrl = `${window.location.origin}${window.location.pathname}?key=${newListId}`;
                
                // Small delay to show success message, then redirect
                setTimeout(() => {
                    window.location.href = newListUrl;
                }, 500);

            } catch (error) {
                console.error('Error creating new list:', error);
                this.saveStatus = `Error creating new list: ${error.message}`;
                
                // Clear error status after 3 seconds
                setTimeout(() => {
                    if (this.saveStatus.includes('Error creating')) {
                        this.saveStatus = '';
                    }
                }, 3000);
            } finally {
                this.isCreatingNewList = false;
                this.closeMainActions();
            }
        },

        // Backend Integration Methods
        async loadFromBackend() {
            if (!this.listId) return;
            
            this.isLoading = true;
            this.saveStatus = 'Loading list...';
            
            try {
                const result = await window.BackendOperations.loadFromBackend(this.backendService, this.listId);
                
                if (result.success) {
                    this.listTitle = result.title;
                    this.hideCheckedItems = result.hideChecked || false;
                    this.showNoteEditor = result.showNoteEditor !== undefined ? result.showNoteEditor : true;
                    this.checklist = this.sortItems(result.checklist);
                    window.BackendOperations.initializeExpandedCategories(this.checklist);
                    this.jsonError = null;
                    this.saveStatus = 'List loaded';
                    
                    // Debug loaded checklist
                    console.log('[APP] Loaded checklist items:', this.checklist);
                    
                    // Initialize category counts after loading
                    this.updateCategoryCountsAfterChange();
                    
                    // Store baseline for patch generation
                    this.lastSavedData = JSON.parse(JSON.stringify(result.data));
                    
                    // Update JSON textarea for reference
                    this.jsonInput = JSON.stringify(result.data, null, 2);
                } else {
                    this.jsonError = result.error;
                    this.saveStatus = 'Loading error';
                    this.checklist = result.checklist;
                    window.BackendOperations.initializeExpandedCategories(this.checklist);
                }
                
            } catch (error) {
                console.error('Unexpected error loading list:', error);
                this.jsonError = `Loading error: ${error.message}`;
                this.saveStatus = 'Loading error';
                this.checklist = [];
                window.BackendOperations.initializeExpandedCategories(this.checklist);
            } finally {
                this.isLoading = false;
                // Clear status after 3 seconds
                setTimeout(() => {
                    if (this.saveStatus === 'List loaded' || this.saveStatus === 'Loading error') {
                        this.saveStatus = '';
                    }
                }, 3000);
            }
        },
        
        async saveToBackend() {
            if (!this.backendMode || !this.listId) return;
            
            this.saveStatus = window.t ? window.t('Saving...') : 'Saving...';
            
            try {
                const result = await window.BackendOperations.saveToBackend(
                    this.backendService, 
                    this.listId, 
                    this.checklist, 
                    this.listTitle, 
                    this.hideCheckedItems,
                    this.showNoteEditor,
                    window.JsonOperations.cleanDataForExport
                );
                
                if (result.success) {
                    this.saveStatus = 'Saved ✓';
                    // Update JSON textarea for reference
                    this.jsonInput = JSON.stringify(result.payload, null, 2);
                } else {
                    this.saveStatus = `Error: ${result.error}`;
                }
                
            } catch (error) {
                console.error('Unexpected error saving:', error);
                this.saveStatus = `Error: ${error.message}`;
            } finally {
                // Clear status after 2 seconds
                setTimeout(() => {
                    if (this.saveStatus.includes('Saved') || this.saveStatus.includes('Error')) {
                        this.saveStatus = '';
                    }
                }, 2000);
            }
        },

        // JSON Methods
        parseJson() {
            const result = window.JsonOperations.parseJsonInput(this.jsonInput);
            
            if (result.success) {
                this.listTitle = result.title;
                this.hideCheckedItems = result.hideChecked || false;
                this.showNoteEditor = result.showNoteEditor !== undefined ? result.showNoteEditor : true;
                this.checklist = this.sortItems(result.checklist);
                this.initializeExpandedCategories(this.checklist);
                // Initialize category counts after parsing JSON
                this.updateCategoryCountsAfterChange();
                this.jsonError = null;
            } else {
                this.jsonError = result.error;
            }
        },
        
        exportJson() {
            this.jsonInput = window.JsonOperations.exportToJson(this.checklist, this.listTitle, this.hideCheckedItems, this.showNoteEditor);
        },
        

        // Item Management Methods
        async handleItemUpdate() {
            if (!this.backendMode) {
                this.exportJson();
                return;
            }
            
            // Use debounced handler
            this.debouncedUpdateHandler.execute();
        },
        
        async performItemUpdate() {
            this.saveStatus = window.t ? window.t('Saving...') : 'Saving...';
            
            try {
                const result = await window.BackendOperations.handleItemUpdate({
                    backendService: this.backendService,
                    listId: this.listId,
                    checklist: this.checklist,
                    title: this.listTitle,
                    hideChecked: this.hideCheckedItems,
                    showNoteEditor: this.showNoteEditor,
                    lastSavedData: this.lastSavedData,
                    cleanDataForExport: window.JsonOperations.cleanDataForExport,
                    patchDebug: this.patchDebug,
                    patchStats: this.patchStats
                });
                
                if (result.success) {
                    // Update our baseline data
                    this.lastSavedData = JSON.parse(JSON.stringify(result.currentData));
                    
                    // Update local data with server response if available
                    if (result.result && result.result.Checklist) {
                        this.checklist = this.sortItems(result.result.Checklist);
                        this.jsonInput = JSON.stringify(result.result, null, 2);
                    } else {
                        this.jsonInput = JSON.stringify(result.currentData, null, 2);
                    }
                    
                    this.saveStatus = window.BackendOperations.generateSaveStatusMessage(result.method, true, result);
                } else {
                    this.saveStatus = window.BackendOperations.generateSaveStatusMessage(result.method, false, result);
                }
                
            } catch (error) {
                console.error('Error in performItemUpdate:', error);
                this.saveStatus = `Error: ${error.message}`;
            } finally {
                // Clear status after 2 seconds
                setTimeout(() => {
                    if (this.saveStatus.includes('Saved') || this.saveStatus.includes('Error')) {
                        this.saveStatus = '';
                    }
                }, 2000);
            }
        },

        // Debug toggle method
        togglePatchDebug() {
            this.patchDebug = !this.patchDebug;
            console.log('Patch debugging:', this.patchDebug ? 'enabled' : 'disabled');
        },

        // Uncheck all checked items recursively with confirmation
        uncheckAllItems() {
            // Count checked items first
            const checkedCount = this.countCheckedItems(this.checklist);
            
            if (checkedCount === 0) {
                alert('No items are currently checked.');
                this.closeMainActions();
                return;
            }
            
            // Show confirmation dialog
            if (confirm(`Are you sure you want to uncheck all ${checkedCount} checked items?`)) {
                const uncheckedCount = window.BackendOperations.uncheckAllItems(this.checklist);
                if (uncheckedCount > 0) {
                    this.handleItemUpdate();
                }
            }
            this.closeMainActions(); // Close dropdown after action
        },

        // Helper method to count checked items recursively
        countCheckedItems(items) {
            const counts = window.ChecklistOperations.countItems(items);
            return counts.checked;
        },

        // Remove duplicate items within the same category
        removeDuplicates() {
            // First, just detect duplicates without removing them
            const duplicatesFound = this.detectDuplicates(this.checklist);
            
            if (duplicatesFound.totalDuplicates === 0) {
                alert('No duplicate items found.');
                this.closeMainActions();
                return;
            }
            
            // Show confirmation dialog with details
            const message = `Found ${duplicatesFound.totalDuplicates} duplicate items in ${duplicatesFound.categoriesAffected} categories.\n\n` +
                `Categories affected:\n${duplicatesFound.details.map(d => `• ${d.category}: ${d.duplicates} duplicates`).join('\n')}\n\n` +
                `Do you want to remove all duplicates?`;
                
            if (confirm(message)) {
                // Now actually remove the duplicates
                const result = this.findAndRemoveDuplicates(this.checklist);
                this.updateCategoryCountsAfterChange();
                this.handleItemUpdate();
                alert(`Successfully removed ${result.totalRemoved} duplicate items.`);
            }
            
            this.closeMainActions();
        },

        // Detect duplicates without removing them (for confirmation dialog)
        detectDuplicates(items, categoryPath = 'Root') {
            let totalDuplicates = 0;
            const categoriesAffected = new Set();
            const details = [];
            
            // Group items by name (case-insensitive)
            const itemGroups = {};
            const categories = [];
            
            // Separate items and categories
            items.forEach((item) => {
                if (item.Type === 'Item') {
                    const name = item.Name.toLowerCase().trim();
                    if (!itemGroups[name]) {
                        itemGroups[name] = [];
                    }
                    itemGroups[name].push(item);
                } else if (item.Type === 'Category') {
                    categories.push(item);
                }
            });
            
            // Count duplicates within this category
            let duplicatesInThisCategory = 0;
            
            Object.entries(itemGroups).forEach(([name, group]) => {
                if (group.length > 1) {
                    // Count extra duplicates (all but the first one)
                    duplicatesInThisCategory += group.length - 1;
                }
            });
            
            if (duplicatesInThisCategory > 0) {
                categoriesAffected.add(categoryPath);
                details.push({
                    category: categoryPath,
                    duplicates: duplicatesInThisCategory
                });
            }
            
            totalDuplicates += duplicatesInThisCategory;
            
            // Recursively process subcategories
            categories.forEach(category => {
                if (category.Content && category.Content.length > 0) {
                    const subResult = this.detectDuplicates(
                        category.Content, 
                        categoryPath === 'Root' ? category.Name : `${categoryPath} > ${category.Name}`
                    );
                    
                    totalDuplicates += subResult.totalDuplicates;
                    subResult.details.forEach(detail => {
                        categoriesAffected.add(detail.category);
                        details.push(detail);
                    });
                }
            });
            
            return {
                totalDuplicates,
                categoriesAffected: categoriesAffected.size,
                details
            };
        },

        // Find and remove duplicates in a category tree
        findAndRemoveDuplicates(items, categoryPath = 'Root') {
            let totalRemoved = 0;
            const categoriesAffected = new Set();
            const details = [];
            
            // Group items by name (case-insensitive)
            const itemGroups = {};
            const categories = [];
            
            // Separate items and categories
            items.forEach((item, index) => {
                if (item.Type === 'Item') {
                    const name = item.Name.toLowerCase().trim();
                    if (!itemGroups[name]) {
                        itemGroups[name] = [];
                    }
                    itemGroups[name].push({ item, index });
                } else if (item.Type === 'Category') {
                    categories.push(item);
                }
            });
            
            // Remove duplicates within this category
            let removedInThisCategory = 0;
            const indicesToRemove = [];
            
            Object.entries(itemGroups).forEach(([name, group]) => {
                if (group.length > 1) {
                    // Keep the first item, remove the rest
                    // Sort by index to remove from the end (to maintain correct indices)
                    const duplicates = group.slice(1).sort((a, b) => b.index - a.index);
                    
                    duplicates.forEach(duplicate => {
                        indicesToRemove.push(duplicate.index);
                        removedInThisCategory++;
                    });
                }
            });
            
            // Remove duplicates (from highest index to lowest to maintain indices)
            indicesToRemove.sort((a, b) => b - a);
            indicesToRemove.forEach(index => {
                items.splice(index, 1);
            });
            
            if (removedInThisCategory > 0) {
                categoriesAffected.add(categoryPath);
                details.push({
                    category: categoryPath,
                    removed: removedInThisCategory
                });
            }
            
            totalRemoved += removedInThisCategory;
            
            // Recursively process subcategories
            categories.forEach(category => {
                if (category.Content && category.Content.length > 0) {
                    const subResult = this.findAndRemoveDuplicates(
                        category.Content, 
                        categoryPath === 'Root' ? category.Name : `${categoryPath} > ${category.Name}`
                    );
                    
                    totalRemoved += subResult.totalRemoved;
                    subResult.details.forEach(detail => {
                        categoriesAffected.add(detail.category);
                        details.push(detail);
                    });
                }
            });
            
            // Reorder items after removal
            if (removedInThisCategory > 0) {
                this.reorderItems(items);
            }
            
            return {
                totalRemoved,
                categoriesAffected: categoriesAffected.size,
                details
            };
        },

        // Export checklist as JSON file download
        exportChecklist() {
            try {
                // Generate JSON using existing utility function
                const jsonContent = window.JsonOperations.exportToJson(
                    this.checklist, 
                    this.listTitle, 
                    this.hideCheckedItems,
                    this.showNoteEditor
                );
                
                // Create filename with list title and list ID
                const safeTitle = this.listTitle.replace(/[^a-zA-Z0-9]/g, '_'); // Replace special chars
                const listId = this.listId || 'local'; // Use 'local' if no listId (local mode)
                const filename = `${safeTitle}_${listId}.json`;
                
                // Create blob and download
                const blob = new Blob([jsonContent], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                
                // Create temporary download link and trigger download
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                a.style.display = 'none';
                document.body.appendChild(a);
                a.click();
                
                // Cleanup
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                
                console.log(`Exported checklist as ${filename}`);
            } catch (error) {
                console.error('Export failed:', error);
                alert('Failed to export checklist. Please try again.');
            }
            
            this.closeMainActions(); // Close dropdown after action
        },

        deleteItem(list, index) {
            // Get item before deletion for count updates
            const itemToDelete = list[index];
            const wasChecked = itemToDelete.Type === 'Item' ? itemToDelete.Checked : false;
            
            window.ChecklistOperations.deleteItem(list, index);
            
            // Update counts after deletion
            this.updateCategoryCountsAfterChange();
            this.handleItemUpdate();
        },

        deleteItemByReference(itemToDelete) {
            const location = window.ChecklistOperations.findItemLocation(this.checklist, itemToDelete);
            if (location.found) {
                window.ChecklistOperations.deleteItem(location.list, location.index);
                this.handleItemUpdate();
            }
        },
        

        addItem(parentItem, type) {
            const newItem = window.ChecklistOperations.addItem(parentItem, type);
            
            // Trigger edit mode for the new item after DOM update
            this.$nextTick(() => {
                this.triggerEditModeForNewItem(newItem);
            });
            
            // Update counts after adding item
            this.updateCategoryCountsAfterChange();
            this.handleItemUpdate();
        },
        
        addRootItem(type) {
            const newItem = window.ChecklistOperations.addItemToRoot(this.checklist, type);
            
            // Trigger edit mode for the new item after DOM update
            this.$nextTick(() => {
                this.triggerEditModeForNewItem(newItem);
            });
            
            // Update counts after adding root item
            this.updateCategoryCountsAfterChange();
            this.handleItemUpdate();
            this.closeMainActions(); // Close dropdown after action
        },
        
        addRootItemFromPlaceholder() {
            // Create new item similar to addRootItem but optimized for placeholder
            const newItem = {
                "Type": "Item",
                "Name": "",  // Start with empty name for immediate typing
                "Order": this.checklist.length + 1,
                "Checked": false,
                "Notes": "",
                "_isNewItem": true,  // Marker for new items to trigger edit mode
                "_isFromPlaceholder": true  // Special marker for placeholder-created items
            };
            this.checklist.push(newItem);
            this.reorderItems(this.checklist);
            
            // Trigger edit mode for the new item after DOM update
            this.$nextTick(() => {
                this.triggerEditModeForNewItem(newItem);
            });
            
            // Update counts after adding item from placeholder
            this.updateCategoryCountsAfterChange();
            this.handleItemUpdate();
        },
        
        // Helper method to trigger edit mode for new items
        triggerEditModeForNewItem(newItem) {
            // Find the list-item component for the new item
            const findAndEditComponent = (component) => {
                // Check if this component represents our new item
                if (component.item === newItem) {
                    component.startEditingNewItem();
                    return true;
                }
                
                // Recursively search in child components
                if (component.$children) {
                    for (let child of component.$children) {
                        if (findAndEditComponent(child)) {
                            return true;
                        }
                    }
                }
                return false;
            };
            
            // Start search from the root component
            findAndEditComponent(this);
        },

        moveItem(list, index, direction) {
            window.ChecklistOperations.moveItem(list, index, direction);
            // Moving doesn't change counts, just order
            this.handleItemUpdate();
        },

        moveItemByReference(itemToMove, direction) {
            const location = window.ChecklistOperations.findItemLocation(this.checklist, itemToMove);
            if (location.found) {
                window.ChecklistOperations.moveItem(location.list, location.index, direction);
                this.handleItemUpdate();
            }
        },

        // Utility Methods
        sortItems(items) {
            return window.ChecklistOperations.sortItems(items);
        },
        
        reorderItems(list) {
            window.ChecklistOperations.reorderItems(list);
        },
        
        getCategoryId(item, depth, parentPath = '') {
            // Create a unique ID based on the category path
            const currentPath = parentPath ? `${parentPath}/${item.Name}` : item.Name;
            return `${currentPath}_${depth}`;
        },
        
        isCategoryExpanded(item) {
            return item.Expanded !== false; // Default to true if undefined
        },
        
        toggleCategoryExpanded(item) {
            item.Expanded = !item.Expanded;
            this.handleItemUpdate(); // Save expansion state changes
        },
        
        initializeExpandedCategories(items) {
            window.BackendOperations.initializeExpandedCategories(items, true);
        },

        // Drag & Drop Methods
        startDrag(item, sourceList, sourceIndex) {
            this.draggedItem = item;
            this.draggedItemSource = { list: sourceList, index: sourceIndex };
        },
        
        allowDrop(targetItem) {
            // Only allow dropping into categories, not items
            // Don't allow dropping item into itself or its children
            if (!this.draggedItem || targetItem.Type !== 'Category') {
                return false;
            }
            
            // Prevent dropping category into itself or its children
            if (this.draggedItem.Type === 'Category') {
                return !this.isDescendant(targetItem, this.draggedItem);
            }
            
            return true;
        },
        
        isDescendant(potentialDescendant, ancestor) {
            // Check if potentialDescendant is a child of ancestor
            if (!ancestor.Content) return false;
            
            for (let child of ancestor.Content) {
                if (child === potentialDescendant) return true;
                if (child.Type === 'Category' && this.isDescendant(potentialDescendant, child)) {
                    return true;
                }
            }
            return false;
        },
        
        dropItem(targetCategory) {
            if (!this.draggedItem || !this.allowDrop(targetCategory)) {
                return;
            }
            
            // Remove item from source
            this.draggedItemSource.list.splice(this.draggedItemSource.index, 1);
            this.reorderItems(this.draggedItemSource.list);
            
            // Add item to target
            if (!targetCategory.Content) {
                targetCategory.Content = [];
            }
            targetCategory.Content.push(this.draggedItem);
            this.reorderItems(targetCategory.Content);
            
            // Clear drag state
            this.draggedItem = null;
            this.draggedItemSource = null;
            
            // Update counts after drag and drop
            this.updateCategoryCountsAfterChange();
            this.handleItemUpdate();
        },
        
        dropToRoot() {
            if (!this.draggedItem) return;
            
            // Remove item from source
            this.draggedItemSource.list.splice(this.draggedItemSource.index, 1);
            this.reorderItems(this.draggedItemSource.list);
            
            // Add item to root
            this.checklist.push(this.draggedItem);
            this.reorderItems(this.checklist);
            
            // Clear drag state
            this.draggedItem = null;
            this.draggedItemSource = null;
            
            // Update counts after dropping to root
            this.updateCategoryCountsAfterChange();
            this.handleItemUpdate();
        },

        // Root Level Drag & Drop Handlers
        handleRootDragOver(event) {
            if (this.draggedItem) {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
            }
        },
        
        handleRootDragEnter(event) {
            if (this.draggedItem) {
                this.isDragOverRoot = true;
            }
        },
        
        handleRootDragLeave(event) {
            // Only remove highlight if we're actually leaving the root container
            if (!event.currentTarget.contains(event.relatedTarget)) {
                this.isDragOverRoot = false;
            }
        },
        
        handleRootDrop(event) {
            event.preventDefault();
            this.isDragOverRoot = false;
            this.dropToRoot();
        },

        // Move Modal Methods
        showMoveDialog(item, sourceList, sourceIndex) {
            this.itemToMove = item;
            this.draggedItemSource = { list: sourceList, index: sourceIndex };
            this.showMoveModal = true;
        },
        
        moveToCategory(targetCategory) {
            if (!this.itemToMove) return;
            
            // Remove item from source
            this.draggedItemSource.list.splice(this.draggedItemSource.index, 1);
            this.reorderItems(this.draggedItemSource.list);
            
            // Add item to target
            if (targetCategory === null) {
                // Move to root
                this.checklist.push(this.itemToMove);
                this.reorderItems(this.checklist);
            } else {
                // Move to category
                if (!targetCategory.Content) {
                    targetCategory.Content = [];
                }
                targetCategory.Content.push(this.itemToMove);
                this.reorderItems(targetCategory.Content);
            }
            
            this.closeMoveModal();
            // Update counts after moving item via modal
            this.updateCategoryCountsAfterChange();
            this.handleItemUpdate();
        },
        
        closeMoveModal() {
            this.showMoveModal = false;
            this.itemToMove = null;
            this.draggedItemSource = null;
        },

        // Auto-categorization Methods
        getRootLevelData() {
            const items = this.checklist.filter(item => item.Type === 'Item');
            const categories = this.checklist.filter(item => item.Type === 'Category');
            return { items, categories };
        },
        
        async autoCategorize() {
            if (!this.backendMode) {
                alert('Auto-categorization is only available in backend mode');
                this.closeMainActions(); // Close dropdown
                return;
            }
            
            // Clean up old consent format and check for valid consent (within 28 days)
            if (localStorage.getItem('FlexiList_ki_consent') === 'true' && !localStorage.getItem('FlexiList_ki_consent_date')) {
                localStorage.removeItem('FlexiList_ki_consent');
            }
            
            const consentDate = localStorage.getItem('FlexiList_ki_consent_date');
            const hasValidConsent = this.isConsentStillValid(consentDate);
            
            if (hasValidConsent) {
                // Skip dialog, proceed directly
                this.closeMainActions(); // Close dropdown
                this.proceedWithAutoCategorize();
            } else {
                // Show KI consent dialog first
                this.closeMainActions(); // Close dropdown
                this.showKIConsentDialog = true;
            }
        },
        
        isConsentStillValid(consentDateString) {
            if (!consentDateString) return false;
            
            const consentDate = new Date(consentDateString);
            const now = new Date();
            const daysDiff = (now - consentDate) / (1000 * 60 * 60 * 24);
            
            return daysDiff <= 28;
        },
        
        async proceedWithAutoCategorize() {
            // Save user consent with current date to localStorage
            localStorage.setItem('FlexiList_ki_consent_date', new Date().toISOString());
            
            const rootData = this.getRootLevelData();
            
            if (rootData.items.length === 0) {
                alert('No items found to categorize');
                this.showKIConsentDialog = false;
                return;
            }
            if (rootData.categories.length === 0) {
                alert('No categories available for assignment');
                this.showKIConsentDialog = false;
                return;
            }
            
            this.showKIConsentDialog = false;
            this.isAutoCategorizing = true;
            this.saveStatus = window.t ? window.t('Categorizing...') : 'Categorizing...';
            
            try {
                const result = await this.backendService.autoCategorize(
                    this.listId,
                    rootData.items.map(a => a.Name),
                    rootData.categories.map(c => c.Name)
                );
                
                this.applyAutoCategorization(result.assignments);
                this.saveStatus = 'Categorization completed ✓';
                
            } catch (error) {
                console.error('Error during categorization:', error);
                this.saveStatus = `Error: ${error.message}`;
            } finally {
                this.isAutoCategorizing = false;
                this.closeMainActions(); // Close dropdown after action
                // Clear status after 3 seconds
                setTimeout(() => {
                    if (this.saveStatus.includes('Categorization') || this.saveStatus.includes('Error')) {
                        this.saveStatus = '';
                    }
                }, 3000);
            }
        },
        
        cancelKIConsent() {
            this.showKIConsentDialog = false;
            this.kiConsentGiven = false; // Reset consent for next time
        },
        
        applyAutoCategorization(assignments) {
            assignments.forEach(assignment => {
                if (assignment.category) {
                    this.moveItemToCategory(assignment.item, assignment.category);
                }
                // Items with category: null remain at root level
            });
            // Update counts after auto-categorization moves items
            this.updateCategoryCountsAfterChange();
            this.handleItemUpdate();
        },
        
        moveItemToCategory(itemName, categoryName) {
            // Find the item in root level
            const itemIndex = this.checklist.findIndex(
                item => item.Type === 'Item' && item.Name === itemName
            );
            
            if (itemIndex === -1) {
                console.warn(`Item "${itemName}" not found at root level`);
                return;
            }
            
            // Find target category (recursive search)
            const targetCategory = this.findCategoryByName(this.checklist, categoryName);
            
            if (!targetCategory) {
                console.warn(`Category "${categoryName}" not found`);
                return;
            }
            
            // Use utility function to move item
            const result = window.ChecklistOperations.moveItemToCategory(this.checklist, itemIndex, targetCategory);
            
            if (!result.success) {
                console.warn(`Failed to move item: ${result.error}`);
            }
        },
        
        findCategoryByName(items, categoryName) {
            for (const item of items) {
                if (item.Type === 'Category' && item.Name === categoryName) {
                    return item;
                }
                if (item.Type === 'Category' && item.Content) {
                    const found = this.findCategoryByName(item.Content, categoryName);
                    if (found) return found;
                }
            }
            return null;
        },

        // Filter out checked items recursively (keep all categories)
        filterCheckedItems(items) {
            return window.ChecklistOperations.filterCheckedItems(items);
            // Note: Utility function handles recursive filtering
            // Filtering of nested content is now handled at the component level.
        },

        // Category progress indicators
        calculateCategoryCountsRecursively(items, pathPrefix = '') {
            if (!items) return;
            
            items.forEach(item => {
                if (item.Type === 'Category') {
                    const categoryPath = pathPrefix ? `${pathPrefix}/${item.Name}` : item.Name;
                    
                    // Calculate counts for this category
                    const counts = this.countItemsInCategory(item.Content || []);
                    this.categoryCountMap[categoryPath] = counts;
                    
                    // Recursively calculate for subcategories
                    this.calculateCategoryCountsRecursively(item.Content || [], categoryPath);
                }
            });
        },
        
        countItemsInCategory(items) {
            let totalItems = 0;
            let checkedItems = 0;
            
            if (!items) return { total: 0, checked: 0 };
            
            items.forEach(item => {
                if (item.Type === 'Item') {
                    totalItems++;
                    if (item.Checked) {
                        checkedItems++;
                    }
                } else if (item.Type === 'Category' && item.Content) {
                    // Recursively count items in subcategories
                    const subcategoryCounts = this.countItemsInCategory(item.Content);
                    totalItems += subcategoryCounts.total;
                    checkedItems += subcategoryCounts.checked;
                }
            });
            
            return { total: totalItems, checked: checkedItems };
        },
        
        getCategoryProgressText(item, pathPrefix = '') {
            if (item.Type !== 'Category') return '';
            
            const categoryPath = pathPrefix ? `${pathPrefix}/${item.Name}` : item.Name;
            const counts = this.categoryCountMap[categoryPath];
            
            if (!counts || counts.total === 0) return '';
            
            return ` (${counts.checked}/${counts.total})`;
        },
        
        updateCategoryCountsAfterChange() {
            // Recalculate all category counts
            this.categoryCountMap = {};
            this.calculateCategoryCountsRecursively(this.checklist);
        },
        
        updateCategoryCountsIncremental(categoryPath, totalChange, checkedChange) {
            // Update counts incrementally for performance
            const pathParts = categoryPath.split('/');
            
            // Update counts for this category and all parent categories
            for (let i = 0; i < pathParts.length; i++) {
                const currentPath = pathParts.slice(0, i + 1).join('/');
                if (this.categoryCountMap[currentPath]) {
                    this.categoryCountMap[currentPath].total += totalChange;
                    this.categoryCountMap[currentPath].checked += checkedChange;
                    
                    // Ensure counts don't go negative
                    this.categoryCountMap[currentPath].total = Math.max(0, this.categoryCountMap[currentPath].total);
                    this.categoryCountMap[currentPath].checked = Math.max(0, this.categoryCountMap[currentPath].checked);
                }
            }
        },
        
        handleCheckboxChangeOptimized(item, oldChecked, newChecked, itemPath = '') {
            // Handle checkbox changes with optimized count updates
            if (oldChecked === newChecked) return;
            
            const checkedChange = newChecked ? 1 : -1;
            
            // If we have the item path, do incremental updates
            if (itemPath) {
                // Find all parent categories and update their counts
                const pathParts = itemPath.split('/');
                pathParts.pop(); // Remove the item name, keep only category path
                
                if (pathParts.length > 0) {
                    const categoryPath = pathParts.join('/');
                    this.updateCategoryCountsIncremental(categoryPath, 0, checkedChange);
                }
            } else {
                // Fallback: full recalculation if path is unknown
                this.updateCategoryCountsAfterChange();
            }
        },
        
        findItemPath(items, targetItem, currentPath = '') {
            // Find the full path to an item in the checklist hierarchy
            if (!items) return null;
            
            for (const item of items) {
                const itemPath = currentPath ? `${currentPath}/${item.Name}` : item.Name;
                
                if (item === targetItem) {
                    return itemPath;
                }
                
                if (item.Type === 'Category' && item.Content) {
                    const found = this.findItemPath(item.Content, targetItem, itemPath);
                    if (found) return found;
                }
            }
            
            return null;
        },

        // Auto-complete search functionality
        buildSearchIndex() {
            this.searchIndex = [];
            this.flattenItemsForSearch(this.checklist, []);
        },

        flattenItemsForSearch(items, categoryPath = []) {
            if (!items) return;
            
            items.forEach(item => {
                if (item.Type === 'Item') {
                    // Add item to search index
                    this.searchIndex.push({
                        name: item.Name,
                        checked: item.Checked || false,
                        category: categoryPath.length > 0 ? categoryPath[categoryPath.length - 1] : null,
                        path: categoryPath.join(' > '),
                        fullPath: [...categoryPath],
                        originalItem: item
                    });
                } else if (item.Type === 'Category') {
                    // Recursively process category content
                    const newPath = [...categoryPath, item.Name];
                    this.flattenItemsForSearch(item.Content, newPath);
                }
            });
        },

        getSuggestions(query, currentItem = null) {
            if (!this.autoCompleteConfig.enabled || !query || query.length < this.autoCompleteConfig.minChars) {
                return [];
            }

            const searchQuery = this.autoCompleteConfig.caseSensitive ? query : query.toLowerCase();
            
            // Filter suggestions based on prefix match and exclude current item
            let matches = this.searchIndex.filter(item => {
                const itemName = this.autoCompleteConfig.caseSensitive ? item.name : item.name.toLowerCase();
                const matchesQuery = itemName.startsWith(searchQuery);
                const isNotCurrentItem = !currentItem || item.originalItem !== currentItem;
                
                // Also exclude items with the same name (case-insensitive)
                const currentItemName = currentItem && currentItem.Name ? currentItem.Name.trim().toLowerCase() : '';
                const suggestionName = item.name.trim().toLowerCase();
                const isNotSameName = !currentItem || !currentItemName || suggestionName !== currentItemName;
                
                return matchesQuery && isNotCurrentItem && isNotSameName;
            });

            // Sort: checked items first (if enabled), then alphabetical
            if (this.autoCompleteConfig.showCheckedFirst) {
                matches.sort((a, b) => {
                    if (a.checked !== b.checked) {
                        return b.checked - a.checked; // checked items first
                    }
                    return a.name.localeCompare(b.name);
                });
            } else {
                matches.sort((a, b) => a.name.localeCompare(b.name));
            }

            // Limit results
            return matches.slice(0, this.autoCompleteConfig.maxSuggestions);
        },

    }
});

// Register components with error handling
try {
    // Check if components are available
    if (typeof ListItem === 'undefined') {
        throw new Error('ListItem component not available');
    }
    if (typeof MoveModal === 'undefined') {
        throw new Error('MoveModal component not available');
    }
    if (typeof AutoCompleteInput === 'undefined') {
        throw new Error('AutoCompleteInput component not available');
    }
    
    app.component('list-item', ListItem);
    app.component('move-modal', MoveModal);
    app.component('autocomplete-input', AutoCompleteInput);

    // Check if target element exists
    const appElement = document.getElementById('app');
    if (!appElement) {
        throw new Error('Target element #app not found');
    }

    // Mount Vue app
    app.mount('#app');
    
} catch (error) {
    console.error('Failed to initialize Vue app:', error);
    
    // Show error message to user
    const appElement = document.getElementById('app');
    if (appElement) {
        appElement.innerHTML = `
            <div style="padding: 40px; text-align: center; color: #721c24; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; margin: 20px;">
                <h2>Application Error</h2>
                <p>Failed to initialize the application: ${error.message}</p>
                <button onclick="location.reload()" style="background: #007bff; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; margin-top: 16px;">
                    Reload Page
                </button>
            </div>
        `;
    }
    
    // Still signal app mounted to hide loading overlay
    document.dispatchEvent(new CustomEvent('appMounted'));
}
