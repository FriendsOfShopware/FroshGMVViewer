import './page/frosh-gmv-viewer-overview';
import './component/frosh-gmv-chart';

// Shopware Module Registration
Shopware.Module.register('frosh-gmv-viewer', {
    type: 'plugin',
    name: 'frosh-gmv-viewer.general.mainMenuItemGeneral',
    title: 'frosh-gmv-viewer.general.mainMenuItemGeneral',
    description: 'frosh-gmv-viewer.general.descriptionTextModule',
    color: '#9AA8B5',
    icon: 'regular-chart-line',

    routes: {
        overview: {
            component: 'frosh-gmv-viewer-overview',
            path: 'overview',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'frosh_gmv_viewer.viewer'
            }
        }
    },

    settingsItem: {
        group: 'shop',
        to: 'frosh.gmv.viewer.overview',
        icon: 'regular-chart-line',
        privilege: 'frosh_gmv_viewer.viewer'
    }
});
