import 'dart:io';

import '../../Domain/Perangkat/LayananUjiPerangkat.dart';
import 'KanalUsbPrinter.dart';

/// Info perangkat per platform (PRD §17.2.5a, v1.96): Android membaca `Build.MANUFACTURER`/`MODEL` lewat kanal Kotlin
/// (deteksi Sunmi/iMin); platform lain memakai nama & versi sistem operasi.
class InfoPerangkatPlatform implements SumberInfoPerangkat {
  const InfoPerangkatPlatform([this.usb = const KanalUsbPrinter()]);

  final KanalUsbPrinter usb;

  @override
  Future<InfoPerangkat> Ambil() async {
    if (Platform.isAndroid) {
      final info = await usb.AmbilInfo();
      return InfoPerangkat(
        produsen: info.produsen,
        model: info.model,
        sistem: 'Android ${info.versiAndroid}'.trim(),
        adaptor: info.sunmi
            ? 'Sunmi'
            : info.imin
            ? 'iMin'
            : 'Generik',
      );
    }
    return InfoPerangkat(
      produsen: '',
      model: '',
      sistem: '${Platform.operatingSystem} ${Platform.operatingSystemVersion}'.trim(),
      adaptor: 'Generik',
    );
  }
}
