<template>
    <div class="publication-manager">
        <!-- Current status -->
        <div v-if="value" class="publication-manager-status">
            <p class="publication-manager-label">{{ __('Current Publication') }}</p>
            <code class="publication-manager-uri">{{ value }}</code>
        </div>

        <!-- Action buttons -->
        <div class="publication-manager-actions">
            <ui-button
                variant="default"
                :text="checking ? __('Checking...') : __('Check for existing publications')"
                :loading="checking"
                :disabled="isReadOnly"
                @click="checkPublications"
            />
            <ui-button
                variant="primary"
                :text="__('Create new publication')"
                :disabled="isReadOnly"
                @click="showCreateForm = !showCreateForm"
            />
        </div>

        <!-- Create form (collapsible) -->
        <div v-if="showCreateForm" class="publication-manager-form">
            <p class="publication-manager-label">{{ __('Create New Publication') }}</p>
            <div class="publication-manager-fields">
                <div class="publication-manager-field">
                    <label class="publication-manager-field-label">{{ __('Publication Name') }}</label>
                    <ui-input
                        v-model="createForm.name"
                        :placeholder="__('My Blog')"
                        :readonly="isReadOnly"
                    />
                </div>
                <div class="publication-manager-field">
                    <label class="publication-manager-field-label">{{ __('Publication URL') }}</label>
                    <ui-input
                        v-model="createForm.url"
                        :placeholder="__('https://example.com')"
                        :readonly="isReadOnly"
                    />
                </div>
                <div class="publication-manager-field">
                    <label class="publication-manager-field-label">{{ __('Description (optional)') }}</label>
                    <ui-textarea
                        v-model="createForm.description"
                        :placeholder="__('A brief description')"
                        :readonly="isReadOnly"
                    />
                </div>
                <ui-button
                    variant="primary"
                    :text="creating ? __('Creating...') : __('Create Publication Record')"
                    :loading="creating"
                    :disabled="isReadOnly"
                    @click="createPublication"
                />
            </div>
        </div>

        <!-- Error message -->
        <ui-error-message v-if="error" :text="error" class="publication-manager-message" />

        <!-- Success message -->
        <p v-if="success" class="publication-manager-success">{{ success }}</p>

        <!-- Check results -->
        <div v-if="publications.length > 0" class="publication-manager-results">
            <p class="publication-manager-label">
                {{ __('Found :count publication(s):', { count: publications.length }) }}
            </p>
            <div
                v-for="pub in publications"
                :key="pub.uri"
                class="publication-manager-publication"
                :class="{ 'is-selected': selectedUri === pub.uri }"
                @click="selectPublication(pub.uri)"
            >
                <input type="radio" :value="pub.uri" v-model="selectedUri" :disabled="isReadOnly" />
                <div class="publication-manager-publication-details">
                    <strong>{{ pub.name }}</strong>
                    <span class="publication-manager-publication-url">{{ pub.url }}</span>
                    <code class="publication-manager-publication-uri">{{ pub.uri }}</code>
                </div>
            </div>
        </div>

        <p v-if="checked && publications.length === 0" class="publication-manager-empty">
            {{ __('No publication records found. Create one above.') }}
        </p>
    </div>
</template>

<script>
import { FieldtypeMixin } from '@statamic/cms';

export default {
    mixins: [FieldtypeMixin],

    data() {
        return {
            checking: false,
            creating: false,
            error: null,
            success: null,
            publications: [],
            checked: false,
            selectedUri: null,
            showCreateForm: false,
            createForm: {
                name: '',
                url: window.location.origin,
                description: '',
            },
        };
    },

    watch: {
        value(val) {
            this.selectedUri = val;
        },
    },

    created() {
        this.selectedUri = this.value;
    },

    methods: {
        async checkPublications() {
            this.checking = true;
            this.error = null;
            this.success = null;
            this.publications = [];
            this.checked = false;

            try {
                const response = await Statamic.$axios.post('/cp/standard-site/publication/check');

                if (response.data.success) {
                    this.publications = response.data.publications || [];
                    this.checked = true;
                    if (this.publications.length === 0) {
                        this.success = __('No publications found for DID :did', { did: response.data.did });
                    }
                } else {
                    this.error = response.data.error || __('Check failed');
                }
            } catch (err) {
                this.handleError(err);
            } finally {
                this.checking = false;
            }
        },

        async createPublication() {
            if (!this.createForm.name) {
                this.error = __('Please enter a publication name');
                return;
            }

            this.creating = true;
            this.error = null;
            this.success = null;

            try {
                const payload = {
                    name: this.createForm.name,
                    url: this.createForm.url,
                    description: this.createForm.description || null,
                };

                const response = await Statamic.$axios.post('/cp/standard-site/publication/create', payload);

                if (response.data.success) {
                    this.update(response.data.uri);
                    this.selectedUri = response.data.uri;
                    this.success = __('Publication created: :uri', { uri: response.data.uri });
                    this.showCreateForm = false;
                } else {
                    this.error = response.data.error || __('Create failed');
                }
            } catch (err) {
                this.handleError(err);
            } finally {
                this.creating = false;
            }
        },

        selectPublication(uri) {
            if (this.isReadOnly) return;
            this.selectedUri = uri;
            this.update(uri);
            this.success = __('Selected: :uri', { uri });
        },

        handleError(err) {
            if (err.response?.status === 419) {
                this.error = __('Session expired. Please refresh the page.');
            } else {
                this.error = err.response?.data?.error || err.response?.data?.message || __('An error occurred');
            }
        },
    },
};
</script>
