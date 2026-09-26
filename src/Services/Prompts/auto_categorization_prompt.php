<?php

declare(strict_types=1);

/**
 * Auto-Categorization Prompt Generator
 * 
 * Enhanced multilingual prompt for AI-powered checklist item categorization
 * with context awareness and title integration.
 */

function createAutoCategorizationPrompt(array $items, array $categories, string $listTitle = ''): string
{
    $itemsStr = implode(', ', $items);
    $categoriesStr = implode(', ', $categories);

    $titlePrompt = '';
    if (!in_array($listTitle, array('', 'Your Checklist', 'My Checklist'))){
        $titlePrompt = 
"CONTEXT: List Title: \"{$listTitle}\"
- Use the title to understand the overall domain and purpose of this checklist
- Let the title guide interpretation of ambiguous items and category intent
- Consider cultural context indicated by title language
- Apply domain-specific categorization logic based on the title theme";
    }

    $promptString =  
"You are a multilingual checklist organization expert with deep understanding of German, English, and international naming conventions, specializing in intelligent context-aware categorization.
{$titlePrompt}
TASK: Intelligently assign items to the most appropriate categories based on their semantic meaning, typical usage context, and the list's overall purpose.

ITEMS: {$itemsStr}
CATEGORIES: {$categoriesStr}

INTELLIGENT CATEGORIZATION FEATURES:
- Recognize German, English, and mixed-language item names
- Handle informal abbreviations, brand names, and regional variations
- Analyze semantic relationships and common usage patterns

DECISION LOGIC:
- Analyze the list of categories to unterstand the logic of the users list. E.g. is the shopping list categorized by different meals to be cooked or by groups of items typically presented close togehter in stores
- Match items to categories using cross-language understanding
- Assign null only if truly unrelated (confidence < 60%)


QUALITY RULES:
- Each item assigned to exactly ONE most appropriate category
- Prefer broader category interpretations over strict literal matching
- Handle typos and variations gracefully
- Ensure consistent categorization for similar/duplicate items
- Use exactly the provided item and category names (no modifications)

OUTPUT: Valid JSON array only, no explanations or additional text.
FORMAT: [{\"item\": \"exact_item_name\", \"category\": \"exact_category_name_or_null\"}]

JSON RESPONSE:";
    return $promptString;
}