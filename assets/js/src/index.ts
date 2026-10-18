import { LoginTrackerMainPage } from './pages/LoginTrackerMainPage';

function resolveService(container: any, ids: string[]) {
  if (!container) return null;
  for (const id of ids) {
    try {
      if (typeof container.isBound === 'function' && container.isBound(id)) {
        return container.get(id);
      }
    } catch (_) {}
    try {
      const svc = container.get(id);
      if (svc) return svc;
    } catch (_) {}
  }
  return null;
}

let registered = false;

function doRegister(container: any) {
  if (registered || !container) return;
  registered = true;

  try {
    // 1. Register Widget in Studio Widget Registry
    const widgetRegistry = resolveService(container, [
      'WidgetManagerService',
      'widgetManager',
      'App/WidgetManager/WidgetRegistry',
      'widgetRegistry',
    ]);

    if (widgetRegistry && typeof widgetRegistry.registerWidget === 'function') {
      widgetRegistry.registerWidget({
        name: 'login-tracker',
        component: LoginTrackerMainPage,
      });
    }

    // 2. Register Navigation Entry: System -> Login Tracker
    const mainNavRegistry = resolveService(container, [
      'MainNavRegistry',
      'mainNavRegistry',
      'App/MainNavRegistry/MainNavRegistry',
    ]);

    if (mainNavRegistry && typeof mainNavRegistry.registerMainNavItem === 'function') {
      // Primary entry under System
      mainNavRegistry.registerMainNavItem({
        path: 'System/Login Tracker',
        label: 'Login Tracker',
        order: 1040,
        icon: 'security',
        permission: 'plugin_login_tracker_view',
        widgetConfig: {
          name: 'login-tracker',
          id: 'login-tracker',
          component: 'login-tracker',
          config: {
            title: 'Login Tracker',
            translationKey: 'Login Tracker',
            icon: {
              type: 'name',
              value: 'security',
            },
          },
        },
      });

      // Entry under Settings
      mainNavRegistry.registerMainNavItem({
        path: 'Settings/Login Tracker',
        label: 'Login Tracker',
        order: 1040,
        icon: 'security',
        permission: 'plugin_login_tracker_view',
        widgetConfig: {
          name: 'login-tracker',
          id: 'login-tracker',
          component: 'login-tracker',
          config: {
            title: 'Login Tracker',
            translationKey: 'Login Tracker',
            icon: {
              type: 'name',
              value: 'security',
            },
          },
        },
      });
    }
  } catch (e) {
    console.error('[LoginTracker] Plugin registration error:', e);
  }
}

export const PimcoreLoginTrackerPlugin = {
  name: 'PimcoreLoginTrackerBundle',
  priority: 10,

  onInit: ({ container }: { container: any }) => {
    doRegister(container);
  },

  onStartup: () => {
    const globalContainer = (window as any).Pimcore?.container;
    if (globalContainer) {
      doRegister(globalContainer);
    }
  },
};

export default PimcoreLoginTrackerPlugin;
