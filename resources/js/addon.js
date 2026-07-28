import PublicationManager from './components/PublicationManager.vue';
import StatusDashboard from './components/StatusDashboard.vue';
import CollectionManager from './components/CollectionManager.vue';

Statamic.booting(() => {
    Statamic.$components.register('publication-manager-fieldtype', PublicationManager);
    Statamic.$components.register('standard-site-status-fieldtype', StatusDashboard);
    Statamic.$components.register('standard-site-collections-fieldtype', CollectionManager);
});
