<script setup>
import { onMounted } from 'vue';
import { RouterView } from 'vue-router';
import { useQueueStore } from './stores/queueStore';

const queueStore = useQueueStore();

onMounted(() => {
    queueStore.bindEcho();
});
</script>

<template>
    <RouterView />
</template>
