<script setup>
defineProps({
    message: {
        type: String,
    },
});
</script>

<template>
    <div role="alert">
        <p class="text-sm text-red-600">
            {{ message }}
        </p>
    </div>
</template>
