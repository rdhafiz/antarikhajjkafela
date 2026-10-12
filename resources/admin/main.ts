import './admin.css';

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from '@admin/App.vue';
import { router } from '@admin/router';
import { setUnauthenticatedHandler } from '@admin/lib/http';
import { useAuthStore } from '@admin/stores/auth';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

// When the session expires mid-use, send the admin back to the login page.
setUnauthenticatedHandler(() => {
    const auth = useAuthStore(pinia);

    if (auth.isAuthenticated) {
        auth.clear();
        router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } });
    }
});

/*
 * After a new release the old code files are gone. A tab opened before it would
 * fail to load the next screen and stay blank, so reload once to get the new
 * files (guarded, so a real outage cannot cause a reload loop).
 */
function reloadForNewVersion(path?: string): void {
    const key = 'admin-reloaded-at';
    const last = Number(sessionStorage.getItem(key) ?? 0);

    if (Date.now() - last > 10_000) {
        sessionStorage.setItem(key, String(Date.now()));
        path ? window.location.assign(router.resolve(path).href) : window.location.reload();
    }
}

window.addEventListener('vite:preloadError', (event) => {
    event.preventDefault();
    reloadForNewVersion();
});

router.onError((error, to) => {
    if (/dynamically imported module|Importing a module script failed|error loading dynamically imported/i.test(String(error?.message))) {
        reloadForNewVersion(to.fullPath);
    }
});

app.mount('#admin');
