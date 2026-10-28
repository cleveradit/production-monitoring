import { ref } from 'vue';
import { fetchDashboard } from '../api/client.js';

export function useDashboard() {
    const data = ref(null);
    const loading = ref(false);
    const error = ref(null);

    async function load() {
        loading.value = true;
        error.value = null;

        try {
            data.value = await fetchDashboard();
        } catch (e) {
            error.value = e instanceof Error ? e.message : String(e);
        } finally {
            loading.value = false;
        }
    }

    return { data, loading, error, load };
}
