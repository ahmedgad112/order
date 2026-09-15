import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { axios } from '../bootstrap';

export const useQueueStore = defineStore('queue', () => {
    const serving = ref([]);
    const waiting = ref([]);
    const stats = ref({ waiting: 0, serving: 0, completed: 0 });

    function compareTickets(a, b) {
        return (a.id ?? 0) - (b.id ?? 0);
    }
    const currentTicket = ref(null);
    const absentTickets = ref([]);
    const tellerTickets = ref([]);
    const tellerTicketStats = ref({
        total: 0,
        waiting: 0,
        serving: 0,
        entered: 0,
        paid: 0,
        file_withdrawn: 0,
        documents_reviewed: 0,
        medical_checked: 0,
        face_printed: 0,
        file_delivered: 0,
        completed: 0,
        cancelled: 0,
        absent: 0,
    });
    const metrics = ref(null);
    const tellerPerformance = ref([]);
    const tellers = ref([]);
    const queueLanes = ref([]);
    const users = ref([]);
    const registrations = ref([]);
    const registrationDate = ref('');
    const registrationToday = ref('');
    const registrationIsToday = ref(true);
    const registrationDates = ref([]);
    const registrationStats = ref({
        total: 0,
        waiting: 0,
        serving: 0,
        entered: 0,
        paid: 0,
        file_withdrawn: 0,
        documents_reviewed: 0,
        medical_checked: 0,
        face_printed: 0,
        file_delivered: 0,
        completed: 0,
        cancelled: 0,
        absent: 0,
    });
    const catalogFaculties = ref([]);
    const catalogColleges = ref([]);
    const adminRequestTypes = ref([]);
    const system = ref({
        is_open: true,
        is_day_open: true,
        accepting_tickets: true,
        closed_message: null,
        day_ended_message: null,
        request_types: [],
        student_kinds: [],
        colleges: [],
        faculties: [],
        document_kinds: [],
    });
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
        TicketDeleted: new Set(),
        TicketUpdated: new Set(),
        QueueSystemUpdated: new Set(),
        QueueDayReset: new Set(),
        AnnouncementMade: new Set(),
        MicAudioChunk: new Set(),
    };

    const hasWaiting = computed(() => stats.value.waiting > 0);
    const hasCurrentTicket = computed(() => Boolean(currentTicket.value));
    const isSystemOpen = computed(() => system.value.is_open !== false);
    const isDayOpen = computed(() => system.value.is_day_open !== false);
    const isAcceptingTickets = computed(() => system.value.accepting_tickets !== false);
    const isNewStudentOpen = computed(() => isStudentKindEnabled('new_student'));
    const isCurrentStudentOpen = computed(() => isStudentKindEnabled('current_student'));

    function isStudentKindEnabled(kind) {
        const kinds = system.value.student_kinds;

        if (!Array.isArray(kinds) || kinds.length === 0) {
            return true;
        }

        const match = kinds.find((item) => item.value === kind);

        return match ? match.enabled !== false : true;
    }

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
            const { data } = await axios.get(`/teller/tickets/scan/${token}`);
            if (data.system) {
                system.value = data.system;
            }
            return data.ticket;
        } catch (err) {
            error.value = err.response?.status === 401
                ? 'يجب تسجيل الدخول لعرض بيانات التذكرة.'
                : err.response?.status === 404
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
            const { data } = await axios.post('/teller/tickets', payload);
            await fetchTellerTickets();
            return data;
        } catch (err) {
            const validation = err.response?.data?.errors;
            error.value = validation
                ? Object.values(validation).flat()[0]
                : err.response?.data?.message ?? 'تعذر إصدار الدور.';
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
        await fetchTellerTickets();
        return data.ticket;
    }

    async function callTicket(ticketId) {
        const { data } = await axios.post(`/teller/tickets/${ticketId}/call`);
        currentTicket.value = data.ticket;
        await fetchTellerTickets();
        return data.ticket;
    }

    async function skipTicket(ticketId) {
        const { data } = await axios.post(`/teller/tickets/${ticketId}/skip`);
        await fetchTellerTickets();
        return data;
    }

    async function completeTicket(ticketId) {
        const data = await markProcessStep(ticketId, 'completed');
        currentTicket.value = null;
        return data.ticket;
    }

    async function cancelTicket(ticketId) {
        const { data } = await axios.post(`/teller/tickets/${ticketId}/cancel`);
        currentTicket.value = null;
        await fetchTellerTickets();
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
        if (data.serving) {
            serving.value = data.serving;
        }
        if (data.absent_tickets) {
            absentTickets.value = data.absent_tickets;
        }
        if (Object.hasOwn(data, 'current_ticket')) {
            currentTicket.value = data.current_ticket;
        }
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
            tellerTickets.value = [...tellerTickets.value, ticket].sort(compareTickets);
        }
    }

    async function markProcessStep(ticketId, step) {
        const paths = {
            entered: `/teller/tickets/${ticketId}/mark-entered`,
            paid: `/teller/tickets/${ticketId}/mark-paid`,
            file_withdrawn: `/teller/tickets/${ticketId}/mark-file-withdrawn`,
            documents_reviewed: `/teller/tickets/${ticketId}/mark-documents-reviewed`,
            medical_checked: `/teller/tickets/${ticketId}/mark-medical-checked`,
            face_printed: `/teller/tickets/${ticketId}/mark-face-printed`,
            file_delivered: `/teller/tickets/${ticketId}/mark-file-delivered`,
            completed: `/teller/tickets/${ticketId}/complete`,
        };

        const { data } = await axios.post(paths[step]);
        upsertTellerTicket(data.ticket);
        if (step === 'completed' && currentTicket.value?.id === ticketId) {
            currentTicket.value = null;
        }
        await fetchTellerTickets();
        return data;
    }

    async function markTellerEntered(ticketId) {
        return markProcessStep(ticketId, 'entered');
    }

    async function markFileDelivered(ticketId) {
        return markProcessStep(ticketId, 'file_delivered');
    }

    async function markAbsent(ticketId) {
        const { data } = await axios.post(`/teller/tickets/${ticketId}/mark-absent`);
        if (currentTicket.value?.id === ticketId) {
            currentTicket.value = null;
        }
        await fetchTellerTickets();
        return data;
    }

    async function restoreTicket(ticketId) {
        const { data } = await axios.post(`/teller/tickets/${ticketId}/restore`);
        await fetchTellerTickets();
        return data;
    }

    async function fetchAdminDashboard() {
        const { data } = await axios.get('/admin/dashboard');
        metrics.value = data.metrics;
        tellerPerformance.value = data.teller_performance ?? [];
        tellers.value = data.tellers ?? data.teller_performance ?? [];
        queueLanes.value = data.queue_lanes ?? [];
        catalogFaculties.value = data.faculties ?? catalogFaculties.value;
        catalogColleges.value = data.colleges ?? catalogColleges.value;
        if (data.system) {
            system.value = data.system;
        }
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
        if (data.queue_lanes?.length) {
            queueLanes.value = data.queue_lanes;
        }
        return data.users;
    }

    async function createUser(payload) {
        const { data } = await axios.post('/admin/users', payload);
        await Promise.all([fetchUsers(), fetchAdminDashboard()]);
        return data;
    }

    async function updateUser(userId, payload) {
        const { data } = await axios.put(`/admin/users/${userId}`, payload);
        await Promise.all([fetchUsers(), fetchAdminDashboard()]);
        return data;
    }

    async function deactivateUser(userId) {
        const { data } = await axios.delete(`/admin/users/${userId}`);
        await Promise.all([fetchUsers(), fetchAdminDashboard()]);
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
        registrationDate.value = data.date ?? '';
        registrationToday.value = data.today ?? '';
        registrationIsToday.value = data.is_today !== false;
        registrationDates.value = data.available_dates ?? [];
        if (data.system) {
            system.value = data.system;
        }
        return data;
    }

    async function deleteTicket(ticketId) {
        const { data } = await axios.delete(`/admin/tickets/${ticketId}`);
        removeTicketFromLists(ticketId);
        return data;
    }

    async function updateTicket(ticketId, payload) {
        const { data } = await axios.put(`/admin/tickets/${ticketId}`, payload);
        if (data.ticket) {
            replaceTicketInLists(data.ticket);
        }
        return data;
    }

    async function markTicketEntered(ticketId) {
        return markProcessStep(ticketId, 'entered');
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

    async function endDay() {
        const { data } = await axios.post('/admin/system/end-day');
        system.value = data.system;
        return data;
    }

    async function openDay() {
        const { data } = await axios.post('/admin/system/open-day');
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
            paid: 0,
            file_withdrawn: 0,
            documents_reviewed: 0,
            medical_checked: 0,
            face_printed: 0,
            file_delivered: 0,
            completed: 0,
            cancelled: 0,
            absent: 0,
        };
        return data;
    }

    async function updateRequestTypes(enabledRequestTypes) {
        const { data } = await axios.put('/admin/system/request-types', {
            enabled_request_types: enabledRequestTypes,
        });
        system.value = data.system;
        return data;
    }

    function applyRequestTypeCatalog(data) {
        if (data.request_types) {
            adminRequestTypes.value = data.request_types;
        }

        if (data.system) {
            system.value = data.system;
        }
    }

    async function fetchRequestTypes() {
        const { data } = await axios.get('/admin/request-types');
        applyRequestTypeCatalog(data);
        return data;
    }

    async function createRequestType(payload) {
        const { data } = await axios.post('/admin/request-types', payload);
        applyRequestTypeCatalog(data);
        return data;
    }

    async function updateRequestType(requestTypeId, payload) {
        const { data } = await axios.put(`/admin/request-types/${requestTypeId}`, payload);
        applyRequestTypeCatalog(data);
        return data;
    }

    async function deleteRequestType(requestTypeId) {
        const { data } = await axios.delete(`/admin/request-types/${requestTypeId}`);
        applyRequestTypeCatalog(data);
        return data;
    }

    async function updateStudentKinds(enabledStudentKinds) {
        const { data } = await axios.put('/admin/system/student-kinds', {
            enabled_student_kinds: enabledStudentKinds,
        });
        system.value = data.system;
        return data;
    }

    async function updateQueueLaneTellers(lane, tellerIds) {
        const { data } = await axios.put(`/admin/queue-lanes/${lane}/tellers`, {
            teller_ids: tellerIds,
        });
        queueLanes.value = data.queue_lanes ?? [];
        return data;
    }

    function applyCatalog(data) {
        if (data.faculties) {
            catalogFaculties.value = data.faculties;
        }

        if (data.colleges) {
            catalogColleges.value = data.colleges;
        }

        if (data.system) {
            system.value = data.system;
        }
    }

    async function createFaculty(payload) {
        const { data } = await axios.post('/admin/faculties', payload);
        applyCatalog(data);
        return data;
    }

    async function updateFaculty(facultyId, payload) {
        const { data } = await axios.put(`/admin/faculties/${facultyId}`, payload);
        applyCatalog(data);
        return data;
    }

    async function deactivateFaculty(facultyId) {
        const { data } = await axios.delete(`/admin/faculties/${facultyId}`);
        applyCatalog(data);
        return data;
    }

    async function createCollege(payload) {
        const { data } = await axios.post('/admin/colleges', payload);
        applyCatalog(data);
        return data;
    }

    async function updateCollege(collegeId, payload) {
        const { data } = await axios.put(`/admin/colleges/${collegeId}`, payload);
        applyCatalog(data);
        return data;
    }

    async function deactivateCollege(collegeId) {
        const { data } = await axios.delete(`/admin/colleges/${collegeId}`);
        applyCatalog(data);
        return data;
    }

    async function requestTicketAudio(ticketId) {
        const { data } = await axios.post('/public/ticket-audio', { ticket_id: ticketId });
        return data.audio_url;
    }

    async function sendAnnouncement(payload) {
        const { data } = await axios.post('/admin/announce', payload);
        return data;
    }

    async function sendMicChunk(formData) {
        const { data } = await axios.post('/admin/mic-chunk', formData);
        return data;
    }

    function scheduleDataRefresh() {
        clearTimeout(refreshDebounceTimer);
        refreshDebounceTimer = setTimeout(async () => {
            const { useAuthStore } = await import('./authStore');
            const auth = useAuthStore();

            const tasks = [];

            if (auth.isAdmin) {
                tasks.push(fetchAdminDashboard().catch(() => {}));
            }

            if (auth.isTeller) {
                tasks.push(fetchTellerTickets().catch(() => {}));
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
            paid: 0,
            file_withdrawn: 0,
            documents_reviewed: 0,
            medical_checked: 0,
            face_printed: 0,
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
            .sort(compareTickets)
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

    function removeTicketFromLists(ticketId) {
        serving.value = serving.value.filter((item) => item.id !== ticketId);
        removeFromWaiting(ticketId);
        absentTickets.value = absentTickets.value.filter((item) => item.id !== ticketId);
        tellerTickets.value = tellerTickets.value.filter((item) => item.id !== ticketId);
        registrations.value = registrations.value.filter((item) => item.id !== ticketId);
        stats.value.serving = serving.value.length;
        stats.value.waiting = waiting.value.length;
        if (currentTicket.value?.id === ticketId) {
            currentTicket.value = null;
        }
    }

    function replaceTicketInLists(ticket) {
        if (!ticket?.id) {
            return;
        }

        const replaceIn = (list) => {
            const index = list.findIndex((item) => item.id === ticket.id);
            if (index >= 0) {
                list[index] = { ...list[index], ...ticket };
            }
        };

        replaceIn(tellerTickets.value);
        replaceIn(registrations.value);
        replaceIn(serving.value);
        replaceIn(waiting.value);
        replaceIn(absentTickets.value);

        if (currentTicket.value?.id === ticket.id) {
            currentTicket.value = { ...currentTicket.value, ...ticket };
        }
    }

    function handleTicketUpdated(event) {
        scheduleDataRefresh();
        if (event?.ticket) {
            replaceTicketInLists(event.ticket);
        }
    }

    function handleTicketDeleted(event) {
        const ticketId = event.ticket_id;
        if (!ticketId) {
            return;
        }

        removeTicketFromLists(ticketId);
        scheduleDataRefresh();
    }

    function handleTicketIssued(event) {
        const ticket = event.ticket;
        waiting.value = [...waiting.value, ticket]
            .sort(compareTickets)
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

    let echoBindPromise = null;

    async function attachEchoListeners() {
        if (echoBound) {
            return;
        }

        if (echoBindPromise) {
            await echoBindPromise;
            return;
        }

        echoBindPromise = (async () => {
            const { loadEcho } = await import('../echo');
            const echo = await loadEcho();

            if (!echo || echoBound) {
                return;
            }

            echo.channel('queue-channel')
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
                .listen('.TicketDeleted', (event) => {
                    handleTicketDeleted(event);
                    runExtras('TicketDeleted', event);
                })
                .listen('.TicketUpdated', (event) => {
                    handleTicketUpdated(event);
                    runExtras('TicketUpdated', event);
                })
                .listen('.QueueSystemUpdated', (event) => {
                    handleSystemUpdated(event);
                    runExtras('QueueSystemUpdated', event);
                })
                .listen('.QueueDayReset', (event) => {
                    handleDayReset(event);
                    runExtras('QueueDayReset', event);
                })
                .listen('.AnnouncementMade', (event) => {
                    runExtras('AnnouncementMade', event);
                })
                .listen('.MicAudioChunk', (event) => {
                    runExtras('MicAudioChunk', event);
                });

            echoBound = true;
            armLiveRefreshers();
        })();

        await echoBindPromise;
    }

    function detachEchoListeners() {
        if (!echoBound || !window.Echo) {
            echoBindPromise = null;
            return;
        }

        window.Echo.leave('queue-channel');
        echoBound = false;
        echoBindPromise = null;
        armLiveRefreshers();
    }

    /**
     * Subscribe to the shared queue channel. Returns an unsubscribe function.
     * @param {Partial<Record<'TicketIssued'|'TicketCalled'|'TicketCompleted'|'TicketAbsent'|'TicketRestored'|'TicketDeleted'|'TicketUpdated'|'QueueSystemUpdated'|'QueueDayReset', Function>>} handlers
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

    const liveRefreshers = new Set();

    function armLiveRefreshers() {
        liveRefreshers.forEach((controller) => controller.arm());
    }

    function startAutoRefresh(callback, intervalMs = 5000) {
        let inFlight = false;
        let timer = null;

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

        const controller = {
            arm() {
                clearInterval(timer);
                const ms = echoBound ? Math.max(intervalMs, 25000) : intervalMs;
                timer = setInterval(tick, ms);
            },
            stop() {
                clearInterval(timer);
                liveRefreshers.delete(controller);
            },
        };

        liveRefreshers.add(controller);
        controller.arm();

        return () => controller.stop();
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
        queueLanes,
        users,
        registrations,
        registrationDate,
        registrationToday,
        registrationIsToday,
        registrationDates,
        registrationStats,
        system,
        catalogFaculties,
        catalogColleges,
        adminRequestTypes,
        loading,
        error,
        hasWaiting,
        hasCurrentTicket,
        isSystemOpen,
        isDayOpen,
        isAcceptingTickets,
        isNewStudentOpen,
        isCurrentStudentOpen,
        fetchPublicStatus,
        trackTicket,
        fetchScannedTicket,
        issueTicket,
        fetchTellerStatus,
        fetchCurrentTicket,
        callNext,
        callTicket,
        skipTicket,
        completeTicket,
        cancelTicket,
        recallTicket,
        fetchAbsentTickets,
        fetchTellerTickets,
        markTellerEntered,
        markFileDelivered,
        markProcessStep,
        markAbsent,
        restoreTicket,
        fetchDailyMetrics,
        fetchAdminDashboard,
        fetchTellerPerformance,
        fetchTellers,
        fetchUsers,
        createUser,
        updateUser,
        deactivateUser,
        fetchRegistrations,
        deleteTicket,
        updateTicket,
        markTicketEntered,
        fetchSystemStatus,
        closeSystem,
        openSystem,
        endDay,
        openDay,
        updateRequestTypes,
        fetchRequestTypes,
        createRequestType,
        updateRequestType,
        deleteRequestType,
        updateStudentKinds,
        updateQueueLaneTellers,
        createFaculty,
        updateFaculty,
        deactivateFaculty,
        createCollege,
        updateCollege,
        deactivateCollege,
        handleSystemUpdated,
        handleDayReset,
        handleTicketIssued,
        handleTicketCalled,
        handleTicketCompleted,
        handleTicketAbsent,
        handleTicketRestored,
        handleTicketDeleted,
        handleTicketUpdated,
        requestTicketAudio,
        sendAnnouncement,
        sendMicChunk,
        subscribeEcho,
        bindEcho,
        unbindEcho,
        startAutoRefresh,
    };
});
