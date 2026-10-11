import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

class IssueMapMarker {
  IssueMapMarker({required this.id, required this.point, required this.title, this.isResolved = false, this.isStale = false});

  final int id;
  final LatLng point;
  final String title;
  final bool isResolved;

  /// Still open past the report's staleness threshold.
  final bool isStale;
}

/// The web map's pin colors. A glyph rides along on the two that red/green
/// color-blind users couldn't otherwise tell apart: "!" for open too long,
/// "✓" for resolved.
const _openPin = Color(0xFFD03B3B);
const _stalePin = Color(0xFF8F1D1D);
const _resolvedPin = Color(0xFF0CA30C);

/// Flutter's Leaflet-equivalent — OpenStreetMap tiles, no API key/billing,
/// mirroring the web app's own map component exactly (`LeafletMap.vue`).
class IssueMap extends StatelessWidget {
  const IssueMap({
    super.key,
    this.markers = const [],
    this.pickable = false,
    this.pickedLocation,
    this.onPick,
    this.onMarkerTap,
    this.center,
    this.zoom = 12,
    this.height = 260,
    this.fitMarkers = false,
  });

  final List<IssueMapMarker> markers;
  final bool pickable;
  final LatLng? pickedLocation;
  final void Function(LatLng point)? onPick;
  final void Function(int id)? onMarkerTap;
  final LatLng? center;
  final double zoom;
  final double height;

  /// Frame every marker instead of [center]/[zoom] — for maps whose points
  /// span a whole region. Applies on first build only, like any initial
  /// camera, so give the map a new key when its markers change wholesale.
  final bool fitMarkers;

  // Urganch, Khorezm — this app's own company seat, and a sensible
  // default center when there's nothing else to frame the map around.
  static const _defaultCenter = LatLng(41.55, 60.63);

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: height,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(8),
        child: FlutterMap(
          options: MapOptions(
            initialCenter: center ?? _defaultCenter,
            initialZoom: zoom,
            initialCameraFit: fitMarkers && markers.length > 1
                ? CameraFit.coordinates(
                    coordinates: [for (final marker in markers) marker.point],
                    padding: const EdgeInsets.all(32),
                    maxZoom: 15,
                  )
                : null,
            onTap: pickable ? (tapPosition, point) => onPick?.call(point) : null,
          ),
          children: [
            TileLayer(
              urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
              userAgentPackageName: 'uz.urtg.mobile',
            ),
            MarkerLayer(
              markers: [
                for (final marker in markers)
                  Marker(
                    point: marker.point,
                    width: 24,
                    height: 24,
                    child: GestureDetector(
                      onTap: () => onMarkerTap?.call(marker.id),
                      child: Semantics(
                        label: marker.title,
                        child: marker.isResolved
                            ? const _Dot(color: _resolvedPin, glyph: '✓')
                            : marker.isStale
                                ? const _Dot(color: _stalePin, glyph: '!')
                                : const _Dot(color: _openPin),
                      ),
                    ),
                  ),
                if (pickedLocation != null)
                  Marker(
                    point: pickedLocation!,
                    width: 24,
                    height: 24,
                    child: const _Dot(color: Color(0xFF1E3A5F)),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _Dot extends StatelessWidget {
  const _Dot({required this.color, this.glyph});

  final Color color;
  final String? glyph;

  @override
  Widget build(BuildContext context) {
    return Container(
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: color,
        shape: BoxShape.circle,
        border: Border.all(color: Colors.white, width: 2),
        boxShadow: const [BoxShadow(color: Colors.black38, blurRadius: 3)],
      ),
      child: glyph == null
          ? null
          : Text(glyph!, style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w800, height: 1)),
    );
  }
}
