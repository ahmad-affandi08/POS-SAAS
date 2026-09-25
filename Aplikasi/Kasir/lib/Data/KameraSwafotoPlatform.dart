import 'dart:io';
import 'dart:typed_data';

import 'package:image_picker/image_picker.dart';

import '../Domain/Perangkat/KameraSwafoto.dart';

/// Swafoto lewat kamera depan bawaan (Android & iOS) dengan paket `image_picker`: lebar ≤ 480 px, kualitas JPEG 60
/// (±30–60 KB) agar ringan di outbox. Platform lain dianggap tanpa kamera.
class KameraSwafotoPlatform implements KameraSwafoto {
  KameraSwafotoPlatform([ImagePicker? pemilih]) : _pemilih = pemilih ?? ImagePicker();

  final ImagePicker _pemilih;

  @override
  bool CekTersedia() => Platform.isAndroid || Platform.isIOS;

  @override
  Future<Uint8List?> Ambil() async {
    final berkas = await _pemilih.pickImage(
      source: ImageSource.camera,
      preferredCameraDevice: CameraDevice.front,
      maxWidth: 480,
      maxHeight: 640,
      imageQuality: 60,
      requestFullMetadata: false,
    );
    return berkas?.readAsBytes();
  }
}
