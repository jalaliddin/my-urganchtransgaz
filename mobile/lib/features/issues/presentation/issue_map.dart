import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

class IssueMapMarker {
  IssueMapMarker({required this.id, required this.point, required this.title, this.isResolved = false});

  final int id;
  final LatLng point;
  final String title;
  final bool isResolved;
}

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
  });

  final List<IssueMapMarker> markers;
  final bool pickable;
  final LatLng? pickedLocation;
  final void Function(LatLng point)? onPick;
  final void Function(int id)? onMarkerTap;
  final LatLng? center;
  final double zoom;
  final double height;

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
                      child: _Dot(color: marker.isResolved ? Colors.green.shade700 : Colors.red.shade700),
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
  const _Dot({required this.color});

  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: color,
        shape: BoxShape.circle,
        border: Border.all(color: Colors.white, width: 2),
        boxShadow: const [BoxShadow(color: Colors.black38, blurRadius: 3)],
      ),
    );
  }
}
