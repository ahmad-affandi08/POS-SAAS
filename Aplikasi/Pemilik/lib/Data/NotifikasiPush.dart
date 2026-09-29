import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';

class PesanPush {
  const PesanPush({required this.data, this.judul, this.isi});

  final Map<String, String> data;
  final String? judul;
  final String? isi;

  static PesanPush Dari(RemoteMessage pesan) => PesanPush(
    data: Map<String, String>.from(pesan.data),
    judul: pesan.notification?.title,
    isi: pesan.notification?.body,
  );
}

abstract interface class NotifikasiPush {
  Future<String?> AmbilToken();

  Stream<String> get tokenBerubah;

  Stream<PesanPush> get pesanMasuk;

  Stream<PesanPush> get pesanDibuka;

  Future<PesanPush?> AmbilPesanAwal();
}

final class NotifikasiPushTidakAda implements NotifikasiPush {
  const NotifikasiPushTidakAda();

  @override
  Future<String?> AmbilToken() async => null;

  @override
  Stream<String> get tokenBerubah => const Stream.empty();

  @override
  Stream<PesanPush> get pesanMasuk => const Stream.empty();

  @override
  Stream<PesanPush> get pesanDibuka => const Stream.empty();

  @override
  Future<PesanPush?> AmbilPesanAwal() async => null;
}

final class NotifikasiPushFirebase implements NotifikasiPush {
  NotifikasiPushFirebase._(this._pesan);

  final FirebaseMessaging _pesan;

  static Future<NotifikasiPush> Buat() async {
    try {
      await Firebase.initializeApp();
      return NotifikasiPushFirebase._(FirebaseMessaging.instance);
    } on Object {
      // Konfigurasi Firebase boleh belum tersedia pada flavor dev/test.
      return const NotifikasiPushTidakAda();
    }
  }

  @override
  Future<String?> AmbilToken() async {
    final izin = await _pesan.requestPermission(alert: true, badge: true, sound: true);
    if (izin.authorizationStatus == AuthorizationStatus.denied) return null;
    return _pesan.getToken();
  }

  @override
  Stream<String> get tokenBerubah => _pesan.onTokenRefresh;

  @override
  Stream<PesanPush> get pesanMasuk => FirebaseMessaging.onMessage.map(PesanPush.Dari);

  @override
  Stream<PesanPush> get pesanDibuka => FirebaseMessaging.onMessageOpenedApp.map(PesanPush.Dari);

  @override
  Future<PesanPush?> AmbilPesanAwal() async {
    final pesan = await _pesan.getInitialMessage();
    return pesan == null ? null : PesanPush.Dari(pesan);
  }
}
