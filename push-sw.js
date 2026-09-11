/* Abdu Market admin push service worker */
self.addEventListener('push', function (event) {
    var data = { title: 'Abdu Market', body: '', url: '/admin/index.php', tag: 'abdu-admin' };
    try {
        if (event.data) {
            data = Object.assign(data, event.data.json());
        }
    } catch (err) {
        // ignore malformed payloads
    }

    event.waitUntil(
        self.registration.showNotification(data.title || 'Abdu Market', {
            body: data.body || '',
            icon: '/assets/images/abdu-market-logo.png',
            badge: '/assets/images/abdu-market-logo.png',
            tag: data.tag || 'abdu-admin',
            data: { url: data.url || '/admin/index.php' },
            renotify: true,
        })
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var target = (event.notification.data && event.notification.data.url) || '/admin/index.php';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if (client.url.indexOf('/admin/') !== -1 && 'focus' in client) {
                    client.navigate(target);
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(target);
            }
        })
    );
});
