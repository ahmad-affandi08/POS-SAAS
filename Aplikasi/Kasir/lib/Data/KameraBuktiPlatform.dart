import 'dart:io';
import 'dart:typed_data';

import 'package:image_picker/image_picker.dart';

import '../Domain/Perangkat/KameraBukti.dart';

/// Foto bukti kas lewat kamera belakang bawaan (Android & iOS) dengan paket `image_picker`: sisi terpanjang ≤ 1280 px,
/// kualitas JPEG 55 (±80–200 KB) agar tulisan nota tetap terbaca tetapi ringan di outbox. Platform lain dianggap tanpa
/// kamera.
class KameraBuktiPlatform implements KameraBukti {
  KameraBuktiPlatform([ImagePicker? pemilih]) : _pemilih = pemilih ?? ImagePicker();

  final ImagePicker _pemilih;

  @override
  bool CekTersedia() => Platform.isAndroid || Platform.isIOS;

  @override
  Future<Uint8List?> Ambil() async {
    final berkas = await _pemilih.pickImage(
      source: ImageSource.camera,
      preferredCameraDevice: CameraDevice.rear,
      maxWidth: 1280,
      maxHeight: 1280,
      imageQuality: 55,
      requestFullMetadata: false,
    );
    return berkas?.readAsBytes();
  }
}
