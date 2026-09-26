class BackendService {
    constructor(backendUrl = null, accessToken = null) {
        this.backendUrl = (backendUrl || window.FLEXI_CONFIG.BACKEND_URL || window.location.origin).replace(/\/$/, '');
        this.accessToken = accessToken;
    }

    authHeaders(token = this.accessToken) {
        if (!token) {
            throw new Error('List access token is required');
        }
        return {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
        };
    }

    async createList() {
        const response = await fetch(`${this.backendUrl}/api/lists`, { method: 'POST' });
        if (!response.ok) {
            throw new Error(`Could not create list (HTTP ${response.status})`);
        }
        return response.json();
    }

    async loadList(listId) {
        const response = await fetch(`${this.backendUrl}/api/list/${encodeURIComponent(listId)}`, {
            headers: this.authHeaders()
        });
        if (!response.ok) {
            throw new Error(`Could not load list (HTTP ${response.status})`);
        }
        const data = await response.json();
        if (!data || !Array.isArray(data.Checklist)) {
            throw new Error('Invalid data format from server');
        }
        return data;
    }

    async saveList(listId, data, token = this.accessToken) {
        const response = await fetch(`${this.backendUrl}/api/list/${encodeURIComponent(listId)}`, {
            method: 'PUT',
            headers: this.authHeaders(token),
            body: JSON.stringify(data)
        });
        return this.parseResponse(response, 'Could not save list');
    }

    async patchList(listId, operations) {
        const response = await fetch(`${this.backendUrl}/api/list/${encodeURIComponent(listId)}`, {
            method: 'PATCH',
            headers: this.authHeaders(),
            body: JSON.stringify({ operations })
        });
        return this.parseResponse(response, 'Could not update list');
    }

    async autoCategorize(listId, items, categories) {
        const response = await fetch(`${this.backendUrl}/api/list/${encodeURIComponent(listId)}/auto-categorize`, {
            method: 'POST',
            headers: this.authHeaders(),
            body: JSON.stringify({ items, categories })
        });
        return this.parseResponse(response, 'Categorization service unavailable');
    }

    async parseResponse(response, fallbackMessage) {
        let payload = null;
        try {
            payload = await response.json();
        } catch {
            // Use the generic error below.
        }
        if (!response.ok) {
            throw new Error(payload?.detail || payload?.error || `${fallbackMessage} (HTTP ${response.status})`);
        }
        return payload;
    }

    checkBackendAvailability() {
        const listId = new URLSearchParams(window.location.search).get('id');
        const accessToken = new URLSearchParams(window.location.hash.replace(/^#/, '')).get('token');
        if (!listId || !accessToken) {
            return { available: false, backendUrl: null, listId: null, accessToken: null };
        }

        this.accessToken = accessToken;
        return {
            available: true,
            backendUrl: this.backendUrl,
            listId,
            accessToken
        };
    }
}

window.BackendService = BackendService;
