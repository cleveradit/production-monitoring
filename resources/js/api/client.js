export async function fetchDashboard() {
    const response = await fetch('/api/dashboard', {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error(`Request failed with status ${response.status}`);
    }

    return response.json();
}
