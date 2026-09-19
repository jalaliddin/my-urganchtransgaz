<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import logoMarkUrl from '@/assets/logo-mark.png'

const { t, locale } = useI18n()

const namesByLocale: Record<string, string[]> = {
  uz: ['Urganch sh.', 'Urganch t.', 'Xiva sh.', 'Xiva t.', 'Xonqa', "Bog'ot", 'Gurlan', "Qo'shko'pir", 'Shovot', 'Xazorasp', 'Yangiariq', 'Yangibozor', "Tuproqqal'a", 'MATX'],
  en: ['Urganch', 'Urganch d.', 'Khiva', 'Khiva d.', 'Khonqa', 'Bogot', 'Gurlan', 'Qoshkopir', 'Shovot', 'Khazorasp', 'Yangiariq', 'Yangibozor', 'Tuproqqala', 'MATS'],
  ru: ['Ургенч г.', 'Ургенч р.', 'Хива г.', 'Хива р.', 'Ханка', 'Багат', 'Гурлен', 'Кошкупыр', 'Шават', 'Хазарасп', 'Янгиарык', 'Янгибазар', 'Тупроккала', 'МАТС'],
}

const TRUNK_Y = 270

// Seven branches above the trunk, seven below, each leaving the trunk at a
// 45° diagonal and then running straight to its node — the same visual
// grammar as a transit map, which keeps 14 labels legible on one canvas.
const branches = computed(() => {
  const names = namesByLocale[locale.value] ?? namesByLocale.uz
  return names.map((label, index) => {
    const isTop = index < 7
    const slot = isTop ? index : index - 7
    const x = (isTop ? 140 : 176) + slot * 72
    const dir = isTop ? -1 : 1
    const y = TRUNK_Y + dir * 174
    return {
      label,
      x,
      y,
      order: slot * 2 + (isTop ? 0 : 1),
      junctionX: x - 56,
      path: `M${x - 56} ${TRUNK_Y} L${x} ${TRUNK_Y + dir * 56} L${x} ${y - dir * 16}`,
      labelY: y + dir * 30,
    }
  })
})
</script>

<template>
  <svg
    class="network"
    viewBox="0 0 680 540"
    role="img"
    :aria-label="t('landing.hero.mapLabel')"
    xmlns="http://www.w3.org/2000/svg"
  >
    <defs>
      <pattern id="nm-grid" width="36" height="36" patternUnits="userSpaceOnUse">
        <path d="M36 0H0V36" fill="none" stroke="rgba(72,155,205,0.08)" stroke-width="1" />
      </pattern>
      <linearGradient id="nm-flow" gradientUnits="userSpaceOnUse" x1="100" y1="0" x2="620" y2="0">
        <stop offset="0" stop-color="#489BCD" />
        <stop offset="1" stop-color="#9ACC48" />
      </linearGradient>
      <radialGradient id="nm-glow">
        <stop offset="0" stop-color="rgba(72,155,205,0.32)" />
        <stop offset="1" stop-color="rgba(72,155,205,0)" />
      </radialGradient>
    </defs>

    <rect width="680" height="540" fill="url(#nm-grid)" />
    <circle cx="70" :cy="TRUNK_Y" r="150" fill="url(#nm-glow)" />

    <g class="pipes" fill="none" stroke-linecap="round" stroke-linejoin="round">
      <path class="pipe" pathLength="1" :d="`M108 ${TRUNK_Y} L586 ${TRUNK_Y}`" style="--i: 0" />
      <path
        v-for="b in branches"
        :key="b.label"
        class="pipe"
        pathLength="1"
        :d="b.path"
        :style="{ '--i': b.order + 1 }"
      />

      <g class="flow-layer">
        <path class="flow" :d="`M108 ${TRUNK_Y} L586 ${TRUNK_Y}`" />
        <path v-for="b in branches" :key="b.label" class="flow" :d="b.path" />
      </g>
    </g>

    <g class="junctions">
      <circle v-for="b in branches" :key="b.label" :cx="b.junctionX" :cy="TRUNK_Y" r="4" />
      <rect x="584" :y="TRUNK_Y - 9" width="4" height="18" rx="2" />
    </g>

    <g class="hub">
      <circle cx="70" :cy="TRUNK_Y" r="54" class="hub-ring" />
      <circle cx="70" :cy="TRUNK_Y" r="42" class="hub-core" />
      <image :href="logoMarkUrl" x="51" :y="TRUNK_Y - 30" width="38" height="61" />
      <text x="70" :y="TRUNK_Y + 82" text-anchor="middle" class="hub-label">
        {{ t('landing.hero.hub') }}
      </text>
    </g>

    <g class="nodes">
      <g v-for="b in branches" :key="b.label" class="node" :style="{ '--i': b.order + 1 }">
        <circle :cx="b.x" :cy="b.y" r="9" class="node-ring" />
        <circle :cx="b.x" :cy="b.y" r="3.5" class="node-dot" />
        <text :x="b.x" :y="b.labelY" text-anchor="middle" class="node-label">{{ b.label }}</text>
      </g>
    </g>
  </svg>
</template>

<style scoped>
.network {
  display: block;
  width: 100%;
  height: auto;
}

.pipe {
  stroke: #1d3d6b;
  stroke-width: 5;
  stroke-dasharray: 1;
  stroke-dashoffset: 1;
  animation: draw 0.9s cubic-bezier(0.3, 0.6, 0.2, 1) forwards;
  animation-delay: calc(var(--i) * 70ms + 150ms);
}

.flow-layer {
  opacity: 0;
  animation: fade-in 0.6s ease forwards;
  animation-delay: 1.7s;
}

.flow {
  stroke: url(#nm-flow);
  stroke-width: 2.2;
  stroke-dasharray: 5 15;
  animation: flow 2.4s linear infinite;
}

.junctions circle {
  fill: #489bcd;
}

.junctions rect {
  fill: #2c5c97;
}

.hub-ring {
  fill: none;
  stroke: rgba(72, 155, 205, 0.35);
  stroke-width: 1.5;
  stroke-dasharray: 2 7;
}

.hub-core {
  fill: #0f2a50;
  stroke: #2c5c97;
  stroke-width: 1.5;
}

.hub-label {
  fill: #ffffff;
  font-size: 14px;
  font-weight: 600;
}

.node {
  opacity: 0;
  animation: fade-in 0.5s ease forwards;
  animation-delay: calc(var(--i) * 70ms + 700ms);
}

.node-ring {
  fill: #0a1a31;
  stroke: #489bcd;
  stroke-width: 2;
}

.node-dot {
  fill: #9acc48;
}

.node-label {
  fill: rgba(224, 236, 250, 0.82);
  font-size: 12px;
  font-weight: 500;
}

@keyframes draw {
  to {
    stroke-dashoffset: 0;
  }
}

@keyframes flow {
  to {
    stroke-dashoffset: -20;
  }
}

@keyframes fade-in {
  to {
    opacity: 1;
  }
}

@media (max-width: 639px) {
  .node-label {
    display: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .pipe {
    animation: none;
    stroke-dashoffset: 0;
  }

  .node,
  .flow-layer {
    animation: none;
    opacity: 1;
  }

  .flow {
    animation: none;
  }
}
</style>
