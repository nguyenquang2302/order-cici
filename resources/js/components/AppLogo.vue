<script setup lang="ts">
import { Gamepad2 } from '@lucide/vue';
</script>

<template>
    <div class="flex items-center gap-3">
        <div
            class="flex h-10 w-10 items-center justify-center rounded-xl bg-red-600 text-white shadow-lg shadow-red-950/40"
        >
            <Gamepad2 class="h-6 w-6" />
        </div>

        <div class="flex flex-col leading-tight">
            <span class="text-base font-black tracking-wider text-white">
                CiCi <span class="text-red-500">Gaming</span>
            </span>
            <span class="text-[10px] font-semibold tracking-[0.2em] text-zinc-500">
                GAMING CENTER
            </span>
        </div>
    </div>
</template>
