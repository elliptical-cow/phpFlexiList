// FlexiList Frontend Translation System
// Automatically detects browser language and provides translation function

(function() {
    // Use language detected by backend, fallback to browser detection
    let browserLang = 'en';
    if (window.DETECTED_LANGUAGE) {
        browserLang = window.DETECTED_LANGUAGE;
    } else {
        // Fallback to browser detection if backend didn't inject language
        const acceptLang = navigator.language || navigator.userLanguage || 'en';
        browserLang = acceptLang.toLowerCase().startsWith('de') ? 'de' : 'en';
    }
    
    // Translation mappings
    const translations = {
        'en': {
            // Main actions and buttons
            'New Category': 'New Category',
            'Auto-categorize': 'Auto-categorize',
            'Hide checked items': 'Hide checked items',
            'Copy List Link': 'Copy List Link',
            'Export Checklist': 'Export Checklist',
            'Save as New List': 'Save as New List',
            'Uncheck All': 'Uncheck All',
            'Start New List': 'Start New List',
            
            // Item operations
            'Add new item': 'Add new item',
            'Add new item to': 'Add new item to',
            'New Item': 'New Item',
            'New Category': 'New Category',
            'Edit': 'Edit',
            'Delete': 'Delete',
            'Move': 'Move',
            'Move to different category': 'Move to different category',
            'Add Subcategory': 'Add Subcategory',
            
            // Notes functionality
            'Note': 'Note',
            'Show Note': 'Show Note',
            'Hide Note': 'Hide Note',
            'Add note...': 'Add note...',
            'Edit note': 'Edit note',
            'Add note for': 'Add note for',
            'Edit note for': 'Edit note for',
            'for': 'for',
            'Note too long': 'Note too long',
            'characters': 'characters',
            
            // Status messages
            'Saving...': 'Saving...',
            'Creating...': 'Creating...',
            'Categorizing...': 'Categorizing...',
            'Loading...': 'Loading...',
            'Saved': 'Saved',
            'Error': 'Error',
            
            // Drag and drop
            'Drop here to move to root level': 'Drop here to move to root level',
            'Drag to reorder': 'Drag to reorder',
            
            // Modal and form elements
            'Cancel': 'Cancel',
            'Confirm': 'Confirm',
            'Close': 'Close',
            'Save': 'Save',
            
            // Accessibility labels
            'Click to edit title': 'Click to edit title',
            'Edit list title': 'Edit list title',
            'Checklist actions': 'Checklist actions',
            'Add new item to checklist': 'Add new item to checklist',
            'Checklist items': 'Checklist items',
            
            // Default values
            'Your Checklist': 'Your Checklist',
            'Untitled List': 'Untitled List',
            
            // Mobile specific
            'Cat': 'Cat',
            'Auto': 'Auto',
            'Hide ✓': 'Hide ✓',
            
            // Dropdown menu items  
            'Move Up': 'Move Up',
            'Move Down': 'Move Down',
            'Move to Category': 'Move to Category',
            
            // Additional move actions
            'up': 'up',
            'down': 'down', 
            'to different category': 'to different category',
            'Add subcategory': 'Add subcategory',
            
            // Checkbox and actions
            'Mark': 'Mark',
            'as': 'as',
            'complete': 'complete',
            'incomplete': 'incomplete',
            'Actions for': 'Actions for',
            
            // AutoComplete component
            'Item suggestions': 'Item suggestions',
            'in': 'in',
            'Already checked': 'Already checked',
            'No matching items found': 'No matching items found'
        },
        'de': {
            // Main actions and buttons
            'New Category': 'Neue Kategorie',
            'Auto-categorize': 'Autom. kategorisieren',
            'Hide checked items': 'Erledigte ausblenden',
            'Copy List Link': 'Link kopieren',
            'Export Checklist': 'Checkliste exportieren',
            'Save as New List': 'Als neue Liste speichern',
            'Uncheck All': 'Alle Häkchen entfernen',
            'Start New List': 'Neue Liste erstellen',
            
            // Item operations
            'Add new item': 'Neuer Eintrag',
            'Add new item to': 'Neuen Eintrag hinzufügen zu',
            'New Item': 'Neuer Eintrag',
            'New Category': 'Neue Kategorie',
            'Edit': 'Bearbeiten',
            'Delete': 'Löschen',
            'Move': 'Verschieben',
            'Move to different category': 'In andere Kategorie verschieben',
            'Add Subcategory': 'Unterkategorie hinzufügen',
            
            // Notes functionality
            'Note': 'Notiz',
            'Show Note': 'Notiz anzeigen',
            'Hide Note': 'Notiz verbergen',
            'Add note...': 'Notiz hinzufügen...',
            'Edit note': 'Notiz bearbeiten',
            'Add note for': 'Notiz hinzufügen für',
            'Edit note for': 'Notiz bearbeiten für',
            'for': 'für',
            'Note too long': 'Notiz zu lang',
            'characters': 'Zeichen',
            
            // Status messages
            'Saving...': 'Speichern...',
            'Creating...': 'Erstellen...',
            'Categorizing...': 'Kategorisieren...',
            'Loading...': 'Laden...',
            'Saved': 'Gespeichert',
            'Error': 'Fehler',
            
            // Drag and drop
            'Drop here to move to root level': 'Hier ablegen, um zur obersten Ebene zu verschieben',
            'Drag to reorder': 'Ziehen zum Umordnen',
            
            // Modal and form elements
            'Cancel': 'Abbrechen',
            'Confirm': 'Bestätigen',
            'Close': 'Schließen',
            'Save': 'Speichern',
            
            // Accessibility labels
            'Click to edit title': 'Klicken um Titel zu bearbeiten',
            'Edit list title': 'Listen-Titel bearbeiten',
            'Checklist actions': 'Checklisten-Aktionen',
            'Add new item to checklist': 'Neuen Eintrag hinzufügen',
            'Checklist items': 'Checklist-Einträge',
            
            // Default values
            'Your Checklist': 'Deine Checklist',
            'Untitled List': 'Unbenannte Liste',
            
            // Mobile specific
            'Cat': 'Kat',
            'Auto': 'Auto',
            'Hide ✓': '✓ verbergen',
            
            // Dropdown menu items
            'Move Up': 'Nach oben schieben',
            'Move Down': 'Nach unten schieben', 
            'Move to Category': 'Zu Kategorie verschieben',
            
            // Additional move actions
            'up': 'nach oben',
            'down': 'nach unten',
            'to different category': 'in andere Kategorie',
            'Add subcategory': 'Unterkategorie hinzufügen',
            
            // Checkbox and actions
            'Mark': 'Markiere',
            'as': 'als',
            'complete': 'erledigt',
            'incomplete': 'unerledigt', 
            'Actions for': 'Aktionen für',
            
            // AutoComplete component
            'Item suggestions': 'Vorschläge',
            'in': 'in',
            'Already checked': 'Bereits erledigt',
            'No matching items found': 'Keine passenden Einträge gefunden'
        }
    };
    
    // Global translation function
    window.t = function(key) {
        const translation = translations[browserLang] && translations[browserLang][key];
        return translation || key; // Fallback to original key if translation not found
    };
    
    // Expose current language for other scripts
    window.CURRENT_LANGUAGE = browserLang;
    
    // Debug function (can be called from console)
    window.getTranslations = function() {
        return {
            currentLanguage: browserLang,
            allTranslations: translations,
            availableKeys: Object.keys(translations.en)
        };
    };
    
})();