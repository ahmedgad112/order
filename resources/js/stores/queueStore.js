import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { axios } from '../bootstrap';

export const useQueueStore = defineStore('queue', () => {
    const serving = ref([]);
    const waiting = ref([]);
    const stats = ref({ waiting: 0, serving: 0, completed: 0 });
    const currentTicket = ref(null);
    const absentTickets = ref([]);
    const tellerTickets = ref([]);
    const tellerTicketStats = ref({
        total: 0,
        waiting: 0,
        serving: 0,
        entered: 0,
        file_delivered: 0,
        completed: 0,
        cancelled: 0,
        absent: 0,
    });
    const metrics = ref(null);
    const tellerPerformance = ref([]);
    const tellers = ref([]);
    const users = ref([]);
    const registrations = ref([]);
    const registrationStats = ref({
        total: 0,
        waiting: 0,
        serving: 0,
        entered: 0,
        file_delivered: 0,
        completed: 0,
        cancelled: 0,
        absent: 0,
    });
    const system = ref({ is_open: true, closed_message: null });
    const loading = ref(false);
    const error = ref(null);

    let echoBound = false;
    let echoSubscribers = 0;
    let refreshDebounceTimer = null;
    const extraHandlers = {
        TicketIssued: new Set(),
        TicketCalled: new Set(),
        TicketCompleted: new Set(),
        TicketAbsent: new Set(),
        TicketRestored: new Set(),
        QueueSystemUpdated: new Set(),
        QueueDayReset: new Set(),
    };

    const hasWaiting = computed(() => stats.value.waiting > 0);
    const hasCurrentTicket = computed(() => Boolean(currentTicket.value));
    const isSystemOpen = computed(() => system.value.is_open !== false);

    function applyQueuePayload(data) {
        serving.value = data.serving ?? [];
        waiting.value = data.waiting ?? [];
        stats.value = data.stats ?? { waiting: 0, serving: 0, completed: 0 };
        if (data.system) {
            system.value = data.system;
        }
    }

    async function fetchPublicStatus(options = {}) {
        if (!options.silent) {
            loading.value = true;
        }
        error.value = null;

        try {
            const { data } = await axios.get('/public/queue-status');
            applyQueuePayload(data);
        } catch (err) {
            error.value = err.response?.data?.message ?? 'تعذر تحميل حالة الطابور.';
            throw err;
        } finally {
            if (!options.silent) {
                loading.value = false;
            }
        }
    }

    async function trackTicket(payload) {
        loading.value = true;
        error.value = null;

        try {
            const { data } = await axios.post('/public/tickets/track', payload);
            if (data.system) {
                system.value = data.system;
            }
            return data.ticket;
        } catch (err) {
            const validation = err.response?.data?.errors;
            error.value = validation
                ? Object.values(validation).flat()[0]
                : err.response?.data?.message ?? 'تعذر العثور على التذكرة.';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function fetchScannedTicket(token, options = {}) {
        if (!options.silent) {
            loading.value = true;
        }
        error.value = null;

        try {
            const { data } = await axios.get(`/public/tickets/${token}`);
            if (data.system) {
                system.value = data.system;
            }
            return data.ticket;
        } catch (err) {
            error.value = err.response?.status === 404
                ? 'لم يتم العثور على التذكرة.'
                : err.response?.data?.message ?? 'تعذر تحميل بيانات التذكرة.';
            throw err;
        } finally {
            if (!options.silent) {
                loading.value = false;
            }
        }
    }

    async function issueTicket(payload) {
        loading.value = true;
        error.value = null;

        try {
            const { data } = await axios.post('/public/tickets', payload);
            await fetchPublicStatus();
            return data.ticket;
        } catch (err) {
            const validation = err.response?.data?.errors;
            error.value = validation
                ? Object.values(validation).flat()[0]
                : err.response?.data?.message ?? 'تعذر إصدار التذكرة.';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function fetchTellerStatus() {
        const { data } = await axios.get('/teller/queue-status');
        applyQueuePayload(data);
    }

    async function fetchCurrentTicket() {
        const { data } = await axios.get('/teller/current-ticket');
        currentTicket.value = data.ticket;
    }

    async function callNext() {
        const { data } = await axios.post('/teller/call-next');
        currentTicket.value = data.ticket;
        await fetchTellerStatus();
        return data.ticket;
    }

    async function completeTicket(ticketId) {
        const { data } = await axios.post(`/teller/tickets/${ticketId}/complete`);
        currentTicket.value = null;
        await fetchTellerStatus();
        return data.ticket;
    }

    async function cancelTicket(ticketId) {
        const { data } = await axios.post(`/teller/tickets/${ticketId}/cancel`);
        currentTicket.value = null;
        await fetchTellerStatus();
        return data.ticket;
    }

    async function recallTicket(ticketId) {
        const { data } = await axios.post(`/teller/tickets/${ticketId}/recall`);
        currentTicket.value = data.ticket;
        return data.ticket;
    }

    async function fetchAbsentTickets() {
        const { data } = await axios.get('/teller/absent-tickets');
        absentTickets.value = data.tickets;
        return data.tickets;
    }

    let lastTellerTicketParams = {};

    async function fetchTellerTickets(params) {
        if (params !== undefined) {
            lastTellerTicketParams = params;
        }

        const { data } = await axios.get('/teller/tickets', { params: lastTellerTicketParams });
        tellerTickets.value = data.tickets;
        tellerTicketStats.value = data.stats;
        if (data.system) {
            system.value = data.system;
        }
        return data;
    }

    function upsertTellerTicket(ticket) {
        if (!ticket?.id) {
            return;
        }

        const index = tellerTickets.value.findIndex((item) => item.id === ticket.id);
        if (index >= 0) {
            tellerTickets.value[index] = ticket;
        } else {
            tellerTickets.value = [...tellerTickets.value, ticket].sort(
                (a, b) => a.ticket_number - b.ticket_number,
            );
        }
    }

    async function markTellerEntered(ticketId) {
        const { data } = await axios.post(`/teller/tickets/${ticketId}/mark-entered`);
        upsertTellerTicket(data.ticket);
        await Promise.all([fetchTellerTickets(), fetchTellerStatus(), fetchCurrentTicket()]);
        return data;
    }

    async function markFileDelivered(ticketId) {
        const { data } = await axios.post(`/teller/tickets/${ticketId}/mark-file-delivered`);
        upsertTellerTicket(data.ticket);
        await Promise.all([fetchTellerTickets(), fetchTellerStatus(), fetchCurrentTicket()]);
        return data;
    }

    async function markAbsent(ticketId) {
        const { data } = await axios.post(`/teller/tickets/${ticketId}/mark-absent`);
        if (currentTicket.value?.id === ticketId) {
            currentTicket.value = null;
        }
        await Promise.all([fetchTellerTickets(), fetchTellerStatus(), fetchAbsentTickets(), fetchCurrentTicket()]);
        return data;
    }

    async function restoreTicket(ticketId) {
        const { data } = await axios.post(`/teller/tickets/${ticketId}/restore`);
        await Promise.all([fetchTellerTickets(), fetchTellerStatus(), fetchAbsentTickets()]);
        return data;
    }

    async function fetchDailyMetrics() {
        const { data } = await axios.get('/admin/reports/daily');
        metrics.value = data.metrics;
        return data.metrics;
    }

    async function fetchTellerPerformance() {
        const { data } = await axios.get('/admin/reports/teller-performance');
        tellerPerformance.value = data.tellers;
        return data.tellers;
    }

    async function fetchTellers() {
        const { data } = await axios.get('/admin/tellers');
        tellers.value = data.tellers;
        return data.tellers;
    }

    async function fetchUsers() {
        const { data } = await axios.get('/admin/users');
        users.value = data.users;
        return data.users;
    }

    async function createUser(payload) {
        const { data } = await axios.post('/admin/users', payload);
        await fetchUsers();
        await fetchTellers();
        await fetchTellerPerformance();
        return data;
    }

    async function updateUser(userId, payload) {
        const { data } = await axios.put(`/admin/users/${userId}`, payload);
        await fetchUsers();
        await fetchTellers();
        await fetchTellerPerformance();
        return data;
    }

    async function deactivateUser(userId) {
        const { data } = await axios.delete(`/admin/users/${userId}`);
        await fetchUsers();
        await fetchTellers();
        await fetchTellerPerformance();
        return data;
    }

    let lastRegistrationParams = {};

    async function fetchRegistrations(params) {
        if (params !== undefined) {
            lastRegistrationParams = params;
        }

        const { data } = await axios.get('/admin/tickets', { params: lastRegistrationParams });
        registrations.value = data.tickets;
        registrationStats.value = data.stats;
        return data;
    }

    async function markTicketEntered(ticketId) {
        const { data } = await axios.post(`/admin/tickets/${ticketId}/mark-entered`);
        const index = registrations.value.findIndex((t) => t.id === ticketId);
        if (index >= 0) {
            registrations.value[index] = data.ticket;
        }
        await fetchRegistrations();
        return data;
    }

    async function fetchSystemStatus() {
        const { data } = await axios.get('/admin/system/status');
        system.value = data.system;
        return data.system;
    }

    async function closeSystem(message = null) {
        const { data } = await axios.post('/admin/system/close', { message });
        system.value = data.system;
        return data;
    }

    async function openSystem() {
        const { data } = await axios.post('/admin/system/open');
        system.value = data.system;
        return data;
    }

    async function resetDay() {
        const { data } = await axios.post('/admin/system/reset-day');
        system.value = data.system;
        serving.value = [];
        waiting.value = [];
        stats.value = { waiting: 0, serving: 0, completed: 0 };
        currentTicket.value = null;
        absentTickets.value = [];
        tellerTickets.value = [];
        tellerTicketStats.value = {
            total: 0,
            waiting: 0,
            serving: 0,
            entered: 0,
            file_delivered: 0,
            completed: 0,
            cancelled: 0,
            absent: 0,
        };
        return data;
    }

    function scheduleDataRefresh() {
        clearTimeout(refreshDebounceTimer);
        refreshDebounceTimer = setTimeout(async () => {
            const { useAuthStore } = await import('./authStore');
            const auth = useAuthStore();

            const tasks = [];

            if (auth.isAdmin) {
                tasks.push(
                    fetchDailyMetrics().catch(() => {}),
                    fetchTellerPerformance().catch(() => {}),
                );
            }

            if (auth.isTeller) {
                tasks.push(
                    fetchCurrentTicket().catch(() => {}),
                    fetchAbsentTickets().catch(() => {}),
                    fetchTellerTickets().catch(() => {}),
                );
            }

            if (tasks.length) {
                await Promise.all(tasks);
            }
        }, 400);
    }

    function handleSystemUpdated(event) {
        if (event.system) {
            system.value = event.system;
        }
    }

    function handleDayReset() {
        serving.value = [];
        waiting.value = [];
        stats.value = { waiting: 0, serving: 0, completed: 0 };
        currentTicket.value = null;
        absentTickets.value = [];
        tellerTickets.value = [];
        tellerTicketStats.value = {
            total: 0,
            waiting: 0,
            serving: 0,
            entered: 0,
            file_delivered: 0,
            completed: 0,
            cancelled: 0,
            absent: 0,
        };
        scheduleDataRefresh();
    }

    function upsertAbsentTicket(ticket) {
        const detail = ticket.ticket_detail ?? ticket;
        if (!detail?.id) {
            return;
        }

        const index = absentTickets.value.findIndex((item) => item.id === detail.id);
        if (index >= 0) {
            absentTickets.value[index] = detail;
        } else {
            absentTickets.value = [detail, ...absentTickets.value];
        }
    }

    function removeAbsentTicket(ticketId) {
        absentTickets.value = absentTickets.value.filter((item) => item.id !== ticketId);
    }

    function handleTicketAbsent(event) {
        const ticket = event.ticket;
        serving.value = serving.value.filter((item) => item.id !== ticket.id);
        stats.value.serving = serving.value.length;
        if (currentTicket.value?.id === ticket.id) {
            currentTicket.value = null;
        }
        upsertAbsentTicket(event);
        scheduleDataRefresh();
    }

    function handleTicketRestored(event) {
        const ticket = event.ticket;
        removeAbsentTicket(ticket.id);
        waiting.value = [...waiting.value, ticket]
            .sort((a, b) => a.ticket_number - b.ticket_number)
            .slice(0, 10);
        stats.value.waiting += 1;
        scheduleDataRefresh();
    }

    function upsertServingTicket(ticket) {
        const index = serving.value.findIndex((item) => item.id === ticket.id);
        if (index >= 0) {
            serving.value[index] = ticket;
        } else {
            serving.value.push(ticket);
        }
        serving.value = serving.value.filter((item) => item.status === 'serving');
    }

    function removeFromWaiting(ticketId) {
        waiting.value = waiting.value.filter((item) => item.id !== ticketId);
    }

    function handleTicketIssued(event) {
        const ticket = event.ticket;
        waiting.value = [...waiting.value, ticket]
            .sort((a, b) => a.ticket_number - b.ticket_number)
            .slice(0, 10);
        stats.value.waiting += 1;
        scheduleDataRefresh();
    }

    function handleTicketCalled(event) {
        const ticket = event.ticket;
        removeFromWaiting(ticket.id);
        upsertServingTicket(ticket);
        stats.value.waiting = Math.max(0, stats.value.waiting - 1);
        stats.value.serving = serving.value.length;
        scheduleDataRefresh();
    }

    function handleTicketCompleted(event) {
        const ticket = event.ticket;
        serving.value = serving.value.filter((item) => item.id !== ticket.id);
        removeFromWaiting(ticket.id);
        stats.value.serving = serving.value.length;
        if (ticket.status === 'completed') {
            stats.value.completed += 1;
        }
        if (currentTicket.value?.id === ticket.id) {
            currentTicket.value = null;
        }
        scheduleDataRefresh();
    }

    function runExtras(eventName, payload) {
        extraHandlers[eventName].forEach((handler) => {
            try {
                handler(payload);
            } catch {
                // ignore listener errors
            }
        });
    }

    function attachEchoListeners() {
        if (echoBound || !window.Echo) {
            return;
        }

        window.Echo.channel('queue-channel')
            .listen('.TicketIssued', (event) => {
                handleTicketIssued(event);
                runExtras('TicketIssued', event);
            })
            .listen('.TicketCalled', (event) => {
                handleTicketCalled(event);
                runExtras('TicketCalled', event);
            })
            .listen('.TicketCompleted', (event) => {
                handleTicketCompleted(event);
                runExtras('TicketCompleted', event);
            })
            .listen('.TicketAbsent', (event) => {
                handleTicketAbsent(event);
                runExtras('TicketAbsent', event);
            })
            .listen('.TicketRestored', (event) => {
                handleTicketRestored(event);
                runExtras('TicketRestored', event);
            })
            .listen('.QueueSystemUpdated', (event) => {
                handleSystemUpdated(event);
                runExtras('QueueSystemUpdated', event);
            })
            .listen('.QueueDayReset', (event) => {
                handleDayReset(event);
                runExtras('QueueDayReset', event);
            });

        echoBound = true;
    }

    function detachEchoListeners() {
        if (!echoBound || !window.Echo) {
            return;
        }

        window.Echo.leave('queue-channel');
        echoBound = false;
    }

    /**
     * Subscribe to the shared queue channel. Returns an unsubscribe function.
     * @param {Partial<Record<'TicketIssued'|'TicketCalled'|'TicketCompleted'|'QueueSystemUpdated'|'QueueDayReset', Function>>} handlers
     */
    function subscribeEcho(handlers = {}) {
        Object.entries(handlers).forEach(([eventName, handler]) => {
            if (handler && extraHandlers[eventName]) {
                extraHandlers[eventName].add(handler);
            }
        });

        echoSubscribers += 1;
        attachEchoListeners();

        let active = true;

        return () => {
            if (!active) {
                return;
            }
            active = false;

            Object.entries(handlers).forEach(([eventName, handler]) => {
                if (handler && extraHandlers[eventName]) {
                    extraHandlers[eventName].delete(handler);
                }
            });

            echoSubscribers = Math.max(0, echoSubscribers - 1);
            if (echoSubscribers === 0) {
                detachEchoListeners();
            }
        };
    }

    let legacyUnsub = null;

    /** Prefer subscribeEcho() when you need custom handlers. */
    function bindEcho() {
        if (!legacyUnsub) {
            legacyUnsub = subscribeEcho();
        }
    }

    function unbindEcho() {
        if (legacyUnsub) {
            legacyUnsub();
            legacyUnsub = null;
        }
    }

    function startAutoRefresh(callback, intervalMs = 4000) {
        let inFlight = false;

        const tick = async () => {
            if (inFlight || document.hidden) {
                return;
            }

            inFlight = true;
            try {
                await callback();
            } catch {
                // ignore polling errors
            } finally {
                inFlight = false;
            }
        };

        const timer = setInterval(tick, intervalMs);

        return () => clearInterval(timer);
    }

    return {
        serving,
        waiting,
        stats,
        currentTicket,
        absentTickets,
        tellerTickets,
        tellerTicketStats,
        metrics,
        tellerPerformance,
        tellers,
        users,
        registrations,
        registrationStats,
        system,
        loading,
        error,
        hasWaiting,
        hasCurrentTicket,
        isSystemOpen,
        fetchPublicStatus,
        trackTicket,
        fetchScannedTicket,
        issueTicket,
        fetchTellerStatus,
        fetchCurrentTicket,
        callNext,
        completeTicket,
        cancelTicket,
        recallTicket,
        fetchAbsentTickets,
        fetchTellerTickets,
        markTellerEntered,
        markFileDelivered,
        markAbsent,
        restoreTicket,
        fetchDailyMetrics,
        fetchTellerPerformance,
        fetchTellers,
        fetchUsers,
        createUser,
        updateUser,
        deactivateUser,
        fetchRegistrations,
        markTicketEntered,
        fetchSystemStatus,
        closeSystem,
        openSystem,
        resetDay,
        handleSystemUpdated,
        handleDayReset,
        handleTicketIssued,
        handleTicketCalled,
        handleTicketCompleted,
        handleTicketAbsent,
        handleTicketRestored,
        subscribeEcho,
        bindEcho,
        unbindEcho,
        startAutoRefresh,
    };
});
