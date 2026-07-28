import PublicationManager from './components/PublicationManager.vue';
import StatusDashboard from './components/StatusDashboard.vue';

Statamic.booting(() => {
    Statamic.$components.register('publication-manager-fieldtype', PublicationManager);
    Statamic.$components.register('standard-site-status', StatusDashboard);
});
