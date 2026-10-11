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

app.mount('#admin');
