const ListItem = {
    props: ['item', 'depth', 'parentPath'],
    emits: ['update:item', 'delete-item', 'add-item', 'move-item'],
    inject: ['autoCompleteService'],
    template: `
        <div :style="{ marginLeft: depth * hierarchyIndentation + 'px' }" 
             :class="['list-item', 'item-type-' + item.Type.toLowerCase(), {
                'drag-over': isDragOver && item.Type === 'Category',
                'being-dragged': $root.draggedItem === item,
                'editing': isEditing
             }]"
             draggable="true"
             @dragstart="handleDragStart"
             @dragover="handleDragOver"
             @dragenter="handleDragEnter"
             @dragleave="handleDragLeave"
             @drop="handleDrop"
             role="listitem"
             :aria-label="item.Type === 'Category' ? 'Category: ' + item.Name : 'Item: ' + item.Name"
             :aria-expanded="item.Type === 'Category' ? isExpanded : null">
            <div class="list-item-content">
                <span v-if="item.Type === 'Category'" 
                      @click="toggleExpanded" 
                      class="fold-icon"
                      :title="isExpanded ? 'Collapse' : 'Expand'"
                      :aria-label="isExpanded ? 'Collapse category' : 'Expand category'"
                      role="button"
                      tabindex="0"
                      @keydown.enter="toggleExpanded"
                      @keydown.space.prevent="toggleExpanded">
                    {{ isExpanded ? '▼' : '▶' }}
                </span>
                <input type="checkbox" 
                       v-if="item.Type === 'Item'" 
                       v-model="item.Checked" 
                       @change="handleCheckboxChange"
                       :aria-label="t('Mark') + ' ' + item.Name + ' ' + t('as') + ' ' + (item.Checked ? t('incomplete') : t('complete'))">
                <div v-if="!isEditing" class="item-name-container">
                    <span @click="startEditing" 
                          class="item-name"
                          :aria-label="t('Edit') + ' ' + item.Name"
                          role="button"
                          tabindex="0"
                          @keydown.enter="startEditing"
                          @keydown.space.prevent="startEditing">
                        {{ item.Name }}<span v-if="item.Type === 'Category' && categoryProgressText" class="category-progress">{{ categoryProgressText }}</span>
                    </span>
                    <span v-if="item.Type === 'Item' && shouldShowNoteToggle" 
                          @click="toggleNoteExpanded" 
                          class="note-toggle"
                          :class="{ 'has-note': hasNote, 'empty-note': !hasNote }"
                          :title="hasNote ? (isNoteExpanded ? t('Hide Note') : t('Show Note')) : t('Add note...')"
                          :aria-label="hasNote ? ((isNoteExpanded ? t('Hide Note') : t('Show Note')) + ' ' + t('for') + ' ' + item.Name) : (t('Add note for') + ' ' + item.Name)"
                          :aria-expanded="hasNote ? isNoteExpanded : null"
                          role="button"
                          tabindex="0"
                          @keydown.enter="toggleNoteExpanded"
                          @keydown.space.prevent="toggleNoteExpanded">
                        <template v-if="hasNote">
                            {{ isNoteExpanded ? '▼' : '▶' }} {{ t('Note') }}
                        </template>
                        <template v-else>
                            Add Note
                        </template>
                    </span>
                </div>
                <div v-else class="item-name-container editing">
                    <autocomplete-input ref="editInput"
                                       v-model="editedName"
                                       :config="autoCompleteService.config"
                                       :suggestions="autoCompleteService.getSuggestions(editedName, item)"
                                       :placeholder="'Enter ' + item.Type.toLowerCase() + ' name...'"
                                       @suggestion-selected="handleSuggestionSelected"
                                       @enter="finishEditing"
                                       @escape="cancelEditing"
                                       @blur="finishEditing"
                                       class="item-name editing"></autocomplete-input>
                </div>
            </div>
            <div class="item-actions" role="toolbar" :aria-label="t('Actions for') + ' ' + item.Name">
                <button @click="moveItem('up')" 
                        :title="t('Move Up')"
                        :aria-label="t('Move Up')">↑</button>
                <button @click="moveItem('down')" 
                        :title="t('Move Down')"
                        :aria-label="t('Move Down')">↓</button>
                <button @click="showMoveDialog" 
                        :title="t('Move to different category')"
                        :aria-label="t('Move') + ' ' + item.Name + ' ' + t('to different category')">📁</button>
                <button @click="deleteItem" 
                        :title="t('Delete')"
                        :aria-label="t('Delete')">🗑️</button>
                <div v-if="item.Type === 'Category'" class="add-buttons">
                    <button @click="addItem('Category')" 
                            :title="t('Add subcategory')"
                            :aria-label="t('Add subcategory')">➕</button>
                </div>
                <!-- Mobile dropdown menu for category actions -->
                <div v-if="item.Type === 'Category'" class="mobile-dropdown" @click.stop>
                    <button class="dropdown-toggle" 
                            @click="toggleDropdown"
                            :aria-label="'More actions for ' + item.Name"
                            :aria-expanded="showDropdown">⋯</button>
                    <div v-show="showDropdown" class="dropdown-menu" @click.stop>
                        <button @click="moveItem('up')" class="dropdown-item">
                            ↑ {{ t('Move Up') }}
                        </button>
                        <button @click="moveItem('down')" class="dropdown-item">
                            ↓ {{ t('Move Down') }}
                        </button>
                        <button @click="showMoveDialog" class="dropdown-item">
                            📁 {{ t('Move to Category') }}
                        </button>
                        <button @click="addItem('Category')" class="dropdown-item">
                            ➕ {{ t('Add Subcategory') }}
                        </button>
                        <button @click="deleteItem" class="dropdown-item delete-action">
                            🗑️ {{ t('Delete') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="item.Type === 'Category' && isExpanded" 
             class="category-content"
             :style="{ '--line-position': verticalLinePosition + 'px' }">
            <list-item v-for="(subItem, index) in sortedContent"
                       :key="subItem.Name + index"
                       :item="subItem"
                       :depth="depth + 1"
                       :parent-path="currentPath"
                       @update:item="emitUpdate"
                       @delete-item="deleteSubItem(getOriginalIndex(subItem))"
                       @add-item="$emit('add-item', $event)"
                       @move-item="moveSubItem(getOriginalIndex(subItem), $event)">
            </list-item>
            
            <!-- Category Placeholder -->
            <div class="placeholder-item category-placeholder" 
                 :style="{ marginLeft: (depth + 1) * hierarchyIndentation + 'px' }"
                 @click="addItemFromPlaceholder"
                 role="button"
                 tabindex="0"
                 @keydown.enter="addItemFromPlaceholder"
                 @keydown.space.prevent="addItemFromPlaceholder"
                 :aria-label="t('Add new item to') + ' ' + item.Name">
                <div class="placeholder-content">
                    <span class="placeholder-icon">+</span>
                    <span class="placeholder-text">{{ translatedAddNewItem }}</span>
                </div>
            </div>
        </div>
        <!-- Notiz-Content-Bereich (außerhalb des list-item) -->
        <div v-if="item.Type === 'Item' && isNoteExpanded && shouldShowNoteToggle" 
             class="note-content"
             :style="{ marginLeft: (depth * hierarchyIndentation + 22) + 'px' }">
            <div v-if="!isEditingNote" 
                 @click="startEditingNote"
                 class="note-display"
                 :class="{ 'note-empty': !hasNote }"
                 role="button"
                 tabindex="0"
                 :aria-label="hasNote ? (t('Edit note') + ': ' + item.Notes) : t('Add note for') + ' ' + item.Name"
                 @keydown.enter="startEditingNote"
                 @keydown.space.prevent="startEditingNote">
                {{ item.Notes || t('Add note...') }}
            </div>
            <textarea v-else
                      ref="noteTextarea"
                      v-model="editedNotes"
                      @blur="finishEditingNote"
                      @keydown.escape="cancelEditingNote"
                      @keydown.ctrl.enter="finishEditingNote"
                      class="note-textarea"
                      :placeholder="t('Add note...')"
                      :aria-label="t('Edit note for') + ' ' + item.Name"
                      rows="2">
            </textarea>
        </div>
    `,
    data() {
        return {
            isEditing: false,
            editedName: this.item.Name,
            isDragOver: false,
            showDropdown: false,
            isNoteExpanded: false,
            isEditingNote: false,
            editedNotes: this.item.Notes || ''
        };
    },
    mounted() {
        // Check if this is a new item that should start in edit mode
        if (this.item._isNewItem) {
            this.startEditingNewItem();
        }
    },
    computed: {
        // Make translations reactive to window.t availability
        translatedAddNewItem() {
            return this.t('Add new item');
        },
        // Get configurable hierarchy indentation
        hierarchyIndentation() {
            return window.FLEXI_CONFIG?.UI_SETTINGS?.HIERARCHY_INDENTATION ?? 20;
        },
        // Calculate vertical line position to align with parent fold icon center
        verticalLinePosition() {
            // Parent category is at depth * indentation, fold icon center is +12px
            return (this.depth * this.hierarchyIndentation) + 12;
        },
        sortedContent() {
            if (!this.item.Content) return [];
            
            let content = this.item.Content;
            
            // Apply filtering if hide checked items is enabled
            if (this.$root.hideCheckedItems) {
                content = content.filter(item => {
                    // Keep categories and unchecked items
                    if (item.Type === 'Category') return true;
                    return !item.Checked;
                });
            }
            
            // Sort by order
            return content.sort((a, b) => a.Order - b.Order);
        },
        categoryId() {
            if (this.item.Type === 'Category') {
                return this.$root.getCategoryId(this.item, this.depth, this.parentPath || '');
            }
            return null;
        },
        isExpanded() {
            if (this.item.Type === 'Category') {
                return this.$root.isCategoryExpanded(this.item);
            }
            return true;
        },
        currentPath() {
            return this.parentPath ? `${this.parentPath}/${this.item.Name}` : this.item.Name;
        },
        categoryProgressText() {
            if (this.item.Type !== 'Category') return '';
            return this.$root.getCategoryProgressText(this.item, this.parentPath);
        },
        hasNote() {
            return this.item.Type === 'Item' && this.item.Notes && this.item.Notes.trim();
        },
        shouldShowNoteToggle() {
            // Show note toggle if:
            // 1. Note editor is enabled (showNoteEditor is true), OR
            // 2. There is already a non-empty note (for backward compatibility)
            return this.$root.showNoteEditor || this.hasNote;
        }
    },
    methods: {
        // Translation helper method
        t(key) {
            return (window.t && window.t(key)) || key;
        },
        
        getOriginalIndex(subItem) {
            // Find the index of this item in the original (unfiltered) Content array
            return this.item.Content.indexOf(subItem);
        },
        emitUpdate() {
            this.$emit('update:item', this.item);
        },
        handleCheckboxChange() {
            // Handle checkbox changes with immediate save to prevent state loss
            // when items are filtered out due to hideCheckedItems
            
            // For checkbox changes, just do a full recalculation since it's simpler and more reliable
            // The performance impact is minimal for most use cases
            this.$root.updateCategoryCountsAfterChange();
            
            // Directly notify root to bypass category filtering issues
            this.$root.handleItemUpdate();
        },
        toggleDropdown() {
            this.showDropdown = !this.showDropdown;
            // Close other dropdowns when opening this one
            if (this.showDropdown) {
                // Add event listener to close dropdown when clicking outside
                this.$nextTick(() => {
                    document.addEventListener('click', this.closeDropdown);
                });
            }
        },
        closeDropdown() {
            this.showDropdown = false;
            document.removeEventListener('click', this.closeDropdown);
        },
        startEditing() {
            this.isEditing = true;
            this.$nextTick(() => {
                if (this.$refs.editInput) {
                    this.$refs.editInput.focus();
                }
            });
        },
        startEditingNewItem() {
            this.isEditing = true;
            // For placeholder items, start with empty name, for button-created items use default name
            if (this.item._isFromPlaceholder) {
                this.editedName = "";  // Start empty for immediate typing
            } else {
                this.editedName = this.item.Name;  // Use the default name (e.g., "New Item")
            }
            this.$nextTick(() => {
                if (this.$refs.editInput) {
                    this.$refs.editInput.focus();
                    if (!this.item._isFromPlaceholder) {
                        this.$refs.editInput.select();  // Select all text for easy replacement
                    }
                }
            });
            // Keep the new item markers until edit is completed or cancelled
            // delete this.item._isNewItem;
            // delete this.item._isFromPlaceholder;
        },
        finishEditing() {
            this.isEditing = false;
            // Trim whitespace and provide default name if empty
            const trimmed = this.editedName.trim();
            if (!trimmed) {
                this.editedName = window.t ? window.t('New Item') : 'New Item';
            } else {
                this.editedName = trimmed;
            }
            this.item.Name = this.editedName;
            
            // Remove new item markers on successful completion
            if (this.item._isNewItem) {
                delete this.item._isNewItem;
            }
            if (this.item._isFromPlaceholder) {
                delete this.item._isFromPlaceholder;
            }
            
            this.emitUpdate();
        },
        cancelEditing() {
            this.isEditing = false;
            
            // If this was a new item that was never properly confirmed, remove it
            if (this.item._isNewItem || this.item._isFromPlaceholder) {
                this.$emit('delete-item');
                return;
            }
            
            // Normal cancel - just reset display name
            this.editedName = this.item.Name;
        },
        handleSuggestionSelected(suggestion) {
            // When a suggestion is selected, set the name and finish editing
            this.editedName = suggestion.name;
            this.finishEditing();
        },
        deleteItem() {
            this.$emit('delete-item');
        },
        addItem(type) {
            this.$emit('add-item', { parentItem: this.item, type: type });
        },
        addSubItem(subItem, type) {
            this.$emit('add-item', { parentItem: subItem, type: type });
        },
        addItemFromPlaceholder() {
            // Add new item to this category from placeholder
            const newItem = {
                "Type": "Item",
                "Name": "",  // Start with empty name for immediate typing
                "Order": this.item.Content ? this.item.Content.length + 1 : 1,
                "Checked": false,
                "Notes": "",
                "_isNewItem": true,  // Marker for new items to trigger edit mode
                "_isFromPlaceholder": true  // Special marker for placeholder-created items
            };

            // Ensure the category has a Content array
            if (this.item.Type === 'Category') {
                if (!this.item.Content) {
                    this.item.Content = [];
                }
                // Auto-expand parent category when adding items to it
                this.item.Expanded = true;
                this.item.Content.push(newItem);
                this.$root.reorderItems(this.item.Content);
                
                // Update counts after adding new item to category
                this.$root.updateCategoryCountsAfterChange();
                
                // Trigger edit mode for the new item after DOM update
                this.$nextTick(() => {
                    this.$root.triggerEditModeForNewItem(newItem);
                });
            }
            
            this.emitUpdate();
        },
        deleteSubItem(index) {
            const itemToDelete = this.item.Content[index];
            
            // If it's a category with children, preserve the children
            if (itemToDelete.Type === 'Category' && itemToDelete.Content && itemToDelete.Content.length > 0) {
                this.preserveChildrenAndDeleteSub(index);
            } else {
                // Simple deletion for items or empty categories
                this.item.Content.splice(index, 1);
                this.$root.reorderItems(this.item.Content);
            }
            // Update counts after deleting sub-item
            this.$root.updateCategoryCountsAfterChange();
            this.emitUpdate();
        },
        preserveChildrenAndDeleteSub(categoryIndex) {
            const categoryToDelete = this.item.Content[categoryIndex];
            const childrenToPreserve = [...categoryToDelete.Content]; // Create a copy
            
            // Remove the category first
            this.item.Content.splice(categoryIndex, 1);
            
            // Append children to the end of the parent's content
            this.item.Content.push(...childrenToPreserve);
            
            // Reorder the entire parent's content
            this.$root.reorderItems(this.item.Content);
            
            // Update counts after preserving children and deleting category
            this.$root.updateCategoryCountsAfterChange();
        },
        moveItem(direction) {
            this.$emit('move-item', direction);
        },
        moveSubItem(index, direction) {
            const list = this.item.Content;
            const item = list[index];
            if (direction === 'up' && index > 0) {
                list.splice(index, 1);
                list.splice(index - 1, 0, item);
            } else if (direction === 'down' && index < list.length - 1) {
                list.splice(index, 1);
                list.splice(index + 1, 0, item);
            }
            this.$root.reorderItems(list);
            this.emitUpdate();
        },
        toggleExpanded() {
            if (this.item.Type === 'Category') {
                this.$root.toggleCategoryExpanded(this.item);
            }
        },
        // Drag & Drop Event Handlers
        handleDragStart(event) {
            // Find the source list and index
            let sourceList, sourceIndex;
            if (this.depth === 0) {
                sourceList = this.$root.checklist;
                sourceIndex = sourceList.indexOf(this.item);
            } else {
                // Find parent component and get the source list
                let parent = this.$parent;
                while (parent && !parent.item) {
                    parent = parent.$parent;
                }
                if (parent && parent.item && parent.item.Content) {
                    sourceList = parent.item.Content;
                    sourceIndex = sourceList.indexOf(this.item);
                }
            }
            
            this.$root.startDrag(this.item, sourceList, sourceIndex);
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', this.item.Name);
        },
        handleDragOver(event) {
            if (this.$root.allowDrop(this.item)) {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
            }
        },
        handleDragEnter() {
            if (this.$root.allowDrop(this.item)) {
                this.isDragOver = true;
            }
        },
        handleDragLeave(event) {
            // Only remove highlight if we're actually leaving this element
            if (!event.currentTarget.contains(event.relatedTarget)) {
                this.isDragOver = false;
            }
        },
        handleDrop(event) {
            event.preventDefault();
            this.isDragOver = false;
            
            if (this.$root.allowDrop(this.item)) {
                this.$root.dropItem(this.item);
            }
        },
        // Move Dialog
        showMoveDialog() {
            // Find the source list and index
            let sourceList, sourceIndex;
            if (this.depth === 0) {
                sourceList = this.$root.checklist;
                sourceIndex = sourceList.indexOf(this.item);
            } else {
                // Find parent component and get the source list
                let parent = this.$parent;
                while (parent && !parent.item) {
                    parent = parent.$parent;
                }
                if (parent && parent.item && parent.item.Content) {
                    sourceList = parent.item.Content;
                    sourceIndex = sourceList.indexOf(this.item);
                }
            }
            
            this.$root.showMoveDialog(this.item, sourceList, sourceIndex);
        },
        
        // Notiz-Funktionalität Methoden
        toggleNoteExpanded() {
            if (this.item.Type === 'Item') {
                this.isNoteExpanded = !this.isNoteExpanded;
                
                // Auto-start editing für leere Notizen (sowohl bestehende leere als auch neue)
                if (this.isNoteExpanded && !this.hasNote) {
                    this.$nextTick(() => {
                        this.startEditingNote();
                    });
                }
            }
        },
        
        startEditingNote() {
            this.isEditingNote = true;
            this.editedNotes = this.item.Notes || '';
            this.$nextTick(() => {
                if (this.$refs.noteTextarea) {
                    this.$refs.noteTextarea.focus();
                    this.$refs.noteTextarea.select();
                }
            });
        },
        
        finishEditingNote() {
            this.isEditingNote = false;
            const trimmed = this.editedNotes.trim();
            
            // Validierung der Notizlänge (max 1000 Zeichen)
            if (trimmed.length > 1000) {
                alert(this.t('Note too long') + ' (max. 1000 ' + this.t('characters') + ')');
                this.isEditingNote = true;
                this.$nextTick(() => {
                    if (this.$refs.noteTextarea) {
                        this.$refs.noteTextarea.focus();
                    }
                });
                return;
            }
            
            this.item.Notes = trimmed;
            
            // Kollabiere Notiz-Sektion wenn leer
            if (!trimmed) {
                this.isNoteExpanded = false;
            }
            
            this.emitUpdate();
        },
        
        cancelEditingNote() {
            this.isEditingNote = false;
            this.editedNotes = this.item.Notes || '';
            
            // Kollabiere wenn Notiz leer war
            if (!this.hasNote) {
                this.isNoteExpanded = false;
            }
        }
    },
    watch: {
        'item.Name': {
            handler(newName) {
                this.editedName = newName;
            },
            immediate: true
        }
    },
    beforeUnmount() {
        // Cleanup event listener if component is destroyed
        if (this.showDropdown) {
            document.removeEventListener('click', this.closeDropdown);
        }
    }
};

// Export for use in other files
window.ListItem = ListItem;
