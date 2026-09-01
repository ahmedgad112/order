<script setup>
import { onMounted, ref, watch } from 'vue';
import QRCode from 'qrcode';

const props = defineProps({
    value: {
        type: String,
        required: true,
    },
    size: {
        type: Number,
        default: 180,
    },
});

const dataUrl = ref('');

async function renderQr() {
    if (!props.value) {
        dataUrl.value = '';
        return;
    }

    dataUrl.value = await QRCode.toDataURL(props.value, {
        width: props.size,
        margin: 1,
        color: {
            dark: '#0f172a',
            light: '#ffffff',
        },
    });
}

onMounted(renderQr);
watch(() => [props.value, props.size], renderQr);
</script>

<template>
    <img
        v-if="dataUrl"
        :src="dataUrl"
        alt="رمز QR للتذكرة"
        :width="size"
        :height="size"
        class="mx-auto rounded-xl bg-white"
    />
</template>
