<script setup lang="ts">
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { onMounted, onUnmounted, ref, watch } from 'vue'

export interface MapMarker {
  id: number
  lat: number
  lng: number
  title: string
  status?: 'open' | 'resolved'
}

const props = withDefaults(
  defineProps<{
    markers?: MapMarker[]
    pickable?: boolean
    pickedLocation?: { lat: number; lng: number } | null
    height?: number
    center?: [number, number]
    zoom?: number
  }>(),
  {
    markers: () => [],
    pickable: false,
    pickedLocation: null,
    height: 320,
    // Urganch, Khorezm — this app's own company seat, and a sensible
    // default center when there's nothing else to frame the map around.
    center: () => [41.55, 60.63],
    zoom: 11,
  },
)

const emit = defineEmits<{
  pick: [location: { lat: number; lng: number }]
  markerClick: [id: number]
}>()

const mapContainer = ref<HTMLDivElement>()
let map: L.Map | undefined
let markerLayer: L.LayerGroup | undefined
let pickMarker: L.Marker | undefined

// A colored dot via divIcon, not L.Icon.Default — Leaflet's default marker
// image paths don't resolve correctly once bundled by Vite, and this also
// gives free status-based coloring without needing separate icon assets.
function dotIcon(color: string): L.DivIcon {
  return L.divIcon({
    className: '',
    html: `<div style="width:16px;height:16px;border-radius:50%;background:${color};border:2px solid white;box-shadow:0 0 3px rgba(0,0,0,0.5);"></div>`,
    iconSize: [16, 16],
    iconAnchor: [8, 8],
  })
}

function renderMarkers() {
  if (!map) return
  markerLayer?.clearLayers()

  for (const marker of props.markers) {
    const color = marker.status === 'resolved' ? '#2e7d32' : '#c62828'
    L.marker([marker.lat, marker.lng], { icon: dotIcon(color) })
      .bindPopup(marker.title)
      .on('click', () => emit('markerClick', marker.id))
      .addTo(markerLayer!)
  }
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

onMounted(() => {
  map = L.map(mapContainer.value!).setView(props.center, props.zoom)

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19,
  }).addTo(map)

  markerLayer = L.layerGroup().addTo(map)
  renderMarkers()
  renderPickMarker()

  if (props.pickable) {
    map.on('click', (e: L.LeafletMouseEvent) => emit('pick', { lat: e.latlng.lat, lng: e.latlng.lng }))
  }
})

onUnmounted(() => {
  map?.remove()
  map = undefined
})

watch(() => props.markers, renderMarkers, { deep: true })
watch(() => props.pickedLocation, renderPickMarker, { deep: true })
</script>

<template>
  <div ref="mapContainer" :style="{ height: `${height}px`, width: '100%' }" />
</template>
