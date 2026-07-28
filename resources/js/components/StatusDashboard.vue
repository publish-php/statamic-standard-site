<template>
    <div class="space-y-6">
        <!-- Sync Errors Badge -->
        <div v-if="errorCount > 0" class="p-4 border border-red-200 dark:border-red-800 rounded-lg bg-red-50 dark:bg-red-900/20">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-red-500 text-white text-xs font-bold">
                    {{ errorCount }}
                </span>
                <p class="text-sm font-semibold text-red-700 dark:text-red-400">
                    Sync {{ errorCount === 1 ? 'error' : 'errors' }} since last viewed
                </p>
            </div>
            <ul v-if="errors.length" class="mt-3 space-y-2">
                <li v-for="err in errors" :key="err.timestamp" class="text-xs text-red-600 dark:text-red-500">
                    <span class="font-mono">{{ err.entry_id }}</span>:
                    {{ err.error }}
                    <span class="text-red-400 dark:text-red-600">({{ err.timestamp }})</span>
                </li>
            </ul>
        </div>

        <!-- Document Listing -->
        <div>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold">Synced Documents</h3>
                <Button
                    variant="default"
                    :text="loading ? 'Loading...' : 'Refresh'"
                    :loading="loading"
                    @click="loadDocuments"
                />
            </div>

            <div v-if="error" class="p-3 text-sm text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800 rounded">
                {{ error }}
            </div>

            <div v-else-if="documents.length === 0 && hasLoaded" class="text-sm text-gray-500 dark:text-gray-400">
                No documents found on the PDS.
            </div>

            <table v-else-if="documents.length > 0" class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b border-gray-200 dark:border-gray-700">
                        <th class="pb-2 pr-4 font-semibold">Title</th>
                        <th class="pb-2 pr-4 font-semibold">Path</th>
                        <th class="pb-2 pr-4 font-semibold">Published</th>
                        <th class="pb-2 pr-4 font-semibold">Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="doc in documents" :key="doc.uri" class="border-b border-gray-100 dark:border-gray-800">
                        <td class="py-2 pr-4">{{ doc.title }}</td>
                        <td class="py-2 pr-4 font-mono text-xs">{{ doc.path }}</td>
                        <td class="py-2 pr-4 text-xs">{{ doc.publishedAt }}</td>
                        <td class="py-2 pr-4 text-xs">{{ doc.updatedAt || '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Button } from '@statamic/cms/ui';
import { getCurrentInstance } from 'vue';

const { $axios } = getCurrentInstance().appContext.config.globalProperties;

const loading = ref(false);
const hasLoaded = ref(false);
const documents = ref([]);
const error = ref(null);
const errorCount = ref(0);
const errors = ref([]);

// Load sync errors on mount (for the badge)
onMounted(async () => {
    try {
        const response = await $axios.get('/cp/standard-site/status/errors');
        errorCount.value = response.data.count || 0;
        errors.value = response.data.errors || [];
    } catch (err) {
        // Silently fail — not critical
    }
});

async function loadDocuments() {
    loading.value = true;
    error.value = null;
    try {
        const response = await $axios.get('/cp/standard-site/status/documents');
        if (response.data.success) {
            documents.value = response.data.documents || [];
        } else {
            error.value = response.data.error || 'Failed to load documents';
        }
    } catch (err) {
        error.value = err.response?.data?.error || 'Failed to connect to PDS';
    } finally {
        loading.value = false;
        hasLoaded.value = true;
    }
}
</script>
