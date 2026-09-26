const MoveModal = {
    props: ['show', 'itemToMove', 'checklist'],
    emits: ['close', 'move-to-category'],
    template: `
        <div v-if="show" class="modal-overlay" @click="$emit('close')">
            <div class="modal-content" @click.stop>
                <h3>Move Item: {{ itemToMove?.Name }}</h3>
                <div class="category-list">
                    <div v-for="category in availableCategories" 
                         :key="category.path"
                         :class="['category-option', { 'root-level': category.level === 0 }]"
                         :style="{ paddingLeft: (category.level * 20) + 'px' }"
                         @click="moveToCategory(category.item)">
                        {{ category.name }}
                    </div>
                </div>
                <div class="modal-actions">
                    <button @click="$emit('close')">Cancel</button>
                </div>
            </div>
        </div>
    `,
    computed: {
        availableCategories() {
            if (!this.itemToMove) return [];
            return this.buildCategoryTree();
        }
    },
    methods: {
        buildCategoryTree(items = this.checklist, path = '', level = 0) {
            let categories = [];
            
            // Add root level option
            if (level === 0) {
                categories.push({
                    name: 'Root Level',
                    path: '',
                    item: null,
                    level: 0
                });
            }
            
            items.forEach(item => {
                if (item.Type === 'Category') {
                    const currentPath = path ? `${path}/${item.Name}` : item.Name;
                    
                    // Don't include the item being moved or its descendants
                    if (this.itemToMove !== item && !this.isDescendant(item, this.itemToMove)) {
                        categories.push({
                            name: item.Name,
                            path: currentPath,
                            item: item,
                            level: level
                        });
                        
                        // Add subcategories
                        if (item.Content) {
                            categories = categories.concat(
                                this.buildCategoryTree(item.Content, currentPath, level + 1)
                            );
                        }
                    }
                }
            });
            
            return categories;
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
        
        moveToCategory(targetCategory) {
            this.$emit('move-to-category', targetCategory);
        }
    }
};

// Export for use in other files
window.MoveModal = MoveModal;
