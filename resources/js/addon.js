import PublicationManager from './components/PublicationManager.vue';

Statamic.booting(() => {
    Statamic.$components.register('publication-manager-fieldtype', PublicationManager);
});
