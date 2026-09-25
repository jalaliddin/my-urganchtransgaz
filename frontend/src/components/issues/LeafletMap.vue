<script setup lang="ts">
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

export interface MapMarker {
  id: number
  lat: number
  lng: number
  title: string
  status?: 'open' | 'resolved'
  /** Overrides the status color. */
  color?: string
  /** A one-character symbol drawn on the pin, so status never rests on color alone. */
  glyph?: string
  /** Extra plain-text lines shown in the popup. */
  details?: string[]
  /** Bubble mode: drives the bubble's size and label. */
  count?: number
}

export interface MapLegendItem {
  color: string
  label: string
  glyph?: string
}

const props = withDefaults(
  defineProps<{
    markers?: MapMarker[]
    mode?: 'pins' | 'bubbles'
    pickable?: boolean
    pickedLocation?: { lat: number; lng: number } | null
    height?: number
    center?: [number, number]
    zoom?: number
    fitToMarkers?: boolean
    legend?: MapLegendItem[] | null
    detailsButton?: boolean
  }>(),
  {
    markers: () => [],
    mode: 'pins',
    pickable: false,
    pickedLocation: null,
    height: 320,
    // Urganch, Khorezm — this app's own company seat, and a sensible
    // default center when there's nothing else to frame the map around.
    center: () => [41.55, 60.63],
    zoom: 11,
    fitToMarkers: true,
    legend: null,
    detailsButton: true,
  },
)

const emit = defineEmits<{
  pick: [location: { lat: number; lng: number }]
  markerClick: [id: number]
}>()

const { t } = useI18n()

// The dataviz skill's reserved status steps (critical / good). Red and green
// are indistinguishable to many color-blind readers, so each also carries a
// glyph — the pin's shape, not its hue, is what tells them apart.
const STATUS_STYLES = {
  open: { color: '#d03b3b', glyph: '' },
  resolved: { color: '#0ca30c', glyph: '✓' },
} as const
const BUBBLE_COLOR = '#2a78d6'

const mapContainer = ref<HTMLDivElement>()
const fullscreen = ref(false)
const locating = ref(false)
const locateFailed = ref(false)

let map: L.Map | undefined
let markerLayer: L.FeatureGroup | undefined
let pickMarker: L.Marker | undefined
let resizeObserver: ResizeObserver | undefined
const markerById = new Map<number, L.Marker | L.CircleMarker>()

const legendItems = computed<MapLegendItem[]>(
  () =>
    props.legend ?? [
      { ...STATUS_STYLES.open, label: t('status.open') },
      { ...STATUS_STYLES.resolved, label: t('status.resolved') },
    ],
)

function markerStyle(marker: MapMarker): { color: string; glyph: string } {
  const base = STATUS_STYLES[marker.status ?? 'open']
  return { color: marker.color ?? base.color, glyph: marker.glyph ?? base.glyph }
}

// A colored dot via divIcon, not L.Icon.Default — Leaflet's default marker
// image paths don't resolve correctly once bundled by Vite, and this also
// gives free status-based coloring without needing separate icon assets.
// Color and glyph only ever come from this component's constants or its
// callers' code, never from user input, so interpolating them is safe.
function dotIcon(color: string, glyph = ''): L.DivIcon {
  const size = glyph ? 20 : 16
  return L.divIcon({
    className: '',
    html: `<div class="map-pin" style="width:${size}px;height:${size}px;background:${color};">${glyph}</div>`,
    iconSize: [size, size],
    iconAnchor: [size / 2, size / 2],
    popupAnchor: [0, -size / 2],
  })
}

// Built from DOM nodes with textContent, never an HTML string: titles and
// object names are typed by users, and Leaflet inserts a string popup as
// raw HTML.
function popupContent(marker: MapMarker): HTMLElement {
  const root = document.createElement('div')
  root.className = 'map-popup'

  const title = document.createElement('div')
  title.className = 'map-popup__title'
  title.textContent = marker.title
  root.appendChild(title)

  for (const line of marker.details ?? []) {
    const detail = document.createElement('div')
    detail.className = 'map-popup__detail'
    detail.textContent = line
    root.appendChild(detail)
  }

  if (props.detailsButton) {
    const button = document.createElement('button')
    button.type = 'button'
    button.className = 'map-popup__button'
    button.textContent = t('map.openDetails')
    button.addEventListener('click', () => emit('markerClick', marker.id))
    root.appendChild(button)
  }

  return root
}

function bubbleRadius(count: number): number {
  return Math.min(12 + Math.sqrt(count) * 5, 48)
}

function renderMarkers() {
  if (!map || !markerLayer) return
  markerLayer.clearLayers()
  markerById.clear()

  for (const marker of props.markers) {
    const { color, glyph } = markerStyle(marker)
    let layer: L.Marker | L.CircleMarker

    if (props.mode === 'bubbles') {
      const bubbleColor = marker.color ?? BUBBLE_COLOR
      layer = L.circleMarker([marker.lat, marker.lng], {
        radius: bubbleRadius(marker.count ?? 1),
        color: bubbleColor,
        weight: 2,
        fillColor: bubbleColor,
        fillOpacity: 0.35,
      })
      layer.bindTooltip(String(marker.count ?? ''), {
        permanent: true,
        direction: 'center',
        className: 'map-bubble-label',
      })
    } else {
      layer = L.marker([marker.lat, marker.lng], { icon: dotIcon(color, glyph), title: marker.title })
    }

    layer.bindPopup(() => popupContent(marker)).addTo(markerLayer)
    markerById.set(marker.id, layer)
  }

  if (props.fitToMarkers) fitToMarkers()
  spreadDuplicatePins()
}

// Several issues are often reported at the very same spot (one station, one
// building). Zooming never separates identical coordinates, so pins sharing
// a spot are fanned out in a small ring around it — recomputed after every
// zoom, since the ring is measured in screen pixels.
function spreadDuplicatePins() {
  if (!map) return

  const groups = new Map<string, MapMarker[]>()
  for (const marker of props.markers) {
    const key = `${marker.lat.toFixed(5)},${marker.lng.toFixed(5)}`
    groups.set(key, [...(groups.get(key) ?? []), marker])
  }

  for (const group of groups.values()) {
    if (group.length < 2) continue
    const center = map.latLngToLayerPoint([group[0].lat, group[0].lng])
    const radius =
      props.mode === 'bubbles'
        ? Math.max(...group.map((marker) => bubbleRadius(marker.count ?? 1))) + 4
        : 12 + group.length * 3
    group.forEach((marker, index) => {
      const angle = (2 * Math.PI * index) / group.length - Math.PI / 2
      const offset = L.point(radius * Math.cos(angle), radius * Math.sin(angle))
      markerById.get(marker.id)?.setLatLng(map!.layerPointToLatLng(center.add(offset)))
    })
  }
}

function fitToMarkers() {
  if (!map || !props.markers.length) return

  if (props.markers.length === 1) {
    map.setView([props.markers[0].lat, props.markers[0].lng], Math.max(map.getZoom(), 13))
    return
  }

  map.fitBounds(L.latLngBounds(props.markers.map((marker) => [marker.lat, marker.lng])), {
    padding: [32, 32],
    maxZoom: 15,
  })
}

function renderPickMarker() {
  if (!map) return

  if (pickMarker) {
    map.removeLayer(pickMarker)
    pickMarker = undefined
  }

  if (props.pickedLocation) {
    pickMarker = L.marker([props.pickedLocation.lat, props.pickedLocation.lng], {
      icon: dotIcon('#1e3a5f'),
    }).addTo(map)
  }
}

function focusMarker(id: number) {
  const layer = markerById.get(id)
  if (!map || !layer) return
  map.flyTo(layer.getLatLng(), Math.max(map.getZoom(), 15), { duration: 0.6 })
  layer.openPopup()
}

function locateMe() {
  if (!map) return
  locating.value = true
  locateFailed.value = false
  map.locate({ setView: true, maxZoom: 16, enableHighAccuracy: true, timeout: 10000 })
}

function toggleFullscreen() {
  fullscreen.value = !fullscreen.value
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && fullscreen.value) fullscreen.value = false
}

onMounted(() => {
  map = L.map(mapContainer.value!).setView(props.center, props.zoom)

  const street = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19,
  }).addTo(map)
  const satellite = L.tileLayer(
    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    { attribution: 'Tiles &copy; Esri', maxZoom: 19 },
  )
  L.control.layers({ [t('map.street')]: street, [t('map.satellite')]: satellite }, undefined, { position: 'topright' }).addTo(map)
  L.control.scale({ imperial: false }).addTo(map)

  markerLayer = L.featureGroup().addTo(map)
  map.on('zoomend', spreadDuplicatePins)
  renderMarkers()
  renderPickMarker()

  if (props.pickable) {
    map.on('click', (e: L.LeafletMouseEvent) => emit('pick', { lat: e.latlng.lat, lng: e.latlng.lng }))
    map.on('locationfound', (e: L.LocationEvent) => {
      locating.value = false
      emit('pick', { lat: e.latlng.lat, lng: e.latlng.lng })
    })
    map.on('locationerror', () => {
      locating.value = false
      locateFailed.value = true
    })
  }

  // Covers the dialog opening animation and the fullscreen toggle — Leaflet
  // only measures its container once unless told the size changed.
  resizeObserver = new ResizeObserver(() => map?.invalidateSize())
  resizeObserver.observe(mapContainer.value!)
  window.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
  resizeObserver?.disconnect()
  window.removeEventListener('keydown', onKeydown)
  map?.remove()
  map = undefined
})

watch(() => [props.markers, props.mode], renderMarkers, { deep: true })
watch(() => props.pickedLocation, renderPickMarker, { deep: true })

defineExpose({ focusMarker, fitToMarkers })
</script>

<template>
  <div class="map-wrapper" :class="{ 'map-wrapper--fullscreen': fullscreen }">
    <div ref="mapContainer" class="map-canvas" :style="fullscreen ? undefined : { height: `${height}px` }" />

    <div class="map-tools">
      <v-btn
        v-if="!pickable"
        :icon="fullscreen ? 'mdi-fullscreen-exit' : 'mdi-fullscreen'"
        size="small"
        variant="flat"
        color="surface"
        elevation="2"
        :title="fullscreen ? $t('map.exitFullscreen') : $t('map.fullscreen')"
        :aria-label="fullscreen ? $t('map.exitFullscreen') : $t('map.fullscreen')"
        @click="toggleFullscreen"
      />
      <v-btn
        v-if="markers.length > 1"
        icon="mdi-fit-to-screen-outline"
        size="small"
        variant="flat"
        color="surface"
        elevation="2"
        :title="$t('map.fitAll')"
        :aria-label="$t('map.fitAll')"
        @click="fitToMarkers"
      />
      <v-btn
        v-if="pickable"
        icon="mdi-crosshairs-gps"
        size="small"
        variant="flat"
        color="surface"
        elevation="2"
        :loading="locating"
        :title="$t('map.myLocation')"
        :aria-label="$t('map.myLocation')"
        @click="locateMe"
      />
    </div>

    <div v-if="legendItems.length && !pickable" class="map-legend">
      <div v-for="item in legendItems" :key="item.label" class="map-legend__item">
        <span class="map-legend__swatch" :style="{ background: item.color }">{{ item.glyph }}</span>
        {{ item.label }}
      </div>
    </div>

    <div v-if="locateFailed" class="map-notice text-caption">{{ $t('map.locationFailed') }}</div>
  </div>
</template>

<style scoped>
.map-wrapper {
  position: relative;
  width: 100%;
}

.map-canvas {
  width: 100%;
}

.map-wrapper--fullscreen {
  position: fixed;
  inset: 0;
  z-index: 2400;
  background: rgb(var(--v-theme-surface));
}

.map-wrapper--fullscreen .map-canvas {
  height: 100%;
}

.map-tools {
  position: absolute;
  top: 80px;
  left: 10px;
  z-index: 500;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.map-legend {
  position: absolute;
  bottom: 24px;
  right: 10px;
  z-index: 500;
  padding: 6px 10px;
  border-radius: 8px;
  background: rgba(var(--v-theme-surface), 0.92);
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.25);
  font-size: 12px;
  line-height: 1.6;
}

.map-legend__item {
  display: flex;
  align-items: center;
  gap: 6px;
}

.map-legend__swatch {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  border: 1.5px solid white;
  box-shadow: 0 0 2px rgba(0, 0, 0, 0.5);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 10px;
  font-weight: 700;
  line-height: 1;
}

:deep(.map-pin) {
  border-radius: 50%;
  border: 2px solid white;
  box-shadow: 0 0 3px rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 12px;
  font-weight: 700;
  line-height: 1;
  font-family: system-ui, sans-serif;
}

.map-notice {
  position: absolute;
  top: 10px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 500;
  padding: 4px 8px;
  border-radius: 6px;
  background: rgb(var(--v-theme-error));
  color: rgb(var(--v-theme-on-error));
}

:deep(.map-popup) {
  min-width: 180px;
  max-width: 260px;
}

:deep(.map-popup__title) {
  font-weight: 600;
  margin-bottom: 4px;
}

:deep(.map-popup__detail) {
  font-size: 12px;
  color: #555;
}

:deep(.map-popup__button) {
  margin-top: 8px;
  padding: 4px 10px;
  border-radius: 6px;
  background: #1e3a5f;
  color: white;
  font-size: 12px;
  cursor: pointer;
}

:deep(.map-bubble-label) {
  background: transparent;
  border: none;
  box-shadow: none;
  color: #1a1a1a;
  font-weight: 700;
  font-size: 12px;
}

:deep(.map-bubble-label::before) {
  display: none;
}
</style>
