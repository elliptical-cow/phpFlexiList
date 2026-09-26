class BackendService {
    constructor(backendUrl = null) {
        // Use config from config.js if available, otherwise fall back to parameter or default
        this.backendUrl = backendUrl || 
                         (window.FLEXI_CONFIG && window.FLEXI_CONFIG.BACKEND_URL) || 
                         window.location.origin;
    }

    async loadList(listId) {
        if (!listId) {
            throw new Error('List ID is required');
        }

        const response = await fetch(`${this.backendUrl}/api/list/${listId}`);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();
        
        if (!data || !data.Checklist) {
            throw new Error('Invalid data format from server');
        }

        return data;
    }

    async saveList(listId, data) {
        if (!listId) {
            throw new Error('List ID is required');
        }


        const response = await fetch(`${this.backendUrl}/api/list/${listId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        if (!response.ok) {
            // Try to get detailed error response
            let errorDetail = `HTTP ${response.status}: ${response.statusText}`;
            try {
                const errorData = await response.json();
                if (errorData.detail) {
                    errorDetail = `${errorDetail} - ${errorData.detail}`;
                }
                console.error('PUT Error Response:', errorData);
            } catch (e) {
                console.error('Could not parse error response:', e);
            }
            throw new Error(errorDetail);
        }

        return await response.json();
    }

    async patchList(listId, operations) {
        if (!listId) {
            throw new Error('List ID is required');
        }

        const patchRequest = {
            operations: operations
        };
        
        try {
            
            const response = await fetch(`${this.backendUrl}/api/list/${listId}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(patchRequest)
            });
            
            if (!response.ok) {
                // Try to get detailed error response
                let errorDetail = `HTTP ${response.status}: ${response.statusText}`;
                try {
                    const errorData = await response.json();
                    if (errorData.detail) {
                        errorDetail = `${errorDetail} - ${errorData.detail}`;
                    }
                    console.error('PATCH Error Response:', errorData);
                } catch (e) {
                    console.error('Could not parse error response:', e);
                }
                throw new Error(errorDetail);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Error patching list:', error);
            throw error;
        }
    }

    async autoCategorize(listId, items, categories) {
        if (!listId) {
            throw new Error('List ID is required');
        }

        const response = await fetch(`${this.backendUrl}/api/list/${listId}/auto-categorize`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                items: items,
                categories: categories
            })
        });
        
        if (!response.ok) {
            let errorData;
            try {
                errorData = await response.json();
            } catch {
                // Handle empty or invalid JSON response
                throw new Error(`Categorization service unavailable (HTTP ${response.status}). Please try again later.`);
            }
            throw new Error(errorData.detail || errorData.message || `HTTP ${response.status}`);
        }

        let result;
        try {
            result = await response.json();
        } catch {
            // Handle empty or invalid JSON response
            throw new Error('Categorization service returned invalid response. Please try again later.');
        }
        
        if (!result.success) {
            throw new Error(result.message || 'Categorization failed');
        }

        return result;
    }

    checkBackendAvailability() {
        // Check if we have backend integration variables from the server
        if (window.BACKEND_URL && window.LIST_ID) {
            return {
                available: true,
                backendUrl: window.BACKEND_URL,
                listId: window.LIST_ID
            };
        }
        
        // Check URL parameters directly (for local development)
        // Check for 'key' first, then fall back to 'id' for backward compatibility
        const urlParams = new URLSearchParams(window.location.search);
        const listId = urlParams.get('key') || urlParams.get('id');
        
        if (listId) {
            return {
                available: true,
                backendUrl: this.backendUrl,
                listId: listId
            };
        }

        return {
            available: false,
            backendUrl: null,
            listId: null
        };
    }
}

// Export for use in other files
window.BackendService = BackendService;
