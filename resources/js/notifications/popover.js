/**
 * TopBar通知ベルのポップオーバー。開閉 / タブ切替 / 一覧取得 / 既読化 / バッジ更新を担う。
 * 対象の data-* 契約は resources/views/notifications/_partials/notification-popover.blade.php を参照。
 */

import { getJson, postJson } from '../utils/fetch-json';

export function initNotificationPopover() {
    const root = document.querySelector('[data-notification-popover-root]');
    if (!root) return;

    const trigger = root.querySelector('[data-notification-popover-trigger]');
    const panel = root.querySelector('[data-notification-popover-panel]');
    const badge = root.querySelector('[data-notification-popover-badge]');
    const unreadCountEl = root.querySelector('[data-notification-popover-unread-count]');
    const tabs = root.querySelectorAll('[data-notification-popover-tab]');
    const markAllBtn = root.querySelector('[data-notification-popover-mark-all]');
    const loadingEl = root.querySelector('[data-notification-popover-loading]');
    const emptyEl = root.querySelector('[data-notification-popover-empty]');
    const itemsEl = root.querySelector('[data-notification-popover-items]');
    const rowTemplate = root.querySelector('[data-notification-popover-row-template]');

    if (!trigger || !panel || !itemsEl || !rowTemplate) return;

    let notifications = [];
    let loaded = false;
    let currentTab = 'all';

    function formatCount(count) {
        return count > 99 ? '99+' : String(count);
    }

    function updateBadge(unreadCount) {
        if (badge) {
            badge.textContent = formatCount(unreadCount);
            badge.classList.toggle('hidden', unreadCount <= 0);
        }
        if (unreadCountEl) {
            unreadCountEl.textContent = formatCount(unreadCount);
        }
        trigger.setAttribute('aria-label', `通知 (${unreadCount} 件未読)`);
    }

    function render() {
        itemsEl.innerHTML = '';
        const list = currentTab === 'unread' ? notifications.filter((n) => !n.is_read) : notifications;

        emptyEl?.classList.toggle('hidden', list.length > 0);

        list.forEach((item) => {
            const fragment = rowTemplate.content.cloneNode(true);
            const link = fragment.querySelector('[data-notification-popover-row]');
            const dot = fragment.querySelector('[data-notification-popover-row-dot]');
            fragment.querySelector('[data-notification-popover-row-title]').textContent = item.title;
            fragment.querySelector('[data-notification-popover-row-message]').textContent = item.message;
            fragment.querySelector('[data-notification-popover-row-time]').textContent = item.time;

            if (link) {
                link.href = item.url ?? '/notifications';
                if (item.is_read) {
                    link.removeAttribute('aria-data-unread');
                } else {
                    link.setAttribute('aria-data-unread', 'true');
                }
                link.addEventListener('click', (event) => handleRowClick(event, item));
            }
            dot?.classList.toggle('hidden', item.is_read);

            itemsEl.appendChild(fragment);
        });
    }

    async function handleRowClick(event, item) {
        event.preventDefault();
        if (!item.is_read) {
            try {
                const result = await postJson(`/api/v1/notifications/${item.id}/read`);
                item.is_read = true;
                updateBadge(result.unread_count);
            } catch (error) {
                // 既読化に失敗しても画面遷移は継続する
            }
        }
        window.location.href = item.url ?? '/notifications';
    }

    async function load() {
        loadingEl?.classList.remove('hidden');
        try {
            const result = await getJson('/api/v1/notifications');
            notifications = result.notifications;
            updateBadge(result.unread_count);
            loaded = true;
        } finally {
            loadingEl?.classList.add('hidden');
        }
        render();
    }

    function isOpen() {
        return trigger.getAttribute('aria-expanded') === 'true';
    }

    function openPanel() {
        panel.style.display = 'flex';
        panel.classList.remove('hidden');
        requestAnimationFrame(() => {
            panel.classList.remove('opacity-0', '-translate-y-1');
        });
        trigger.setAttribute('aria-expanded', 'true');
        if (!loaded) {
            load();
        }
    }

    function closePanel() {
        panel.classList.add('opacity-0', '-translate-y-1');
        panel.classList.add('hidden');
        panel.style.display = 'none';
        trigger.setAttribute('aria-expanded', 'false');
    }

    trigger.addEventListener('click', (event) => {
        event.stopPropagation();
        isOpen() ? closePanel() : openPanel();
    });

    document.addEventListener('click', (event) => {
        if (isOpen() && !root.contains(event.target)) {
            closePanel();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen()) {
            closePanel();
            trigger.focus();
        }
    });

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            currentTab = tab.dataset.notificationPopoverTab;
            tabs.forEach((t) => t.setAttribute('aria-selected', t === tab ? 'true' : 'false'));
            render();
        });
    });

    markAllBtn?.addEventListener('click', async () => {
        try {
            const result = await postJson('/api/v1/notifications/read-all');
            notifications = notifications.map((n) => ({ ...n, is_read: true }));
            updateBadge(result.unread_count);
            render();
        } catch (error) {
            // no-op: 失敗時は状態を変更しない
        }
    });
}
