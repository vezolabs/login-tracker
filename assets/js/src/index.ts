import { LoginTrackerMainPage } from './pages/LoginTrackerMainPage';

export const PimcoreLoginTrackerPlugin = {
  name: 'PimcoreLoginTrackerBundle',
  priority: 10,

  onInit: ({ container }: { container: any }) => {
    try {
      // 1. Register Widget in Studio Widget Registry
      const widgetRegistry =
        container.get('widgetRegistry') ||
        container.get('App/WidgetManager/WidgetRegistry');

      if (widgetRegistry?.registerWidget) {
        widgetRegistry.registerWidget({
          name: 'login-tracker',
          component: LoginTrackerMainPage,
        });
      }

      // 2. Register Navigation Entry: System -> Login Tracker
      const mainNavRegistry =
        container.get('mainNavRegistry') ||
        container.get('App/MainNavRegistry/MainNavRegistry');

      if (mainNavRegistry?.registerMainNavItem) {
        mainNavRegistry.registerMainNavItem({
          path: 'System/Login Tracker',
          label: 'Login Tracker',
          order: 1060,
          permission: 'plugin_login_tracker_view',
          widgetConfig: {
            name: 'login-tracker',
            id: 'login-tracker',
            component: 'login-tracker',
            config: {
              translationKey: 'Login Tracker',
              icon: {
                type: 'name',
                value: 'safety-certificate',
              },
            },
          },
        });
      }
    } catch (e) {
      console.error('[LoginTracker] Plugin registration error:', e);
    }
  },

  onStartup: () => {
    console.log('[LoginTracker] Pimcore Login Tracker plugin initialized.');
  },
};
