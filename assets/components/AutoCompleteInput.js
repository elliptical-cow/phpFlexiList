const AutoCompleteInput = {
    props: {
        'modelValue': String,
        'placeholder': {
            type: String,
            default: ''
        },
        'config': {
            type: Object,
            default: () => ({
                enabled: true,
                minChars: 3,
                maxSuggestions: 10,
                showCheckedFirst: true,
                debounceMs: 150,
                caseSensitive: false
            })
        },
        'suggestions': {
            type: Array,
            default: () => []
        }
    },
    emits: ['update:modelValue', 'suggestion-selected', 'enter', 'escape'],
    template: `
        <div class="autocomplete-container" :class="{ 'has-suggestions': showSuggestions }">
            <input 
                ref="input"
                type="text" 
                :value="modelValue"
                :placeholder="placeholder"
                @input="handleInput"
                @keydown="handleKeydown"
                @focus="handleFocus"
                @blur="handleBlur"
                class="autocomplete-input"
                :aria-label="placeholder"
                :aria-expanded="showSuggestions"
                :aria-activedescendant="selectedIndex >= 0 ? 'suggestion-' + selectedIndex : null"
                autocomplete="off"
            />
            
            <div 
                v-if="showSuggestions && filteredSuggestions.length > 0" 
                ref="dropdown"
                class="autocomplete-dropdown"
                role="listbox"
                :aria-label="t('Item suggestions')"
            >
                <div 
                    v-for="(suggestion, index) in filteredSuggestions" 
                    :key="suggestion.name + suggestion.category"
                    :id="'suggestion-' + index"
                    class="autocomplete-suggestion"
                    :class="{ 
                        'selected': index === selectedIndex,
                        'checked-item': suggestion.checked 
                    }"
                    @mousedown.prevent="selectSuggestion(suggestion, index)"
                    @mouseenter="selectedIndex = index"
                    role="option"
                    :aria-selected="index === selectedIndex"
                >
                    <div class="suggestion-content">
                        <span class="suggestion-name">{{ suggestion.name }}</span>
                        <span v-if="suggestion.category" class="suggestion-category">
                            {{ t('in') }} {{ suggestion.category }}
                        </span>
                        <span v-if="suggestion.checked" class="suggestion-status" :title="t('Already checked')">
                            ✓
                        </span>
                    </div>
                </div>
                
                <div v-if="filteredSuggestions.length === 0 && searchQuery.length >= config.minChars" 
                     class="no-suggestions">
                    {{ t('No matching items found') }}
                </div>
            </div>
        </div>
    `,
    data() {
        return {
            searchQuery: '',
            showSuggestions: false,
            selectedIndex: -1,
            debounceTimeout: null,
            hasFocus: false
        };
    },
    computed: {
        filteredSuggestions() {
            if (!this.config.enabled || this.searchQuery.length < this.config.minChars) {
                return [];
            }
            
            const query = this.config.caseSensitive ? 
                this.searchQuery : 
                this.searchQuery.toLowerCase();
            
            // Filter suggestions based on prefix match
            let matches = this.suggestions.filter(suggestion => {
                const name = this.config.caseSensitive ? 
                    suggestion.name : 
                    suggestion.name.toLowerCase();
                return name.startsWith(query);
            });
            
            // Sort: checked items first, then alphabetical
            if (this.config.showCheckedFirst) {
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
            return matches.slice(0, this.config.maxSuggestions);
        }
    },
    watch: {
        modelValue: {
            handler(newValue) {
                this.searchQuery = newValue || '';
                this.debouncedSearch();
            },
            immediate: true
        },
        filteredSuggestions() {
            // Reset selection when suggestions change
            this.selectedIndex = -1;
        }
    },
    methods: {
        // Translation helper method
        t(key) {
            return (window.t && window.t(key)) || key;
        },
        
        handleInput(event) {
            const value = event.target.value;
            this.$emit('update:modelValue', value);
            this.searchQuery = value;
            this.debouncedSearch();
        },
        
        debouncedSearch() {
            clearTimeout(this.debounceTimeout);
            this.debounceTimeout = setTimeout(() => {
                this.updateSuggestions();
            }, this.config.debounceMs);
        },
        
        updateSuggestions() {
            if (this.hasFocus && this.config.enabled && this.searchQuery.length >= this.config.minChars) {
                this.showSuggestions = true;
            } else {
                this.showSuggestions = false;
            }
        },
        
        handleKeydown(event) {
            if (!this.showSuggestions) {
                // Handle non-suggestion navigation
                if (event.key === 'Enter') {
                    event.preventDefault();
                    this.$emit('enter');
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    this.$emit('escape');
                }
                return;
            }
            
            // Handle suggestion navigation
            switch (event.key) {
                case 'ArrowDown':
                    event.preventDefault();
                    this.selectedIndex = Math.min(
                        this.selectedIndex + 1, 
                        this.filteredSuggestions.length - 1
                    );
                    break;
                    
                case 'ArrowUp':
                    event.preventDefault();
                    this.selectedIndex = Math.max(this.selectedIndex - 1, -1);
                    break;
                    
                case 'Enter':
                    event.preventDefault();
                    if (this.selectedIndex >= 0 && this.filteredSuggestions[this.selectedIndex]) {
                        this.selectSuggestion(this.filteredSuggestions[this.selectedIndex], this.selectedIndex);
                    } else {
                        this.closeSuggestions();
                        this.$emit('enter');
                    }
                    break;
                    
                case 'Escape':
                    event.preventDefault();
                    if (this.showSuggestions) {
                        this.closeSuggestions();
                    } else {
                        this.$emit('escape');
                    }
                    break;
                    
                case 'Tab':
                    // Allow normal tab behavior, just close suggestions
                    this.closeSuggestions();
                    break;
            }
        },
        
        handleFocus() {
            this.hasFocus = true;
            this.updateSuggestions();
        },
        
        handleBlur() {
            this.hasFocus = false;
            // Delay hiding suggestions to allow clicking on them
            setTimeout(() => {
                if (!this.hasFocus) {
                    this.closeSuggestions();
                    // Emit enter event when blur occurs with content (handles "fertig" button on mobile)
                    if (this.modelValue && this.modelValue.trim()) {
                        this.$emit('enter');
                    }
                }
            }, 150);
        },
        
        selectSuggestion(suggestion, index) {
            this.$emit('update:modelValue', suggestion.name);
            this.$emit('suggestion-selected', suggestion);
            this.closeSuggestions();
            
            // Focus back to input for continued editing
            this.$nextTick(() => {
                if (this.$refs.input) {
                    this.$refs.input.focus();
                }
            });
        },
        
        closeSuggestions() {
            this.showSuggestions = false;
            this.selectedIndex = -1;
        },
        
        focus() {
            if (this.$refs.input) {
                this.$refs.input.focus();
            }
        },
        
        select() {
            if (this.$refs.input) {
                this.$refs.input.select();
            }
        }
    },
    
    beforeUnmount() {
        clearTimeout(this.debounceTimeout);
    }
};

// Export for use in other files
window.AutoCompleteInput = AutoCompleteInput;