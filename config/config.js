/**
 * FlexiList Frontend Configuration
 * 
 * This file contains all configurable frontend settings.
 * Update these values for different deployment environments.
 */
window.FLEXI_CONFIG = {
    // Backend API URL - update this for production deployments
    // Options: 'http://localhost:8080', 'localhost:8080'
    BACKEND_URL: 'localhost:8080',
    
    // Application settings
    APP_NAME: 'FlexiList',
    VERSION: '1.1.0',
    
    // Feature flags
    FEATURES: {
        DEBUG_MODE: false,            // Master debug flag
        SHOW_PATCH_DEBUG: false,      // Show patch debugging controls
        SHOW_JSON_VIEW: false,        // Show JSON reference section
        AUTO_CATEGORIZATION: true
    },
    
    // Auto-complete settings
    AUTO_COMPLETE: {
        ENABLED: true,                // Enable/disable auto-complete feature
        MIN_CHARS: 3,                 // Minimum characters before showing suggestions
        MAX_SUGGESTIONS: 5,          // Maximum number of suggestions to show
        SHOW_CHECKED_FIRST: true,     // Prioritize checked items in suggestions
        DEBOUNCE_MS: 150,            // Milliseconds to wait after typing
        CASE_SENSITIVE: false         // Case sensitive matching
    },
    
    // UI Settings
    UI_SETTINGS: {
        HIERARCHY_INDENTATION: 20     // Pixels of indentation per nesting level
    }
};
