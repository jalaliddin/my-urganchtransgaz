<script setup lang="ts">
const pins = [
  { x: 150, y: 118, open: true },
  { x: 318, y: 86, open: true },
  { x: 398, y: 206, open: true, focus: true },
  { x: 212, y: 230, open: true },
  { x: 92, y: 196, open: false },
  { x: 286, y: 152, open: false },
  { x: 462, y: 118, open: false },
]
</script>

<template>
  <svg class="mini-map" viewBox="0 0 520 300" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
    <rect width="520" height="300" fill="#0d2242" />
    <g fill="none" stroke="rgba(255,255,255,0.07)" stroke-width="2" stroke-linecap="round">
      <path d="M0 60 L180 40 L340 70 L520 30" />
      <path d="M40 300 L110 170 L250 120 L330 0" />
      <path d="M180 300 L230 200 L420 160 L520 190" />
      <path d="M430 300 L400 210 L470 90 L520 60" />
      <path d="M0 150 L90 160 L200 130" />
    </g>
    <path
      d="M-10 214 C 90 152, 170 262, 270 196 S 450 118, 540 172"
      fill="none"
      stroke="rgba(72,155,205,0.3)"
      stroke-width="18"
      stroke-linecap="round"
    />
    <g v-for="(pin, index) in pins" :key="index">
      <circle
        v-if="pin.open"
        class="pulse"
        :cx="pin.x"
        :cy="pin.y"
        r="8"
        :style="{ '--d': `${index * 0.45}s` }"
      />
      <circle
        :cx="pin.x"
        :cy="pin.y"
        :r="pin.focus ? 9 : 6.5"
        :fill="pin.open ? '#f0616d' : '#9acc48'"
        stroke="#0d2242"
        stroke-width="2.5"
      />
      <circle v-if="pin.focus" :cx="pin.x" :cy="pin.y" r="16" fill="none" stroke="#ffffff" stroke-opacity="0.55" stroke-width="1.5" />
    </g>
  </svg>
</template>

<style scoped>
.mini-map {
  display: block;
  width: 100%;
  height: 100%;
}

.pulse {
  fill: none;
  stroke: #f0616d;
  stroke-width: 1.5;
  transform-box: fill-box;
  transform-origin: center;
  animation: pulse 2.6s ease-out infinite;
  animation-delay: var(--d);
}

@keyframes pulse {
  from {
    transform: scale(0.8);
    opacity: 0.8;
  }
  to {
    transform: scale(2.6);
    opacity: 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .pulse {
    animation: none;
    opacity: 0.35;
  }
}
</style>
