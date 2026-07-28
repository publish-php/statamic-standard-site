<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold">Collection Sync</h3>
            <Button
                variant="default"
                :text="loading ? 'Loading...' : 'Refresh'"
                :loading="loading"
                @click="loadCollections"
            />
        </div>

        <div v-if="error" class="p-3 text-sm text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800 rounded">
            {{ error }}
        </div>

        <div v-else-if="collections.length === 0 && hasLoaded" class="text-sm text-gray-500 dark:text-gray-400">
            No collections found.
        </div>

        <table v-else-if="collections.length > 0" class="w-full text-sm">
            <thead>
                <tr class="text-left border-b border-gray-200 dark:border-gray-700">
                    <th class="pb-2 pr-4 font-semibold">Collection</th>
                    <th class="pb-2 pr-4 font-semibold">Entries</th>
                    <th class="pb-2 pr-4 font-semibold">Sync</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="col in collections"
                    :key="col.handle"
                    class="border-b border-gray-100 dark:border-gray-800"
                >
                    <td class="py-3 pr-4">
                        <div class="font-medium">{{ col.title }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ col.handle }}</div>
                    </td>
                    <td class="py-3 pr-4 text-gray-500 dark:text-gray-400">
                        {{ col.entries_count }}
                    </td>
                    <td class="py-3 pr-4">
                        <button
                            type="button"
                            :class="[
                                'inline-flex items-center rounded-full px-3 py-1 text-xs font-medium transition-colors',
                                col.enabled
                                    ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-400'
                                    : 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400'
                            ]"
                            :disabled="toggling === col.handle"
                            @click="toggleCollection(col)"
                        >
                            <span v-if="toggling === col.handle">...</span>
                            <span v-else-if="col.enabled">Enabled</span>
                            <span v-else>Disabled</span>
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
import { ref, onMounted, getCurrentInstance } from 'vue';
import { Fieldtype } from '@statamic/cms';
import { Button } from '@statamic/cms/ui';

const { $axios } = getCurrentInstance().appContext.config.globalProperties;

const props = defineProps(Fieldtype.props);
const { expose } = Fieldtype.use({}, props);
defineExpose(expose);

const loading = ref(false);
const hasLoaded = ref(false);
const collections = ref([]);
const error = ref(null);
const toggling = ref(null);

onMounted(() => {
    loadCollections();
});

async function loadCollections() {
    loading.value = true;
    error.value = null;
    try {
        const response = await $axios.get(props.meta.collections_url);
        collections.value = response.data.collections || [];
    } catch (err) {
        error.value = err.response?.data?.error || 'Failed to load collections';
    } finally {
        loading.value = false;
        hasLoaded.value = true;
    }
}

async function toggleCollection(col) {
    toggling.value = col.handle;
    const newEnabled = !col.enabled;
    try {
        await $axios.patch(props.meta.toggle_url.replace('__HANDLE__', col.handle), {
            enabled: newEnabled,
        });
        col.enabled = newEnabled;
    } catch (err) {
        error.value = err.response?.data?.error || 'Failed to toggle collection';
    } finally {
        toggling.value = null;
    }
}
</script>
