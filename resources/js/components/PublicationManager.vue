<template>
    <div class="publication-manager">
        <!-- Current status -->
        <div v-if="value" class="publication-status">
            <div class="card p-4 mb-4">
                <h3 class="text-sm font-semibold mb-2">Current Publication</h3>
                <code class="text-xs break-all">{{ value }}</code>
            </div>
        </div>

        <!-- Action buttons -->
        <div class="flex gap-2 mb-4">
            <button type="button" class="btn" @click="checkPublications" :disabled="loading">
                <span v-if="checking">Checking...</span>
                <span v-else>Check for existing publications</span>
            </button>
            <button type="button" class="btn-primary" @click="showCreateForm = !showCreateForm">
                Create new publication
            </button>
        </div>

        <!-- Create form (collapsible) -->
        <div v-if="showCreateForm" class="card p-4 mb-4">
            <h3 class="text-sm font-semibold mb-3">Create New Publication</h3>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm mb-1">Publication Name</label>
                    <input type="text" v-model="createForm.name" class="input-text" placeholder="My Blog" />
                </div>
                <div>
                    <label class="block text-sm mb-1">Publication URL</label>
                    <input type="text" v-model="createForm.url" class="input-text" placeholder="https://example.com" />
                </div>
                <div>
                    <label class="block text-sm mb-1">Description (optional)</label>
                    <textarea v-model="createForm.description" class="input-text" rows="2" placeholder="A brief description"></textarea>
                </div>
                <button type="button" class="btn-primary" @click="createPublication" :disabled="creating">
                    <span v-if="creating">Creating...</span>
                    <span v-else>Create Publication Record</span>
                </button>
            </div>
        </div>

        <!-- Error message -->
        <div v-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            {{ error }}
        </div>

        <!-- Success message -->
        <div v-if="success" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ success }}
        </div>

        <!-- Check results -->
        <div v-if="publications.length > 0" class="space-y-2">
            <h3 class="text-sm font-semibold">Found {{ publications.length }} publication(s):</h3>
            <div v-for="pub in publications" :key="pub.uri"
                 class="card p-3 flex items-start gap-3 cursor-pointer hover:bg-gray-50"
                 @click="selectPublication(pub.uri)">
                <input type="radio" :value="pub.uri" v-model="selectedUri" />
                <div>
                    <strong>{{ pub.name }}</strong>
                    <div class="text-sm text-gray-500">{{ pub.url }}</div>
                    <code class="text-xs text-gray-400 break-all">{{ pub.uri }}</code>
                </div>
            </div>
        </div>

        <div v-if="checked && publications.length === 0" class="text-gray-500">
            No publication records found. Create one above.
        </div>
    </div>
</template>

<script>
export default {
    props: {
        value: String,
        meta: Object,
        config: Object,
    },

    data() {
        return {
            loading: false,
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

    methods: {
        getCredentials() {
            // Read from the Statamic publish form store
            const store = this.$store.state.publish;
            // The settings form store name varies, so we try to get values from the form
            return {
                identifier: this.getFieldValue('identifier'),
                app_password: this.getFieldValue('app_password'),
                pds_host: this.getFieldValue('pds_host') || 'https://bsky.social',
            };
        },

        getFieldValue(handle) {
            // Access sibling field values from the settings form
            const storeName = this.storeName;
            if (!storeName) return null;
            const values = this.$store.state.publish[storeName]?.values;
            return values?.[handle] || null;
        },

        async checkPublications() {
            this.checking = true;
            this.error = null;
            this.success = null;
            this.publications = [];
            this.checked = false;

            try {
                const creds = this.getCredentials();
                const response = await this.$axios.post('/cp/standard-site/publication/check', creds);

                if (response.data.success) {
                    this.publications = response.data.publications || [];
                    this.checked = true;
                    if (this.publications.length === 0) {
                        this.success = 'No publications found for DID ' + response.data.did;
                    }
                } else {
                    this.error = response.data.error || 'Check failed';
                }
            } catch (err) {
                this.handleError(err);
            } finally {
                this.checking = false;
            }
        },

        async createPublication() {
            if (!this.createForm.name) {
                this.error = 'Please enter a publication name';
                return;
            }

            this.creating = true;
            this.error = null;
            this.success = null;

            try {
                const creds = this.getCredentials();
                const payload = {
                    ...creds,
                    name: this.createForm.name,
                    url: this.createForm.url,
                    description: this.createForm.description || null,
                };

                const response = await this.$axios.post('/cp/standard-site/publication/create', payload);

                if (response.data.success) {
                    this.update(response.data.uri);
                    this.success = 'Publication created: ' + response.data.uri;
                    this.showCreateForm = false;
                } else {
                    this.error = response.data.error || 'Create failed';
                }
            } catch (err) {
                this.handleError(err);
            } finally {
                this.creating = false;
            }
        },

        selectPublication(uri) {
            this.selectedUri = uri;
            this.update(uri);
            this.success = 'Selected: ' + uri;
        },

        handleError(err) {
            if (err.response?.status === 419) {
                this.error = 'Session expired. Please refresh the page.';
            } else {
                this.error = err.response?.data?.error || err.response?.data?.message || 'An error occurred';
            }
        },
    },
};
</script>
