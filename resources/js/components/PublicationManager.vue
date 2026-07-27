<template>
    <div class="space-y-4">
        <!-- Current status -->
        <div
            v-if="props.value"
            class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800"
        >
            <p class="text-sm font-semibold mb-2">{{ __('Current Publication') }}</p>
            <code class="block text-xs break-all">{{ props.value }}</code>
        </div>

        <!-- Action buttons -->
        <div class="flex gap-2">
            <Button
                variant="default"
                :text="checking ? __('Checking...') : __('Check for existing publications')"
                :loading="checking"
                :disabled="isReadOnly"
                @click="checkPublications"
            />
            <Button
                variant="primary"
                :text="__('Create new publication')"
                :disabled="isReadOnly"
                @click="showCreateForm = !showCreateForm"
            />
        </div>

        <!-- Create form (collapsible) -->
        <div
            v-if="showCreateForm"
            class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800 space-y-3"
        >
            <p class="text-sm font-semibold">{{ __('Create New Publication') }}</p>

            <div class="space-y-1">
                <label class="text-sm font-medium">{{ __('Publication Name') }}</label>
                <Input
                    v-model="createForm.name"
                    :placeholder="__('My Blog')"
                    :read-only="isReadOnly"
                />
            </div>

            <div class="space-y-1">
                <label class="text-sm font-medium">{{ __('Publication URL') }}</label>
                <Input
                    v-model="createForm.url"
                    :placeholder="__('https://example.com')"
                    :read-only="isReadOnly"
                />
            </div>

            <div class="space-y-1">
                <label class="text-sm font-medium">{{ __('Description (optional)') }}</label>
                <Textarea
                    v-model="createForm.description"
                    :placeholder="__('A brief description')"
                    :read-only="isReadOnly"
                    :rows="3"
                />
            </div>

            <Button
                variant="primary"
                :text="creating ? __('Creating...') : __('Create Publication Record')"
                :loading="creating"
                :disabled="isReadOnly"
                @click="createPublication"
            />
        </div>

        <!-- Error message -->
        <ErrorMessage v-if="error" :text="error" />

        <!-- Success message -->
        <p v-if="success" class="text-sm font-medium text-green-700 dark:text-green-400">
            {{ success }}
        </p>

        <!-- Check results -->
        <div v-if="publications.length > 0" class="space-y-2">
            <p class="text-sm font-semibold">
                {{ __('Found :count publication(s):', { count: publications.length }) }}
            </p>

            <label
                v-for="pub in publications"
                :key="pub.uri"
                class="flex items-start gap-3 p-3 border border-gray-200 dark:border-gray-700 rounded-md cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-gray-800"
                :class="{
                    'border-primary bg-primary/5 dark:border-primary dark:bg-primary/10':
                        selectedUri === pub.uri,
                }"
            >
                <input
                    type="radio"
                    :value="pub.uri"
                    v-model="selectedUri"
                    :disabled="isReadOnly"
                    class="mt-0.5"
                    @change="selectPublication(pub.uri)"
                />
                <div class="flex flex-col gap-1">
                    <strong class="text-sm">{{ pub.name }}</strong>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ pub.url }}</span>
                    <code class="text-xs text-gray-400 dark:text-gray-500 break-all">{{ pub.uri }}</code>
                </div>
            </label>
        </div>

        <p v-if="checked && publications.length === 0" class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('No publication records found. Create one above.') }}
        </p>
    </div>
</template>

<script setup>
import { ref, reactive, watch } from 'vue';
import { Fieldtype } from '@statamic/cms';
import { Button, Input, Textarea, ErrorMessage } from '@statamic/cms/ui';

const emit = defineEmits(Fieldtype.emits);
const props = defineProps(Fieldtype.props);
const { isReadOnly, update, expose } = Fieldtype.use(emit, props);
defineExpose(expose);

// Local state
const checking = ref(false);
const creating = ref(false);
const error = ref(null);
const success = ref(null);
const publications = ref([]);
const checked = ref(false);
const selectedUri = ref(null);
const showCreateForm = ref(false);

const createForm = reactive({
    name: '',
    url: window.location.origin,
    description: '',
});

// Initialize selectedUri from current value
selectedUri.value = props.value;

// Keep selectedUri in sync when the field value changes externally
watch(
    () => props.value,
    (val) => {
        selectedUri.value = val;
    },
);

// Methods
function handleError(err) {
    if (err.response?.status === 419) {
        error.value = __('Session expired. Please refresh the page.');
    } else {
        error.value =
            err.response?.data?.error ||
            err.response?.data?.message ||
            __('An error occurred');
    }
}

async function checkPublications() {
    checking.value = true;
    error.value = null;
    success.value = null;
    publications.value = [];
    checked.value = false;

    try {
        const response = await Statamic.$axios.post('/cp/standard-site/publication/check');

        if (response.data.success) {
            publications.value = response.data.publications || [];
            checked.value = true;
            if (publications.value.length === 0) {
                success.value = __('No publications found for DID :did', {
                    did: response.data.did,
                });
            }
        } else {
            error.value = response.data.error || __('Check failed');
        }
    } catch (err) {
        handleError(err);
    } finally {
        checking.value = false;
    }
}

async function createPublication() {
    if (!createForm.name) {
        error.value = __('Please enter a publication name');
        return;
    }

    creating.value = true;
    error.value = null;
    success.value = null;

    try {
        const payload = {
            name: createForm.name,
            url: createForm.url,
            description: createForm.description || null,
        };

        const response = await Statamic.$axios.post(
            '/cp/standard-site/publication/create',
            payload,
        );

        if (response.data.success) {
            update(response.data.uri);
            selectedUri.value = response.data.uri;
            success.value = __('Publication created: :uri', { uri: response.data.uri });
            showCreateForm.value = false;
        } else {
            error.value = response.data.error || __('Create failed');
        }
    } catch (err) {
        handleError(err);
    } finally {
        creating.value = false;
    }
}

function selectPublication(uri) {
    if (isReadOnly.value) return;
    selectedUri.value = uri;
    update(uri);
    success.value = __('Selected: :uri', { uri });
}
</script>
